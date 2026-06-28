# StockApp — ระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต
# StockApp — Suan Dusit University Stock Management System

---

## English

### Overview
A desktop stock/asset management application built with **PHP + SQLite + phpdesktop (CEF)**. Designed for Suan Dusit University to manage assets, print barcode labels (Brother PT-2730), and handle requisitions.

### Features
- Asset CRUD with **Parent/Child items** — each item has its own database record, editable individually
- Requisition management
- **Barcode printing** (CODE128) — single, batch, or range — supports Brother PT-2730 label printer
- Label designer — drag & drop position for code, name, barcode
- Dark Mode
- Webcam barcode scanning (html5-qrcode)
- Audit log

### Installation
#### End Users
1. Download `StockApp-Package.zip` from [GitHub Releases](https://github.com/pooh18528/StockApp-/releases)
2. Unzip and run `StockApp.exe`

#### Developers
```bash
git clone https://github.com/pooh18528/StockApp-.git
```
Then place phpdesktop runtime files (CEF DLLs, `php/`, `locales/`, `StockApp.exe`, `settings.json`) in the root folder and run `StockApp.exe`.

### Brother PT-2730 Barcode Printer Setup
1. Install Brother P-touch Editor (downloads the driver) from https://support.brother.com
2. Connect PT-2730 via USB
3. Set as default printer (or the app selects it automatically)
4. Use 12mm, 18mm, or 24mm tape

#### Printing from the App
1. Go to **Items** page
2. Click the **Print** button on a row, or select multiple items and click **Print Batch**
3. In the label designer:
   - Drag and drop code, name, barcode freely
   - Choose tape width
   - Click **Print** (uses Kiosk Printing Mode — no OK dialog)

#### Barcode Configuration
- `displayValue: true` in JsBarcode — shows human-readable numbers below the barcode
- `includetext=true` in bwipjs API — shows text in barcode image
- `textxalign=center` — center text alignment
- Barcode value uses dots removed (`SDU690301061`) for scanner compatibility
- Display text uses dots (`SDU.69.03.01.06.1`) for readability

### Parent/Child Items System
When an asset has quantity > 1, the system creates individual child records:
- **Parent item**: master record (category, price, date, etc.)
- **Child items**: each has its own ID and name, editable individually
- Click `>` to expand and view children, then **Edit** to rename each one

### Packaging as Setup.exe
1. **ZIP** — simplest: copy all files (exclude `webcache/`, `debug.log`, `.git/`) and zip
2. **Inno Setup** — use the script in the "การแพ็กเกจ" section below
3. **NSIS** — lightweight installer

### Tech Stack
- **Frontend:** HTML, CSS, JavaScript, Lucide Icons, Tom Select
- **Backend:** PHP 7.1.3 (phpdesktop)
- **Database:** SQLite (PDO)
- **Runtime:** phpdesktop (Chromium Embedded Framework)
- **Barcode:** JsBarcode (SVG), bwipjs API (image)
- **QR Scanning:** html5-qrcode

### Key Files
| File | Description |
|------|-------------|
| `www/items.php` | Asset list + parent/child management |
| `www/requisitions.php` | Requisitions |
| `www/index.php` | Dashboard |
| `www/print_barcode_pt2730.php` | Single barcode print (label designer) |
| `www/print_barcodes_batch.php` | Batch barcode print |
| `www/print_output.php` | Send to PT-2730 printer |
| `www/includes/db.php` | DB connection + migration |
| `www/database.sqlite` | SQLite database |

### File Structure
```
StockApp/
├── www/                    # PHP source code
│   ├── includes/           # header, footer, db.php
│   ├── assets/             # CSS, JS, images, fonts
│   ├── items.php
│   ├── requisitions.php
│   ├── print_barcode_pt2730.php
│   ├── print_barcodes_batch.php
│   ├── print_output.php
│   ├── index.php
│   └── database.sqlite
├── php/                    # PHP interpreter (runtime)
├── settings.json           # App config
├── locales/                # CEF locale packs
└── StockApp.exe            # Launcher
```

---

## ภาษาไทย

### ภาพรวม
โปรแกรมบริหารจัดการพัสดุแบบ Desktop พัฒนาด้วย **PHP + SQLite + phpdesktop (CEF)** สำหรับมหาวิทยาลัยสวนดุสิต รองรับการจัดการครุภัณฑ์ พิมพ์สติ๊กเกอร์บาร์โค้ด (Brother PT-2730) และระบบเบิกพัสดุ

### คุณสมบัติ
- จัดการรายการครุภัณฑ์ (เพิ่ม, ลบ, แก้ไข)
- รองรับรายการย่อย (Parent/Child items) — แต่ละตัวมี record ของตัวเอง แก้ไขชื่อทีละตัวได้
- ระบบเบิกพัสดุ
- พิมพ์บาร์โค้ด (CODE128) รองรับเครื่องพิมพ์ PT-2730 (Brother P-touch)
- พิมพ์ทีละรายการ, หลายรายการพร้อมกัน, หรือพิมพ์เป็นช่วง
- ออกแบบป้ายได้ — ลากวางตำแหน่งรหัส ชื่อ บาร์โค้ด
- รองรับ Dark Mode
- สแกนบาร์โค้ดจากกล้องเว็บแคม (html5-qrcode)
- ระบบบันทึกประวัติการแก้ไข (Audit Log)

### การติดตั้ง
#### สำหรับผู้ใช้ทั่วไป
1. ดาวน์โหลด `StockApp-Package.zip` จาก [GitHub Releases](https://github.com/pooh18528/StockApp-/releases)
2. แตก ZIP แล้วรัน `StockApp.exe`

#### สำหรับนักพัฒนา
```bash
git clone https://github.com/pooh18528/StockApp-.git
```
วางไฟล์ runtime (CEF DLLs, `php/`, `locales/`, `StockApp.exe`, `settings.json`) ในโฟลเดอร์ root แล้วรัน `StockApp.exe`

### การใช้งานเครื่องพิมพ์สติ๊กเกอร์ Brother PT-2730
1. ติดตั้ง Brother P-touch Editor (สำหรับ Driver) จาก https://support.brother.com
2. เสียบ PT-2730 ผ่าน USB
3. ตั้งเป็นเครื่องพิมพ์เริ่มต้น (Default Printer)
4. ใช้เทปขนาด 12mm, 18mm หรือ 24mm

#### พิมพ์บาร์โค้ดจากโปรแกรม
1. ไปที่หน้า **รายการครุภัณฑ์**
2. คลิกปุ่ม **พิมพ์** ในแถว หรือเลือกหลายรายการแล้วกด **พิมพ์หลายรายการ**
3. ในหน้าออกแบบป้าย:
   - ลากวางตำแหน่งรหัส ชื่อ บาร์โค้ด ได้อิสระ
   - เลือกขนาดเทป (Tape Width)
   - กด **พิมพ์** (Kiosk Printing Mode — พิมพ์ทันที)

#### การปรับแต่งค่าบาร์โค้ด
- `displayValue: true` — แสดงตัวเลขใต้บาร์โค้ด
- `includetext=true` — แสดงข้อความในรูปบาร์โค้ด bwipjs
- `textxalign=center` — จัดข้อความกึ่งกลาง
- รหัสในบาร์โค้ด: แบบไม่มีจุด (`SDU690301061`) เพื่อให้สแกนเนอร์อ่านได้
- ข้อความที่แสดง: แบบมีจุด (`SDU.69.03.01.06.1`) เพื่อให้อ่านง่าย

### ระบบรายการย่อย (Parent/Child Items)
เมื่อเพิ่มครุภัณฑ์ที่มีจำนวน > 1 ระบบจะสร้าง record แยกแต่ละตัว:
- **Parent item**: รายการหลัก (หมวดหมู่, ราคา, วันที่ ฯลฯ)
- **Child items**: แต่ละตัวมี ID และชื่อของตัวเอง แก้ไขทีละตัวได้
- คลิก `>` เพื่อขยายดูรายการย่อย กด **แก้ไข** เพื่อเปลี่ยนชื่อ

### การแพ็กเกจเป็น Setup.exe
#### วิธี ZIP (ง่ายที่สุด)
คัดลอกทุกไฟล์ (ยกเว้น `webcache/`, `debug.log`, `.git/`) แล้วอัด ZIP

#### วิธี Inno Setup (สร้าง Setup.exe)
ติดตั้ง Inno Setup แล้วใช้สคริปต์นี้:
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

#### วิธี NSIS
ใช้ NSIS Script เพื่อสร้าง installer ขนาดเล็ก

#### หมายเหตุ
- รวม `php/`, `www/`, `locales/`, DLLs, `.pak`, `.bin`, `.dat` ให้ครบ
- ลบ `webcache/` และ `debug.log` ก่อนแพ็กเกจ
- ถ้าต้องการ portable (ไม่ลง registry) ให้ใช้ ZIP

### สแตกเทคโนโลยี
- **Frontend:** HTML, CSS, JavaScript, Lucide Icons, Tom Select
- **Backend:** PHP 7.1.3 (phpdesktop)
- **Database:** SQLite (PDO)
- **Runtime:** phpdesktop (Chromium Embedded Framework)
- **Barcode:** JsBarcode (SVG), bwipjs API (image)
- **QR Scanning:** html5-qrcode

### ไฟล์สำคัญ
| ไฟล์ | คำอธิบาย |
|------|----------|
| `www/items.php` | หน้ารายการครุภัณฑ์ + จัดการ parent/child |
| `www/requisitions.php` | หน้ารายการเบิก |
| `www/index.php` | หน้า Dashboard |
| `www/print_barcode_pt2730.php` | พิมพ์บาร์โค้ดทีละรายการ (ออกแบบป้าย) |
| `www/print_barcodes_batch.php` | พิมพ์บาร์โค้ดหลายรายการ |
| `www/print_output.php` | ส่งออกไปยัง PT-2730 |
| `www/includes/db.php` | เชื่อมต่อฐานข้อมูล + migration |
| `www/database.sqlite` | ฐานข้อมูล |

### โครงสร้างไฟล์
```
StockApp/
├── www/                    # Source code PHP
│   ├── includes/           # header, footer, db.php
│   ├── assets/             # CSS, JS, รูปภาพ, ฟอนต์
│   ├── items.php
│   ├── requisitions.php
│   ├── print_barcode_pt2730.php
│   ├── print_barcodes_batch.php
│   ├── print_output.php
│   ├── index.php
│   └── database.sqlite
├── php/                    # PHP interpreter (runtime)
├── settings.json           # ตั้งค่าโปรแกรม
├── locales/                # ภาษา CEF
└── StockApp.exe            # ตัวรันโปรแกรม
```

---

### Barcode Configuration / การปรับแต่งบาร์โค้ด

**JsBarcode (SVG)**
```javascript
JsBarcode(svgEl, value, {
    format: 'CODE128',
    width: 1.2,
    height: 36,
    displayValue: true,
    text: displayText,
    fontSize: 9,
    margin: 2
});
```

**bwipjs API (image)**
```
https://bwipjs-api.metafloor.com/?bcid=code128
    &text=SDU690301061
    &scale=2
    &height=12
    &includetext=true
    &textxalign=center
```
