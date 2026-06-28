<?php
/**
 * api_ptouch_export.php — Export สำหรับ P-touch Editor (Brother PT-2730)
 * 
 * ออกแบบสำหรับ PHP Desktop (EXE) — ไม่ใช้ browser download
 * ใช้ system command เปิดไฟล์/โฟลเดอร์โดยตรง
 * 
 * Actions:
 *   csv         — สร้าง CSV + เปิดโฟลเดอร์ใน Explorer (highlight ไฟล์)
 *   csv_open    — สร้าง CSV + เปิดไฟล์ใน Excel/Default app
 *   open_folder — เปิดโฟลเดอร์ temp ใน Explorer
 */

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'csv';
$barcodes = $input['barcodes'] ?? [];

if (empty($barcodes) && !in_array($action, ['open_folder'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล Barcode']);
    exit;
}

$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// ลบไฟล์เก่า (เก่ากว่า 2 ชั่วโมง)
foreach (glob($tempDir . '/ptouch_*') as $oldFile) {
    if (filemtime($oldFile) < time() - 7200) {
        @unlink($oldFile);
    }
}

/**
 * ฟังก์ชันสร้างไฟล์ CSV
 */
function createCSV($tempDir, $barcodes) {
    $timestamp = date('Ymd_His');
    $filename = "ptouch_data_{$timestamp}.csv";
    $filepath = $tempDir . '/' . $filename;
    
    $fp = fopen($filepath, 'w');
    // BOM for UTF-8 encoding in Excel/P-touch Editor
    fwrite($fp, "\xEF\xBB\xBF");
    // Header row
    fputcsv($fp, ['No', 'BarcodeData', 'Label']);
    
    foreach ($barcodes as $idx => $item) {
        $value = $item['value'] ?? '';
        $label = $item['label'] ?? $value;
        fputcsv($fp, [$idx + 1, $value, $label]);
    }
    fclose($fp);
    
    return ['filename' => $filename, 'filepath' => $filepath];
}

if ($action === 'csv') {
    // === สร้าง CSV + เปิดโฟลเดอร์ใน Explorer (highlight ไฟล์) ===
    $result = createCSV($tempDir, $barcodes);
    $count = count($barcodes);
    
    // เปิด Explorer พร้อม highlight ไฟล์ที่สร้าง
    $fullPath = str_replace('/', '\\', realpath($result['filepath']));
    $command = 'explorer /select,"' . $fullPath . '"';
    pclose(popen($command, 'r'));
    
    echo json_encode([
        'success' => true,
        'action' => 'csv',
        'message' => "สร้างไฟล์ CSV สำเร็จ ({$count} รายการ) — เปิดโฟลเดอร์แล้ว",
        'file' => $result['filename'],
        'fullPath' => $fullPath,
        'count' => $count
    ]);
    
} elseif ($action === 'csv_open') {
    // === สร้าง CSV + เปิดไฟล์ใน Excel/Default App โดยตรง ===
    $result = createCSV($tempDir, $barcodes);
    $count = count($barcodes);
    
    // เปิดไฟล์ CSV ด้วย Default App (Excel, Notepad, etc.)
    $fullPath = str_replace('/', '\\', realpath($result['filepath']));
    $command = 'start "" "' . $fullPath . '"';
    pclose(popen($command, 'r'));
    
    echo json_encode([
        'success' => true,
        'action' => 'csv_open',
        'message' => "สร้างไฟล์ CSV สำเร็จ ({$count} รายการ) — เปิดไฟล์แล้ว",
        'file' => $result['filename'],
        'fullPath' => $fullPath,
        'count' => $count
    ]);
    
} elseif ($action === 'open_folder') {
    // === เปิดโฟลเดอร์ temp ใน Explorer ===
    $fullPath = str_replace('/', '\\', realpath($tempDir));
    $command = 'explorer "' . $fullPath . '"';
    pclose(popen($command, 'r'));
    
    echo json_encode([
        'success' => true,
        'message' => 'เปิดโฟลเดอร์แล้ว',
        'folder' => $fullPath
    ]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Action ไม่ถูกต้อง: ' . $action]);
}
