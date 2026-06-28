<?php
require_once 'includes/db.php';

// Dynamically check and add signature_data column to requisitions table if not exists
try {
    $pdo->exec("ALTER TABLE requisitions ADD COLUMN signature_data TEXT DEFAULT '';");
} catch (Exception $e) {
    // Column already exists, ignore
}

// Micro API for Real-time Stock Check
if (isset($_GET['get_stock']) && isset($_GET['item_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT quantity, unit_price FROM items WHERE id = ?");
    $stmt->execute([$_GET['item_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($item ?: ['quantity' => 0, 'unit_price' => 0]);
    exit;
}

// Handle Add Requisition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $item_id = $_POST['item_id'];
    $order_no = $_POST['order_no'];
    $requester_name = $_POST['requester_name'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $purpose = $_POST['purpose'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];
    $discount = $_POST['discount'];
    $amount = ($quantity * $unit_price) - $discount;
    $signature_data = $_POST['signature_data'] ?? '';

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'req_' . time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename);
        $image_path = 'uploads/' . $filename;
    }

    $stmt = $pdo->prepare("INSERT INTO requisitions (item_id, order_no, requester_name, position, department, purpose, quantity, unit_price, discount, amount, image_path, signature_data) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$item_id, $order_no, $requester_name, $position, $department, $purpose, $quantity, $unit_price, $discount, $amount, $image_path, $signature_data]);
    
    $req_id = $pdo->lastInsertId();
    $item_stmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
    $item_stmt->execute([$item_id]);
    $item_name = $item_stmt->fetchColumn() ?: "ไม่ทราบชื่อ";
    log_audit('INSERT', 'requisitions', $req_id, "สร้างใบเบิกใหม่โดยคุณ " . $requester_name . " สำหรับครุภัณฑ์: " . $item_name . " จำนวน " . $quantity);

    header("Location: requisitions.php?success=1");
    exit;
}

// Handle Delete Requisition
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Optional: Revert quantity if it was Approved or Delivered
    $req = $pdo->prepare("SELECT item_id, quantity, status FROM requisitions WHERE id = ?");
    $req->execute([$id]);
    $data = $req->fetch();
    
    if ($data && ($data['status'] == 'Approved' || $data['status'] == 'Delivered')) {
        $pdo->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ?")->execute([$data['quantity'], $data['item_id']]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM requisitions WHERE id = ?");
    $stmt->execute([$id]);
    log_audit('DELETE', 'requisitions', $id, "ลบใบเบิกเลขที่ " . $id);
    header("Location: requisitions.php?success=deleted");
    exit;
}

// Handle Edit Requisition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $item_id = $_POST['item_id'];
    $order_no = $_POST['order_no'];
    $requester_name = $_POST['requester_name'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $purpose = $_POST['purpose'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];
    $discount = $_POST['discount'];
    $amount = ($quantity * $unit_price) - $discount;
    $signature_data = $_POST['signature_data'] ?? '';

    // Handle stock quantity sync on edit!
    $stmt = $pdo->prepare("SELECT status, item_id, quantity FROM requisitions WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    if ($current && ($current['status'] == 'Approved' || $current['status'] == 'Delivered')) {
        // Revert old quantity first
        $pdo->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ?")->execute([$current['quantity'], $current['item_id']]);
        // Deduct new quantity
        $pdo->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ?")->execute([$quantity, $item_id]);
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'req_' . time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename);
        $image_path = 'uploads/' . $filename;
        
        $stmt = $pdo->prepare("UPDATE requisitions SET item_id=?, order_no=?, requester_name=?, position=?, department=?, purpose=?, quantity=?, unit_price=?, discount=?, amount=?, image_path=?, signature_data=? WHERE id=?");
        $stmt->execute([$item_id, $order_no, $requester_name, $position, $department, $purpose, $quantity, $unit_price, $discount, $amount, $image_path, $signature_data, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE requisitions SET item_id=?, order_no=?, requester_name=?, position=?, department=?, purpose=?, quantity=?, unit_price=?, discount=?, amount=?, signature_data=? WHERE id=?");
        $stmt->execute([$item_id, $order_no, $requester_name, $position, $department, $purpose, $quantity, $unit_price, $discount, $amount, $signature_data, $id]);
    }
    
    log_audit('UPDATE', 'requisitions', $id, "แก้ไขใบเบิกเลขที่ " . $id . " ของคุณ " . $requester_name);
    header("Location: requisitions.php?success=updated");
    exit;
}

// Handle Status Update (Transition-Safe Recalculation Block)
if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    $stmt = $pdo->prepare("SELECT status, item_id, quantity FROM requisitions WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    
    if ($current) {
        $old_status = $current['status'];
        $item_id = $current['item_id'];
        $qty = $current['quantity'];
        
        if (($old_status == 'Approved' || $old_status == 'Delivered') && ($status == 'Pending' || $status == 'Rejected')) {
            $pdo->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $item_id]);
        }
        if (($status == 'Approved' || $status == 'Delivered') && ($old_status == 'Pending' || $old_status == 'Rejected')) {
            $pdo->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $item_id]);
        }
    }
    
    $stmt = $pdo->prepare("UPDATE requisitions SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    log_audit($status, 'requisitions', $id, "เปลี่ยนสถานะใบเบิกเลขที่ " . $id . " เป็น " . $status);
    header("Location: requisitions.php");
    exit;
}

// Handle Clear Data
if (isset($_POST['action']) && $_POST['action'] == 'clear_data') {
    $pdo->query("DELETE FROM requisitions");
    $pdo->query("DELETE FROM items");
    log_audit('DELETE', 'requisitions', 0, "ล้างตารางใบเบิกพัสดุและครุภัณฑ์ทั้งหมด");
    header("Location: requisitions.php?success=cleared");
    exit;
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids)) {
        // Convert array of IDs to comma-separated string for SQL IN clause
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Handle quantity revert for Approved items before deleting
        $stmt = $pdo->prepare("SELECT item_id, quantity, status FROM requisitions WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $toRevert = $stmt->fetchAll();
        
        foreach ($toRevert as $req) {
            if ($req['status'] == 'Approved') {
                $pdo->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ?")->execute([$req['quantity'], $req['item_id']]);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM requisitions WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        log_audit('DELETE', 'requisitions', 0, "ลบใบเบิกแบบกลุ่ม จำนวน " . count($ids) . " รายการ");
        header("Location: requisitions.php?success=deleted_bulk&count=" . count($ids));
        exit;
    }
}

// Handle Dummy Data Generation
if (isset($_POST['action']) && $_POST['action'] == 'generate_dummy') {
    // Subtypes to pick from
    $subtypes = $pdo->query("SELECT s.*, c.name as cat_name FROM item_subtypes s JOIN item_categories c ON s.category_code = c.code")->fetchAll();
    
    if (count($subtypes) > 0) {
        // Pick only 3 random subtypes to increase chance of repeats (001, 002, 003)
        shuffle($subtypes);
        $subset = array_slice($subtypes, 0, 3);
        
        $names = ["สมชาย เข็มกลัด", "สมหญิง รักดี", "วิชัย มีชัย", "นรินทร์ ศักดิ์ศรี", "สุดาพร ผ่องใส"];
        $deps = ["แผนกงบประมาณ", "ฝ่ายพัสดุ", "สำนักคณบดี", "กองอาคารสถานที่", "โรงเรียนสาธิตฯ"];
        $pos = ["เจ้าหน้าที่พัสดุ", "หัวหน้างาน", "ผู้อำนวยการ", "นักวิชาการ", "พนักงานธุรการ"];
        $thaiYear = date('Y') + 543 - 2500;

        for ($i = 0; $i < 5; $i++) {
            // 1. Pick from the limited subset
            $st = $subset[array_rand($subset)];
            
            // 2. Find next sequential number for this specific subtype
            $prefix = "SDU" . $thaiYear . "." . $st['category_code'] . "." . $st['type_code'] . ".";
            $stmt = $pdo->prepare("SELECT item_code FROM items WHERE item_code LIKE ? ORDER BY item_code DESC LIMIT 1");
            $stmt->execute([$prefix . "%"]);
            $last = $stmt->fetch();
            
            $nextNum = 1;
            if ($last) {
                $parts = explode('.', $last['item_code']);
                $lastPart = end($parts);
                if (is_numeric($lastPart)) {
                    $nextNum = (int)$lastPart + 1;
                }
            }
            
            $suffix = str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $code = $prefix . $suffix;
            $barcode = str_replace('.', '', $code);
            
            // 3. Insert as a NEW unique item
            $price = rand(500, 5000);
            $stmt = $pdo->prepare("INSERT INTO items (item_code, barcode, name, category, item_type, quantity, unit, unit_price, acquisition_date, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $barcode, $st['name'], $st['cat_name'], $st['name'], rand(20, 50), "ชิ้น", $price, date('Y-m-d'), "ห้องพัสดุ"]);
            $newItemId = $pdo->lastInsertId();

            // 4. Create Requisition linked to this NEW unique item
            $name = $names[array_rand($names)];
            $dep = $deps[array_rand($deps)];
            $p = $pos[array_rand($pos)];
            $qty = rand(1, 5);
            $order = rand(100, 999) . "/2569";
            $amt = $qty * $price;
            $date = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));

            $stmt = $pdo->prepare("INSERT INTO requisitions (item_id, order_no, requester_name, position, department, purpose, quantity, unit_price, discount, amount, requisition_date, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$newItemId, $order, $name, $p, $dep, "เพื่อใช้งานในราชการ", $qty, $price, 0, $amt, $date]);
        }
    }
    header("Location: requisitions.php?success=dummy");
    exit;
}

// Fetch unique requester names for autocomplete (trimmed)
$requesters_list = $pdo->query("SELECT DISTINCT TRIM(requester_name) FROM requisitions WHERE requester_name != '' AND requester_name IS NOT NULL ORDER BY requester_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$responsible_people_list = $pdo->query("SELECT DISTINCT TRIM(responsible_person) FROM items WHERE responsible_person != '' AND responsible_person IS NOT NULL ORDER BY responsible_person ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_names = array_unique(array_merge($requesters_list, $responsible_people_list));
sort($all_names);

// Fetch unique positions and departments (trimmed)
$positions_list = $pdo->query("SELECT DISTINCT TRIM(position) FROM requisitions WHERE position != '' AND position IS NOT NULL ORDER BY position ASC")->fetchAll(PDO::FETCH_COLUMN);
$departments_list = $pdo->query("SELECT DISTINCT TRIM(department) FROM requisitions WHERE department != '' AND department IS NOT NULL ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch unique order numbers and purposes (trimmed)
$orders_list = $pdo->query("SELECT DISTINCT TRIM(order_no) FROM requisitions WHERE order_no != '' AND order_no IS NOT NULL ORDER BY order_no ASC")->fetchAll(PDO::FETCH_COLUMN);
$purposes_list = $pdo->query("SELECT DISTINCT TRIM(purpose) FROM requisitions WHERE purpose != '' AND purpose IS NOT NULL ORDER BY purpose ASC")->fetchAll(PDO::FETCH_COLUMN);

// Handle Autocomplete Management AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    $response = ['success' => false];
    $action = $_POST['ajax_action'];
    
    if ($action == 'delete_requester') {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("UPDATE requisitions SET requester_name = '' WHERE TRIM(requester_name) = ?");
        $stmt->execute([$name]);
        $stmt = $pdo->prepare("UPDATE items SET responsible_person = '' WHERE TRIM(responsible_person) = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'rename_requester') {
        $old = $_POST['old_name'];
        $new = $_POST['new_name'];
        $stmt = $pdo->prepare("UPDATE requisitions SET requester_name = ? WHERE requester_name = ?");
        $stmt->execute([$new, $old]);
        $stmt = $pdo->prepare("UPDATE items SET responsible_person = ? WHERE responsible_person = ?");
        $stmt->execute([$new, $old]);
        $response['success'] = true;
    } elseif ($action == 'delete_position') {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("UPDATE requisitions SET position = '' WHERE TRIM(position) = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'delete_department') {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("UPDATE requisitions SET department = '' WHERE TRIM(department) = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'delete_order') {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("UPDATE requisitions SET order_no = '' WHERE TRIM(order_no) = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'delete_purpose') {
        $name = trim($_POST['name']);
        $stmt = $pdo->prepare("UPDATE requisitions SET purpose = '' WHERE TRIM(purpose) = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    }
    
    echo json_encode($response);
    exit;
}

include 'includes/header.php';
?>

<style>
    /* Global Lucide Icon Constraint Guard */
    svg.lucide, .lucide {
        width: 18px !important;
        height: 18px !important;
        stroke-width: 2 !important;
        display: inline-block !important;
        vertical-align: middle !important;
        flex-shrink: 0 !important;
    }
    h2 svg.lucide, h2 .lucide {
        width: 24px !important;
        height: 24px !important;
        stroke-width: 2 !important;
    }

    .action-group {
        display: flex !important;
        gap: 20px !important;
        align-items: center !important;
        background: transparent !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        margin-top: 15px !important;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        cursor: pointer;
        border: 1px solid transparent;
        background: white;
        color: #475569;
        gap: 6px;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        height: 36px;
    }
    .action-btn i,
    .action-btn svg,
    .action-btn .lucide {
        width: 14px !important;
        height: 14px !important;
        stroke-width: 2.5 !important;
        flex-shrink: 0 !important;
    }
    .action-btn:hover {
        background: #f1f5f9;
        color: #1e293b;
        transform: translateY(-1px);
    }
    .action-btn.danger { color: #ef4444; }
    .action-btn.danger:hover { background: #fef2f2; border-color: #fee2e2; }
    .action-btn.warning-outline { color: #f97316; }
    .action-btn.warning-outline:hover { background: #fff7ed; border-color: #ffedd5; }
    .action-btn.primary-outline { color: #2563eb; }
    .action-btn.primary-outline:hover { background: #eff6ff; border-color: #dbeafe; }
    .action-btn.primary { background: #2563eb; color: white; border-color: #2563eb; }
    .action-btn.primary:hover { background: #1d4ed8; }

    /* Dark Mode overrides for action-btn */
    [data-theme="dark"] .action-btn {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    [data-theme="dark"] .action-btn:hover {
        background: #334155 !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .action-btn.danger {
        color: #f87171 !important;
        border-color: rgba(239, 68, 68, 0.4) !important;
        background: rgba(239, 68, 68, 0.1) !important;
    }
    [data-theme="dark"] .action-btn.danger:hover {
        background: #ef4444 !important;
        color: white !important;
    }
    [data-theme="dark"] .action-btn.warning-outline {
        color: #fbbf24 !important;
        border-color: rgba(245, 158, 11, 0.4) !important;
        background: rgba(245, 158, 11, 0.1) !important;
    }
    [data-theme="dark"] .action-btn.warning-outline:hover {
        background: #f59e0b !important;
        color: white !important;
    }
    [data-theme="dark"] .action-btn.primary-outline {
        color: #60a5fa !important;
        border-color: rgba(59, 130, 246, 0.4) !important;
        background: rgba(59, 130, 246, 0.1) !important;
    }
    [data-theme="dark"] .action-btn.primary-outline:hover {
        background: #2563eb !important;
        color: white !important;
    }

    /* Modern Status Badges */
    .status-badge {
        display: inline-flex !important;
        align-items: center !important;
        padding: 6px 14px !important;
        border-radius: 99px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        gap: 8px !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
        border: 1px solid transparent !important;
        font-family: 'Sarabun', sans-serif !important;
        line-height: 1 !important;
    }
    .status-dot {
        width: 10px !important;
        height: 10px !important;
        border-radius: 50% !important;
        flex-shrink: 0 !important;
    }
    .status-pending { 
        background: #fef3c7 !important; 
        color: #92400e !important; 
        border-color: #fde68a !important; 
    }
    .status-approved { 
        background: #dcfce7 !important; 
        color: #166534 !important; 
        border-color: #bbf7d0 !important; 
    }
    .status-rejected { 
        background: #fee2e2 !important; 
        color: #991b1b !important; 
        border-color: #fecaca !important; 
    }
    .status-pending .status-dot { background: #f59e0b !important; box-shadow: 0 0 8px rgba(245,158,11,0.4) !important; }
    .status-approved .status-dot { background: #22c55e !important; box-shadow: 0 0 8px rgba(34,197,94,0.4) !important; }
    .status-rejected .status-dot { background: #ef4444 !important; box-shadow: 0 0 8px rgba(239,68,68,0.4) !important; }

    [data-theme="dark"] .status-pending { background: rgba(245, 158, 11, 0.15) !important; color: #fbbf24 !important; border-color: rgba(245, 158, 11, 0.3) !important; }
    [data-theme="dark"] .status-approved { background: rgba(34, 197, 94, 0.15) !important; color: #4ade80 !important; border-color: rgba(34, 197, 94, 0.3) !important; }
    [data-theme="dark"] .status-rejected { background: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; border-color: rgba(239, 68, 68, 0.3) !important; }

    /* Layout & Table Scroll Cache-Busting Overrides */
    html, body {
        overflow-x: hidden !important;
    }
    .main-content {
        min-width: 0 !important;
        overflow-x: hidden !important;
    }
    .table-container {
        overflow-x: auto !important;
    }

    /* Actions Column Buttons Styling */
    .btn-row-action {
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-row-action.warning {
        background: #fffbeb !important;
        border: 1px solid rgba(245, 158, 11, 0.4) !important;
        color: #d97706 !important;
    }
    .btn-row-action.warning:hover {
        background: #f59e0b !important;
        border-color: #f59e0b !important;
        color: white !important;
    }
    .btn-row-action.danger {
        background: #fef2f2 !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
        color: #dc2626 !important;
    }
    .btn-row-action.danger:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: white !important;
    }

    [data-theme="dark"] .btn-row-action.warning {
        background: rgba(245, 158, 11, 0.12) !important;
        border: 1px solid rgba(245, 158, 11, 0.4) !important;
        color: #fbbf24 !important;
    }
    [data-theme="dark"] .btn-row-action.warning:hover {
        background: #f59e0b !important;
        border-color: #f59e0b !important;
        color: white !important;
    }
    [data-theme="dark"] .btn-row-action.danger {
        background: rgba(239, 68, 68, 0.12) !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
        color: #f87171 !important;
    }
    [data-theme="dark"] .btn-row-action.danger:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: white !important;
    }

    /* Keep high-contrast light styles inside selected white rows */
    .row-selected .btn-row-action.warning {
        background: #fffbeb !important;
        border: 1px solid rgba(245, 158, 11, 0.4) !important;
        color: #d97706 !important;
    }
    .row-selected .btn-row-action.warning:hover {
        background: #f59e0b !important;
        border-color: #f59e0b !important;
        color: white !important;
    }
    .row-selected .btn-row-action.danger {
        background: #fef2f2 !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
        color: #dc2626 !important;
    }
    .row-selected .btn-row-action.danger:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: white !important;
    }

    /* === Scan Result Modal === */
    #scanResultModal {
        display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.55); z-index: 10001; overflow-y: auto; padding: 2rem 1rem;
        backdrop-filter: blur(4px); animation: scanFadeIn 0.25s ease;
    }
    @keyframes scanFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scanSlideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    .scan-modal-card {
        background: var(--card, #fff); border-radius: 1.25rem; width: 100%; max-width: 600px;
        margin: 0 auto; box-shadow: 0 25px 80px rgba(0,0,0,0.25); overflow: hidden;
        animation: scanSlideUp 0.3s ease;
    }
    .scan-modal-header {
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
        color: white; padding: 1.5rem 2rem; position: relative;
    }
    .scan-modal-header h2 { font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
    .scan-modal-header .scan-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255,255,255,0.2); border-radius: 20px;
        padding: 4px 14px; font-size: 0.75rem; font-weight: 600; margin-top: 8px;
    }
    .scan-modal-close {
        position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.2);
        border: none; color: white; width: 32px; height: 32px; border-radius: 8px;
        font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
    }
    .scan-modal-close:hover { background: rgba(255,255,255,0.35); }
    .scan-modal-body { padding: 1.5rem 2rem; }
    .scan-info-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
    }
    .scan-info-item {
        background: var(--bg-hover, #f8fafc); border-radius: 0.75rem; padding: 0.875rem 1rem;
        border: 1px solid var(--border, #e2e8f0); transition: transform 0.15s, box-shadow 0.15s;
    }
    .scan-info-item:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .scan-info-item.full-width { grid-column: 1 / -1; }
    .scan-info-label {
        font-size: 0.7rem; font-weight: 700; color: var(--text-muted, #94a3b8);
        text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;
    }
    .scan-info-value {
        font-size: 0.95rem; font-weight: 600; color: var(--text, #1e293b);
        word-break: break-word;
    }
    .scan-info-value.code { font-family: 'Courier New', monospace; letter-spacing: 0.5px; }
    .scan-condition-badge {
        display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
        border-radius: 12px; font-size: 0.8rem; font-weight: 600;
    }
    .scan-condition-badge.good { background: #dcfce7; color: #15803d; }
    .scan-condition-badge.fair { background: #dbeafe; color: #1d4ed8; }
    .scan-condition-badge.poor { background: #fef3c7; color: #92400e; }
    .scan-condition-badge.broken { background: #fee2e2; color: #b91c1c; }
    .scan-status-badge {
        display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
        border-radius: 12px; font-size: 0.8rem; font-weight: 600;
    }
    .scan-status-badge.pending { background: #fef3c7; color: #92400e; }
    .scan-status-badge.approved { background: #dcfce7; color: #15803d; }
    .scan-status-badge.rejected { background: #fee2e2; color: #b91c1c; }
    .scan-modal-footer {
        padding: 1rem 2rem 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;
        border-top: 1px solid var(--border, #e2e8f0);
    }
    .scan-btn {
        padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem;
        border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    }
    .scan-btn.secondary { background: var(--bg-hover, #e2e8f0); color: var(--text, #1e293b); }
    .scan-btn.secondary:hover { background: #cbd5e1; }
    .scan-btn.warning { background: #f59e0b; color: white; }
    .scan-btn.warning:hover { background: #d97706; }
    .scan-btn.primary { background: #2563eb; color: white; }
    .scan-btn.primary:hover { background: #1d4ed8; }

    /* Premium Interactive Stepper Timeline */
    .stepper-timeline span {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .stepper-dot {
        padding: 3px 10px;
        border-radius: 9999px;
        color: var(--text-muted, #64748b);
        background: var(--bg-hover, #e2e8f0);
        border: 1px solid var(--border, #e2e8f0);
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }
    .stepper-dot:hover {
        transform: scale(1.05);
    }
    .stepper-dot.active-pending {
        background: #fef3c7 !important;
        color: #b45309 !important;
        border-color: #fde68a !important;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.25);
    }
    .stepper-dot.active-approved {
        background: #dcfce7 !important;
        color: #15803d !important;
        border-color: #bbf7d0 !important;
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.25);
    }
    .stepper-dot.active-rejected {
        background: #fee2e2 !important;
        color: #b91c1c !important;
        border-color: #fecaca !important;
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.25);
    }
    .stepper-dot.active-delivered {
        background: #dbeafe !important;
        color: #1d4ed8 !important;
        border-color: #bfdbfe !important;
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.25);
    }
    .stepper-line {
        width: 16px;
        height: 2px;
        background: var(--border, #cbd5e1);
        flex-shrink: 0;
    }
    .stepper-line.active {
        background: var(--primary, #3b82f6);
    }
    
    /* Digital Signature Pad Styling */
    .signature-container {
        border: 2px dashed var(--border);
        border-radius: 10px;
        background: #fafafa;
        position: relative;
        height: 120px;
        cursor: crosshair;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        transition: border-color 0.2s;
    }
    [data-theme="dark"] .signature-container {
        background: #0f172a;
    }
    .signature-container:hover {
        border-color: var(--primary);
    }
    .signature-clear-btn {
        position: absolute;
        bottom: 8px;
        right: 8px;
        padding: 4px 10px;
        font-size: 11px;
        background: #e2e8f0;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: #475569;
        font-weight: 700;
        transition: all 0.15s;
    }
    .signature-clear-btn:hover {
        background: #cbd5e1;
        transform: translateY(-1px);
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 15px; flex-wrap: wrap;">
    <div style="flex-shrink: 0; min-width: 200px;">
        <h1 class="page-title" style="white-space: nowrap; margin-bottom: 2px;">รายการเบิกพัสดุ</h1>
        <p class="page-subtitle">จัดการการเบิกจ่ายครุภัณฑ์และติดตามสถานะ</p>
    </div>
    <div class="action-group">
        <form method="POST" style="margin: 0;" onsubmit="return confirm('คำเตือน: ข้อมูลการเบิกและพัสดุทั้งหมดจะถูกลบถาวร ยืนยันการล้างข้อมูล?')">
            <input type="hidden" name="action" value="clear_data">
            <button type="submit" class="action-btn danger">
                <i data-lucide="trash-2"></i>
                ล้างข้อมูลทั้งหมด
            </button>
        </form>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="generate_dummy">
            <button type="submit" class="action-btn warning-outline">
                <i data-lucide="database"></i>
                สร้างข้อมูลจำลอง
            </button>
        </form>
        <a href="print_barcodes_batch.php" class="action-btn primary-outline">
            <i data-lucide="scan-barcode"></i>
            พิมพ์บาร์โค้ดรวม
        </a>
        <a href="api_print_all_requisitions.php" class="action-btn">
            <i data-lucide="printer"></i>
            พิมพ์รายงานสรุป
        </a>
        <div class="action-btn primary" onclick="openAddReqModal()">
            <i data-lucide="file-plus"></i>
            สร้างใบเบิกใหม่
        </div>
    </div>
</div>
<script>if(window.lucide) lucide.createIcons();</script>

<?php if (isset($_GET['success'])): ?>
<div style="background: #dcfce7; color: var(--success); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid var(--success);">
    <?php 
    if ($_GET['success'] == 'dummy') echo "สร้างข้อมูลจำลอง 5 รายการเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'cleared') echo "ล้างข้อมูลทั้งหมดออกจากระบบเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'deleted') echo "ลบรายการเบิกเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'deleted_bulk') echo "ลบรายการที่เลือกจำนวน " . intval($_GET['count'] ?? 0) . " รายการเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'updated') echo "แก้ไขข้อมูลการเบิกเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'report_opened') echo "📄 เปิดรายงานสรุปการเบิกในเบราว์เซอร์หลัก (Chrome/Edge) เรียบร้อยแล้ว กรุณากดพิมพ์จากหน้าต่างนั้นครับ";
    else echo "ส่งรายการเบิกเรียบร้อยแล้ว";
    ?>
</div>
<?php endif; ?>

<!-- ช่องค้นหา -->
<div style="margin-top: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 15px;">
    <div style="position: relative; flex: 1; max-width: 400px;">
        <input type="text" id="searchInput" placeholder="ค้นหา... (ชื่อรายการ, รหัส, บาร์โค้ด, ผู้เบิก)" 
               oninput="filterTable()" 
               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: 'Sarabun', sans-serif; outline: none; transition: border-color 0.2s; background: var(--card); color: var(--text);">
    </div>
    <span id="searchCount" style="font-size: 0.85rem; color: #94a3b8; white-space: nowrap; margin-left: 5px;"></span>
</div>

<div class="table-container" style="margin-top: 0; overflow-x: auto;">
    <table id="reqTable" style="width: 100%; min-width: 1500px; border-collapse: collapse; table-layout: auto;">
        <thead>
            <tr>
                <th rowspan="2" style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" style="cursor: pointer; width: 18px; height: 18px;"></th>
                <th rowspan="2" style="width: 60px; text-align: center; white-space: nowrap;">ลำดับ<br>ที่</th>
                <th rowspan="2" style="width: 100px; text-align: center; white-space: nowrap;">เลขที่ใบเบิก</th>
                <th rowspan="2" style="width: 180px; min-width: 160px; text-align: center; white-space: nowrap;">ประเภท/ชนิดครุภัณฑ์</th>
                <th rowspan="2" style="width: 250px; min-width: 200px; text-align: left; padding-left: 16px; white-space: nowrap;">รายการ</th>
                <th rowspan="2" style="width: 120px; text-align: center; white-space: nowrap;">บาร์โค้ด</th>
                <th rowspan="2" style="width: 150px; text-align: center; white-space: nowrap;">หมายเลขครุภัณฑ์</th>
                <th colspan="4" style="background: var(--bg-hover); text-align: center;">ยอดรับระหว่างปี</th>
                <th rowspan="2" style="width: 120px; text-align: center; white-space: nowrap;">สถานที่ใช้</th>
                <th rowspan="2" style="width: 150px; text-align: center; white-space: nowrap;">วัตถุประสงค์</th>
                <th rowspan="2" style="width: 100px; text-align: center; white-space: nowrap;">สภาพงาน</th>
                <th rowspan="2" style="width: 220px; min-width: 185px; text-align: center; white-space: nowrap;">ผู้เบิก</th>
                <th rowspan="2" style="width: 100px; text-align: center; white-space: nowrap;">จัดการ</th>
            </tr>
            <tr class="sub-header">
                <th style="width: 100px; text-align: center; white-space: nowrap;">วัน เดือน ปี</th>
                <th style="width: 150px; text-align: center; white-space: nowrap;">วิธีได้มา/ประเภทเงิน</th>
                <th style="width: 80px; text-align: center; white-space: nowrap;">จำนวน</th>
                <th style="width: 120px; text-align: center; white-space: nowrap;">จำนวนเงิน/บาท</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT r.*, r.image_path as req_image, i.name as item_name, i.item_code, i.barcode, i.image_path as item_image, i.category, i.item_type, i.acquisition_method as item_acquisition, i.location as item_location, i.condition_status
                      FROM requisitions r 
                      JOIN items i ON r.item_id = i.id";
            $reqs = $pdo->query($query)->fetchAll();
            
            // Sort by Requisition Number (เลขที่ใบเบิก) ascending numerically
            usort($reqs, function($a, $b) {
                $a_num = 0; $a_year = 0;
                if (!empty($a['order_no'])) {
                    $parts = explode('/', $a['order_no']);
                    $a_num = isset($parts[0]) ? intval(trim($parts[0])) : 0;
                    $a_year = isset($parts[1]) ? intval(trim($parts[1])) : 0;
                }
                $b_num = 0; $b_year = 0;
                if (!empty($b['order_no'])) {
                    $parts = explode('/', $b['order_no']);
                    $b_num = isset($parts[0]) ? intval(trim($parts[0])) : 0;
                    $b_year = isset($parts[1]) ? intval(trim($parts[1])) : 0;
                }
                if ($a_year !== $b_year) {
                    return $a_year <=> $b_year;
                }
                return $a_num <=> $b_num;
            });
            
            if (empty($reqs)) {
                echo "<tr><td colspan='15' style='text-align: center; color: var(--text-muted); padding: 4rem;'>ยังไม่มีรายการเบิกในระบบ</td></tr>";
            } else {
                foreach ($reqs as $index => $req) {
                    $req_json_attr = htmlspecialchars(json_encode($req, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    echo "<tr data-id='{$req['id']}' data-code='" . htmlspecialchars($req['item_code']) . "' data-barcode='" . htmlspecialchars($req['barcode'] ?? '') . "' data-req-json='{$req_json_attr}'>";
                    echo "<td style='text-align: center;'><input type='checkbox' class='row-select' value='{$req['id']}' onclick='updateBulkBar()' style='cursor: pointer; width: 16px; height: 16px;'></td>";
                    echo "<td style='text-align: center; color: var(--text-muted); font-weight: 500;'>" . ($index + 1) . "</td>";
                    echo "<td style='text-align: center; font-weight: 600; color: var(--primary);'>" . htmlspecialchars($req['order_no'] ?: '-') . "</td>";
                    echo "<td style='white-space: nowrap; padding: 12px 10px;'>
                            <div style='font-weight: 600; color: var(--text); font-size: 13px;'>" . htmlspecialchars($req['category'] ?: 'ครุภัณฑ์') . "</div>
                          </td>";
                    echo "<td style='white-space: nowrap; padding: 12px 16px; text-align: left;'><strong style='color: var(--text); font-size: 14px; font-weight: 600;'>" . htmlspecialchars($req['item_name']) . "</strong></td>";
                    echo "<td style='text-align: center;'>";
                    if ($req['barcode']) {
                        echo "<img src='https://bwipjs-api.metafloor.com/?bcid=code128&text=" . urlencode($req['barcode']) . "&scale=1&height=10&includetext' style='max-width: 100px; filter: grayscale(1); opacity: 0.9;'>";
                    } else {
                        echo "-";
                    }
                    echo "</td>";
                    $displayCode = $req['item_code'];
                    if (preg_match('/^SDU(\d)/', $displayCode)) {
                        $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
                    }
                    echo "<td style='text-align: center;'><code style='background: var(--bg-hover); padding: 2px 6px; border-radius: 4px; color: var(--text); font-size: 11px; border: 1px solid var(--border); font-family: monospace;'>" . htmlspecialchars($displayCode) . "</code></td>";
                    echo "<td style='text-align: center; color: var(--text-muted);'>" . date('d/m/Y', strtotime($req['requisition_date'])) . "</td>";
                    echo "<td style='text-align: center;' class='text-muted-sm'>" . htmlspecialchars($req['item_acquisition'] ?: '-') . "</td>";
                    echo "<td style='text-align: center; font-weight: 600; color: var(--text);'>" . number_format($req['quantity']) . "</td>";
                    echo "<td style='text-align: right; font-family: monospace; font-weight: 500; color: var(--text); padding-right: 12px;'>" . number_format($req['amount'], 2) . "</td>";
                    echo "<td style='text-align: center;' class='text-muted-sm'>" . htmlspecialchars($req['item_location'] ?: '-') . "</td>";
                    echo "<td style='text-align: center;' class='text-muted-sm'>" . htmlspecialchars($req['purpose'] ?: '-') . "</td>";
                    $status = $req['status'];
                    $reqId = $req['id'];
                    
                    $pendingClass = ($status == 'Pending') ? 'active-pending' : '';
                    $approvedClass = ($status == 'Approved') ? 'active-approved' : '';
                    $rejectedClass = ($status == 'Rejected') ? 'active-rejected' : '';
                    $deliveredClass = ($status == 'Delivered') ? 'active-delivered' : '';
                    
                    $line1Class = ($status == 'Approved' || $status == 'Delivered') ? 'active' : '';
                    $line2Class = ($status == 'Delivered') ? 'active' : '';
                    
                    echo "<td style='text-align: center; vertical-align: middle;'>";
                    if ($req['req_image']) {
                        echo "<img src='" . $req['req_image'] . "' style='width: 35px; height: 35px; object-fit: cover; border-radius: 4px; display: block; margin: 0 auto 6px; border: 1px solid #e2e8f0;'>";
                    }
                    echo "<div class='stepper-timeline' style='display: flex; align-items: center; justify-content: center; gap: 4px; background: var(--bg-hover); padding: 6px 10px; border-radius: 20px; border: 1px solid var(--border); font-size: 10px; font-weight: 700; width: fit-content; margin: 0 auto; user-select: none;'>";
                    echo "<span onclick='updateReqStatus(\"{$reqId}\", \"Pending\")' class='stepper-dot {$pendingClass}' title='คลิกเพื่อเปลี่ยนสถานะเป็น: รออนุมัติ'>รออนุมัติ</span>";
                    echo "<span class='stepper-line {$line1Class}'></span>";
                    echo "<span onclick='updateReqStatus(\"{$reqId}\", \"Approved\")' class='stepper-dot {$approvedClass}' title='คลิกเพื่อเปลี่ยนสถานะเป็น: อนุมัติแล้ว'>อนุมัติแล้ว</span>";
                    echo "<span class='stepper-line {$line2Class}'></span>";
                    echo "<span onclick='updateReqStatus(\"{$reqId}\", \"Delivered\")' class='stepper-dot {$deliveredClass}' title='คลิกเพื่อเปลี่ยนสถานะเป็น: ส่งมอบแล้ว'>ส่งมอบแล้ว</span>";
                    if ($status == 'Rejected') {
                        echo "<span style='color: #cbd5e1; font-weight: 700; margin: 0 2px;'>|</span>";
                        echo "<span onclick='updateReqStatus(\"{$reqId}\", \"Rejected\")' class='stepper-dot active-rejected' title='คลิกเพื่อเปลี่ยนสถานะเป็น: ไม่อนุมัติ'>ไม่อนุมัติ</span>";
                    } else {
                        echo "<span style='color: #cbd5e1; font-weight: 700; margin: 0 2px; cursor: pointer;' onclick='updateReqStatus(\"{$reqId}\", \"Rejected\")' title='คลิกเพื่อเปลี่ยนสถานะเป็น: ไม่อนุมัติ'>✕</span>";
                    }
                    echo "</div></td>";
                    echo "<td style='white-space: nowrap; padding: 12px 14px;'>
                            <div style='font-weight: 600; color: var(--text); text-align: center; font-size: 14px; white-space: nowrap;'>" . htmlspecialchars($req['requester_name']) . "</div>
                            <div class='text-muted-sm' style='font-size: 12px; text-align: center; color: var(--primary); font-weight: 500; white-space: nowrap; margin-top: 2px;'>" . htmlspecialchars($req['position'] ?: '-') . "</div>
                            <div class='text-muted-sm' style='font-size: 11px; text-align: center; opacity: 0.8; white-space: nowrap; margin-top: 1px;'>" . htmlspecialchars($req['department'] ?: '-') . "</div>
                          </td>";
                    echo "<td style='text-align: center;'>
                            <div style='display: flex; gap: 4px; justify-content: center;'>
                                <a href='print_requisition.php?id=" . $req['id'] . "' target='_blank' class='btn-row-action primary' title='พิมพ์ใบเบิกพัสดุ PDF' style='background: #eff6ff; border-color: #3b82f6; color: #2563eb;'>
                                    <i data-lucide='printer' style='width: 14px; height: 14px;'></i>
                                    <span>พิมพ์ใบเบิก</span>
                                </a>
                                <button onclick='editReq(" . htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') . ")' class='btn-row-action warning' title='แก้ไข'>
                                    <i data-lucide='edit-3' style='width: 14px; height: 14px;'></i>
                                    <span>แก้ไข</span>
                                </button>
                                <a href='requisitions.php?action=delete&id=" . $req['id'] . "' onclick='return confirm(\"ยืนยันการลบรายการนี้?\")' class='btn-row-action danger' title='ลบ'>
                                    <i data-lucide='trash-2' style='width: 14px; height: 14px;'></i>
                                    <span>ลบ</span>
                                </a>
                            </div>
                          </td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Bulk Action Bar (V4 - Refined & Orderly) -->
<div id="bulkBar_V2" style="display: none !important; position: fixed !important; bottom: 2rem !important; left: 50% !important; transform: translateX(-50%) !important; background: #0a0f1e !important; color: white !important; padding: 0 40px !important; height: 64px !important; border-radius: 14px !important; box-shadow: 0 25px 60px rgba(0,0,0,0.6) !important; z-index: 9999 !important; border: 1px solid rgba(255,255,255,0.15) !important; min-width: fit-content !important; white-space: nowrap !important; font-family: 'Sarabun', sans-serif !important; text-align: center !important;">
    <div style="display: flex !important; align-items: center !important; justify-content: center !important; height: 64px !important; gap: 8px !important;">
        <!-- ส่วนแสดงจำนวน (สะอาดตาขึ้น) -->
        <span style="font-weight: 700 !important; font-size: 16px !important; color: #f1f5f9 !important; display: inline-block !important; vertical-align: middle !important;">เลือกอยู่</span>
        <span id="selectedCount" style="color: #ffffff !important; font-weight: 900 !important; font-size: 26px !important; font-family: 'Inter', sans-serif !important; display: inline-block !important; vertical-align: middle !important; margin: 0 5px !important; line-height: 1 !important;">0</span>
        <span style="font-weight: 700 !important; font-size: 16px !important; color: #f1f5f9 !important; display: inline-block !important; vertical-align: middle !important;">รายการ</span>

        <!-- เส้นคั่น -->
        <div style="width: 1px !important; height: 36px !important; background: rgba(255,255,255,0.2) !important; margin: 0 25px !important; display: inline-block !important; vertical-align: middle !important;"></div>

        <!-- ส่วนปุ่มคำสั่ง -->
        <div style="display: flex !important; align-items: center !important; gap: 10px !important;">
            <button type="button" onclick="openBulkPrintModal()" class="action-btn-bulk primary" style="height: 40px !important; padding: 0 24px !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 14px !important; background: #3b82f6 !important; border: none !important; color: white !important; cursor: pointer !important;">
                พิมพ์บาร์โค้ด
            </button>
            <form method="POST" id="bulkDeleteForm" style="margin: 0 !important; display: flex !important; align-items: center !important;" onsubmit="return confirm('ยืนยันการลบรายการที่เลือกทั้งหมด?')">
                <input type="hidden" name="action" value="bulk_delete">
                <div id="bulkIdsContainer"></div>
                <button type="submit" class="action-btn-bulk danger" style="height: 40px !important; padding: 0 24px !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 14px !important; background: #ef4444 !important; border: none !important; color: white !important; cursor: pointer !important;">
                    ลบที่เลือก
                </button>
            </form>
            <button type="button" onclick="cancelSelection()" class="action-btn-bulk secondary" style="height: 40px !important; padding: 0 20px !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 14px !important; background: #334155 !important; border: none !important; color: white !important; cursor: pointer !important;">ยกเลิก</button>
        </div>
    </div>
</div>

<style>
@keyframes slideUp {
    from { transform: translate(-50%, 100%); opacity: 0; }
    to { transform: translate(-50%, 0); opacity: 1; }
}
html:not([data-theme="dark"]) .row-selected td { 
    background-color: #ffffff !important; 
    color: #0f172a !important;
    transition: background-color 0.15s ease;
}
html:not([data-theme="dark"]) .row-selected:hover td { 
    background-color: #f1f5f9 !important; 
}
html:not([data-theme="dark"]) .row-selected td strong,
html:not([data-theme="dark"]) .row-selected td span:not(.status-dot),
html:not([data-theme="dark"]) .row-selected td div:not(.status-badge),
html:not([data-theme="dark"]) .row-selected td code {
    color: #0f172a !important;
}
html:not([data-theme="dark"]) .row-selected td code {
    background-color: rgba(0, 0, 0, 0.05) !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
}
html:not([data-theme="dark"]) .row-selected td .text-muted-sm,
html:not([data-theme="dark"]) .row-selected td .text-muted {
    color: #475569 !important;
}
html:not([data-theme="dark"]) .row-selected td .text-muted-sm[style*="var(--primary)"] {
    color: #1e40af !important;
}

/* Requisitions Action Buttons Override when Row Selected (Light Mode only) */
html:not([data-theme="dark"]) .row-selected td button.btn,
html:not([data-theme="dark"]) .row-selected td a.btn {
    background: #ffffff !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}
html:not([data-theme="dark"]) .row-selected td button.btn:hover,
html:not([data-theme="dark"]) .row-selected td a.btn:hover {
    background: #f1f5f9 !important;
}
html:not([data-theme="dark"]) .row-selected td button.btn[style*="var(--warning)"],
html:not([data-theme="dark"]) .row-selected td button.btn[onclick*="editReq"] {
    background: #fffbeb !important;
    color: #d97706 !important;
    border-color: #f59e0b !important;
}
html:not([data-theme="dark"]) .row-selected td button.btn[style*="var(--warning)"]:hover,
html:not([data-theme="dark"]) .row-selected td button.btn[onclick*="editReq"]:hover {
    background: #f59e0b !important;
    color: #ffffff !important;
}
html:not([data-theme="dark"]) .row-selected td a.btn[style*="var(--danger)"],
html:not([data-theme="dark"]) .row-selected td a.btn[href*="action=delete"] {
    background: #fef2f2 !important;
    color: #dc2626 !important;
    border-color: #ef4444 !important;
}
html:not([data-theme="dark"]) .row-selected td a.btn[style*="var(--danger)"]:hover,
html:not([data-theme="dark"]) .row-selected td a.btn[href*="action=delete"]:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
}

/* Requisitions Status Badges Override when Row Selected (Light Mode only) */
html:not([data-theme="dark"]) .row-selected td .status-pending { 
    background: #fef3c7 !important; 
    color: #92400e !important; 
    border-color: #fde68a !important; 
}
html:not([data-theme="dark"]) .row-selected td .status-approved { 
    background: #dcfce7 !important; 
    color: #166534 !important; 
    border-color: #bbf7d0 !important; 
}
html:not([data-theme="dark"]) .row-selected td .status-rejected { 
    background: #fee2e2 !important; 
    color: #991b1b !important; 
    border-color: #fecaca !important; 
}

/* Dark Mode Selected Row Premium Styling */
[data-theme="dark"] .row-selected td {
    background-color: rgba(59, 130, 246, 0.15) !important;
    transition: background-color 0.15s ease;
}
[data-theme="dark"] .row-selected:hover td {
    background-color: rgba(59, 130, 246, 0.25) !important;
}

/* Bulk Action Bar Styles */
.action-btn-bulk {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    padding: 0 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    gap: 8px;
    white-space: nowrap;
}
.action-btn-bulk i,
.action-btn-bulk svg,
.action-btn-bulk .lucide {
    width: 16px !important;
    height: 16px !important;
    flex-shrink: 0 !important;
}
.action-btn-bulk.primary { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
.action-btn-bulk.primary:hover { background: #2563eb; transform: translateY(-2px); }
.action-btn-bulk.danger { background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
.action-btn-bulk.danger:hover { background: #dc2626; transform: translateY(-2px); }
.action-btn-bulk.secondary { background: #334155; color: white; border: 1px solid rgba(255,255,255,0.1); }
.action-btn-bulk.secondary:hover { background: #475569; transform: translateY(-2px); }
</style>

<!-- Bulk Print Modal -->
<div id="bulkPrintModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 2rem 1rem;">
    <div style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 800px; margin: 0 auto; padding: 2rem; position: relative;">
        <button type="button" onclick="document.getElementById('bulkPrintModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="printer"></i> พิมพ์บาร์โค้ดต่อเนื่อง
        </h2>
        
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem;">
            <!-- Sidebar Settings -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; height: fit-content;">
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Text Settings</div>
                
                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">Font</label>
                    <select id="printFont" onchange="updatePrintPreview()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="'Sarabun', sans-serif">Sarabun (Standard)</option>
                        <option value="'Arial Black', sans-serif">Arial Black (Heavy)</option>
                        <option value="'Courier New', monospace">Courier New (Mono)</option>
                    </select>
                    <div style="margin-top: 8px; display: flex; gap: 8px; align-items: center;">
                        <input type="number" id="printFontSize" value="14" min="6" max="72" oninput="updatePrintPreview()" style="width: 60px; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                        <span style="font-size: 0.7rem; color: #94a3b8;">pt</span>
                    </div>
                </div>

                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">Style</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                        <button type="button" class="ac-btn-style" id="btnPBold" onclick="togglePrintStyle('bold')">B</button>
                        <button type="button" class="ac-btn-style" id="btnPItalic" onclick="togglePrintStyle('italic')">I</button>
                        <button type="button" class="ac-btn-style" id="btnPUnderline" onclick="togglePrintStyle('underline')">U</button>
                    </div>
                </div>

                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">Alignment</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                        <button type="button" class="ac-btn-style" id="pAlignLeft" onclick="setPrintAlignment('left')">L</button>
                        <button type="button" class="ac-btn-style active" id="pAlignCenter" onclick="setPrintAlignment('center')">C</button>
                        <button type="button" class="ac-btn-style" id="pAlignRight" onclick="setPrintAlignment('right')">R</button>
                    </div>
                </div>

                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">Layout Mode</label>
                    <select id="printLayout" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="1" selected>1 รายการ/แผ่น</option>
                        <option value="2">2 รายการ/แผ่น</option>
                        <option value="4">4 รายาร/แผ่น</option>
                        <option value="999">แบบต่อเนื่อง (แถวเดียว)</option>
                    </select>
                </div>
                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">แท่งบาร์โค้ด (Barcode lines)</label>
                    <select id="printShowBarcode" onchange="updatePrintPreview()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="false">ไม่แสดง (แสดงเฉพาะข้อความ)</option>
                        <option value="true" selected>แสดง (แสดงข้อความคู่บาร์โค้ด)</option>
                    </select>
                </div>
                <div style="padding: 12px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block;">ขนาดเทป (Tape Size)</label>
                    <select id="printTapeWidth" onchange="updatePrintPreview()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="12" selected>12 mm</option>
                        <option value="18">18 mm</option>
                        <option value="24">24 mm</option>
                    </select>
                </div>
            </div>

            <!-- Preview Area -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="font-size: 0.9rem; color: #64748b; font-weight: 600;">ตัวอย่างป้ายแรก:</div>
                <div style="background: #94a3b8; border-radius: 12px; padding: 2rem; display: flex; align-items: center; justify-content: center; min-height: 150px;">
                    <div style="background: white; width: 100%; max-width: 300px; height: 60px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; padding: 0 15px; position: relative; overflow: hidden;">
                        <div id="pPreviewContent" style="width: 100%; color: black; line-height: 1.2; font-family: 'Sarabun', sans-serif; font-size: 16pt;">
                            SDU.XX.XX.XX.XX.X
                        </div>
                    </div>
                </div>
                
                <div style="font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 1rem; border-radius: 8px;">
                    <strong>รายการที่จะพิมพ์:</strong>
                    <div id="printItemsSummary" style="margin-top: 5px; font-family: monospace; max-height: 100px; overflow-y: auto;">
                        <!-- Populate via JS -->
                    </div>
                </div>

                <div style="text-align: right; margin-top: auto;">
                    <button type="button" class="btn" onclick="document.getElementById('bulkPrintModal').style.display='none'" style="margin-right: 0.5rem;">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="btnStartBulkPrint" onclick="executeBulkPrint()">
                        🖨️ เริ่มการพิมพ์ทั้งหมด
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ac-btn-style {
    background: white; border: 1px solid var(--border); padding: 6px; border-radius: 4px; cursor: pointer; transition: all 0.2s; font-weight: bold;
}
.ac-btn-style:hover { background: var(--bg-hover); }
.ac-btn-style.active { background: #dbeafe; border-color: #3b82f6; color: #2563eb; }
</style>
<div id="reqModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 2rem 1rem;">
    <table style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 700px; margin: 0 auto; border-collapse: collapse; border-style: hidden; box-shadow: 0 0 0 0 transparent;">
        <tr>
            <td style="padding: 2rem; position: relative; vertical-align: top;">
                <button type="button" onclick="document.getElementById('reqModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);" title="ปิดหน้าต่าง">&times;</button>
                <h2 style="margin-bottom: 1.5rem;">สร้างใบเบิกใหม่</h2>
                <form method="POST" enctype="multipart/form-data" style="display: block; width: 100%;">
                    <input type="hidden" name="action" value="add">
                    
                    <div style="width: 100%; display: block;">
                        <!-- Column 1 -->
                        <div style="float: left; width: 48%;">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">เลขที่ใบเบิก</label>
                                <div class="ac-wrapper" id="ac_order_no_wrapper">
                                    <input type="text" name="order_no" id="order_no" class="ac-input" autocomplete="off" placeholder="เช่น 046/2569">
                                    <div class="ac-dropdown" id="ac_order_no_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ชื่อผู้เบิก</label>
                                <div class="ac-wrapper" id="ac_requester_wrapper">
                                    <input type="text" name="requester_name" id="requester_name" class="ac-input" required autocomplete="off" placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_requester_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">หน่วยงาน/ศูนย์</label>
                                <div class="ac-wrapper" id="ac_department_wrapper">
                                    <input type="text" name="department" id="department" class="ac-input" autocomplete="off" placeholder="พิมพ์หน่วยงานเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_department_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">แนบรูปถ่ายรายการ (ถ้ามี)</label>
                                <input type="file" name="image" accept="image/*" style="width: 100%; padding: 0.45rem; border: 1px solid var(--border); border-radius: 0.5rem; font-size: 0.8rem;">
                            </div>
                        </div>

                        <!-- Column 2 -->
                        <div style="float: right; width: 48%;">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ประเภทครุภัณฑ์</label>
                                <select name="item_id" id="item_id" required onchange="updatePrice()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                                    <option value="">-- กรุณาเลือก --</option>
                                    <?php
                                    $available = $pdo->query("SELECT id, name, item_code, quantity, unit_price FROM items WHERE quantity > 0")->fetchAll();
                                    foreach ($available as $item) {
                                        echo "<option value='" . $item['id'] . "' data-price='" . $item['unit_price'] . "' data-qty='" . $item['quantity'] . "'>" . htmlspecialchars($item['name']) . " (" . $item['item_code'] . ") - คงเหลือ " . $item['quantity'] . "</option>";
                                    }
                                    ?>
                                </select>
                                <div id="addStockAlert" style="display: none; background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.8rem; margin-top: 0.5rem; font-weight: 500; line-height: 1.4;"></div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ตำแหน่ง</label>
                                <div class="ac-wrapper" id="ac_position_wrapper">
                                    <input type="text" name="position" id="position" class="ac-input" autocomplete="off" placeholder="พิมพ์ตำแหน่งเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_position_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">วัตถุประสงค์</label>
                                <div class="ac-wrapper" id="ac_purpose_wrapper">
                                    <input type="text" name="purpose" id="purpose" class="ac-input" autocomplete="off" placeholder="พิมพ์วัตถุประสงค์เพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_purpose_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ลงลายมือชื่อผู้เบิก (Digital Signature)</label>
                                <div class="signature-container">
                                    <canvas id="addSignatureCanvas" style="width: 100%; height: 100%; display: block; border-radius: 8px;"></canvas>
                                    <button type="button" class="signature-clear-btn" onclick="clearAddSignature()">ล้างลายเซ็น</button>
                                </div>
                                <input type="hidden" name="signature_data" id="addSignatureInput">
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                    </div>

                    <div style="width: 100%; display: table; margin-bottom: 1rem; background: var(--bg-hover); padding: 1rem; border-radius: 0.5rem; box-sizing: border-box;">
                        <div style="display: table-cell; width: 33%; padding-right: 10px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">จำนวน</label>
                            <input type="number" name="quantity" id="qty" value="1" min="1" required oninput="calcTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                        <div style="display: table-cell; width: 33%; padding-right: 10px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ราคาต่อหน่วย</label>
                            <input type="number" step="0.01" name="unit_price" id="price" value="0" required oninput="calcTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                        <div style="display: table-cell; width: 33%;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ส่วนลด</label>
                            <input type="number" step="0.01" name="discount" id="discount" value="0" oninput="calcTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                    </div>

                    <div style="text-align: right; font-size: 1.25rem; font-weight: 700; margin-top: 1rem;">
                        ยอดรวมสุทธิ: <span id="total_display">0.00</span> บาท
                    </div>

                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn" onclick="document.getElementById('reqModal').style.display='none'" style="background: var(--bg-hover); color: var(--text); margin-right: 1rem;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">ส่งรายการเบิก</button>
                    </div>
                </form>
            </td>
        </tr>
    </table>
</div>

<!-- Edit Requisition Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 2rem 1rem;">
    <table style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 700px; margin: 0 auto; border-collapse: collapse; border-style: hidden; box-shadow: 0 0 0 0 transparent;">
        <tr>
            <td style="padding: 2rem; position: relative; vertical-align: top;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);" title="ปิดหน้าต่าง">&times;</button>
                <h2 style="margin-bottom: 1.5rem;">แก้ไขรายการเบิก</h2>
                <form method="POST" enctype="multipart/form-data" style="display: block; width: 100%;">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div style="width: 100%; display: block;">
                        <div style="float: left; width: 48%;">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">เลขที่ใบเบิก</label>
                                <div class="ac-wrapper" id="ac_edit_order_no_wrapper">
                                    <input type="text" name="order_no" id="edit_order_no" class="ac-input" autocomplete="off" placeholder="เช่น 046/2569">
                                    <div class="ac-dropdown" id="ac_edit_order_no_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ชื่อผู้เบิก</label>
                                <div class="ac-wrapper" id="ac_edit_requester_wrapper">
                                    <input type="text" name="requester_name" id="edit_requester_name" class="ac-input" required autocomplete="off" placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_edit_requester_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">หน่วยงาน/ศูนย์</label>
                                <div class="ac-wrapper" id="ac_edit_department_wrapper">
                                    <input type="text" name="department" id="edit_department" class="ac-input" autocomplete="off" placeholder="พิมพ์หน่วยงานเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_edit_department_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">เปลี่ยนรูปถ่าย (ถ้ามี)</label>
                                <input type="file" name="image" accept="image/*" style="width: 100%; padding: 0.45rem; border: 1px solid var(--border); border-radius: 0.5rem; font-size: 0.8rem;">
                            </div>
                        </div>

                        <div style="float: right; width: 48%;">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ประเภทครุภัณฑ์</label>
                                <select name="item_id" id="edit_item_id" required onchange="updateEditPrice()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                                    <?php
                                    foreach ($available as $item) {
                                        echo "<option value='" . $item['id'] . "' data-price='" . $item['unit_price'] . "' data-qty='" . $item['quantity'] . "'>" . htmlspecialchars($item['name']) . " (" . $item['item_code'] . ")</option>";
                                    }
                                    ?>
                                </select>
                                <div id="editStockAlert" style="display: none; background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.8rem; margin-top: 0.5rem; font-weight: 500; line-height: 1.4;"></div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ตำแหน่ง</label>
                                <div class="ac-wrapper" id="ac_edit_position_wrapper">
                                    <input type="text" name="position" id="edit_position" class="ac-input" autocomplete="off" placeholder="พิมพ์ตำแหน่งเพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_edit_position_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">วัตถุประสงค์</label>
                                <div class="ac-wrapper" id="ac_edit_purpose_wrapper">
                                    <input type="text" name="purpose" id="edit_purpose" class="ac-input" autocomplete="off" placeholder="พิมพ์วัตถุประสงค์เพื่อค้นหา...">
                                    <div class="ac-dropdown" id="ac_edit_purpose_dropdown"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ลงลายมือชื่อผู้เบิก (Digital Signature)</label>
                                <div class="signature-container">
                                    <canvas id="editSignatureCanvas" style="width: 100%; height: 100%; display: block; border-radius: 8px;"></canvas>
                                    <button type="button" class="signature-clear-btn" onclick="clearEditSignature()">ล้างลายเซ็น</button>
                                </div>
                                <input type="hidden" name="signature_data" id="editSignatureInput">
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                    </div>

                    <div style="width: 100%; display: table; margin-bottom: 1rem; background: var(--bg-hover); padding: 1rem; border-radius: 0.5rem; box-sizing: border-box;">
                        <div style="display: table-cell; width: 33%; padding-right: 10px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">จำนวน</label>
                            <input type="number" name="quantity" id="edit_qty" min="1" required oninput="calcEditTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                        <div style="display: table-cell; width: 33%; padding-right: 10px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ราคาต่อหน่วย</label>
                            <input type="number" step="0.01" name="unit_price" id="edit_price" required oninput="calcEditTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                        <div style="display: table-cell; width: 33%;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ส่วนลด</label>
                            <input type="number" step="0.01" name="discount" id="edit_discount" oninput="calcEditTotal()" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                    </div>

                    <div style="text-align: right; font-size: 1.25rem; font-weight: 700; margin-top: 1rem;">
                        ยอดรวมสุทธิ: <span id="edit_total_display">0.00</span> บาท
                    </div>

                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn" onclick="document.getElementById('editModal').style.display='none'" style="background: var(--bg-hover); color: var(--text); margin-right: 1rem;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </td>
        </tr>
    </table>
</div>

<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.row-select');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        const row = cb.closest('tr');
        if (master.checked) row.classList.add('row-selected');
        else row.classList.remove('row-selected');
    });
    updateBulkBar();
}

function updateBulkBar() {
    const selected = document.querySelectorAll('.row-select:checked');
    const bar = document.getElementById('bulkBar_V2');
    const countSpan = document.getElementById('selectedCount');
    const idsContainer = document.getElementById('bulkIdsContainer');
    const master = document.getElementById('selectAll');
    const total = document.querySelectorAll('.row-select').length;

    idsContainer.innerHTML = '';
    selected.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        idsContainer.appendChild(input);
        
        cb.closest('tr').classList.add('row-selected');
    });

    document.querySelectorAll('.row-select:not(:checked)').forEach(cb => {
        cb.closest('tr').classList.remove('row-selected');
    });

    countSpan.innerText = selected.length;
    bar.style.setProperty('display', selected.length > 0 ? 'block' : 'none', 'important');
    
    if (selected.length === 0) {
        master.checked = false;
        master.indeterminate = false;
    } else if (selected.length === total) {
        master.checked = true;
        master.indeterminate = false;
    } else {
        master.checked = false;
        master.indeterminate = true;
    }
}

let printStyle = { bold: false, italic: false, underline: false, align: 'center' };

function togglePrintStyle(style) {
    printStyle[style] = !printStyle[style];
    document.getElementById('btnP' + style.charAt(0).toUpperCase() + style.slice(1)).classList.toggle('active', printStyle[style]);
    updatePrintPreview();
}

function setPrintAlignment(align) {
    printStyle.align = align;
    ['left', 'center', 'right'].forEach(a => {
        document.getElementById('pAlign' + a.charAt(0).toUpperCase() + a.slice(1)).classList.toggle('active', align === a);
    });
    updatePrintPreview();
}

function updatePrintPreview() {
    const previewEl = document.getElementById('pPreviewContent');
    const selected = document.querySelectorAll('.row-select:checked');
    let codeText = 'SDU.XX.XX.XX.XX.X';
    if (selected.length > 0) {
        codeText = selected[0].closest('tr').dataset.code || 'SDU.XX.XX.XX.XX.X';
    }
    
    // Add dot separator formatting if code starts with SDU
    if (codeText.toUpperCase().startsWith('SDU') && !codeText.includes('.')) {
        let clean = codeText.replace(/^SDU/i, '');
        if (clean.length >= 8) {
            codeText = 'SDU.' + clean.substring(0, 2) + '.' + clean.substring(2, 4) + '.' + clean.substring(4, 6) + '.' + clean.substring(6);
        }
    }
    
    const showBarcode = document.getElementById('printShowBarcode').value === 'true';
    
    if (showBarcode) {
        const cleanCode = codeText.replace(/\./g, '');
        const barcodeUrl = `https://bwipjs-api.metafloor.com/?bcid=code128&text=${encodeURIComponent(codeText)}&scale=2&height=12&includetext`;
        previewEl.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; height: 100%; gap: 10px; padding: 2px 8px;">
                <div id="pPreviewText" style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${codeText}</div>
                <img src="${barcodeUrl}" style="max-height: 32px; max-width: 100px; object-fit: contain; flex-shrink: 0; filter: grayscale(1);" alt="Barcode">
            </div>
        `;
        const textEl = document.getElementById('pPreviewText');
        applyTextStyle(textEl);
    } else {
        previewEl.innerHTML = `<div id="pPreviewText" style="width: 100%;">${codeText}</div>`;
        const textEl = document.getElementById('pPreviewText');
        applyTextStyle(textEl);
    }
}

function applyTextStyle(el) {
    if (!el) return;
    el.style.fontFamily = document.getElementById('printFont').value;
    el.style.fontSize = document.getElementById('printFontSize').value + 'pt';
    el.style.fontWeight = printStyle.bold ? 'bold' : 'normal';
    el.style.fontStyle = printStyle.italic ? 'italic' : 'normal';
    el.style.textDecoration = printStyle.underline ? 'underline' : 'none';
    el.style.textAlign = printStyle.align;
}

function openBulkPrintModal() {
    const selected = document.querySelectorAll('.row-select:checked');
    const summaryEl = document.getElementById('printItemsSummary');
    summaryEl.innerHTML = '';
    
    selected.forEach(cb => {
        const row = cb.closest('tr');
        const code = row.dataset.code;
        const div = document.createElement('div');
        div.textContent = '• ' + code;
        summaryEl.appendChild(div);
    });
    
    document.getElementById('bulkPrintModal').style.display = 'flex';
    updatePrintPreview();
}

function executeBulkPrint() {
    const selected = document.querySelectorAll('.row-select:checked');
    const codes = Array.from(selected).map(cb => ({
        value: cb.closest('tr').dataset.barcode || cb.closest('tr').dataset.code,
        label: cb.closest('tr').dataset.code
    }));
    
    let layoutMode = parseInt(document.getElementById('printLayout').value);
    if (layoutMode === 999) layoutMode = codes.length;

    const showBarcode = document.getElementById('printShowBarcode').value === 'true';
    const tapeWidth = parseInt(document.getElementById('printTapeWidth').value) || 12;

    const btn = document.getElementById('btnStartBulkPrint');
    const originalText = btn.innerHTML;
    const originalBg = btn.style.backgroundColor;
    const originalBorder = btn.style.borderColor;

    btn.innerHTML = '⏳ กำลังเตรียมพิมพ์...';
    btn.disabled = true;

    fetch('api_print.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            barcodes: codes,
            layoutMode: layoutMode,
            tapeWidth: tapeWidth,
            showBarcode: showBarcode,
            customStyle: {
                font: document.getElementById('printFont').value,
                fontSize: document.getElementById('printFontSize').value + 'pt',
                bold: printStyle.bold,
                italic: printStyle.italic,
                underline: printStyle.underline,
                align: printStyle.align
            }
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '✅ เปิดหน้าต่างพิมพ์แล้ว!';
            btn.style.backgroundColor = '#16a34a';
            btn.style.borderColor = '#16a34a';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.backgroundColor = originalBg;
                btn.style.borderColor = originalBorder;
                btn.disabled = false;
            }, 2000);
        } else {
            alert('Error: ' + data.error);
            btn.innerHTML = originalText;
            btn.style.backgroundColor = originalBg;
            btn.style.borderColor = originalBorder;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.innerHTML = originalText;
        btn.style.backgroundColor = originalBg;
        btn.style.borderColor = originalBorder;
        btn.disabled = false;
    });
}

function cancelSelection() {
    document.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('row-selected');
    });
    const master = document.getElementById('selectAll');
    if (master) master.checked = false;
    updateBulkBar();
}

// Global Signature Pad Instances & State
let addSigPad = null;
let editSigPad = null;

function initSignaturePad(canvasId, inputId) {
    const canvas = document.getElementById(canvasId);
    const input = document.getElementById(inputId);
    if (!canvas) return null;
    
    const ctx = canvas.getContext('2d');
    let drawing = false;
    
    // Exact sizing setup
    canvas.width = canvas.parentElement.offsetWidth;
    canvas.height = canvas.parentElement.offsetHeight;
    
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#1e3a8a'; // SDU Premium Navy blue ink
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches.length > 0) {
            return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
        }
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }
    
    function startDraw(e) {
        drawing = true;
        ctx.beginPath();
        const pos = getPos(e);
        ctx.moveTo(pos.x, pos.y);
    }
    
    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }
    
    function stopDraw() {
        if (drawing) {
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        }
    }
    
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    document.addEventListener('mouseup', stopDraw);
    
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    document.addEventListener('touchend', stopDraw);
    
    return {
        clear: function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            input.value = '';
        },
        load: function(base64Data) {
            if (!base64Data) return;
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                input.value = base64Data;
            };
            img.src = base64Data;
        }
    };
}

function clearAddSignature() {
    if (addSigPad) addSigPad.clear();
}

function clearEditSignature() {
    if (editSigPad) editSigPad.clear();
}

// Realtime Stock Safeguard sync
async function checkRealtimeStock(itemId, qtyInputId, alertId, formSubmitSelector) {
    const qtyInput = document.getElementById(qtyInputId);
    const alertBox = document.getElementById(alertId);
    const submitBtn = document.querySelector(formSubmitSelector);
    
    if (!itemId) {
        alertBox.style.display = 'none';
        return;
    }
    
    try {
        const res = await fetch(`requisitions.php?get_stock=1&item_id=${itemId}`);
        const data = await res.json();
        const availableStock = parseInt(data.quantity) || 0;
        const requestedQty = parseInt(qtyInput.value) || 0;
        
        if (requestedQty > availableStock) {
            alertBox.innerHTML = `⚠️ <strong>เตือนสต็อกไม่เพียงพอ!</strong> ปริมาณคงเหลือจริงคือ <strong>${availableStock}</strong> ชิ้น แต่คุณต้องการเบิก <strong>${requestedQty}</strong> ชิ้น (ระบบระงับการส่งชั่วคราว)`;
            alertBox.style.display = 'block';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        } else {
            alertBox.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }
    } catch (e) {
        console.error("Stock check error", e);
    }
}

function updatePrice() {
    const selector = document.getElementById('item_id');
    const selectedOption = selector.options[selector.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    document.getElementById('price').value = price;
    calcTotal();
}

function calcTotal() {
    const itemId = document.getElementById('item_id').value;
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const price = parseFloat(document.getElementById('price').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total = (qty * price) - discount;
    document.getElementById('total_display').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    checkRealtimeStock(itemId, 'qty', 'addStockAlert', '#reqModal button[type="submit"]');
}

function editReq(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_item_id').value = data.item_id;
    document.getElementById('edit_order_no').value = data.order_no || '';
    document.getElementById('edit_requester_name').value = data.requester_name || '';
    document.getElementById('edit_department').value = data.department || '';
    document.getElementById('edit_position').value = data.position || '';
    document.getElementById('edit_purpose').value = data.purpose || '';
    document.getElementById('edit_qty').value = data.quantity || 1;
    document.getElementById('edit_price').value = data.unit_price || 0;
    document.getElementById('edit_discount').value = data.discount || 0;
    
    document.getElementById('editModal').style.display = 'flex';
    if (window.editItemSelect) {
        window.editItemSelect.setValue(data.item_id);
    }
    calcEditTotal();
    
    // Async load signature to make sure canvas dimension is properly initialized
    setTimeout(() => {
        if (!editSigPad) {
            editSigPad = initSignaturePad('editSignatureCanvas', 'editSignatureInput');
        } else {
            editSigPad.clear();
        }
        if (data.signature_data) {
            editSigPad.load(data.signature_data);
        }
    }, 150);
}

function updateEditPrice() {
    const selector = document.getElementById('edit_item_id');
    const selectedOption = selector.options[selector.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    document.getElementById('edit_price').value = price;
    calcEditTotal();
}

function calcEditTotal() {
    const itemId = document.getElementById('edit_item_id').value;
    const qty = parseFloat(document.getElementById('edit_qty').value) || 0;
    const price = parseFloat(document.getElementById('edit_price').value) || 0;
    const discount = parseFloat(document.getElementById('edit_discount').value) || 0;
    const total = (qty * price) - discount;
    document.getElementById('edit_total_display').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    checkRealtimeStock(itemId, 'edit_qty', 'editStockAlert', '#editModal button[type="submit"]');
}

// === Chrome-like Autocomplete Engine ===
class ChromeAC {
    constructor(inputEl, dropdownEl, options = {}) {
        this.input = inputEl;
        this.dropdown = dropdownEl;
        this.items = options.items || [];
        this.onSelect = options.onSelect || null;
        this.showCode = options.showCode || false;
        this.manageable = options.manageable !== false; // default true
        this.icon = options.icon || 'search';
        this.onEdit = options.onEdit || null;
        this.onDelete = options.onDelete || null;
        this.activeIndex = -1;
        this.isOpen = false;
        this._bindEvents();
    }

    setItems(items) {
        this.items = items;
    }

    _bindEvents() {
        this.input.addEventListener('input', () => this._onInput());
        this.input.addEventListener('focus', () => this._onInput());
        this.input.addEventListener('keydown', (e) => this._onKeydown(e));
        this.input.addEventListener('blur', () => {
            setTimeout(() => this._close(), 200);
        });
    }

    _onInput() {
        const query = this.input.value.trim().toLowerCase();
        if (!query && this.items.length > 0) {
            this._renderAll();
            return;
        }
        if (!query) { this._close(); return; }

        const results = this.items.filter(item => {
            const label = (item.label || item.value || '').toLowerCase();
            const code = (item.code || '').toLowerCase();
            return label.includes(query) || code.includes(query);
        });

        if (results.length === 0) {
            this.dropdown.innerHTML = '<div class="ac-empty">ไม่พบรายการที่ตรงกัน</div>';
            this._open();
            this.activeIndex = -1;
            return;
        }

        this._renderItems(results, query);
    }

    _renderAll() {
        if (this.items.length === 0) { this._close(); return; }
        this._renderItems(this.items, '');
    }

    _renderItems(results, query) {
        this.dropdown.innerHTML = '';
        this.activeIndex = -1;

        results.forEach((item, idx) => {
            const div = document.createElement('div');
            div.className = 'ac-item';
            div.setAttribute('data-index', idx);

            const iconDiv = document.createElement('div');
            iconDiv.className = 'ac-icon';
            iconDiv.innerHTML = this._getIconSVG(this.icon);

            const textDiv = document.createElement('div');
            textDiv.className = 'ac-text';
            textDiv.innerHTML = this._highlight(item.label || item.value, query);

            div.appendChild(iconDiv);
            div.appendChild(textDiv);

            if (this.showCode && item.code) {
                const codeSpan = document.createElement('span');
                codeSpan.className = 'ac-code';
                codeSpan.textContent = item.code;
                div.appendChild(codeSpan);
            }

            // Edit/Delete buttons
            if (this.manageable) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'ac-actions';

                const editBtn = document.createElement('button');
                editBtn.className = 'ac-btn-edit';
                editBtn.type = 'button';
                editBtn.title = 'แก้ไข';
                editBtn.innerHTML = '✏️';
                editBtn.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this._editItem(item);
                });

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'ac-btn-delete';
                deleteBtn.type = 'button';
                deleteBtn.title = 'ลบ';
                deleteBtn.innerHTML = '🗑️';
                deleteBtn.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this._deleteItem(item);
                });

                actionsDiv.appendChild(editBtn);
                actionsDiv.appendChild(deleteBtn);
                div.appendChild(actionsDiv);
            }

            div.addEventListener('mousedown', (e) => {
                if (e.target.closest('.ac-actions')) return;
                e.preventDefault();
                this._selectItem(item);
            });

            this.dropdown.appendChild(div);
        });

        this._open();
    }

    _editItem(item) {
        const oldLabel = item.label || item.value;
        const newLabel = prompt('แก้ไขชื่อ:', oldLabel);
        if (newLabel && newLabel !== oldLabel) {
            const oldCode = item.code || '';
            const newCode = this.showCode ? prompt('แก้ไขรหัส:', oldCode) : oldCode;

            // Update in items array
            const idx = this.items.findIndex(i => (i.label || i.value) === oldLabel);
            if (idx !== -1) {
                this.items[idx].label = newLabel;
                if (this.items[idx].value !== undefined) this.items[idx].value = newLabel;
                if (newCode !== null && newCode !== oldCode) this.items[idx].code = newCode;
            }

            if (this.onEdit) this.onEdit(item, newLabel, newCode);
            this._onInput(); // re-render
        }
    }

    _deleteItem(item) {
        const label = item.label || item.value;
        if (confirm('ยืนยันการลบ "' + label + '" ออกจากรายการทั้งหมด?')) {
            // Update the array in-place to affect all instances sharing the same options
            const index = this.items.findIndex(i => (i.label || i.value) === label);
            if (index !== -1) {
                this.items.splice(index, 1);
            }
            
            if (this.onDelete) this.onDelete(item);
            this._onInput(); // re-render
        }
    }

    _highlight(text, query) {
        if (!query) return this._escapeHtml(text);
        const escaped = this._escapeHtml(text);
        const escapedQuery = this._escapeHtml(query);
        const regex = new RegExp('(' + escapedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return escaped.replace(regex, '<span class="ac-highlight">$1</span>');
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    _getIconSVG(type) {
        const icons = {
            search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            map: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            wallet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
        };
        return icons[type] || icons.search;
    }

    _onKeydown(e) {
        const items = this.dropdown.querySelectorAll('.ac-item');
        if (!this.isOpen || items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1);
            this._updateActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this._updateActive(items);
        } else if (e.key === 'Enter' && this.activeIndex >= 0) {
            e.preventDefault();
            items[this.activeIndex].dispatchEvent(new Event('mousedown'));
        } else if (e.key === 'Escape') {
            this._close();
        }
    }

    _updateActive(items) {
        items.forEach(el => el.classList.remove('ac-active'));
        if (this.activeIndex >= 0 && items[this.activeIndex]) {
            items[this.activeIndex].classList.add('ac-active');
            items[this.activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    _selectItem(item) {
        this.input.value = item.label || item.value;
        this._close();
        if (this.onSelect) this.onSelect(item);
        this.input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    _open() { this.dropdown.classList.add('show'); this.isOpen = true; }
    _close() { this.dropdown.classList.remove('show'); this.isOpen = false; this.activeIndex = -1; }
}

const requesterOptions = <?php echo json_encode(array_map(function($n) { return ['label' => $n, 'value' => $n]; }, $all_names)); ?>;
const positionOptions = <?php echo json_encode(array_map(function($n) { return ['label' => $n, 'value' => $n]; }, $positions_list)); ?>;
const departmentOptions = <?php echo json_encode(array_map(function($n) { return ['label' => $n, 'value' => $n]; }, $departments_list)); ?>;
const orderOptions = <?php echo json_encode(array_map(function($n) { return ['label' => $n, 'value' => $n]; }, $orders_list)); ?>;
const purposeOptions = <?php echo json_encode(array_map(function($n) { return ['label' => $n, 'value' => $n]; }, $purposes_list)); ?>;

// Initialize Searchable Dropdowns
document.addEventListener('DOMContentLoaded', function() {
    const tsConfig = {
        create: false,
        sortField: {
            field: "text",
            direction: "asc"
        },
        placeholder: "🔍 พิมพ์เพื่อค้นหาพัสดุ...",
        noResultsText: "ไม่พบข้อมูลพัสดุ",
        allowEmptyOption: true,
    };
    
    window.itemSelect = new TomSelect("#item_id", tsConfig);
    window.editItemSelect = new TomSelect("#edit_item_id", tsConfig);

    // Initialize Requester Autocomplete
    const acRequester = new ChromeAC(
        document.getElementById('requester_name'),
        document.getElementById('ac_requester_dropdown'),
        {
            items: requesterOptions,
            icon: 'user',
            onDelete: function(item) {
                const formData = new FormData();
                formData.append('ajax_action', 'delete_requester');
                formData.append('name', item.label);
                fetch('requisitions.php', { method: 'POST', body: formData });
            },
            onEdit: function(item, newLabel) {
                const formData = new FormData();
                formData.append('ajax_action', 'rename_requester');
                formData.append('old_name', item.label);
                formData.append('new_name', newLabel);
                fetch('requisitions.php', { method: 'POST', body: formData });
            }
        }
    );

    // Position Autocomplete
    new ChromeAC(document.getElementById('position'), document.getElementById('ac_position_dropdown'), {
        items: positionOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_position');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });
    new ChromeAC(document.getElementById('edit_position'), document.getElementById('ac_edit_position_dropdown'), {
        items: positionOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_position');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });

    // Department Autocomplete
    new ChromeAC(document.getElementById('department'), document.getElementById('ac_department_dropdown'), {
        items: departmentOptions, icon: 'map', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_department');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });
    new ChromeAC(document.getElementById('edit_department'), document.getElementById('ac_edit_department_dropdown'), {
        items: departmentOptions, icon: 'map', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_department');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });

    // Order No Autocomplete
    new ChromeAC(document.getElementById('order_no'), document.getElementById('ac_order_no_dropdown'), {
        items: orderOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_order');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });
    new ChromeAC(document.getElementById('edit_order_no'), document.getElementById('ac_edit_order_no_dropdown'), {
        items: orderOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_order');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });

    // Purpose Autocomplete
    new ChromeAC(document.getElementById('purpose'), document.getElementById('ac_purpose_dropdown'), {
        items: purposeOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_purpose');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });
    new ChromeAC(document.getElementById('edit_purpose'), document.getElementById('ac_edit_purpose_dropdown'), {
        items: purposeOptions, icon: 'search', manageable: true,
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_purpose');
            formData.append('name', item.label);
            fetch('requisitions.php', { method: 'POST', body: formData });
        }
    });

    const acEditRequester = new ChromeAC(
        document.getElementById('edit_requester_name'),
        document.getElementById('ac_edit_requester_dropdown'),
        {
            items: requesterOptions,
            icon: 'user',
            onDelete: function(item) {
                const formData = new FormData();
                formData.append('ajax_action', 'delete_requester');
                formData.append('name', item.label);
                fetch('requisitions.php', { method: 'POST', body: formData });
            },
            onEdit: function(item, newLabel) {
                const formData = new FormData();
                formData.append('ajax_action', 'rename_requester');
                formData.append('old_name', item.label);
                formData.append('new_name', newLabel);
                fetch('requisitions.php', { method: 'POST', body: formData });
            }
        }
    );
});

// Update functions to handle TomSelect if needed (TomSelect triggers 'change' event normally)

// === ค้นหา / กรองตาราง ===
function filterTable() {
    var input = document.getElementById('searchInput').value.toLowerCase().trim();
    var table = document.getElementById('reqTable');
    var rows = table.querySelectorAll('tbody tr');
    var visibleCount = 0;

    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        var cleanText = text.replace(/\./g, '');
        var cleanInput = input.replace(/\./g, '');
        
        if (input === '' || text.indexOf(input) > -1 || cleanText.indexOf(cleanInput) > -1) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    var countEl = document.getElementById('searchCount');
    if (input !== '') {
        countEl.textContent = 'พบ ' + visibleCount + ' รายการ';
    } else {
        countEl.textContent = '';
    }
}

// === Status Dropdown Menu ===
function toggleStatusMenu(badge, reqId) {
    // ปิดเมนูเก่าก่อน
    document.querySelectorAll('.status-menu').forEach(m => m.remove());

    var menu = document.createElement('div');
    menu.className = 'status-menu';
    menu.innerHTML = 
        '<a href="requisitions.php?id=' + reqId + '&status=Pending" class="status-option status-pending">🟡 รออนุมัติ</a>' +
        '<a href="requisitions.php?id=' + reqId + '&status=Approved" class="status-option status-approved">🟢 อนุมัติแล้ว</a>' +
        '<a href="requisitions.php?id=' + reqId + '&status=Rejected" class="status-option status-rejected">🔴 ไม่อนุมัติ</a>';

    // ใช้ fixed positioning เพื่อไม่ให้ถูกตัดโดย overflow ของตาราง
    var rect = badge.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top = (rect.bottom + 6) + 'px';
    menu.style.left = (rect.left + rect.width / 2) + 'px';

    document.body.appendChild(menu);

    // ปิดเมนูเมื่อคลิกที่อื่น
    setTimeout(function() {
        document.addEventListener('click', function closeMenu(e) {
            if (!menu.contains(e.target) && e.target !== badge) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        });
    }, 10);
}
</script>

<style>
/* === Status Dropdown Menu === */
.status-menu {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: var(--card);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    z-index: 999;
    min-width: 150px;
    padding: 6px;
    margin-top: 6px;
    animation: fadeIn 0.15s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateX(-50%) translateY(-5px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.status-option {
    display: block;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.15s;
    white-space: nowrap;
    font-family: 'Sarabun', sans-serif;
    color: var(--text);
}
.status-option:hover {
    background: var(--bg-hover);
}
.status-pending { color: #92400e; }
.status-pending:hover { background: #fef3c7; }
.status-approved { color: var(--success); }
.status-approved:hover { background: #dcfce7; }
.status-rejected { color: #991b1b; }
.status-rejected:hover { background: #fee2e2; }

/* TomSelect Custom Styling */
.ts-control {
    padding: 0.625rem !important;
    border-radius: 0.5rem !important;
    border-color: var(--border) !important;
    font-family: inherit !important;
    background-color: var(--card) !important;
    color: var(--text) !important;
}
.ts-control input {
    font-family: inherit !important;
    color: var(--text) !important;
}
.ts-dropdown {
    border-radius: 0.5rem !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
    border-color: var(--border) !important;
    margin-top: 5px !important;
    background-color: var(--card) !important;
    color: var(--text) !important;
}
.ts-dropdown .active {
    background-color: var(--bg-hover) !important;
    color: var(--primary) !important;
}
.ts-dropdown .option {
    padding: 10px 12px !important;
}
</style>

<!-- Scan Result Modal -->
<div id="scanResultModal">
    <div class="scan-modal-card">
        <div class="scan-modal-header">
            <button class="scan-modal-close" onclick="closeScanModal()">&times;</button>
            <h2>📱 ผลการสแกน Barcode</h2>
            <div class="scan-badge" id="scanBarcodeValue">—</div>
        </div>
        <div class="scan-modal-body">
            <div class="scan-info-grid" id="scanInfoGrid">
                <!-- populated by JS -->
            </div>
        </div>
        <div class="scan-modal-footer">
            <button class="scan-btn secondary" onclick="closeScanModal()">ปิด</button>
            <button class="scan-btn warning" id="scanEditBtn" onclick="scanEditItem()">✏️ แก้ไขข้อมูล</button>
        </div>
    </div>
</div>

<script>
// ==========================================
// Barcode Scanner Listener
// ==========================================
let barcodeBuffer = '';
let barcodeTimeout;
let _scanCurrentReq = null;
let _scanCurrentRow = null;

document.addEventListener('keydown', function(e) {
    const isInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA';
    if (isInput && e.target.id !== 'searchInput') return;
    if (document.getElementById('scanResultModal').style.display === 'flex') return;

    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 3) {
            handleBarcodeScan(barcodeBuffer);
            barcodeBuffer = '';
            e.preventDefault();
        }
    } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimeout);
        barcodeTimeout = setTimeout(() => {
            barcodeBuffer = '';
        }, 50);
    }
});

function handleBarcodeScan(barcode) {
    const cleanBarcode = barcode.trim();
    let row = document.querySelector(`tr[data-barcode="${cleanBarcode}"]`);
    
    if (!row) {
        row = document.querySelector(`tr[data-code="${cleanBarcode}"]`);
    }

    if (!row) {
        const noDots = cleanBarcode.replace(/\./g, '');
        const rows = document.querySelectorAll('tr[data-code], tr[data-barcode]');
        for (let r of rows) {
            const bc = (r.getAttribute('data-barcode') || '').replace(/\./g, '');
            const cd = (r.getAttribute('data-code') || '').replace(/\./g, '');
            if ((bc && bc === noDots) || (cd && cd === noDots)) {
                row = r;
                break;
            }
        }
    }

    if (row) {
        // Highlight the row briefly
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.style.transition = 'background 0.3s';
        row.style.background = '#dbeafe';
        setTimeout(() => { row.style.background = ''; }, 2000);

        // Try to get full req JSON from data attribute
        const jsonStr = row.getAttribute('data-req-json');
        if (jsonStr) {
            try {
                const req = JSON.parse(jsonStr);
                _scanCurrentReq = req;
                _scanCurrentRow = row;
                openScanResultModal(req, cleanBarcode);
                return;
            } catch(e) { /* fallback below */ }
        }

        // Fallback: open edit modal directly
        const editBtn = row.querySelector('button[onclick^="editReq"]');
        if (editBtn) editBtn.click();
    } else {
        // Not found — search in table
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = cleanBarcode;
            if(typeof filterTable === 'function') filterTable();
        }
        showScanNotFound(cleanBarcode);
    }
}

function openScanResultModal(req, scannedCode) {
    const modal = document.getElementById('scanResultModal');
    const grid = document.getElementById('scanInfoGrid');
    const badge = document.getElementById('scanBarcodeValue');

    badge.textContent = '🔍 ' + scannedCode;

    // Format item code with dots
    let displayCode = req.item_code || '';
    if (displayCode.match(/^SDU\d/)) {
        displayCode = displayCode.replace(/^SDU(\d)/, 'SDU.$1');
    }

    // Status badge
    const statusMap = {
        'Pending': { label: '🟡 รออนุมัติ', cls: 'pending' },
        'Approved': { label: '🟢 อนุมัติแล้ว', cls: 'approved' },
        'Rejected': { label: '🔴 ไม่อนุมัติ', cls: 'rejected' }
    };
    const st = statusMap[req.status] || { label: req.status || '—', cls: '' };
    const statusHTML = `<span class="scan-status-badge ${st.cls}">${st.label}</span>`;

    // Condition badge
    const conditionMap = {
        'Good': { label: '✅ ดีมาก', cls: 'good' },
        'Fair': { label: '👍 ดี', cls: 'fair' },
        'Poor': { label: '⚠️ พอใช้', cls: 'poor' },
        'Broken': { label: '❌ ชำรุด', cls: 'broken' }
    };
    const cond = conditionMap[req.condition_status] || { label: req.condition_status || '—', cls: '' };
    const condHTML = `<span class="scan-condition-badge ${cond.cls}">${cond.label}</span>`;

    // Format amount
    const amount = req.amount ? Number(req.amount).toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท' : '—';
    const unitPrice = req.unit_price ? Number(req.unit_price).toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท' : '—';

    // Format date
    let dateStr = '—';
    if (req.requisition_date) {
        const d = new Date(req.requisition_date);
        if (!isNaN(d)) {
            const thYear = d.getFullYear() + 543;
            const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            dateStr = d.getDate() + ' ' + months[d.getMonth()] + ' ' + thYear;
        }
    }

    grid.innerHTML = `
        <div class="scan-info-item full-width">
            <div class="scan-info-label">รายการครุภัณฑ์</div>
            <div class="scan-info-value" style="font-size: 1.1rem;">${req.item_name || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">รหัสครุภัณฑ์</div>
            <div class="scan-info-value code">${displayCode || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">บาร์โค้ด</div>
            <div class="scan-info-value code">${req.barcode || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ประเภท/ชนิด</div>
            <div class="scan-info-value">${req.category || '—'} / ${req.item_type || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">สถานะการเบิก</div>
            <div class="scan-info-value">${statusHTML}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">เลขที่ใบเบิก</div>
            <div class="scan-info-value">${req.order_no || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">วันที่เบิก</div>
            <div class="scan-info-value">${dateStr}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ผู้เบิก</div>
            <div class="scan-info-value">${req.requester_name || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ตำแหน่ง / หน่วยงาน</div>
            <div class="scan-info-value">${req.position || '—'} / ${req.department || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">จำนวน</div>
            <div class="scan-info-value">${req.quantity || 0}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ราคาต่อหน่วย</div>
            <div class="scan-info-value">${unitPrice}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">จำนวนเงินรวม</div>
            <div class="scan-info-value" style="font-size: 1.05rem; color: #2563eb;">${amount}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">สถานที่ตั้ง</div>
            <div class="scan-info-value">${req.item_location || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">สภาพครุภัณฑ์</div>
            <div class="scan-info-value">${condHTML}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">งบประมาณ</div>
            <div class="scan-info-value">${req.item_acquisition || '—'}</div>
        </div>
        ${req.purpose ? `<div class="scan-info-item">
            <div class="scan-info-label">วัตถุประสงค์</div>
            <div class="scan-info-value">${req.purpose}</div>
        </div>` : ''}
    `;

    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
}

function closeScanModal() {
    document.getElementById('scanResultModal').style.display = 'none';
    _scanCurrentReq = null;
    _scanCurrentRow = null;
}

function scanEditItem() {
    const req = _scanCurrentReq;
    const row = _scanCurrentRow;
    closeScanModal();
    if (row) {
        const editBtn = row.querySelector('button[onclick^="editReq"]');
        if (editBtn) editBtn.click();
    } else if (req) {
        editReq(req);
    }
}

function showScanNotFound(code) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 2rem; left: 50%; transform: translateX(-50%);
        background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
        padding: 1rem 2rem; border-radius: 12px; font-weight: 600; z-index: 10002;
        box-shadow: 0 10px 40px rgba(239,68,68,0.4); animation: scanSlideUp 0.3s ease;
        font-family: 'Sarabun', sans-serif;
    `;
    toast.innerHTML = `❌ ไม่พบรายการที่ตรงกับ: <strong>${code}</strong>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; }, 2500);
    setTimeout(() => toast.remove(), 3000);
}

// Close scan modal on Escape or click outside
document.getElementById('scanResultModal').addEventListener('click', function(e) {
    if (e.target === this) closeScanModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('scanResultModal').style.display === 'flex') {
        closeScanModal();
    }
});

function updateReqStatus(reqId, newStatus) {
    if (confirm("คุณยืนยันที่จะเปลี่ยนสถานะใบเบิกนี้เป็น '" + newStatus + "' ใช่หรือไม่?")) {
        window.location.href = 'requisitions.php?id=' + reqId + '&status=' + newStatus;
    }
}

function openAddReqModal() {
    document.getElementById('reqModal').style.display = 'flex';
    setTimeout(() => {
        if (!addSigPad) {
            addSigPad = initSignaturePad('addSignatureCanvas', 'addSignatureInput');
        } else {
            addSigPad.clear();
        }
    }, 150);
}

// Check if URL has ?scan=BARCODE on page load
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const scanCode = urlParams.get('scan');
    if (scanCode) {
        setTimeout(() => {
            handleBarcodeScan(scanCode);
        }, 500);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
