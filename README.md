# StockApp

| ภาษาไทย | English |
|---------|---------|
| **ระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต** | **Suan Dusit University Stock Management System** |
| พัฒนาด้วย PHP + SQLite + phpdesktop (CEF) | Built with PHP + SQLite + phpdesktop (CEF) |

---

## คุณสมบัติ / Features

| ภาษาไทย | English |
|---------|---------|
| จัดการรายการครุภัณฑ์ (เพิ่ม, ลบ, แก้ไข) | Asset CRUD (Add, Edit, Delete) |
| รายการย่อย (Parent/Child) — แต่ละตัวมี record แยก แก้ไขชื่อทีละตัวได้ | Parent/Child items — each has own record, editable individually |
| ระบบเบิกพัสดุ | Requisition management |
| พิมพ์บาร์โค้ด CODE128 รองรับ Brother PT-2730 (ทีละรายการ, หลายรายการ, เป็นช่วง) | Barcode printing CODE128, supports Brother PT-2730 (single, batch, range) |
| ออกแบบป้าย — ลากวางตำแหน่งรหัส ชื่อ บาร์โค้ด | Label designer — drag & drop code, name, barcode |
| รองรับ Dark Mode | Dark Mode support |
| สแกนบาร์โค้ดจากกล้องเว็บแคม (html5-qrcode) | Webcam barcode scanning (html5-qrcode) |
| บันทึกประวัติการแก้ไข (Audit Log) | Audit log |

---

## การติดตั้ง / Installation

| ภาษาไทย | English |
|---------|---------|
| **ผู้ใช้ทั่วไป** | **End Users** |
| 1. ดาวน์โหลด `StockApp-Package.zip` จาก [GitHub Releases](https://github.com/pooh18528/StockApp-/releases) | 1. Download `StockApp-Package.zip` from [GitHub Releases](https://github.com/pooh18528/StockApp-/releases) |
| 2. แตก ZIP แล้วรัน `StockApp.exe` | 2. Unzip and run `StockApp.exe` |
| **นักพัฒนา** | **Developers** |
| 1. `git clone https://github.com/pooh18528/StockApp-.git` | 1. Clone the repo |
| 2. วาง runtime (CEF DLLs, `php/`, `locales/`, `StockApp.exe`, `settings.json`) ใน root | 2. Place runtime files (CEF DLLs, `php/`, `locales/`, `StockApp.exe`, `settings.json`) in root |
| 3. รัน `StockApp.exe` | 3. Run `StockApp.exe` |

---

## การใช้งาน Brother PT-2730 / PT-2730 Setup

| ภาษาไทย | English |
|---------|---------|
| **ติดตั้ง Driver** | **Install Driver** |
| 1. ติดตั้ง Brother P-touch Editor จาก support.brother.com (รุ่น PT-2730) | 1. Install Brother P-touch Editor from support.brother.com (model PT-2730) |
| 2. เสียบ PT-2730 ผ่าน USB | 2. Connect PT-2730 via USB |
| 3. ตั้งเป็น Default Printer | 3. Set as default printer |
| 4. ใช้เทป 12mm, 18mm หรือ 24mm | 4. Use 12mm, 18mm, or 24mm tape |
| **พิมพ์บาร์โค้ด** | **Print Barcode** |
| 1. ไปที่หน้า **รายการครุภัณฑ์** | 1. Go to **Items** page |
| 2. คลิก **พิมพ์** ในแถว หรือเลือกหลายรายการแล้วกด **พิมพ์หลายรายการ** | 2. Click **Print** on a row or select multiple items and click **Print Batch** |
| 3. ในหน้าออกแบบป้าย: ลากวางรหัส ชื่อ บาร์โค้ด เลือกขนาดเทป กด **พิมพ์** | 3. In label designer: drag & drop, choose tape width, click **Print** (Kiosk Mode) |
| **ปรับแต่งบาร์โค้ด** | **Barcode Config** |
| `displayValue: true` (JsBarcode) — แสดงตัวเลขใต้บาร์โค้ด | `displayValue: true` (JsBarcode) — show numbers below barcode |
| `includetext=true` (bwipjs) — แสดงข้อความในรูป | `includetext=true` (bwipjs) — show text in image |
| รหัสไม่มีจุด (`SDU690301061`) — ให้สแกนเนอร์อ่านได้ | Value: no dots (`SDU690301061`) — for scanner compatibility |
| ข้อความมีจุด (`SDU.69.03.01.06.1`) — ให้อ่านง่าย | Display: with dots (`SDU.69.03.01.06.1`) — for readability |

---

## ระบบรายการย่อย / Parent/Child Items

| ภาษาไทย | English |
|---------|---------|
| เมื่อจำนวน > 1 ระบบจะสร้าง record แยกแต่ละตัว | When quantity > 1, each item gets its own record |
| **Parent item**: รายการหลัก (หมวดหมู่, ราคา, วันที่) | **Parent item**: master record (category, price, date) |
| **Child items**: แต่ละตัวมี ID และชื่อของตัวเอง | **Child items**: each has its own ID and name |
| คลิก `>` เพื่อขยายดูรายการย่อย | Click `>` to expand children |
| กด **แก้ไข** เพื่อเปลี่ยนชื่อแต่ละตัว | Click **Edit** to rename each one |

---

## การแพ็กเกจ / Packaging

| ภาษาไทย | English |
|---------|---------|
| **ZIP** — คัดลอกทุกไฟล์ (ยกเว้น `webcache/`, `debug.log`, `.git/`) แล้วอัด ZIP | **ZIP** — copy all files (exclude `webcache/`, `debug.log`, `.git/`) and zip |
| **Inno Setup** — ใช้สคริปต์ด้านล่าง | **Inno Setup** — use script below |
| **NSIS** — ใช้ NSIS Script | **NSIS** — use NSIS Script |

### Inno Setup Script
```iss
#define AppName "StockApp"
#define AppVersion "1.0"
#define AppPublisher "มหาวิทยาลัยสวนดุสิต / Suan Dusit University"

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

| ภาษาไทย | English |
|---------|---------|
| **หมายเหตุ** | **Notes** |
| รวม `php/`, `www/`, `locales/`, DLLs, `.pak`, `.bin`, `.dat` ให้ครบ | Include all runtime files |
| ลบ `webcache/` และ `debug.log` ก่อนแพ็ก | Remove `webcache/` and `debug.log` before packaging |
| ถ้าต้องการ portable ให้ใช้ ZIP | For portable (no registry), use ZIP |

---

## เทคโนโลยี / Tech Stack

| ภาษาไทย | English |
|---------|---------|
| **Frontend:** HTML, CSS, JavaScript, Lucide Icons, Tom Select | Same |
| **Backend:** PHP 7.1.3 (phpdesktop) | Same |
| **Database:** SQLite (PDO) | Same |
| **Runtime:** phpdesktop (Chromium Embedded Framework) | Same |
| **Barcode:** JsBarcode (SVG), bwipjs API (image) | Same |
| **QR Scanning:** html5-qrcode | Same |

---

## ไฟล์สำคัญ / Key Files

| ภาษาไทย | English |
|---------|---------|
| `www/items.php` — รายการครุภัณฑ์ + จัดการ parent/child | Asset list + parent/child management |
| `www/requisitions.php` — เบิกพัสดุ | Requisitions |
| `www/index.php` — Dashboard | Dashboard |
| `www/print_barcode_pt2730.php` — พิมพ์บาร์โค้ดเดี่ยว (ออกแบบป้าย) | Single barcode print (label designer) |
| `www/print_barcodes_batch.php` — พิมพ์หลายรายการ | Batch barcode print |
| `www/print_output.php` — ส่งออกเครื่องพิมพ์ PT-2730 | Send to PT-2730 printer |
| `www/api_print.php` — API พิมพ์จาก Modal | Print API |
| `www/includes/db.php` — เชื่อมต่อ DB + migration | DB connection + migration |
| `www/database.sqlite` — ฐานข้อมูล | Database |

---

## โครงสร้างไฟล์ / File Structure

| ภาษาไทย | English |
|---------|---------|
| `www/` — Source code PHP | PHP source code |
| `www/includes/` — header, footer, db.php | Shared components |
| `www/assets/` — CSS, JS, รูปภาพ, ฟอนต์ | CSS, JS, images, fonts |
| `php/` — PHP interpreter (runtime) | PHP interpreter |
| `settings.json` — ตั้งค่าโปรแกรม | App config |
| `locales/` — ภาษา CEF | CEF locale packs |
| `StockApp.exe` — ตัวรันโปรแกรม | Launcher |

---

## การปรับแต่งบาร์โค้ด / Barcode Configuration

### JsBarcode (SVG)
```javascript
JsBarcode(svgEl, value, {
    format: 'CODE128',
    width: 1.2,
    height: 36,
    displayValue: true,        // แสดงตัวเลข / show numbers
    text: displayText,
    fontSize: 9,
    margin: 2
});
```

### bwipjs API (image)
```
https://bwipjs-api.metafloor.com/?bcid=code128
    &text=SDU690301061
    &scale=2
    &height=12
    &includetext=true           // แสดงข้อความ / show text
    &textxalign=center
```
