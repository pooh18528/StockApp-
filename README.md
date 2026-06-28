# StockApp ระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต

ระบบบริหารจัดการพัสดุ (Stock Management System) สำหรับมหาวิทยาลัยสวนดุสิต พัฒนาด้วย PHP + SQLite + phpdesktop (CEF)

## คุณสมบัติ

- จัดการรายการครุภัณฑ์ (เพิ่ม, ลบ, แก้ไข)
- รองรับรายการย่อย (Parent/Child items) — แต่ละตัวมี record ของตัวเอง แก้ไขชื่อทีละตัวได้
- ระบบเบิกพัสดุ
- พิมพ์บาร์โค้ด (CODE128) รองรับเครื่องพิมพ์ PT-2730 (Brother P-touch)
- พิมพ์บาร์โค้ดทีละรายการ, หลายรายการพร้อมกัน, หรือพิมพ์เป็นช่วง
- พิมพ์รายงานสรุป PDF
- รองรับ Dark Mode
- สแกนบาร์โค้ดจากกล้องเว็บแคม (html5-qrcode)
- ระบบบันทึกประวัติการแก้ไข (Audit Log)

## การติดตั้ง

### สำหรับผู้ใช้ทั่วไป (Download .exe หรือ ZIP)
1. ดาวน์โหลดไฟล์ `StockApp-Package.zip` จาก [GitHub Releases](https://github.com/pooh18528/StockApp-/releases)
2. แตก ZIP แล้วรัน `StockApp.exe`

### สำหรับนักพัฒนา (จาก Source)
1. Clone repository:
```bash
git clone https://github.com/pooh18528/StockApp-.git
```
2. วาง phpdesktop runtime (PHP + CEF DLLs + StockApp.exe + php/ + locales/) ลงในโฟลเดอร์
3. รัน `StockApp.exe`

## การใช้งานเครื่องพิมพ์สติ๊กเกอร์บาร์โค้ด Brother PT-2730

### เตรียมเครื่องพิมพ์
1. ติดตั้งโปรแกรม Brother P-touch Editor (สำหรับติดตั้ง Driver)
   - ดาวน์โหลดจาก https://support.brother.com
   - เลือกรุ่น PT-2730 แล้วติดตั้ง Driver
2. เสียบเครื่องพิมพ์ PT-2730 ผ่าน USB
3. ตั้งเป็นเครื่องพิมพ์เริ่มต้น (Default Printer) หรือระบบจะเลือกให้อัตโนมัติ
4. ใช้เทป (Tape) ขนาด 12mm, 18mm, หรือ 24mm

### พิมพ์บาร์โค้ดจากโปรแกรม
1. ไปที่ **รายการครุภัณฑ์**
2. เลือกรายการที่ต้องการพิมพ์
3. คลิกปุ่ม **พิมพ์บาร์โค้ด** ในแถวรายการ หรือเลือกหลายรายการแล้วกด **พิมพ์หลายรายการ**
4. ในหน้าตัวออกแบบป้าย:
   - ปรับตำแหน่ง รหัส ชื่อ บาร์โค้ด ได้อิสระ (ลากวาง)
   - เลือกขนาดเทป (Tape Width)
   - เลือกข้อมูลที่ต้องการแสดง (รหัส, ชื่อ, บาร์โค้ด)
   - กด **พิมพ์** (ใช้ Kiosk Printing Mode — พิมพ์ตรงโดยไม่ต้องกด OK)

### การปรับแต่งค่าบาร์โค้ด
- **การแสดงหมายเลขใต้บาร์โค้ด** (`displayValue: true` ใน JsBarcode): แสดงตัวเลข readable ใต้แท่งบาร์โค้ด
- **includetext=true** (bwipjs API): แสดงข้อความในรูปบาร์โค้ดที่เรียกจาก API
- **textxalign=center** จัดข้อความกึ่งกลาง
- รหัสที่ใช้ในบาร์โค้ด: เป็นรหัสไม่มีจุด (SDU690301061) เพื่อให้สแกนเนอร์อ่านได้
- ข้อความที่แสดง: เป็นรหัสมีจุด (SDU.69.03.01.06.1) เพื่อให้อ่านง่าย

## ระบบรายการย่อย (Parent / Child Items)

ตั้งแต่เวอร์ชันล่าสุด รายการครุภัณฑ์ที่มีจำนวนมากกว่า 1 จะสร้าง record แยกแต่ละตัวในฐานข้อมูล:

- **Parent item**: รายการหลัก เก็บข้อมูลทั่วไป (หมวดหมู่, ราคา, วันที่, ฯลฯ)
- **Child items**: รายการย่อย แต่ละตัวมี ID และชื่อของตัวเอง
- สามารถ **แก้ไขชื่อทีละตัว** ได้ในตารางรายการย่อย
- สามารถ **พิมพ์บาร์โค้ด** ของแต่ละตัวได้

### การเพิ่มรายการย่อย
1. เพิ่มครุภัณฑ์ใหม่ ใส่จำนวน > 1
2. ระบบจะสร้างรายการย่อยอัตโนมัติ พร้อมรหัสต่อท้าย `.01`, `.02`, ...
3. คลิก `>` ขยายเพื่อดูรายการย่อย
4. กดปุ่ม **แก้ไข** เพื่อเปลี่ยนชื่อแต่ละตัว

## ไฟล์สำคัญ

### ระบบหลัก
| ไฟล์ | คำอธิบาย |
|------|----------|
| `www/items.php` | หน้ารายการครุภัณฑ์ + จัดการ parent/child |
| `www/requisitions.php` | หน้ารายการเบิกพัสดุ |
| `www/index.php` | หน้า Dashboard |
| `www/includes/db.php` | เชื่อมต่อฐานข้อมูล SQLite + migration |
| `www/database.sqlite` | ฐานข้อมูล |

### พิมพ์บาร์โค้ด
| ไฟล์ | คำอธิบาย |
|------|----------|
| `www/print_barcode_pt2730.php` | พิมพ์บาร์โค้ดทีละรายการ (ออกแบบป้าย) |
| `www/print_barcodes_batch.php` | พิมพ์บาร์โค้ดหลายรายการ (WYSIWYG) |
| `www/print_barcode_range.php` | พิมพ์บาร์โค้ดเป็นช่วง |
| `www/print_output.php` | ส่งออกไปยังเครื่องพิมพ์ PT-2730 |
| `www/api_print.php` | API สำหรับพิมพ์จาก Modal |
| `www/api_ptouch_print.php` | API พิมพ์ตรงไปยัง P-touch |

## การปรับแต่งบาร์โค้ด

### การตั้งค่า JsBarcode (SVG)
```javascript
JsBarcode(svgEl, value, {
    format: 'CODE128',
    width: 1.2,       // ความกว้างแท่ง
    height: 36,        // ความสูง
    displayValue: true, // แสดงตัวเลขใต้บาร์โค้ด
    text: displayText,  // ข้อความที่แสดง
    fontSize: 9,        // ขนาดตัวอักษร
    margin: 2
});
```

### การตั้งค่า bwipjs API (รูปภาพ)
```
https://bwipjs-api.metafloor.com/?bcid=code128
    &text=SDU690301061
    &scale=2
    &height=12
    &includetext=true
    &textxalign=center
```

## การแพ็กเกจเป็น .exe (สำหรับแจกจ่าย)

### วิธีที่ 1: ZIP (ง่ายที่สุด)
1. คัดลอกโฟลเดอร์ทั้งหมด (ยกเว้น `webcache/`, `debug.log`, `.git/`)
2. อัด ZIP แล้วแจกจ่าย ผู้ใช้แตกไฟล์แล้วรัน `StockApp.exe`

### วิธีที่ 2: Inno Setup (สร้าง Setup.exe)
ใช้ Inno Setup Script (ติดตั้ง Inno Setup ก่อน):
```iss
#define AppName "StockApp"
#define AppVersion "1.0"
#define AppPublisher "มหาวิทยาลัยสวนดุสิต"

[Setup]
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={pf}\{#AppName}
DefaultGroupName={#AppName}
OutputDir=installer
OutputBaseFilename=StockApp_Setup

[Files]
Source: "StockApp.exe"; DestDir: "{app}"
Source: "php\*"; DestDir: "{app}\php"; Flags: recursesubdirs
Source: "www\*"; DestDir: "{app}\www"; Flags: recursesubdirs ignoreversion
Source: "*.dll"; DestDir: "{app}"
Source: "*.pak"; DestDir: "{app}"
Source: "*.bin"; DestDir: "{app}"
Source: "*.dat"; DestDir: "{app}"
Source: "icon.ico"; DestDir: "{app}"
Source: "locales\*"; DestDir: "{app}\locales"; Flags: recursesubdirs
Source: "settings.json"; DestDir: "{app}"

[Icons]
Name: "{group}\StockApp"; Filename: "{app}\StockApp.exe"
Name: "{commondesktop}\StockApp"; Filename: "{app}\StockApp.exe"
```

### วิธีที่ 3: NSIS (Nullsoft Scriptable Install System)
ใช้ NSIS Script เพื่อสร้าง installer ขนาดเล็ก

### หมายเหตุ
- ต้องแน่ใจว่าได้รวม `php/`, `www/`, `locales/`, DLLs, `.pak`, `.bin`, `.dat` ครบ
- ลบ `webcache/` และ `debug.log` ก่อนแพ็กเกจ
- ถ้าต้องการ portable ไม่ต้องลง registry ให้ใช้ ZIP แทน

## เทคโนโลยี

- **Frontend:** HTML, CSS, JavaScript, Lucide Icons, Tom Select
- **Backend:** PHP 7.1.3 (phpdesktop)
- **Database:** SQLite (PDO)
- **Runtime:** phpdesktop (Chromium Embedded Framework)
- **Barcode:** JsBarcode (SVG), bwipjs API (image)
- **QR Scanning:** html5-qrcode

## โครงสร้างไฟล์

```
StockApp/
├── www/                    # Source code PHP
│   ├── includes/           # header, footer, db.php
│   ├── assets/             # CSS, JS, รูปภาพ, ฟอนต์
│   ├── items.php           # หน้ารายการครุภัณฑ์
│   ├── requisitions.php    # หน้ารายการเบิก
│   ├── print_barcode_pt2730.php  # พิมพ์บาร์โค้ด PT-2730
│   ├── print_barcodes_batch.php  # พิมพ์หลายรายการ
│   ├── print_output.php    # ส่งออกเครื่องพิมพ์
│   ├── index.php           # หน้า Dashboard
│   └── database.sqlite     # ฐานข้อมูล
├── php/                    # PHP interpreter (runtime)
├── settings.json           # ตั้งค่าโปรแกรม
├── locales/                # ภาษา CEF
└── StockApp.exe            # ตัวรันโปรแกรม
```
