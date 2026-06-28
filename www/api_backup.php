<?php
/**
 * api_backup.php — สำรองข้อมูลฐานข้อมูล SQLite ของระบบพัสดุ
 */

// ตรวจสอบที่อยู่ของไฟล์ฐานข้อมูลจาก db.php
require_once 'includes/db.php';

if (!file_exists($db_file)) {
    http_response_code(404);
    echo "ไม่พบไฟล์ฐานข้อมูลหลัก";
    exit;
}

// กำหนด headers สำหรับดาวน์โหลดไฟล์
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="StockApp_Backup_' . date('Ymd_His') . '.sqlite"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($db_file));

// เคลียร์ output buffer เพื่อป้องกันไฟล์เสียหาย
ob_clean();
flush();

// อ่านและส่งไฟล์ฐานข้อมูล
readfile($db_file);
exit;
?>
