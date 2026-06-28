<?php
// กำหนด path สำหรับไฟล์ SQLite จะถูกสร้างไว้ที่เดียวกับโฟลเดอร์หลัก (เช่น StockApp/www/database.sqlite)
$db_file = __DIR__ . '/../database.sqlite';

try {
    // เชื่อมต่อ SQLite (ถ้าไม่มีไฟล์ มันจะสร้างให้ใหม่แบบอัตโนมัติ)
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // เปิดใช้งานระบบเชื่อมโยงข้อมูล Foreign Keys สำหรับ SQLite
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // สร้างตารางข้อมูลต่างๆ (ปรับแก้คำสั่งให้รองรับ SQLite)
    $pdo->exec("CREATE TABLE IF NOT EXISTS item_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS item_subtypes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_code VARCHAR(10),
        type_code VARCHAR(10),
        name VARCHAR(255) NOT NULL,
        UNIQUE (category_code, type_code)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_code VARCHAR(50) UNIQUE NOT NULL,
        barcode VARCHAR(50),
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        item_type VARCHAR(100),
        meaning TEXT,
        remark TEXT,
        quantity INTEGER DEFAULT 0,
        unit VARCHAR(50),
        unit_price DECIMAL(10, 2) DEFAULT 0.00,
        acquisition_date DATE,
        acquisition_method VARCHAR(100),
        condition_status TEXT DEFAULT 'Good',
        location VARCHAR(255),
        responsible_person VARCHAR(255),
        image_path VARCHAR(255),
        parent_id INTEGER DEFAULT NULL,
        seq_number INTEGER DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    
    // Migrate existing tables: add columns if missing
    try { $pdo->exec("ALTER TABLE items ADD COLUMN parent_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE items ADD COLUMN seq_number INTEGER DEFAULT NULL"); } catch (Exception $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS requisitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER,
        order_no VARCHAR(50),
        requester_name VARCHAR(255) NOT NULL,
        position VARCHAR(255),
        department VARCHAR(255),
        purpose VARCHAR(255),
        quantity INTEGER NOT NULL,
        unit_price DECIMAL(10, 2) DEFAULT 0.00,
        discount DECIMAL(10, 2) DEFAULT 0.00,
        amount DECIMAL(10, 2) DEFAULT 0.00,
        image_path VARCHAR(255),
        status TEXT DEFAULT 'Pending',
        requisition_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id INTEGER,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");


    // ใส่ข้อมูลเริ่มต้น - หมวดหมู่ครุภัณฑ์
    $count = $pdo->query("SELECT COUNT(*) FROM item_categories")->fetchColumn();
    if ($count == 0) {
        $categories = [
            '01' => 'ครุภัณฑ์สำนักงาน',
            '02' => 'ครุภัณฑ์ยานพาหนะและขนส่ง',
            '03' => 'ครุภัณฑ์การเกษตร',
            '04' => 'ครุภัณฑ์ก่อสร้าง',
            '05' => 'ครุภัณฑ์ไฟฟ้าและวิทยุ',
            '06' => 'ครุภัณฑ์โฆษณาและเผยแพร่',
            '07' => 'ครุภัณฑ์วิทยาศาสตร์และการแพทย์',
            '08' => 'ครุภัณฑ์งานบ้านงานครัว',
            '09' => 'ครุภัณฑ์โรงงาน',
            '10' => 'ครุภัณฑ์กีฬา/กายบริหาร',
            '11' => 'ครุภัณฑ์ดนตรี/นาฏศิลป์',
            '12' => 'ครุภัณฑ์ดนตรีสากล/ดนตรีไทย',
            '13' => 'ครุภัณฑ์การศึกษา/ครุภัณฑ์เพื่อความพิการ',
            '14' => 'ครุภัณฑ์คอมพิวเตอร์'
        ];
        foreach ($categories as $code => $name) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO item_categories (code, name) VALUES (?, ?)");
            $stmt->execute([$code, $name]);
        }
    }

    // ใส่ข้อมูลเริ่มต้น - ชนิดครุภัณฑ์ (เพิ่มข้อมูลให้ครอบคลุมทุกหมวดหมู่)
    $subtypes_to_seed = [
        // 01 ครุภัณฑ์สำนักงาน
        ['01', '01', 'เก้าอี้'], ['01', '02', 'โต๊ะ'], ['01', '03', 'ตู้เหล็ก'], ['01', '04', 'ตู้ไม้'], ['01', '05', 'เครื่องปรับอากาศ'], ['01', '06', 'เครื่องถ่ายเอกสาร'],
        // 02 ครุภัณฑ์ยานพาหนะ
        ['02', '01', 'รถยนต์'], ['02', '02', 'รถจักรยานยนต์'], ['02', '03', 'รถบรรทุก'],
        // 03 ครุภัณฑ์การเกษตร
        ['03', '01', 'เครื่องสูบน้ำ'], ['03', '02', 'รถแทรกเตอร์'], ['03', '03', 'เครื่องพ่นยา'],
        // 04 ครุภัณฑ์ก่อสร้าง
        ['04', '01', 'เครื่องผสมคอนกรีต'], ['04', '02', 'สว่านไฟฟ้า'], ['04', '03', 'รถบดถนน'],
        // 05 ครุภัณฑ์ไฟฟ้าและวิทยุ
        ['05', '01', 'โทรทัศน์'], ['05', '02', 'เครื่องเสียง'], ['05', '03', 'พัดลม'], ['05', '04', 'วิทยุสื่อสาร'],
        // 06 ครุภัณฑ์โฆษณาและเผยแพร่
        ['06', '01', 'กล้องถ่ายรูป'], ['06', '02', 'เครื่องขยายเสียง'], ['06', '03', 'จอโปรเจคเตอร์'],
        // 07 ครุภัณฑ์วิทยาศาสตร์และการแพทย์
        ['07', '01', 'กล้องจุลทรรศน์'], ['07', '02', 'เครื่องชั่งดิจิทัล'], ['07', '03', 'ตู้เก็บสารเคมี'],
        // 08 ครุภัณฑ์งานบ้านงานครัว
        ['08', '01', 'ตู้เย็น'], ['08', '02', 'เตาไมโครเวฟ'], ['08', '03', 'เตาแก๊ส'], ['08', '04', 'หม้อหุงข้าว'],
        // 09 ครุภัณฑ์โรงงาน
        ['09', '01', 'เครื่องกลึง'], ['09', '02', 'เครื่องเชื่อมไฟฟ้า'], ['09', '03', 'ปั๊มลม'],
        // 10 ครุภัณฑ์กีฬา/กายบริหาร
        ['10', '01', 'ลู่วิ่งไฟฟ้า'], ['10', '02', 'จักรยานออกกำลังกาย'], ['10', '03', 'โต๊ะปิงปอง'],
        // 11 ครุภัณฑ์ดนตรี/นาฏศิลป์
        ['11', '01', 'ระนาด'], ['11', '02', 'ฆ้องวง'], ['11', '03', 'ชุดไทย/ชุดนาฏศิลป์'],
        // 12 ครุภัณฑ์ดนตรีสากล/ดนตรีไทย
        ['12', '01', 'กลองเซต'], ['12', '02', 'กีตาร์'], ['12', '03', 'คีย์บอร์ด'], ['12', '04', 'เปียโน'], ['12', '05', 'ไวโอลิน'],
        // 13 ครุภัณฑ์การศึกษา/ครุภัณฑ์เพื่อความพิการ
        ['13', '01', 'กระดานไวท์บอร์ด'], ['13', '02', 'เครื่องฉายภาพ 3 มิติ'], ['13', '03', 'หุ่นจำลองกายวิภาค'],
        // 14 ครุภัณฑ์คอมพิวเตอร์
        ['14', '01', 'เครื่องไมโครคอมพิวเตอร์'], ['14', '02', 'จอภาพ'], ['14', '03', 'เครื่องพิมพ์'], ['14', '04', 'เครื่องสำรองไฟ'], ['14', '05', 'เครื่องคอมพิวเตอร์โน้ตบุ๊ก']
    ];

    foreach ($subtypes_to_seed as $st) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO item_subtypes (category_code, type_code, name) VALUES (?, ?, ?)");
        $stmt->execute($st);
    }

    // ฟังก์ชันสำหรับบันทึกประวัติการเปลี่ยนแปลงข้อมูล (Audit Log)
    function log_audit($action_type, $table_name, $record_id, $details) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (action_type, table_name, record_id, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$action_type, $table_name, $record_id, $details]);
        } catch (Exception $e) {
            // ทำงานเงียบเพื่อป้องกันระบบล่ม
        }
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>