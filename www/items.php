<?php
require_once 'includes/db.php';

// Handle AJAX request for next sequence number
if (isset($_GET['action']) && $_GET['action'] == 'get_next_num') {
    $cat = $_GET['cat'];
    $type = $_GET['type'];
    $year = date('Y') + 543 - 2500;
    
    $stmt = $pdo->prepare("SELECT item_code FROM items WHERE item_code LIKE ? OR item_code LIKE ?");
    $stmt->execute(["SDU$year.$cat.$type.%", "SDU.$year.$cat.$type.%"]);
    $items = $stmt->fetchAll();
    
    $maxNum = 0;
    foreach ($items as $item) {
        $parts = explode('.', $item['item_code']);
        $lastPart = end($parts);
        if (is_numeric($lastPart)) {
            $num = (int)$lastPart;
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    
    $nextNum = $maxNum + 1;
    echo $nextNum; // No padding as requested
    exit;
}

// Handle Rename Subtype
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'rename_subtype') {
    $id = $_POST['id'];
    $new_name = $_POST['new_name'];
    
    $stmt = $pdo->prepare("UPDATE item_subtypes SET name = ? WHERE id = ?");
    $stmt->execute([$new_name, $id]);
    
    header("Location: items.php?success=renamed");
    exit;
}

// Handle Add Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $base_item_code = $_POST['item_code'];
    $barcode_post = $_POST['barcode'] ?? '';
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $subtype_name = trim($_POST['subtype_name']);
    
    // Fetch category info
    $catStmt = $pdo->prepare("SELECT code, name FROM item_categories WHERE id = ?");
    $catStmt->execute([$category_id]);
    $catData = $catStmt->fetch();
    $category = $catData['name'];
    $category_code = $catData['code'];

    // Check if subtype exists
    $subStmt = $pdo->prepare("SELECT name, type_code FROM item_subtypes WHERE category_code = ? AND name = ?");
    $subStmt->execute([$category_code, $subtype_name]);
    $existing_subtype = $subStmt->fetch();

    if ($existing_subtype) {
        $item_type = $existing_subtype['name'];
    } else {
        // Generate new type_code
        $maxStmt = $pdo->prepare("SELECT MAX(CAST(type_code AS INTEGER)) FROM item_subtypes WHERE category_code = ?");
        $maxStmt->execute([$category_code]);
        $max_code = (int)$maxStmt->fetchColumn();
        $type_code = str_pad($max_code + 1, 2, '0', STR_PAD_LEFT);
        
        // Insert new subtype
        $insertSub = $pdo->prepare("INSERT INTO item_subtypes (category_code, type_code, name) VALUES (?, ?, ?)");
        $insertSub->execute([$category_code, $type_code, $subtype_name]);
        
        $item_type = $subtype_name;
    }

    $meaning = $_POST['meaning'] ?? '';
    $remark = $_POST['remark'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = $_POST['unit'];
    $unit_price = $_POST['unit_price'];
    $acquisition_date = $_POST['acquisition_date'];
    $acquisition_method = $_POST['acquisition_method'] ?: 'เงินงบประมาณ';
    $condition = $_POST['condition'] ?: 'Good';
    $location = $_POST['location'];
    $responsible_person = $_POST['responsible_person'] ?: '';
    
    // Server-side validation for item_code placeholders
    if (strpos($base_item_code, '??') !== false || strpos($base_item_code, 'XXXX') !== false) {
        die("Error: Invalid item code. Please ensure category and subtype are selected correctly.");
    }

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($ext, $allowed_ext)) {
            die("Error: อนุญาตให้อัปโหลดเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น");
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            die("Error: ขนาดไฟล์ต้องไม่เกิน 5MB");
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mimes)) {
            die("Error: ไฟล์ที่อัปโหลดไม่ใช่ไฟล์รูปภาพที่ถูกต้อง");
        }
        
        $filename = time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename)) {
            die("Error: ไม่สามารถอัปโหลดไฟล์ได้");
        }
        $image_path = 'uploads/' . $filename;
    }
    
    $insertSQL = "INSERT INTO items (item_code, barcode, name, category, item_type, meaning, remark, quantity, unit, unit_price, acquisition_date, acquisition_method, condition_status, location, responsible_person, image_path, parent_id, seq_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertSQL);
    
    $qty = max(1, $quantity);
    
    if ($qty == 1) {
        $current_barcode = !empty($barcode_post) ? $barcode_post : str_replace('.', '', $base_item_code);
        $stmt->execute([$base_item_code, $current_barcode, $name, $category, $item_type, $meaning, $remark, 1, $unit, $unit_price, $acquisition_date, $acquisition_method, $condition, $location, $responsible_person, $image_path, null, null]);
        $new_id = $pdo->lastInsertId();
        log_audit('INSERT', 'items', $new_id, "เพิ่มครุภัณฑ์ใหม่: " . $name . " (รหัส: " . $base_item_code . ")");
    } else {
        // First, insert the parent record with full quantity
        $current_barcode = !empty($barcode_post) ? $barcode_post : str_replace('.', '', $base_item_code);
        $stmt->execute([$base_item_code, $current_barcode, $name, $category, $item_type, $meaning, $remark, $qty, $unit, $unit_price, $acquisition_date, $acquisition_method, $condition, $location, $responsible_person, $image_path, null, null]);
        $parent_id = $pdo->lastInsertId();
        log_audit('INSERT', 'items', $parent_id, "เพิ่มครุภัณฑ์แม่: " . $name . " (รหัส: " . $base_item_code . ")");
        
        // Insert each sub-item as individual record
        for ($i = 1; $i <= $qty; $i++) {
            $subIdx = str_pad($i, 2, '0', STR_PAD_LEFT);
            $subCode = $base_item_code . '.' . $subIdx;
            $subBarcode = str_replace('.', '', $subCode);
            $subName = $name;
            $stmt->execute([$subCode, $subBarcode, $subName, $category, $item_type, $meaning, $remark, 1, $unit, $unit_price, $acquisition_date, $acquisition_method, $condition, $location, $responsible_person, $image_path, $parent_id, $i]);
            log_audit('INSERT', 'items', $pdo->lastInsertId(), "เพิ่มครุภัณฑ์ย่อย: " . $subName . " (รหัส: " . $subCode . ")");
        }
    }

    header("Location: items.php?success=1");
    exit;
}

// Handle Edit Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $barcode_post = $_POST['barcode'] ?? '';
    $meaning = $_POST['meaning'] ?? '';
    $remark = $_POST['remark'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = $_POST['unit'];
    $unit_price = $_POST['unit_price'];
    $acquisition_date = $_POST['acquisition_date'];
    $acquisition_method = $_POST['acquisition_method'] ?: 'เงินงบประมาณ';
    $condition = $_POST['condition'] ?: 'Good';
    $location = $_POST['location'];
    $responsible_person = $_POST['responsible_person'] ?: '';
    
    $image_query = "";
    $params = [$barcode_post, $name, $meaning, $remark, $quantity, $unit, $unit_price, $acquisition_date, $acquisition_method, $condition, $location, $responsible_person];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename);
        $image_path = 'uploads/' . $filename;
        $image_query = ", image_path = ?";
        $params[] = $image_path;
    }
    
    $params[] = $id;
    
    try {
        $stmt = $pdo->prepare("UPDATE items SET barcode = ?, name = ?, meaning = ?, remark = ?, quantity = ?, unit = ?, unit_price = ?, acquisition_date = ?, acquisition_method = ?, condition_status = ?, location = ?, responsible_person = ? $image_query WHERE id = ?");
        $stmt->execute($params);
    } catch (PDOException $e) {
        die("Error: ไม่สามารถบันทึกข้อมูลได้: " . $e->getMessage());
    }
    
    log_audit('UPDATE', 'items', $id, "แก้ไขครุภัณฑ์: " . $name);
    
    header("Location: items.php?success=edited");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $item_stmt = $pdo->prepare("SELECT id, name, item_code FROM items WHERE id = ?");
    $item_stmt->execute([$_GET['delete']]);
    $item_to_delete = $item_stmt->fetch();
    if ($item_to_delete) {
        log_audit('DELETE', 'items', $_GET['delete'], "ลบครุภัณฑ์: " . $item_to_delete['name'] . " (รหัส: " . $item_to_delete['item_code'] . ")");
        // Also delete children (sub-items)
        $delChildren = $pdo->prepare("DELETE FROM items WHERE parent_id = ?");
        $delChildren->execute([$_GET['delete']]);
    }

    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: items.php");
    exit;
}

// Handle Clear All Data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clear_all') {
    $pdo->query("DELETE FROM requisitions");
    $pdo->query("DELETE FROM items");
    log_audit('DELETE', 'items', 0, "ล้างฐานข้อมูลครุภัณฑ์และใบเบิกทั้งหมด");
    header("Location: items.php?success=cleared");
    exit;
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids)) {
        $allIds = $ids;
        // Also include children of deleted parents
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $childStmt = $pdo->prepare("SELECT id FROM items WHERE parent_id IN ($placeholders)");
        $childStmt->execute($ids);
        $childIds = $childStmt->fetchAll(PDO::FETCH_COLUMN);
        $allIds = array_merge($ids, $childIds);
        
        $allPlaceholders = str_repeat('?,', count($allIds) - 1) . '?';
        
        // Delete linked requisitions first
        $stmt = $pdo->prepare("DELETE FROM requisitions WHERE item_id IN ($allPlaceholders)");
        $stmt->execute($allIds);

        $stmt = $pdo->prepare("DELETE FROM items WHERE id IN ($allPlaceholders)");
        $stmt->execute($allIds);
        log_audit('DELETE', 'items', 0, "ลบครุภัณฑ์แบบกลุ่ม จำนวน " . count($allIds) . " รายการ");
        header("Location: items.php?success=deleted_bulk&count=" . count($allIds));
        exit;
    }
}

// Fetch Master Data
$categories = $pdo->query("SELECT * FROM item_categories ORDER BY code ASC")->fetchAll();
$subtypes = $pdo->query("SELECT * FROM item_subtypes ORDER BY type_code ASC")->fetchAll();

// Fetch history from existing items for more comprehensive autocomplete
$historical_subtypes = $pdo->query("SELECT DISTINCT category, item_type, item_code FROM items WHERE item_type != '' AND item_type IS NOT NULL")->fetchAll();
$responsible_people_list = $pdo->query("SELECT DISTINCT responsible_person FROM items WHERE responsible_person != '' AND responsible_person IS NOT NULL ORDER BY responsible_person ASC")->fetchAll(PDO::FETCH_COLUMN);
$locations_list = $pdo->query("SELECT DISTINCT location FROM items WHERE location != '' AND location IS NOT NULL ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$acquisition_history = $pdo->query("SELECT DISTINCT acquisition_method FROM items WHERE acquisition_method != '' AND acquisition_method IS NOT NULL ORDER BY acquisition_method ASC")->fetchAll(PDO::FETCH_COLUMN);

// Handle Autocomplete Management AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    $response = ['success' => false];
    $action = $_POST['ajax_action'];
    
    if ($action == 'delete_budget') {
        $name = $_POST['name'];
        $stmt = $pdo->prepare("UPDATE items SET acquisition_method = '' WHERE acquisition_method = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'rename_budget') {
        $old = $_POST['old_name'];
        $new = $_POST['new_name'];
        $stmt = $pdo->prepare("UPDATE items SET acquisition_method = ? WHERE acquisition_method = ?");
        $stmt->execute([$new, $old]);
        $response['success'] = true;
    } elseif ($action == 'delete_person') {
        $name = $_POST['name'];
        $stmt = $pdo->prepare("UPDATE items SET responsible_person = '' WHERE responsible_person = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'rename_person') {
        $old = $_POST['old_name'];
        $new = $_POST['new_name'];
        $stmt = $pdo->prepare("UPDATE items SET responsible_person = ? WHERE responsible_person = ?");
        $stmt->execute([$new, $old]);
        $response['success'] = true;
    } elseif ($action == 'delete_location') {
        $name = $_POST['name'];
        $stmt = $pdo->prepare("UPDATE items SET location = '' WHERE location = ?");
        $stmt->execute([$name]);
        $response['success'] = true;
    } elseif ($action == 'rename_location') {
        $old = $_POST['old_name'];
        $new = $_POST['new_name'];
        $stmt = $pdo->prepare("UPDATE items SET location = ? WHERE location = ?");
        $stmt->execute([$new, $old]);
        $response['success'] = true;
    } elseif ($action == 'delete_subtype') {
        $name = $_POST['name'];
        $stmt = $pdo->prepare("DELETE FROM item_subtypes WHERE name = ?");
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
    [data-theme="dark"] .action-btn.primary-outline {
        color: #60a5fa !important;
        border-color: rgba(59, 130, 246, 0.4) !important;
        background: rgba(59, 130, 246, 0.1) !important;
    }
    [data-theme="dark"] .action-btn.primary-outline:hover {
        background: #2563eb !important;
        color: white !important;
    }

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
    .scan-modal-image {
        width: 80px; height: 80px; object-fit: cover; border-radius: 12px;
        border: 2px solid var(--border, #e2e8f0); float: right; margin-left: 1rem;
    }
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
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 15px; flex-wrap: wrap;">
    <div style="flex-shrink: 0; min-width: 200px;">
        <h1 class="page-title" style="white-space: nowrap; margin-bottom: 2px;">รายการครุภัณฑ์</h1>
        <p class="page-subtitle">จัดการและบันทึกข้อมูลครุภัณฑ์ทั้งหมดในระบบ</p>
    </div>
    <div class="action-group">
        <form method="POST" style="margin: 0;" onsubmit="return confirm('คำเตือน: ข้อมูลครุภัณฑ์และประวัติการเบิกทั้งหมดจะถูกลบถาวร ยืนยันการล้างข้อมูล?')">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="action-btn danger">
                <i data-lucide="trash-2"></i>
                ล้างข้อมูลทั้งหมด
            </button>
        </form>
        <a href="api_print_report.php" class="action-btn">
            <i data-lucide="printer"></i>
            พิมพ์รายงานสรุป
        </a>
        <a href="print_barcode_range.php" class="action-btn primary-outline">
            <i data-lucide="tags"></i>
            พิมพ์ Barcode ต่อเนื่อง
        </a>
        <div class="action-btn primary" onclick="document.getElementById('itemModal').style.display='flex'">
            <i data-lucide="plus"></i>
            เพิ่มครุภัณฑ์ใหม่
        </div>
    </div>
</div>
<script>if(window.lucide) lucide.createIcons();</script>

<?php if (isset($_GET['success'])): ?>
<div style="background: #dcfce7; color: var(--success); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid var(--success);">
    <?php 
    if ($_GET['success'] == 'cleared') echo "ล้างข้อมูลทั้งหมดออกจากระบบเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'renamed') echo "เปลี่ยนชื่อชนิดครุภัณฑ์เรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'deleted_bulk') echo "ลบครุภัณฑ์ที่เลือกจำนวน " . intval($_GET['count'] ?? 0) . " รายการเรียบร้อยแล้ว";
    elseif ($_GET['success'] == 'report_opened') echo "📄 เปิดรายงานในเบราว์เซอร์หลัก (Chrome/Edge) เรียบร้อยแล้ว กรุณากดพิมพ์จากหน้าต่างนั้นครับ";
    else echo "บันทึกข้อมูลเรียบร้อยแล้ว";
    ?>
</div>
<?php endif; ?>

<!-- ช่องค้นหา -->
<div style="margin-top: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 15px;">
    <div style="position: relative; flex: 1; max-width: 400px;">
        <input type="text" id="searchInput" placeholder="ค้นหา... (รหัส, ชื่อรายการ, ประเภท)" 
               oninput="filterTable()" 
               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 0.9rem; font-family: 'Sarabun', sans-serif; outline: none; transition: border-color 0.2s; background: var(--card); color: var(--text);">
    </div>
    <span id="searchCount" style="font-size: 0.85rem; color: #94a3b8; white-space: nowrap; margin-left: 5px;"></span>
</div>

<div class="table-container" style="overflow-x: auto;">
    <table id="itemsTable" style="width: 100%; border-collapse: collapse; min-width: 800px;">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" style="cursor: pointer; width: 18px; height: 18px;"></th>
                <th style="width: 10%;">ประเภท</th>
                <th style="width: 10%;">ชนิด</th>
                <th style="width: 45%;">รายการครุภัณฑ์</th>
                <th style="width: 15%;">จำนวน/หน่วย</th>
                <th style="width: 160px;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch all master data joined with inventory
            // Fetch all items (parents and children)
            $query = "
                SELECT 
                    s.id as subtype_id,
                    s.category_code,
                    s.type_code,
                    s.name as subtype_name,
                    c.name as category_name,
                    i.id as inv_id,
                    i.item_code,
                    i.barcode,
                    i.quantity,
                    i.unit,
                    i.unit_price,
                    i.location,
                    i.meaning,
                    i.remark,
                    i.image_path,
                    i.acquisition_date,
                    i.acquisition_method,
                    i.condition_status,
                    i.responsible_person,
                    i.name as item_name,
                    i.item_type,
                    i.category,
                    i.parent_id,
                    i.seq_number
                FROM items i
                LEFT JOIN item_categories c ON i.category = c.name
                LEFT JOIN item_subtypes s ON (i.item_type = s.name AND c.code = s.category_code)
                ORDER BY i.created_at DESC, i.parent_id IS NOT NULL, i.seq_number ASC
            ";
            $all_rows = $pdo->query($query)->fetchAll();
            
            // Group: separate parents from children
            $parents = [];
            $childrenByParent = [];
            foreach ($all_rows as $row) {
                if ($row['parent_id'] === null) {
                    $parents[] = $row;
                } else {
                    $pid = $row['parent_id'];
                    if (!isset($childrenByParent[$pid])) {
                        $childrenByParent[$pid] = [];
                    }
                    $childrenByParent[$pid][] = $row;
                }
            }
            
            // Merge: for each parent, if it has children, set quantity = count(children)
            // and add children data
            $all_items = [];
            foreach ($parents as $p) {
                $pid = $p['inv_id'];
                $p['children'] = $childrenByParent[$pid] ?? [];
                $all_items[] = $p;
            }

            if (empty($all_items)) {
                echo "<tr><td colspan='5' style='text-align: center; color: var(--text-muted); padding: 4rem;'>ยังไม่มีข้อมูลในระบบ</td></tr>";
            } else {
                foreach ($all_items as $item) {
                    $has_inv = !empty($item['inv_id']);
                    $row_style = $has_inv ? "" : "color: var(--text-muted); background: var(--bg-hover);";
                    
                    $cat_code = $item['category_code'];
                    $type_code = $item['type_code'];
                    $subtype_name = $item['item_name'] ?: ($item['subtype_name'] ?: $item['item_type']);
                    
                    // If master data is missing (custom item), try to extract from item_code
                    if (!$cat_code || !$type_code) {
                        $parts = explode('.', $item['item_code']);
                        if (count($parts) >= 4) {
                            if (!$cat_code) $cat_code = $parts[2];
                            if (!$type_code) $type_code = $parts[3];
                        }
                    }
                    
                    $item_json_attr = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    echo "<tr style='{$row_style}' data-id='{$item['inv_id']}' data-code='" . htmlspecialchars($item['item_code']) . "' data-barcode='" . htmlspecialchars($item['barcode'] ?? '') . "' data-item-json='{$item_json_attr}'>";
                    echo "<td style='text-align: center;'><input type='checkbox' class='row-select' value='{$item['inv_id']}' onclick='updateBulkBar()' style='cursor: pointer; width: 16px; height: 16px;'></td>";
                    echo "<td style='text-align: center; vertical-align: middle; font-family: monospace;'>{$cat_code}</td>";
                    echo "<td style='text-align: center; vertical-align: middle; font-family: monospace;'>{$type_code}</td>";
                    echo "<td>";
                    if ($item['image_path']) {
                        echo "<img src='" . $item['image_path'] . "' style='width: 24px; height: 24px; object-fit: cover; border-radius: 4px; float: left; margin-right: 8px;'>";
                    }
                    echo "<div style='display: flex; align-items: center; gap: 8px;'>";
                    echo "<strong>" . htmlspecialchars($subtype_name) . "</strong>";
                    $children = $item['children'] ?? [];
                    if ($has_inv && ($item['quantity'] > 1 || count($children) > 0)) {
                        echo "<span onclick='toggleSubItems({$item['inv_id']}, this)' style='cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 4px; background: #e0f2fe; color: #0284c7; transition: all 0.2s;' title='ดูรายการย่อย'>";
                        echo "<svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='transition: transform 0.2s;'><path d='m6 9 6 6 6-6'/></svg>";
                        echo "</span>";
                    }
                    echo "</div>";
                    if ($item['item_code']) {
                        $displayCode = $item['item_code'];
                        if (preg_match('/^SDU(\d)/', $displayCode)) {
                            $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
                        }
                        echo "<span style='font-size: 10px; color: var(--text-muted); font-weight: normal;'>รหัส: " . htmlspecialchars($displayCode) . "</span>";
                    }
                    echo "</td>";
                    echo "<td style='text-align: center;'>" . ($has_inv ? "<strong>" . number_format($item['quantity']) . "</strong> " . htmlspecialchars($item['unit']) : '0') . "</td>";
                    echo "<td style='text-align: center; vertical-align: middle; padding: 12px 8px;'>";
                    if ($has_inv) {
                        echo "<div class='table-actions'>
                                <a href='print_barcode_pt2730.php?id=" . $item['inv_id'] . "' class='table-action-btn primary'>
                                    <i data-lucide='printer'></i> พิมพ์บาร์โค้ด
                                </a>
                                <button onclick='openEditModal(" . htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') . ")' class='table-action-btn warning'>
                                    <i data-lucide='edit-3'></i> แก้ไขข้อมูล
                                </button>
                                <a href='?delete=" . $item['inv_id'] . "' onclick='return confirm(\"ยืนยันการลบข้อมูลครุภัณฑ์นี้?\")' class='table-action-btn danger'>
                                    <i data-lucide='trash-2'></i> ลบรายการนี้
                                </a>
                              </div>";
                    } else {
                        echo "<button onclick=\"openAddFromMaster('{$item['category_code']}', '{$item['type_code']}', '" . addslashes($item['subtype_name']) . "')\" class='table-action-btn success'>
                                <i data-lucide='plus-circle'></i> เพิ่มข้อมูลใหม่
                              </button>";
                    }
                    echo "</td>";
                    echo "</tr>";
                    
                    $qty = (int)$item['quantity'];
                    if ($has_inv && ($qty > 1 || count($children) > 0)) {
                        echo "<tr id='subitems_{$item['inv_id']}' class='subitem-row' style='display: none;'>";
                        echo "<td colspan='6' style='padding: 0; border-bottom: 2px solid var(--primary);'>";
                        echo "<div class='subitem-container'>";
                        echo "<div class='subitem-header'>";
                        echo "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='8' x2='21' y1='6' y2='6'/><line x1='8' x2='21' y1='12' y2='12'/><line x1='8' x2='21' y1='18' y2='18'/><line x1='3' x2='3.01' y1='6' y2='6'/><line x1='3' x2='3.01' y1='12' y2='12'/><line x1='3' x2='3.01' y1='18' y2='18'/></svg>";
                        echo " รายการครุภัณฑ์ย่อยของ <strong>" . htmlspecialchars($subtype_name) . "</strong> <span class='subitem-badge'>" . ($qty > count($children) ? $qty : count($children)) . " ชิ้น</span>";
                        echo "</div>";
                        
                        echo "<table class='subitem-table'>";
                        echo "<thead><tr>";
                        echo "<th style='text-align: center;'>#</th>";
                        echo "<th>รหัสครุภัณฑ์ย่อย</th>";
                        echo "<th>ชื่อรายการย่อย</th>";
                        echo "<th style='text-align: center;'>บาร์โค้ด</th>";
                        echo "</tr></thead>";
                        echo "<tbody>";
                        
                        if (count($children) > 0) {
                            // New system: children from DB
                            foreach ($children as $childIdx => $child) {
                                $childId = $child['inv_id'];
                                $childCode = $child['item_code'];
                                $childName = htmlspecialchars($child['item_name']);
                                $childBarcode = $child['barcode'] ?: str_replace('.', '', $childCode);
                                $childSeq = $child['seq_number'] ?: ($childIdx + 1);
                                $rowClass = ($childIdx % 2 == 0) ? 'subitem-row-even' : '';
                                
                                $childJsonAttr = htmlspecialchars(json_encode($child, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                
                                echo "<tr class='{$rowClass}' data-id='{$childId}'>";
                                echo "<td style='text-align: center; color: var(--text-muted);'>{$childSeq}</td>";
                                echo "<td><code class='subitem-code'>{$childCode}</code></td>";
                                echo "<td style='color: var(--text);'>{$childName}</td>";
                                echo "<td style='text-align: center; vertical-align: middle;'><div style='display: flex; align-items: center; justify-content: center; gap: 6px;'><svg class='subitem-barcode' data-barcode='{$childBarcode}' data-barcode-text='{$childCode}'></svg><div style='display: flex; flex-direction: column; gap: 3px;'><button onclick='printSubItemBarcode(\"{$childBarcode}\", \"{$childCode}\", \"{$childName}\")' class='table-action-btn primary' style='padding: 4px 8px; font-size: 11px;'>พิมพ์</button><button onclick='openEditModal({$childJsonAttr})' class='table-action-btn warning' style='padding: 4px 8px; font-size: 11px;'>แก้ไข</button></div></div></td>";
                                echo "</tr>";
                            }
                        } else {
                            // Old system: dynamically generate (backward compat)
                            $baseItemCode2 = $item['item_code'];
                            if (preg_match('/^SDU(\d)/', $baseItemCode2)) {
                                $baseItemCode2 = preg_replace('/^SDU(\d)/', 'SDU.$1', $baseItemCode2);
                            }
                            for ($i = 1; $i <= $qty; $i++) {
                                $subIdxStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                                $subCodeDisplay = $baseItemCode2 . '.' . $subIdxStr;
                                $barcodeClean = str_replace('.', '', $subCodeDisplay);
                                $rowClass = ($i % 2 == 0) ? 'subitem-row-even' : '';
                                echo "<tr class='{$rowClass}'>";
                                echo "<td style='text-align: center; color: var(--text-muted);'>{$i}</td>";
                                echo "<td><code class='subitem-code'>{$subCodeDisplay}</code></td>";
                                echo "<td style='color: var(--text);'>" . htmlspecialchars($subtype_name) . "</td>";
                                echo "<td style='text-align: center; vertical-align: middle;'><div style='display: flex; align-items: center; justify-content: center; gap: 6px;'><svg class='subitem-barcode' data-barcode='{$barcodeClean}' data-barcode-text='{$subCodeDisplay}'></svg><div style='display: flex; flex-direction: column; gap: 3px;'><a href='print_barcode_pt2730.php?id=" . $item['inv_id'] . "' class='table-action-btn primary' style='padding: 4px 8px; font-size: 11px; text-decoration: none;'>พิมพ์</a><button onclick='openEditModal({$item_json_attr})' class='table-action-btn warning' style='padding: 4px 8px; font-size: 11px;'>แก้ไข</button></div></div></td>";
                                echo "</tr>";
                            }
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                        
                        echo "</div>";
                        echo "</td>";
                        echo "</tr>";
                    }
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
            <form method="POST" id="bulkDeleteForm" style="margin: 0 !important; display: flex !important; align-items: center !important;" onsubmit="return confirm('ยืนยันการลบครุภัณฑ์ที่เลือกทั้งหมด?')">
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

<!-- Bulk Print Modal -->
<!-- Bulk Print Modal (WYSIWYG Upgraded) -->
<div id="bulkPrintModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 1.5rem 1rem;">
    <div style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 900px; margin: 0 auto; padding: 2rem; position: relative;">
        <button type="button" onclick="document.getElementById('bulkPrintModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        <h2 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="printer" style="width: 28px; height: 28px; color: var(--primary); flex-shrink: 0;"></i> เครื่องมือออกแบบป้ายก่อนพิมพ์ (WYSIWYG Label Creator)
        </h2>
        
        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem;">
            <!-- Sidebar Settings -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; overflow-y: auto; max-height: 520px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">แบบอักษร (Font Family)</div>
                <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                    <select id="printFont" onchange="updatePrintPreview()" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px;">
                        <option value="'Sarabun', sans-serif">Sarabun (Standard)</option>
                        <option value="'Arial', sans-serif">Arial (Sleek)</option>
                        <option value="'Courier New', monospace">Courier New (Mono)</option>
                    </select>
                </div>

                <!-- รหัสครุภัณฑ์ (Asset Code Settings) -->
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">รหัสครุภัณฑ์ (Code Settings)</div>
                <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">ขนาดอักษร</span>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <input type="number" id="printCodeFontSize" value="14" min="6" max="72" oninput="updatePrintPreview()" style="width: 55px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; text-align: center;">
                            <span style="font-size: 0.7rem; color: #94a3b8;">pt</span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                        <button type="button" class="ac-btn-style" id="printCodeBold" onclick="toggleElementStyle('printCodeBold')">B</button>
                        <button type="button" class="ac-btn-style" id="printCodeItalic" onclick="toggleElementStyle('printCodeItalic')">I</button>
                        <button type="button" class="ac-btn-style" id="printCodeUnderline" onclick="toggleElementStyle('printCodeUnderline')">U</button>
                    </div>
                </div>

                <!-- ชื่อครุภัณฑ์ (Item Name Settings) -->
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">ชื่อครุภัณฑ์ (Name Settings)</div>
                <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">แสดงชื่อครุภัณฑ์</span>
                        <input type="checkbox" id="printShowName" onchange="resetWYSIWYGPositions()" style="width: 18px; height: 18px; cursor: pointer;">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">ขนาดอักษร</span>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <input type="number" id="printNameFontSize" value="9" min="6" max="72" oninput="updatePrintPreview()" style="width: 55px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; text-align: center;">
                            <span style="font-size: 0.7rem; color: #94a3b8;">pt</span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                        <button type="button" class="ac-btn-style" id="printNameBold" onclick="toggleElementStyle('printNameBold')">B</button>
                        <button type="button" class="ac-btn-style" id="printNameItalic" onclick="toggleElementStyle('printNameItalic')">I</button>
                        <button type="button" class="ac-btn-style" id="printNameUnderline" onclick="toggleElementStyle('printNameUnderline')">U</button>
                    </div>
                </div>

                <!-- แท่งบาร์โค้ด (Barcode Settings) -->
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">แท่งบาร์โค้ด (Barcode)</div>
                <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                    <div>
                        <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">แสดงแท่งบาร์โค้ด</label>
                        <select id="printShowBarcode" onchange="resetWYSIWYGPositions()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="false">ไม่แสดง (แสดงเฉพาะข้อความ)</option>
                            <option value="true" selected>แสดงบาร์โค้ด (มีแท่งสแกน)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">ข้อมูลในบาร์โค้ด</label>
                        <select id="printBarcodeData" onchange="updatePrintPreview()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="code" selected>รหัสครุภัณฑ์ (สแกนง่าย)</option>
                            <option value="url">ฝังลิงก์ URL (สแกนด้วยมือถือ)</option>
                        </select>
                    </div>
                </div>

                <!-- Layout & Tape Specifications -->
                <div style="background: #e2e8f0; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">การจัดหน้า & ขนาดเทป</div>
                <div style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <div>
                        <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">รูปแบบการจัดวาง (Layout)</label>
                        <select id="printLayout" onchange="resetWYSIWYGPositions()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="1" selected>1 รายการ / แผ่น</option>
                            <option value="2">2 รายการ / แผ่น</option>
                            <option value="4">4 รายการ / แผ่น</option>
                            <option value="999">แบบต่อเนื่อง (พิมพ์ยาวรวดเดียว)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">ขนาดความกว้างเทป (Tape Size)</label>
                        <select id="printTapeWidth" onchange="resetWYSIWYGPositions()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="12" selected>12 mm</option>
                            <option value="18">18 mm</option>
                            <option value="24">24 mm</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">ความยาวฉลากเดี่ยว (Label Length)</label>
                        <select id="printLabelLength" onchange="resetWYSIWYGPositions()" style="width: 100%; padding: 4px; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="58">58 mm (สั้นพิเศษ - ประหยัดเทป)</option>
                            <option value="70">70 mm (ขนาดปกติ)</option>
                            <option value="85">85 mm (ขนาดกลาง)</option>
                            <option value="100" selected>100 mm (ขนาดปกติ PT-2730)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Preview Area -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="font-size: 0.9rem; color: #64748b; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                    <span>✨ จำลองรูปแบบสติกเกอร์บาร์โค้ดจริง (พิมพ์มาตราส่วน 1:1)</span>
                    <button type="button" onclick="resetWYSIWYGPositions()" class="ac-btn-style" style="padding: 2px 8px; font-size: 11px; font-weight: bold; border-radius: 6px;">Reset ตำแหน่ง</button>
                </div>
                
                <!-- True 1mm = 5px WYSIWYG Canvas Box -->
                <div id="pPreviewContainer" style="background: #94a3b8; border-radius: 12px; padding: 2rem; display: flex; align-items: center; justify-content: center; min-height: 180px; overflow-x: auto; box-shadow: inset 0 3px 10px rgba(0,0,0,0.15);">
                    <div id="pPreviewLabel" style="background: white; width: 550px; height: 60px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); position: relative; overflow: hidden; border: 1px dashed #cbd5e1; transition: width 0.2s, height 0.2s;">
                        <!-- WYSIWYG Elements -->
                        <div id="wysiwyg_code" style="position: absolute; top: 4px; left: 10px; cursor: move; font-family: 'Sarabun', sans-serif; font-size: 14pt; font-weight: bold; color: black; white-space: nowrap; user-select: none;">
                            SDU.69.01.01.06.1
                        </div>
                        <div id="wysiwyg_name" style="position: absolute; top: 32px; left: 10px; cursor: move; font-family: 'Sarabun', sans-serif; font-size: 9pt; color: black; white-space: nowrap; user-select: none; display: none;">
                            ชื่อครุภัณฑ์ตัวอย่าง
                        </div>
                        <div id="wysiwyg_barcode" style="position: absolute; top: 10px; left: 280px; width: 200px; height: 40px; cursor: move; display: none; background: repeating-linear-gradient(90deg, #000, #000 2px, #fff 2px, #fff 5px); user-select: none;">
                        </div>
                    </div>
                </div>
                
                <span style="font-size: 0.75rem; color: #e2e8f0; background: #64748b; padding: 6px 12px; border-radius: 6px; text-align: center; font-weight: 500;">💡 แนะนำ: คุณสามารถเอาเมาส์คลิกเพื่อขยับ "รหัส" "ชื่อ" หรือ "บาร์โค้ด" สลับตำแหน่งกันได้อิสระ!</span>

                <div style="font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                    <strong>📋 รายการครุภัณฑ์ที่จะพิมพ์:</strong>
                    <div id="printItemsSummary" style="margin-top: 5px; font-family: monospace; max-height: 100px; overflow-y: auto;"></div>
                </div>
                
                <div style="text-align: right; margin-top: auto; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn" onclick="document.getElementById('bulkPrintModal').style.display='none'" style="background: #e2e8f0;">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="btnStartBulkPrint" onclick="executeBulkPrint()">🖨️ เริ่มการพิมพ์ทั้งหมด</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes slideUp { from { transform: translate(-50%, 100%); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
html:not([data-theme="dark"]) .row-selected td { 
    background-color: #ffffff !important; 
    color: #0f172a !important;
    transition: background-color 0.15s ease;
}
html:not([data-theme="dark"]) .row-selected:hover td { 
    background-color: #f1f5f9 !important; 
}
html:not([data-theme="dark"]) .row-selected td strong,
html:not([data-theme="dark"]) .row-selected td span,
html:not([data-theme="dark"]) .row-selected td div,
html:not([data-theme="dark"]) .row-selected td code {
    color: #0f172a !important;
}
html:not([data-theme="dark"]) .row-selected td code {
    background-color: rgba(0, 0, 0, 0.05) !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
}
html:not([data-theme="dark"]) .row-selected td .text-muted {
    color: #475569 !important;
}

/* Dark Mode Selected Row Premium Styling */
[data-theme="dark"] .row-selected td {
    background-color: rgba(59, 130, 246, 0.15) !important;
    transition: background-color 0.15s ease;
}
[data-theme="dark"] .row-selected:hover td {
    background-color: rgba(59, 130, 246, 0.25) !important;
}
.ac-btn-style { background: white; border: 1px solid var(--border); padding: 6px; border-radius: 4px; cursor: pointer; transition: all 0.2s; font-weight: bold; }
.ac-btn-style:hover { background: var(--bg-hover); }
.ac-btn-style.active { background: #dbeafe; border-color: #3b82f6; color: #2563eb; }

/* Table Actions Styles */
.table-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: center;
    justify-content: center;
}
.table-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 140px;
    height: 34px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    background: white;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s;
    gap: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.table-action-btn i,
.table-action-btn svg,
.table-action-btn .lucide {
    width: 14px !important;
    height: 14px !important;
    stroke-width: 2.5 !important;
    flex-shrink: 0 !important;
}
.table-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.table-action-btn.primary { border-color: #3b82f6; color: #2563eb; background: #eff6ff; }
.table-action-btn.primary:hover { background: #2563eb; color: white; border-color: #2563eb; }
.table-action-btn.warning { border-color: #f59e0b; color: #d97706; background: #fffbeb; }
.table-action-btn.warning:hover { background: #f59e0b; color: white; border-color: #f59e0b; }
.table-action-btn.danger { border-color: #ef4444; color: #dc2626; background: #fef2f2; }
.table-action-btn.danger:hover { background: #ef4444; color: white; border-color: #ef4444; }
.table-action-btn.success { border-color: #10b981; color: #059669; background: #ecfdf5; }
.table-action-btn.success:hover { background: #10b981; color: white; border-color: #10b981; }

/* Dark Mode overrides for table-action-btn */
[data-theme="dark"] .table-action-btn {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .table-action-btn.primary {
    background: rgba(59, 130, 246, 0.12) !important;
    color: #60a5fa !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
}
[data-theme="dark"] .table-action-btn.primary:hover {
    background: #2563eb !important;
    color: white !important;
    border-color: #2563eb !important;
}
[data-theme="dark"] .table-action-btn.warning {
    background: rgba(245, 158, 11, 0.12) !important;
    color: #fbbf24 !important;
    border-color: rgba(245, 158, 11, 0.4) !important;
}
[data-theme="dark"] .table-action-btn.warning:hover {
    background: #f59e0b !important;
    color: white !important;
    border-color: #f59e0b !important;
}
[data-theme="dark"] .table-action-btn.danger {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
}
[data-theme="dark"] .table-action-btn.danger:hover {
    background: #ef4444 !important;
    color: white !important;
    border-color: #ef4444 !important;
}
[data-theme="dark"] .table-action-btn.success {
    background: rgba(16, 185, 129, 0.12) !important;
    color: #34d399 !important;
    border-color: rgba(16, 185, 129, 0.4) !important;
}
[data-theme="dark"] .table-action-btn.success:hover {
    background: #10b981 !important;
    color: white !important;
    border-color: #10b981 !important;
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
.action-btn-bulk i { width: 16px; height: 16px; }
.action-btn-bulk.primary { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
.action-btn-bulk.primary:hover { background: #2563eb; transform: translateY(-2px); }
.action-btn-bulk.danger { background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
.action-btn-bulk.danger:hover { background: #dc2626; transform: translateY(-2px); }
.action-btn-bulk.secondary { background: #334155; color: white; border: 1px solid rgba(255,255,255,0.1); }
.action-btn-bulk.secondary:hover { background: #475569; transform: translateY(-2px); }
</style>

<script>
    if(window.lucide) lucide.createIcons();
</script>

<script>
function toggleSubItems(id, iconEl) {
    const row = document.getElementById('subitems_' + id);
    if (!row) return;
    const isHidden = row.style.display === 'none';
    row.style.display = isHidden ? 'table-row' : 'none';
    
    // Rotate the chevron icon
    const svg = iconEl.querySelector('svg');
    if (svg) {
        svg.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
    }
    
    // Render barcodes on first open
    if (isHidden && !row.dataset.rendered) {
        row.dataset.rendered = '1';
        row.querySelectorAll('.subitem-barcode').forEach(function(svgEl) {
            var val = svgEl.getAttribute('data-barcode');
            var displayText = svgEl.getAttribute('data-barcode-text') || val;
            if (val && typeof JsBarcode !== 'undefined') {
                try {
                    JsBarcode(svgEl, val.replace(/\./g, ''), {
                        format: 'CODE128',
                        width: 1,
                        height: 28,
                        displayValue: true,
                        text: displayText,
                        fontSize: 8,
                        margin: 1
                    });
                } catch(e) { /* ignore invalid */ }
            }
        });
        // Render lucide icons for the newly visible buttons
        if (window.lucide) lucide.createIcons();
    }
}

function printSubItemBarcode(barcodeClean, barcodeText, itemName) {
    var w = 500, h = 350;
    var x = Math.max(0, (screen.width - w) / 2);
    var y = Math.max(0, (screen.height - h) / 2);
    var printWin = window.open('', '_blank', 'width=' + w + ',height=' + h + ',left=' + x + ',top=' + y + ',menubar=no,toolbar=no,location=no,status=no,scrollbars=yes');
    if (!printWin) {
        alert('กรุณาอนุญาต Pop-up เพื่อพิมพ์บาร์โค้ด');
        return;
    }
    printWin.document.write('<!DOCTYPE html><html><head><title>พิมพ์บาร์โค้ด</title>');
    printWin.document.write('<style>body{margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;font-family:Sarabun,sans-serif;}.barcode-wrap{text-align:center;max-width:100%;}.barcode-wrap img{max-width:100%;height:auto;}.name{font-size:14px;margin-bottom:10px;color:#333;}@media print{@page{margin:0;}body{padding:10px;}}</style>');
    printWin.document.write('</head><body>');
    printWin.document.write('<div class="name">' + itemName + '</div>');
    printWin.document.write('<div class="barcode-wrap"><img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=' + encodeURIComponent(barcodeClean) + '&scale=2&height=15&includetext=true&textxalign=center" alt="Barcode"></div>');
    printWin.document.write('<script>setTimeout(function(){window.print();window.close();},500);<\/script>');
    printWin.document.write('</body></html>');
    printWin.document.close();
}

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
    bar.style.display = selected.length > 0 ? 'flex' : 'none';
    
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

function cancelSelection() {
    document.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('row-selected');
    });
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
}

// ===== WYSIWYG Label Creator Logic =====
let wysiwygInitialized = false;

function toggleElementStyle(btnId) {
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.classList.toggle('active');
        updatePrintPreview();
    }
}

function makeElementDraggable(el, container) {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    el.addEventListener('mousedown', dragMouseDown);

    function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.addEventListener('mouseup', closeDragElement);
        document.addEventListener('mousemove', elementDrag);
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        
        let newTop = el.offsetTop - pos2;
        let newLeft = el.offsetLeft - pos1;
        
        // Boundaries constraint (stay inside container)
        const maxTop = container.clientHeight - el.clientHeight;
        const maxLeft = container.clientWidth - el.clientWidth;
        
        if (newTop < 0) newTop = 0;
        if (newTop > maxTop) newTop = maxTop;
        if (newLeft < 0) newLeft = 0;
        if (newLeft > maxLeft) newLeft = maxLeft;
        
        el.style.top = newTop + "px";
        el.style.left = newLeft + "px";
    }

    function closeDragElement() {
        document.removeEventListener('mouseup', closeDragElement);
        document.removeEventListener('mousemove', elementDrag);
    }
}

function initWYSIWYGDragging() {
    if (wysiwygInitialized) return;
    const container = document.getElementById('pPreviewLabel');
    if (container) {
        makeElementDraggable(document.getElementById('wysiwyg_code'), container);
        makeElementDraggable(document.getElementById('wysiwyg_name'), container);
        makeElementDraggable(document.getElementById('wysiwyg_barcode'), container);
        wysiwygInitialized = true;
    }
}

function resetWYSIWYGPositions() {
    const showBarcode = document.getElementById('printShowBarcode').value === 'true';
    const showName = document.getElementById('printShowName').checked;
    const tapeWidth = parseInt(document.getElementById('printTapeWidth').value) || 12;
    const layoutMode = parseInt(document.getElementById('printLayout').value) || 1;
    
    let singleLabelLength = parseInt(document.getElementById('printLabelLength').value) || 100;
    
    const w = singleLabelLength * 5;
    const h = tapeWidth * 5;
    
    const codeEl = document.getElementById('wysiwyg_code');
    const nameEl = document.getElementById('wysiwyg_name');
    const barcodeEl = document.getElementById('wysiwyg_barcode');
    
    // Auto layout algorithm based on configurations
    if (showBarcode && showName) {
        // Code top left
        codeEl.style.top = "4px";
        codeEl.style.left = "10px";
        
        // Name bottom left
        nameEl.style.top = (h - 22) + "px";
        nameEl.style.left = "10px";
        
        // Barcode right
        barcodeEl.style.top = "6px";
        barcodeEl.style.left = (w - 180) + "px";
        barcodeEl.style.width = "160px";
    } else if (showBarcode && !showName) {
        // Code vertically centered left
        codeEl.style.top = ((h - 24) / 2) + "px";
        codeEl.style.left = "15px";
        
        // Barcode right
        barcodeEl.style.top = "6px";
        barcodeEl.style.left = (w - 180) + "px";
        barcodeEl.style.width = "160px";
    } else if (!showBarcode && showName) {
        // Code top center
        codeEl.style.top = "4px";
        codeEl.style.left = "20px";
        
        // Name bottom center
        nameEl.style.top = (h - 22) + "px";
        nameEl.style.left = "20px";
    } else {
        // Only Code: centered completely
        codeEl.style.top = ((h - 24) / 2) + "px";
        codeEl.style.left = "20px";
    }
    
    updatePrintPreview();
}

function updatePrintPreview() {
    const previewLabel = document.getElementById('pPreviewLabel');
    if (!previewLabel) return;
    
    const selected = document.querySelectorAll('.row-select:checked');
    let codeText = 'SDU.XX.XX.XX.XX.X';
    let nameText = 'ชื่อครุภัณฑ์ตัวอย่าง';
    
    if (selected.length > 0) {
        const row = selected[0].closest('tr');
        codeText = row.dataset.code || 'SDU.XX.XX.XX.XX.X';
        const itemJson = row.getAttribute('data-item-json');
        if (itemJson) {
            try {
                const item = JSON.parse(itemJson);
                nameText = item.item_name || item.subtype_name || item.item_type || 'ชื่อครุภัณฑ์ตัวอย่าง';
            } catch(e) {}
        }
    }
    
    // Add dot separator formatting if code starts with SDU
    if (codeText.toUpperCase().startsWith('SDU') && !codeText.includes('.')) {
        let clean = codeText.replace(/^SDU/i, '');
        if (clean.length >= 8) {
            codeText = 'SDU.' + clean.substring(0, 2) + '.' + clean.substring(2, 4) + '.' + clean.substring(4, 6) + '.' + clean.substring(6);
        }
    }
    
    document.getElementById('wysiwyg_code').innerText = codeText;
    document.getElementById('wysiwyg_name').innerText = nameText;
    
    const showBarcode = document.getElementById('printShowBarcode').value === 'true';
    const showName = document.getElementById('printShowName').checked;
    const tapeWidth = parseInt(document.getElementById('printTapeWidth').value) || 12;
    const layoutMode = parseInt(document.getElementById('printLayout').value) || 1;
    
    document.getElementById('wysiwyg_name').style.display = showName ? 'block' : 'none';
    document.getElementById('wysiwyg_barcode').style.display = showBarcode ? 'block' : 'none';
    
    // Recalculate dimensions (1mm = 5px scale)
    let singleLabelLength = parseInt(document.getElementById('printLabelLength').value) || 100;
    
    const previewWidth = singleLabelLength * 5;
    const previewHeight = tapeWidth * 5;
    
    previewLabel.style.width = previewWidth + 'px';
    previewLabel.style.height = previewHeight + 'px';
    
    // Style Code
    const codeEl = document.getElementById('wysiwyg_code');
    codeEl.style.fontFamily = document.getElementById('printFont').value;
    codeEl.style.fontSize = document.getElementById('printCodeFontSize').value + 'pt';
    codeEl.style.fontWeight = document.getElementById('printCodeBold').classList.contains('active') ? 'bold' : 'normal';
    codeEl.style.fontStyle = document.getElementById('printCodeItalic').classList.contains('active') ? 'italic' : 'normal';
    codeEl.style.textDecoration = document.getElementById('printCodeUnderline').classList.contains('active') ? 'underline' : 'none';
    
    // Style Name
    const nameEl = document.getElementById('wysiwyg_name');
    nameEl.style.fontFamily = document.getElementById('printFont').value;
    nameEl.style.fontSize = document.getElementById('printNameFontSize').value + 'pt';
    nameEl.style.fontWeight = document.getElementById('printNameBold').classList.contains('active') ? 'bold' : 'normal';
    nameEl.style.fontStyle = document.getElementById('printNameItalic').classList.contains('active') ? 'italic' : 'normal';
    nameEl.style.textDecoration = document.getElementById('printNameUnderline').classList.contains('active') ? 'underline' : 'none';
    
    // Style Barcode preview height based on tape size
    const barcodeEl = document.getElementById('wysiwyg_barcode');
    let barcodeH = (tapeWidth - 2) * 5;
    if (barcodeH < 15) barcodeH = 15;
    barcodeEl.style.height = barcodeH + 'px';
    
    // Set dynamic scan-ready barcode image instead of repeating gradient
    const cleanCode = codeText.replace(/\./g, '');
    const barcodeUrl = `https://bwipjs-api.metafloor.com/?bcid=code128&text=${encodeURIComponent(codeText)}&scale=2&height=12&includetext=true&textxalign=center`;
    barcodeEl.innerHTML = `<img src="${barcodeUrl}" style="width: 100%; height: 100%; object-fit: fill; pointer-events: none; filter: grayscale(1);" alt="Barcode">`;
    
    // Bound/Clamp constraints
    clampElementPosition(codeEl, previewWidth, previewHeight);
    clampElementPosition(nameEl, previewWidth, previewHeight);
    clampElementPosition(barcodeEl, previewWidth, previewHeight);
}

function clampElementPosition(el, containerWidth, containerHeight) {
    let top = parseInt(el.style.top) || 0;
    let left = parseInt(el.style.left) || 0;
    
    const maxTop = containerHeight - el.clientHeight;
    const maxLeft = containerWidth - el.clientWidth;
    
    if (top < 0) top = 0;
    if (top > maxTop && maxTop > 0) top = maxTop;
    if (left < 0) left = 0;
    if (left > maxLeft && maxLeft > 0) left = maxLeft;
    
    el.style.top = top + 'px';
    el.style.left = left + 'px';
}

function openBulkPrintModal() {
    const selected = document.querySelectorAll('.row-select:checked');
    const summaryEl = document.getElementById('printItemsSummary');
    summaryEl.innerHTML = '';
    selected.forEach(cb => {
        const row = cb.closest('tr');
        const div = document.createElement('div');
        div.textContent = '• ' + row.dataset.code;
        summaryEl.appendChild(div);
    });
    
    document.getElementById('bulkPrintModal').style.display = 'flex';
    
    // Initialize Drag & Drop WYSIWYG
    setTimeout(() => {
        initWYSIWYGDragging();
        resetWYSIWYGPositions();
    }, 100);
}

function executeBulkPrint() {
    const selected = document.querySelectorAll('.row-select:checked');
    const codes = Array.from(selected).map(cb => {
        const row = cb.closest('tr');
        let nameVal = '';
        const itemJson = row.getAttribute('data-item-json');
        if (itemJson) {
            try {
                const item = JSON.parse(itemJson);
                nameVal = item.item_name || item.subtype_name || item.item_type || '';
            } catch(e) {}
        }
        return {
            value: row.dataset.barcode || row.dataset.code,
            label: row.dataset.code,
            name: nameVal
        };
    });
    
    let layoutMode = parseInt(document.getElementById('printLayout').value);
    if (layoutMode === 999) layoutMode = codes.length;

    const showBarcode = document.getElementById('printShowBarcode').value === 'true';
    const showName = document.getElementById('printShowName').checked;
    const tapeWidth = parseInt(document.getElementById('printTapeWidth').value) || 12;
    const barcodeDataMode = document.getElementById('printBarcodeData').value;
    
    const codeEl = document.getElementById('wysiwyg_code');
    const nameEl = document.getElementById('wysiwyg_name');
    const barcodeEl = document.getElementById('wysiwyg_barcode');

    // Convert coordinates from px to mm (1mm = 5px)
    const customLayout = {
        showCode: true,
        showName: showName,
        showBarcode: showBarcode,
        codeStyle: `top: ${(parseInt(codeEl.style.top) || 0) / 5}mm !important; left: ${(parseInt(codeEl.style.left) || 0) / 5}mm !important; font-size: ${document.getElementById('printCodeFontSize').value}pt !important; font-weight: ${codeEl.style.fontWeight} !important; font-style: ${codeEl.style.fontStyle} !important; text-decoration: ${codeEl.style.textDecoration} !important; font-family: ${document.getElementById('printFont').value} !important;`,
        nameStyle: `top: ${(parseInt(nameEl.style.top) || 0) / 5}mm !important; left: ${(parseInt(nameEl.style.left) || 0) / 5}mm !important; font-size: ${document.getElementById('printNameFontSize').value}pt !important; font-weight: ${nameEl.style.fontWeight} !important; font-style: ${nameEl.style.fontStyle} !important; text-decoration: ${nameEl.style.textDecoration} !important; font-family: ${document.getElementById('printFont').value} !important;`,
        barcodeStyle: `top: ${(parseInt(barcodeEl.style.top) || 0) / 5}mm !important; left: ${(parseInt(barcodeEl.style.left) || 0) / 5}mm !important; width: ${(parseInt(barcodeEl.style.width) || 160) / 5}mm !important; height: ${(parseInt(barcodeEl.style.height) || 30) / 5}mm !important;`
    };

    const baseUrl = window.location.origin + window.location.pathname.replace('items.php', '');

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
            barcodeDataMode: barcodeDataMode,
            baseUrl: baseUrl,
            customLayout: customLayout,
            labelLength: parseInt(document.getElementById('printLabelLength').value) || 100
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

function openAddFromMaster(catCode, typeCode, name) {
    document.getElementById('itemModal').style.display = 'flex';
    
    // Set category
    const catSelect = document.getElementById('category_select');
    for(let opt of catSelect.options) {
        if(opt.getAttribute('data-code') === catCode) {
            catSelect.value = opt.value;
            break;
        }
    }
    
    // Trigger change to populate subtypes
    catSelect.dispatchEvent(new Event('change'));
    
    // Set subtype
    const subInput = document.getElementById('subtype_input');
    setTimeout(() => {
        if (subInput) {
            subInput.value = name;
            subInput.dispatchEvent(new Event('input'));
        }
    }, 100);
}
</script>

<!-- Add Modal -->
<div id="itemModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 2rem 1rem;">
    <table style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 850px; margin: 0 auto; border-collapse: collapse; border-style: hidden; box-shadow: 0 0 0 0 transparent;">
        <tr>
            <td style="padding: 2rem; position: relative; vertical-align: top;">
        <button type="button" onclick="document.getElementById('itemModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);" title="ปิดหน้าต่าง">&times;</button>
        <h2 style="margin-bottom: 1.5rem;">เพิ่มครุภัณฑ์ใหม่</h2>
        <form method="POST" enctype="multipart/form-data" id="addItemForm" style="display: block; width: 100%;">
            <input type="hidden" name="action" value="add">
            
            <div style="width: 100%; display: block;">
                <!-- Column 1 -->
                <div style="float: left; width: 48%;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ประเภทครุภัณฑ์</label>
                        <select name="category_id" id="category_select" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card);">
                            <option value="">เลือกประเภทครุภัณฑ์</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" data-code="<?php echo $cat['code']; ?>"><?php echo $cat['code'] . ' - ' . $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ชนิดครุภัณฑ์ (รหัส และ ชื่อ)</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="type_code" id="type_code" placeholder="รหัส" style="width: 80px; flex-shrink: 0; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card); text-align: center; font-weight: 600;">
                            <div class="ac-wrapper-inline" id="ac_subtype_wrapper" style="flex: 1; min-width: 0;">
                                <input type="text" name="subtype_name" id="subtype_input" class="ac-input" required placeholder="กรุณาเลือกประเภทก่อน" autocomplete="off">
                                <div class="ac-dropdown" id="ac_subtype_dropdown"></div>
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รายการครุภัณฑ์ (ชื่อ)</label>
                        <input type="text" name="name" id="item_name" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รหัสครุภัณฑ์</label>
                        <input type="text" name="item_code" id="item_code" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg-hover);" placeholder="ระบบจะสร้างให้อัตโนมัติ">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">บาร์โค้ด</label>
                        <input type="text" name="barcode" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                </div>

                <!-- Column 2 -->
                <div style="float: right; width: 48%;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ความหมาย</label>
                        <input type="text" name="meaning" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">จำนวน/หน่วย</label>
                        <div style="width: 100%; display: table;">
                            <input type="number" name="quantity" value="1" min="1" required style="display: table-cell; width: 80px; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; margin-right: 5px;">
                            <input type="text" name="unit" placeholder="หน่วย" style="display: table-cell; width: calc(100% - 90px); padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ราคาต่อหน่วย</label>
                        <input type="number" step="0.01" name="unit_price" value="0.00" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">วันที่ได้มา</label>
                        <input type="date" name="acquisition_date" id="acquisition_date" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">งบประมาณ (รหัส และ ชื่อ)</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="budget_code" id="budget_code" value="" placeholder="รหัส" style="width: 80px; flex-shrink: 0; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card); text-align: center; font-weight: 600;">
                            <div class="ac-wrapper-inline" id="ac_budget_wrapper" style="flex: 1; min-width: 0;">
                                <input type="text" name="acquisition_method" id="acquisition_select" class="ac-input" value="" required autocomplete="off" placeholder="ชื่อวิธีการได้มา">
                                <div class="ac-dropdown" id="ac_budget_dropdown"></div>
                            </div>
                            <button type="button" onclick="addNewBudgetOption('add')" class="ac-btn-style" style="height: 38px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; background: var(--primary); color: white; border-color: var(--primary); font-size: 1.1rem; border-radius: 0.5rem;" title="สร้างรายการงบประมาณใหม่">+</button>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">สถานะ</label>
                        <select name="condition" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card);">
                            <option value="Good">ดีมาก</option>
                            <option value="Fair">ดี</option>
                            <option value="Poor">พอใช้</option>
                            <option value="Broken">ชำรอย</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ผู้รับผิดชอบ</label>
                        <div class="ac-wrapper" id="ac_person_wrapper">
                            <input type="text" name="responsible_person" id="responsible_person" class="ac-input" autocomplete="off" placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                            <div class="ac-dropdown" id="ac_person_dropdown"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">สถานที่ตั้ง</label>
                        <div class="ac-wrapper" id="ac_location_wrapper">
                            <input type="text" name="location" id="location_input" class="ac-input" autocomplete="off" placeholder="พิมพ์สถานที่เพื่อค้นหา...">
                            <div class="ac-dropdown" id="ac_location_dropdown"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">หมายเหตุ</label>
                        <textarea name="remark" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; height: 60px;"></textarea>
                    </div>
                </div>
                
                <div style="clear: both;"></div>
            </div>
            
            <div style="margin-top: 1rem; width: 100%;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รูปภาพครุภัณฑ์</label>
                <div class="image-dropzone" id="dropzone_add">
                    <div class="dropzone-text">
                        <i data-lucide="upload-cloud" style="width: 32px; height: 32px; color: var(--primary);"></i>
                        <p>ลากรูปภาพมาวางที่นี่ หรือ <span>คลิกเพื่อเลือกไฟล์</span></p>
                        <span class="dropzone-sub">รองรับ JPG, PNG, GIF, WEBP (บีบอัดไฟล์อัตโนมัติก่อนบันทึก)</span>
                    </div>
                    <input type="file" name="image" id="file_add" accept="image/*" style="display: none;">
                    <div class="dropzone-preview" id="preview_add" style="display: none;">
                        <img id="preview_img_add" src="">
                        <button type="button" class="remove-btn" id="remove_btn_add" title="ลบรูปภาพ">&times;</button>
                    </div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 2rem;">
                <button type="button" class="btn" onclick="document.getElementById('itemModal').style.display='none'" style="background: var(--bg-hover); color: var(--text); margin-right: 1rem;">ยกเลิก</button>
                <button type="submit" class="btn btn-primary" id="saveButton">บันทึกข้อมูล</button>
            </div>
        </form>
            </td>
        </tr>
    </table>
</div>

<script>
// Check for valid item code before submit
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    const itemCode = document.getElementById('item_code').value;
    if (itemCode.includes('??') || itemCode.includes('XXXX')) {
        e.preventDefault();
        alert('กรุณารอการสร้างรหัสครุภัณฑ์หรือตรวจสอบการเลือกประเภท/ชนิดให้ถูกต้อง');
        return false;
    }
    document.getElementById('saveButton').disabled = true;
    document.getElementById('saveButton').textContent = 'กำลังบันทึก...';
});
</script>

<script>
const categorySelect = document.getElementById('category_select');
const subtypeInput = document.getElementById('subtype_input');
const typeCodeInput = document.getElementById('type_code');
const acquisitionSelect = document.getElementById('acquisition_select');
const budgetCodeInput = document.getElementById('budget_code');
const itemCodeInput = document.getElementById('item_code');
const itemNameInput = document.getElementById('item_name');

// Master data passed from PHP
const subtypes = <?php echo json_encode($subtypes); ?>;
const historySubtypes = <?php echo json_encode($historical_subtypes); ?>;
const responsiblePeopleData = <?php echo json_encode($responsible_people_list); ?>;
const locationsData = <?php echo json_encode($locations_list); ?>;
const acquisitionData = <?php echo json_encode($acquisition_history); ?>;

// Internal subtype options array (replaces datalist)
let _subtypeOptions = [];

if (acquisitionSelect) {
    acquisitionSelect.addEventListener('input', updateItemCode);
}
if (budgetCodeInput) {
    budgetCodeInput.addEventListener('input', updateItemCode);
}
const dateInput = document.getElementById('acquisition_date');
if (dateInput) {
    dateInput.addEventListener('change', updateItemCode);
}

function getEditFields() {
    return {
        type: document.getElementById('edit_type_code'),
        subtype: document.getElementById('edit_subtype_name'),
        budget: document.getElementById('edit_budget_code'),
        acquisition: document.getElementById('edit_acquisition_method'),
        itemCode: document.getElementById('edit_item_code')
    };
}

setTimeout(() => {
    const f = getEditFields();
    if (f.type) f.type.addEventListener('input', updateEditItemCode);
    if (f.subtype) f.subtype.addEventListener('input', updateEditItemCode);
    if (f.budget) f.budget.addEventListener('input', updateEditItemCode);
    if (f.acquisition) f.acquisition.addEventListener('input', updateEditItemCode);
    const editDateInput = document.getElementById('edit_acquisition_date');
    if (editDateInput) editDateInput.addEventListener('change', updateEditItemCode);
}, 500);

categorySelect.addEventListener('change', function() {
    const selectedCatId = this.value;
    const selectedCatOpt = this.options[this.selectedIndex];
    const selectedCatCode = selectedCatOpt ? selectedCatOpt.getAttribute('data-code') : null;
    
    _subtypeOptions = [];
    subtypeInput.value = '';
    
    if (!selectedCatId) {
        subtypeInput.placeholder = 'กรุณาเลือกประเภทก่อน';
        updateItemCode();
        return;
    }
    
    subtypeInput.placeholder = 'เลือกหรือพิมพ์ชนิดครุภัณฑ์';
    
    const filtered = subtypes.filter(s => s.category_code === selectedCatCode);
    const addedNames = new Set();
    
    filtered.forEach(s => {
        _subtypeOptions.push({ name: s.name, code: s.type_code });
        addedNames.add(s.name);
    });

    const selectedCatName = selectedCatOpt.text.split(' - ')[1] || selectedCatOpt.text;
    historySubtypes.forEach(h => {
        if (h.category === selectedCatName && !addedNames.has(h.item_type)) {
            let parts = h.item_code ? h.item_code.split('.') : [];
            let code = parts[3] || '99';
            _subtypeOptions.push({ name: h.item_type, code: code });
            addedNames.add(h.item_type);
        }
    });
    
    updateItemCode();
});

if (subtypeInput) {
    subtypeInput.addEventListener('input', function() {
        const val = this.value;
        if (val) {
            itemNameInput.value = val;
        }
        updateItemCode();
    });
}
if (typeCodeInput) {
    typeCodeInput.addEventListener('input', updateItemCode);
}

function updateItemCode() {
    const catOpt = categorySelect.options[categorySelect.selectedIndex];
    const catCode = catOpt ? catOpt.getAttribute('data-code') : null;
    const val = subtypeInput.value.trim();
    const dateVal = document.getElementById('acquisition_date').value;
    let year = new Date().getFullYear();
    if (dateVal) {
        year = new Date(dateVal).getFullYear();
    }
    const thaiYear = (year + 543).toString().slice(-2);
    // Get budget code from input only
    const budgetCode = (budgetCodeInput && budgetCodeInput.value.trim()) ? budgetCodeInput.value.trim().padStart(2, '0') : "";
    const displayBudget = budgetCode || "??";
    
    let typeCode = null;
    if (typeCodeInput && typeCodeInput.value.trim()) {
        typeCode = typeCodeInput.value.trim().padStart(2, '0');
    }
    
    if (catCode && val) {
        let matched = false;
        for (let opt of _subtypeOptions) {
            if (opt.name === val) {
                if (!typeCode) typeCode = opt.code;
                matched = true;
                break;
            }
        }
        
        if (!matched && !typeCode) {
            const filtered = subtypes.filter(s => s.category_code === catCode);
            let maxCode = 0;
            filtered.forEach(s => {
                let codeInt = parseInt(s.type_code, 10);
                if (codeInt > maxCode) maxCode = codeInt;
            });
            typeCode = String(maxCode + 1).padStart(2, '0');
        }
    }
    if (typeCode && typeCodeInput && !typeCodeInput.value) {
        typeCodeInput.value = typeCode;
    }
    
    if (catCode && typeCode) {
        fetch(`items.php?action=get_next_num&cat=${catCode}&type=${typeCode}&budget=${displayBudget}`)
            .then(res => res.text())
            .then(nextNum => {
                itemCodeInput.value = `SDU.${thaiYear}.${catCode}.${typeCode}.${displayBudget}.${nextNum}`;
            });
    } else {
        itemCodeInput.value = `SDU.${thaiYear}.${catCode || '??'}.${typeCode || '??'}.${displayBudget}.X`;
    }
}

function updateEditItemCode() {
    const f = getEditFields();
    if (!f.itemCode || !f.itemCode.value) return;
    
    const currentCode = f.itemCode.value;
    const parts = currentCode.split('.');
    if (parts.length < 5) return;
    
    let type = (f.type && f.type.value.trim()) ? f.type.value.trim().padStart(2, '0') : parts[3];
    let budget = (f.budget && f.budget.value.trim()) ? f.budget.value.trim().padStart(2, '0') : (parts[4] || '??');

    const dateInput = document.getElementById('edit_acquisition_date');
    let thaiYear = parts[1];
    if (dateInput && dateInput.value) {
        thaiYear = (new Date(dateInput.value).getFullYear() + 543).toString().slice(-2);
    }

    if (parts.length >= 6) {
        f.itemCode.value = `${parts[0]}.${thaiYear}.${parts[2]}.${type}.${budget}.${parts[5]}`;
    } else {
        f.itemCode.value = `${parts[0]}.${thaiYear}.${parts[2]}.${type}.${budget}.${parts[4]}`;
    }
}

function openAddFromMaster2(catCode, typeCode, name) { // Duplicate definition removed
    document.getElementById('itemModal').style.display = 'flex';
    
    // Set category
    const catSelect = document.getElementById('category_select');
    for(let opt of catSelect.options) {
        if(opt.getAttribute('data-code') === catCode) {
            catSelect.value = opt.value;
            break;
        }
    }
    
    // Trigger change to populate subtypes
    catSelect.dispatchEvent(new Event('change'));
    
    // Set subtype
    const subInput = document.getElementById('subtype_input');
    setTimeout(() => {
        if (subInput) {
            subInput.value = name;
            subInput.dispatchEvent(new Event('input'));
        }
    }, 100);
}

function renameSubtype(id, oldName) {
    const newName = prompt("เปลี่ยนชื่อชนิดครุภัณฑ์:", oldName);
    if (newName && newName !== oldName) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="rename_subtype">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="new_name" value="${newName}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<!-- Edit Modal -->
<div id="editItemModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto; padding: 2rem 1rem;">
    <table style="background: var(--card); border-radius: 1rem; width: 100%; max-width: 850px; margin: 0 auto; border-collapse: collapse; border-style: hidden; box-shadow: 0 0 0 0 transparent;">
        <tr>
            <td style="padding: 2rem; position: relative; vertical-align: top;">
        <button type="button" onclick="document.getElementById('editItemModal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);" title="ปิดหน้าต่าง">&times;</button>
        <h2 style="margin-bottom: 1.5rem;">แก้ไขข้อมูลครุภัณฑ์</h2>
        <form method="POST" enctype="multipart/form-data" id="editItemForm" style="display: block; width: 100%;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div style="width: 100%; display: block;">
                <!-- Column 1 -->
                <div style="float: left; width: 48%;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ชนิดครุภัณฑ์ (รหัส และ ชื่อ)</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="type_code" id="edit_type_code" placeholder="รหัส" style="width: 80px; flex-shrink: 0; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card); text-align: center; font-weight: 600;">
                            <div class="ac-wrapper-inline" id="ac_edit_subtype_wrapper" style="flex: 1; min-width: 0;">
                                <input type="text" name="subtype_name" id="edit_subtype_name" class="ac-input" required placeholder="ชื่อชนิด" autocomplete="off">
                                <div class="ac-dropdown" id="ac_edit_subtype_dropdown"></div>
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รายการครุภัณฑ์ (ชื่อเรียกเฉพาะ)</label>
                        <input type="text" name="name" id="edit_item_name" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รหัสครุภัณฑ์</label>
                        <input type="text" name="item_code" id="edit_item_code" readonly style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--bg-hover); color: var(--text-muted); cursor: not-allowed;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">บาร์โค้ด</label>
                        <input type="text" name="barcode" id="edit_barcode" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ความหมาย</label>
                        <input type="text" name="meaning" id="edit_meaning" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">จำนวน/หน่วย</label>
                        <div style="width: 100%; display: table;">
                            <input type="number" name="quantity" id="edit_quantity" min="1" required style="display: table-cell; width: 80px; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; margin-right: 5px;">
                            <input type="text" name="unit" id="edit_unit" placeholder="หน่วย" style="display: table-cell; width: calc(100% - 90px); padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        </div>
                    </div>
                </div>

                <!-- Column 2 -->
                <div style="float: right; width: 48%;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ราคาต่อหน่วย</label>
                        <input type="number" step="0.01" name="unit_price" id="edit_unit_price" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">วันที่ได้มา</label>
                        <input type="date" name="acquisition_date" id="edit_acquisition_date" required style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">งบประมาณ (รหัส และ ชื่อ)</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="budget_code" id="edit_budget_code" placeholder="รหัส" style="width: 80px; flex-shrink: 0; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card); text-align: center; font-weight: 600;">
                            <div class="ac-wrapper-inline" id="ac_edit_budget_wrapper" style="flex: 1; min-width: 0;">
                                <input type="text" name="acquisition_method" id="edit_acquisition_method" class="ac-input" autocomplete="off" placeholder="ชื่อวิธีการได้มา">
                                <div class="ac-dropdown" id="ac_edit_budget_dropdown"></div>
                            </div>
                            <button type="button" onclick="addNewBudgetOption('edit')" class="ac-btn-style" style="height: 38px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; background: #d97706; color: white; border-color: #d97706; font-size: 1.1rem; border-radius: 0.5rem;" title="สร้างรายการงบประมาณใหม่">+</button>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">สถานะ</label>
                        <select name="condition" id="edit_condition" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--card);">
                            <option value="Good">ดีมาก</option>
                            <option value="Fair">ดี</option>
                            <option value="Poor">พอใช้</option>
                            <option value="Broken">ชำรุด</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">ผู้รับผิดชอบ</label>
                        <div class="ac-wrapper" id="ac_edit_person_wrapper">
                            <input type="text" name="responsible_person" id="edit_responsible_person" class="ac-input" autocomplete="off" placeholder="พิมพ์ชื่อเพื่อค้นหา...">
                            <div class="ac-dropdown" id="ac_edit_person_dropdown"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">สถานที่ตั้ง</label>
                        <div class="ac-wrapper" id="ac_edit_location_wrapper">
                            <input type="text" name="location" id="edit_location" class="ac-input" autocomplete="off" placeholder="พิมพ์สถานที่เพื่อค้นหา...">
                            <div class="ac-dropdown" id="ac_edit_location_dropdown"></div>
                        </div>
                    </div>
                </div>
                
                <div style="clear: both;"></div>
            </div>
            
            <div style="width: 100%;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">หมายเหตุ</label>
                <textarea name="remark" id="edit_remark" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 0.5rem; height: 60px;"></textarea>
            </div>
            
            <div style="margin-top: 1rem; width: 100%;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">รูปภาพครุภัณฑ์ (ลากวางหรืออัปโหลดเพื่อเปลี่ยน)</label>
                <div class="image-dropzone" id="dropzone_edit">
                    <div class="dropzone-text">
                        <i data-lucide="upload-cloud" style="width: 32px; height: 32px; color: var(--primary);"></i>
                        <p>ลากรูปภาพมาวางที่นี่ หรือ <span>คลิกเพื่อเลือกไฟล์</span></p>
                        <span class="dropzone-sub">รองรับ JPG, PNG, GIF, WEBP (บีบอัดไฟล์อัตโนมัติก่อนบันทึก)</span>
                    </div>
                    <input type="file" name="image" id="file_edit" accept="image/*" style="display: none;">
                    <div class="dropzone-preview" id="preview_edit" style="display: none;">
                        <img id="preview_img_edit" src="">
                        <button type="button" class="remove-btn" id="remove_btn_edit" title="ลบรูปภาพ">&times;</button>
                    </div>
                </div>
                <div id="edit_current_image_container" style="margin-top: 0.5rem; display: none;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">รูปภาพปัจจุบัน:</span><br>
                    <img id="edit_current_image" src="" style="max-height: 80px; border-radius: 4px; border: 1px solid #e2e8f0; margin-top: 4px;">
                </div>
            </div>

            <div style="text-align: right; margin-top: 2rem;">
                <button type="button" class="btn" onclick="document.getElementById('editItemModal').style.display='none'" style="background: #e2e8f0; margin-right: 1rem; padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer;">ยกเลิก</button>
                <button type="submit" class="btn btn-primary" id="editSaveButton" style="padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer; background: #d97706; color: white;">บันทึกการแก้ไข</button>
            </div>
        </form>
            </td>
        </tr>
    </table>
</div>

<script>
function openEditModal(item) {
    document.getElementById('editItemModal').style.display = 'flex';
    
    // Reset dropzone edit state
    const inputEdit = document.getElementById('file_edit');
    const previewEdit = document.getElementById('preview_edit');
    const previewImgEdit = document.getElementById('preview_img_edit');
    if (inputEdit) inputEdit.value = '';
    if (previewImgEdit) previewImgEdit.src = '';
    if (previewEdit) previewEdit.style.display = 'none';
    
    document.getElementById('edit_id').value = item.inv_id;
    document.getElementById('edit_item_name').value = item.item_name || '';
    document.getElementById('edit_subtype_name').value = item.item_type || '';
    
    document.getElementById('edit_item_code').value = item.item_code || '';
    document.getElementById('edit_barcode').value = item.barcode || '';
    document.getElementById('edit_meaning').value = item.meaning || '';
    document.getElementById('edit_quantity').value = item.quantity || 1;
    document.getElementById('edit_unit').value = item.unit || '';
    document.getElementById('edit_unit_price').value = item.unit_price || 0;
    document.getElementById('edit_acquisition_date').value = item.acquisition_date || '';
    
    if (item.acquisition_method) document.getElementById('edit_acquisition_method').value = item.acquisition_method;
    
    // Extract subtype and budget codes from item_code
    const typeCodeInputEdit = document.getElementById('edit_type_code');
    const budgetCodeInputEdit = document.getElementById('edit_budget_code');
    
    if (item.item_code) {
        const parts = item.item_code.split('.');
        if (parts.length >= 6) {
            // New format: SDU.YY.CAT.TYPE.BUDGET.NUM
            if (typeCodeInputEdit) typeCodeInputEdit.value = parts[3];
            if (budgetCodeInputEdit) budgetCodeInputEdit.value = parts[4];
        } else if (parts.length === 5) {
            // Old format: SDU.YY.CAT.TYPE.NUM
            if (typeCodeInputEdit) typeCodeInputEdit.value = parts[3];
            
            // For old format, if it's 5 parts, the budget code is missing.
            // We set it to 06 by default, OR we could try to extract from acquisition_method
            let bCode = '06';
            if (item.acquisition_method) {
                if (item.acquisition_method.includes("บริจาค")) bCode = '07';
                else if (item.acquisition_method.includes("งบประมาณ") || item.acquisition_method.includes("งดประมาณ")) bCode = '06';
                else bCode = '99';
            }
            if (budgetCodeInputEdit) budgetCodeInputEdit.value = bCode;
        }
    }
    if (item.condition_status) document.getElementById('edit_condition').value = item.condition_status;
    
    document.getElementById('edit_responsible_person').value = item.responsible_person || '';
    document.getElementById('edit_location').value = item.location || '';
    document.getElementById('edit_remark').value = item.remark || '';
    
    const imgContainer = document.getElementById('edit_current_image_container');
    const imgEl = document.getElementById('edit_current_image');
    if (item.image_path) {
        imgEl.src = item.image_path;
        imgContainer.style.display = 'block';
    } else {
        imgContainer.style.display = 'none';
        imgEl.src = '';
    }

    // Update item code to new format in UI
    updateEditItemCode();
}
</script>

<script>
// ===== Chrome-like Autocomplete Engine =====
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
            this.items = this.items.filter(i => (i.label || i.value) !== label);
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

// ===== Budget data (static + historical) =====
// If DB has data, use it. If not, use defaults.
let budgetOptions = [];
if (acquisitionData.length > 0) {
    acquisitionData.forEach(m => {
        let code = '';
        if (m.includes("งบประมาณ")) code = '06';
        else if (m.includes("บริจาค")) code = '07';
        else if (m !== "อื่นๆ") code = '99';
        else code = '99';
        budgetOptions.push({ label: m, code: code });
    });
} else {
    budgetOptions = [
        { label: 'เงินงบประมาณ', code: '06' },
        { label: 'เงินบริจาค', code: '07' },
        { label: 'อื่นๆ', code: '99' }
    ];
}

const personOptions = responsiblePeopleData.map(p => ({ label: p, value: p }));
const locationOptions = locationsData.map(l => ({ label: l, value: l }));

// ===== Initialize Add Form Autocompletes =====
// 1) Subtype
const acSubtype = new ChromeAC(
    document.getElementById('subtype_input'),
    document.getElementById('ac_subtype_dropdown'),
    {
        items: [],
        showCode: true,
        icon: 'box',
        onSelect: function(item) {
            if (item.code && typeCodeInput) {
                typeCodeInput.value = item.code;
            }
            if (itemNameInput) itemNameInput.value = item.label;
            updateItemCode();
        },
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_subtype');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// Update subtype AC items when category changes
const origCatChange = categorySelect.onchange;
categorySelect.addEventListener('change', function() {
    setTimeout(() => {
        acSubtype.setItems(_subtypeOptions.map(s => ({ label: s.name, value: s.name, code: s.code })));
    }, 50);
});

function addNewBudgetOption(mode) {
    const newName = prompt("กรุณาระบุ 'ชื่อแหล่งงบประมาณ' ใหม่:");
    if (!newName) return;
    
    let newCode = prompt("กรุณาระบุ 'รหัสงบประมาณ (ตัวเลข 2 หลัก)' สำหรับ '" + newName + "':");
    if (!newCode) return;
    
    newCode = newCode.trim().padStart(2, '0').slice(-2);
    if (isNaN(newCode)) {
        alert("รหัสงบประมาณต้องเป็นตัวเลข 2 หลักเท่านั้น");
        return;
    }
    
    const exists = budgetOptions.some(b => b.label === newName);
    if (!exists) {
        budgetOptions.push({ label: newName, code: newCode });
        if (window.acBudget) window.acBudget.setItems(budgetOptions);
        if (window.acEditBudget) window.acEditBudget.setItems(budgetOptions);
    }
    
    if (mode === 'add') {
        document.getElementById('acquisition_select').value = newName;
        document.getElementById('budget_code').value = newCode;
        updateItemCode();
    } else {
        document.getElementById('edit_acquisition_method').value = newName;
        document.getElementById('edit_budget_code').value = newCode;
        updateEditItemCode();
    }
}

// 2) Budget
const acBudget = new ChromeAC(
    document.getElementById('acquisition_select'),
    document.getElementById('ac_budget_dropdown'),
    {
        items: budgetOptions,
        showCode: false,
        icon: 'wallet',
        onSelect: function(item) {
            if (item.code && budgetCodeInput) {
                budgetCodeInput.value = item.code;
            }
            updateItemCode();
        },
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_budget');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel, newCode) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_budget');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// 3) Responsible Person
const acPerson = new ChromeAC(
    document.getElementById('responsible_person'),
    document.getElementById('ac_person_dropdown'),
    { 
        items: personOptions, 
        icon: 'user',
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_person');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_person');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// 4) Location
const acLocation = new ChromeAC(
    document.getElementById('location_input'),
    document.getElementById('ac_location_dropdown'),
    { 
        items: locationOptions, 
        icon: 'map',
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_location');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_location');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// ===== Initialize Edit Form Autocompletes =====
// Edit Subtype
const acEditSubtype = new ChromeAC(
    document.getElementById('edit_subtype_name'),
    document.getElementById('ac_edit_subtype_dropdown'),
    {
        items: subtypes.map(s => ({ label: s.name, value: s.name, code: s.type_code })),
        showCode: true,
        icon: 'box',
        onSelect: function(item) {
            const tc = document.getElementById('edit_type_code');
            if (item.code && tc) tc.value = item.code;
            updateEditItemCode();
        },
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_subtype');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// Edit Budget
const acEditBudget = new ChromeAC(
    document.getElementById('edit_acquisition_method'),
    document.getElementById('ac_edit_budget_dropdown'),
    {
        items: budgetOptions,
        showCode: false,
        icon: 'wallet',
        onSelect: function(item) {
            const editBudgetCodeInput = document.getElementById('edit_budget_code');
            if (item.code && editBudgetCodeInput) {
                editBudgetCodeInput.value = item.code;
            }
            updateEditItemCode();
        },
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_budget');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel, newCode) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_budget');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// Edit Person
const acEditPerson = new ChromeAC(
    document.getElementById('edit_responsible_person'),
    document.getElementById('ac_edit_person_dropdown'),
    { 
        items: personOptions, 
        icon: 'user',
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_person');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_person');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// Edit Location
const acEditLocation = new ChromeAC(
    document.getElementById('edit_location'),
    document.getElementById('ac_edit_location_dropdown'),
    { 
        items: locationOptions, 
        icon: 'map',
        onDelete: function(item) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_location');
            formData.append('name', item.label);
            fetch('items.php', { method: 'POST', body: formData });
        },
        onEdit: function(item, newLabel) {
            const formData = new FormData();
            formData.append('ajax_action', 'rename_location');
            formData.append('old_name', item.label);
            formData.append('new_name', newLabel);
            fetch('items.php', { method: 'POST', body: formData });
        }
    }
);

// === ค้นหา / กรองตาราง ===
function filterTable() {
    var input = document.getElementById('searchInput').value.toLowerCase().trim();
    var table = document.getElementById('itemsTable');
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
</script>

<!-- Scan Result Modal -->
<div id="scanResultModal">
    <div class="scan-modal-card">
        <div class="scan-modal-header">
            <button class="scan-modal-close" onclick="closeScanModal()">&times;</button>
            <h2>📱 ผลการสแกน Barcode</h2>
            <div class="scan-badge" id="scanBarcodeValue">—</div>
        </div>
        <div class="scan-modal-body">
            <img id="scanItemImage" class="scan-modal-image" src="" style="display:none;">
            <div class="scan-info-grid" id="scanInfoGrid">
                <!-- populated by JS -->
            </div>
        </div>
        <div class="scan-modal-footer">
            <button class="scan-btn secondary" onclick="closeScanModal()">ปิด</button>
            <button class="scan-btn primary" id="scanPrintBtn" onclick="scanPrintBarcode()">🖨️ พิมพ์บาร์โค้ด</button>
            <button class="scan-btn warning" id="scanEditBtn" onclick="scanEditItem()">✏️ แก้ไขข้อมูล</button>
        </div>
    </div>
</div>

<script>
// ==========================================
// Barcode Scanner Listener
// ==========================================
// Global scanner is now handled in includes/header.php
let _scanCurrentItem = null;
let _scanCurrentRow = null;

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

        // Try to get full item JSON from data attribute
        const jsonStr = row.getAttribute('data-item-json');
        if (jsonStr) {
            try {
                const item = JSON.parse(jsonStr);
                _scanCurrentItem = item;
                _scanCurrentRow = row;
                openScanResultModal(item, cleanBarcode);
                return;
            } catch(e) { /* fallback below */ }
        }

        // Fallback: open edit modal directly
        const editBtn = row.querySelector('.table-action-btn.warning');
        if (editBtn) editBtn.click();
    } else {
        // Not found — search in table
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = cleanBarcode;
            if(typeof filterTable === 'function') filterTable();
        }
        // Show a brief "not found" notification
        showScanNotFound(cleanBarcode);
    }
}

function openScanResultModal(item, scannedCode) {
    const modal = document.getElementById('scanResultModal');
    const grid = document.getElementById('scanInfoGrid');
    const img = document.getElementById('scanItemImage');
    const badge = document.getElementById('scanBarcodeValue');

    // Badge
    badge.textContent = '🔍 ' + scannedCode;

    // Image
    if (item.image_path) {
        img.src = item.image_path;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }

    // Format item code with dots
    let displayCode = item.item_code || '';
    if (displayCode.match(/^SDU\d/)) {
        displayCode = displayCode.replace(/^SDU(\d)/, 'SDU.$1');
    }

    // Condition badge
    const conditionMap = {
        'Good': { label: '✅ ดีมาก', cls: 'good' },
        'Fair': { label: '👍 ดี', cls: 'fair' },
        'Poor': { label: '⚠️ พอใช้', cls: 'poor' },
        'Broken': { label: '❌ ชำรุด', cls: 'broken' }
    };
    const cond = conditionMap[item.condition_status] || { label: item.condition_status || '—', cls: '' };
    const condHTML = `<span class="scan-condition-badge ${cond.cls}">${cond.label}</span>`;

    // Format price
    const price = item.unit_price ? Number(item.unit_price).toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท' : '—';

    // Format date (Thai)
    let dateStr = '—';
    if (item.acquisition_date) {
        const d = new Date(item.acquisition_date);
        if (!isNaN(d)) {
            const thYear = d.getFullYear() + 543;
            const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            dateStr = d.getDate() + ' ' + months[d.getMonth()] + ' ' + thYear;
        }
    }

    grid.innerHTML = `
        <div class="scan-info-item full-width">
            <div class="scan-info-label">ชื่อรายการ / ชนิดครุภัณฑ์</div>
            <div class="scan-info-value" style="font-size: 1.1rem;">${item.item_name || item.subtype_name || item.item_type || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">รหัสครุภัณฑ์</div>
            <div class="scan-info-value code">${displayCode || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">บาร์โค้ด</div>
            <div class="scan-info-value code">${item.barcode || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">หมวดหมู่</div>
            <div class="scan-info-value">${item.category_name || item.category || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ชนิด</div>
            <div class="scan-info-value">${item.subtype_name || item.item_type || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">จำนวน</div>
            <div class="scan-info-value">${item.quantity || 0} ${item.unit || ''}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ราคาต่อหน่วย</div>
            <div class="scan-info-value">${price}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">สถานที่ตั้ง</div>
            <div class="scan-info-value">${item.location || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">ผู้รับผิดชอบ</div>
            <div class="scan-info-value">${item.responsible_person || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">วันที่ได้มา</div>
            <div class="scan-info-value">${dateStr}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">งบประมาณ</div>
            <div class="scan-info-value">${item.acquisition_method || '—'}</div>
        </div>
        <div class="scan-info-item">
            <div class="scan-info-label">สถานะ</div>
            <div class="scan-info-value">${condHTML}</div>
        </div>
        <div class="scan-info-item full-width">
            <div class="scan-info-label">ความหมาย</div>
            <div class="scan-info-value">${item.meaning || '—'}</div>
        </div>
        ${item.remark ? `<div class="scan-info-item full-width">
            <div class="scan-info-label">หมายเหตุ</div>
            <div class="scan-info-value">${item.remark}</div>
        </div>` : ''}
    `;

    // Show/hide print button
    const printBtn = document.getElementById('scanPrintBtn');
    printBtn.style.display = item.inv_id ? '' : 'none';
    printBtn.setAttribute('data-id', item.inv_id || '');

    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
}

function closeScanModal() {
    document.getElementById('scanResultModal').style.display = 'none';
    _scanCurrentItem = null;
    _scanCurrentRow = null;
}

function scanEditItem() {
    closeScanModal();
    if (_scanCurrentRow) {
        const editBtn = _scanCurrentRow.querySelector('.table-action-btn.warning');
        if (editBtn) editBtn.click();
    } else if (_scanCurrentItem) {
        openEditModal(_scanCurrentItem);
    }
}

function scanPrintBarcode() {
    const id = document.getElementById('scanPrintBtn').getAttribute('data-id');
    if (id) {
        window.location.href = 'print_barcode_pt2730.php?id=' + id;
    }
}

function showScanNotFound(code) {
    // Create a temporary toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 2rem; left: 50%; transform: translateX(-50%);
        background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
        padding: 1rem 2rem; border-radius: 12px; font-weight: 600; z-index: 10002;
        box-shadow: 0 10px 40px rgba(239,68,68,0.4); animation: scanSlideUp 0.3s ease;
        font-family: 'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif;
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

// Check if URL has ?scan=BARCODE on page load
window.addEventListener('DOMContentLoaded', () => {
    // Initialize Dropzones
    if (window.initImageDropzone) {
        window.initImageDropzone('dropzone_add', 'file_add', 'preview_add', 'preview_img_add', 'remove_btn_add');
        window.initImageDropzone('dropzone_edit', 'file_edit', 'preview_edit', 'preview_img_edit', 'remove_btn_edit');
    }

    // Reset Add Dropzone state when cancel button is clicked
    const cancelAddBtn = document.querySelector('#itemModal button[onclick*="itemModal"]');
    if (cancelAddBtn) {
        cancelAddBtn.addEventListener('click', () => {
            const inputAdd = document.getElementById('file_add');
            const previewAdd = document.getElementById('preview_add');
            const previewImgAdd = document.getElementById('preview_img_add');
            if (inputAdd) inputAdd.value = '';
            if (previewImgAdd) previewImgAdd.src = '';
            if (previewAdd) previewAdd.style.display = 'none';
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const scanCode = urlParams.get('scan');
    if (scanCode) {
        setTimeout(() => {
            handleBarcodeScan(scanCode);
        }, 500);
    }
});
</script>

<script src="assets/libs/JsBarcode.all.min.js"></script>

<style>
/* ===== Sub-items Table Styles ===== */
.subitem-row td {
    background: var(--bg-hover) !important;
}
.subitem-container {
    margin: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--card);
    overflow-x: auto;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
}
.subitem-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text);
    background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(59,130,246,0.03));
    border-bottom: 1px solid var(--border);
}
.subitem-header svg {
    color: var(--primary);
    flex-shrink: 0;
}
.subitem-badge {
    display: inline-block;
    background: var(--primary);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
    margin-left: 4px;
}
.subitem-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.subitem-table thead tr {
    background: rgba(0,0,0,0.03);
}
.subitem-table th {
    padding: 8px 14px;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
    text-align: left;
}
.subitem-table td {
    padding: 6px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    color: var(--text);
    vertical-align: middle;
}
.subitem-table tbody tr:last-child td {
    border-bottom: none;
}
.subitem-row-even td {
    background: rgba(0,0,0,0.015) !important;
}
.subitem-table tbody tr:hover td {
    background: rgba(59,130,246,0.05) !important;
}
.subitem-code {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.85rem;
    font-weight: 600;
    color: #3b82f6;
    background: rgba(59,130,246,0.08);
    padding: 3px 8px;
    border-radius: 5px;
    border: 1px solid rgba(59,130,246,0.15);
}
.subitem-barcode {
    display: block;
    margin: 0 auto;
}
/* Dark mode */
[data-theme="dark"] .subitem-container {
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
}
[data-theme="dark"] .subitem-header {
    background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05));
}
[data-theme="dark"] .subitem-row-even td {
    background: rgba(255,255,255,0.02) !important;
}
[data-theme="dark"] .subitem-table tbody tr:hover td {
    background: rgba(59,130,246,0.1) !important;
}
[data-theme="dark"] .subitem-code {
    background: rgba(59,130,246,0.15);
    border-color: rgba(59,130,246,0.3);
    color: #60a5fa;
}
[data-theme="dark"] .subitem-table thead tr {
    background: rgba(255,255,255,0.03);
}
</style>

<?php include 'includes/footer.php'; ?>
