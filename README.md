# StockApp — ระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต / Suan Dusit University Stock Management System

---

## คุณสมบัติ / Features

- **จัดการรายการครุภัณฑ์** (เพิ่ม, ลบ, แก้ไข) / **Asset CRUD** (Add, Edit, Delete)
- **รายการย่อย (Parent/Child)** — แต่ละตัวมี record ของตัวเอง แก้ไขชื่อทีละตัวได้ / Each child has its own record, editable individually
- **ระบบเบิกพัสดุ** / Requisition management
- **พิมพ์บาร์โค้ด CODE128** รองรับ Brother PT-2730 — ทีละรายการ, หลายรายการ, หรือเป็นช่วง / Print single, batch, or range
- **ออกแบบป้ายได้** — ลากวางรหัส ชื่อ บาร์โค้ด / Label designer with drag & drop
- **Dark Mode**
- **สแกนบาร์โค้ดจากกล้องเว็บแคม** (html5-qrcode) / Webcam barcode scanning
- **บันทึกประวัติการแก้ไข** / Audit log

---

## การติดตั้ง / Installation

### ผู้ใช้ทั่วไป / End Users
1. ดาวน์โหลด / Download `StockApp-Package.zip` จาก [GitHub Releases](https://github.com/pooh18528/StockApp-/releases)
2. แตก ZIP แล้วรัน / Unzip and run `StockApp.exe`

### นักพัฒนา / Developers
```bash
git clone https://github.com/pooh18528/StockApp-.git
```
วาง runtime (`CEF DLLs`, `php/`, `locales/`, `StockApp.exe`, `settings.json`) ใน root แล้วรัน / Place runtime files in root and run `StockApp.exe`

---

## การใช้งาน Brother PT-2730 / PT-2730 Setup

### ติดตั้ง Driver / Install Driver
1. ติดตั้ง Brother P-touch Editor จาก https://support.brother.com (เลือก / select PT-2730)
2. เสียบ / Connect PT-2730 via USB
3. ตั้งเป็น Default Printer / Set as default printer
4. ใช้เทป / Use tape: 12mm, 18mm หรือ / or 24mm

### พิมพ์บาร์โค้ด / Print Barcode
1. ไปที่ **รายการครุภัณฑ์** / Go to **Items** page
2. คลิก **พิมพ์** ในแถว หรือเลือกหลายรายการแล้วกด **พิมพ์หลายรายการ** / Click **Print** on a row or select multiple items and click **Print Batch**
3. ในหน้าออกแบบป้าย / In label designer:
   - ลากวางรหัส ชื่อ บาร์โค้ด / Drag & drop code, name, barcode
   - เลือกขนาดเทป / Choose tape width
   - กด **พิมพ์** / Click **Print** (Kiosk Mode — no OK dialog)

### ปรับแต่งบาร์โค้ด / Barcode Config
- `displayValue: true` (JsBarcode) — แสดงตัวเลขใต้บาร์โค้ด / show numbers below barcode
- `includetext=true` (bwipjs) — แสดงข้อความในรูป / show text in image
- รหัสในบาร์โค้ด / Barcode value: ไม่มีจุด / no dots (`SDU690301061`) — เพื่อให้สแกนเนอร์อ่านได้ / for scanner compatibility
- ข้อความที่แสดง / Display text: มีจุด / with dots (`SDU.69.03.01.06.1`) — เพื่อให้อ่านง่าย / for readability

---

## ระบบรายการย่อย / Parent/Child Items

เมื่อจำนวน > 1 ระบบสร้าง record แยกแต่ละตัว / When quantity > 1, the system creates individual child records:

- **Parent item** — รายการหลัก / master record (หมวดหมู่ / category, ราคา / price, วันที่ / date)
- **Child items** — แต่ละตัวมี ID และชื่อของตัวเอง / each has its own ID and name
- คลิก `>` ขยายดูรายการย่อย / Click `>` to expand children
- กด **แก้ไข** เปลี่ยนชื่อแต่ละตัว / Click **Edit** to rename each one

---

## การแพ็กเกจ / Packaging

### ZIP (ง่ายที่สุด / Simplest)
คัดลอกทุกไฟล์ (ยกเว้น / exclude `webcache/`, `debug.log`, `.git/`) แล้วอัด ZIP / then zip

### Inno Setup
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

### หมายเหตุ / Notes
- รวม / Include: `php/`, `www/`, `locales/`, DLLs, `.pak`, `.bin`, `.dat`
- ลบ / Remove: `webcache/`, `debug.log` ก่อนแพ็ก / before packaging
- ถ้าต้องการ portable (ไม่ลง registry) ให้ใช้ ZIP / For portable (no registry), use ZIP

---

## เทคโนโลยี / Tech Stack

| | |
|---|---|
| **Frontend** | HTML, CSS, JavaScript, Lucide Icons, Tom Select |
| **Backend** | PHP 7.1.3 (phpdesktop) |
| **Database** | SQLite (PDO) |
| **Runtime** | phpdesktop (Chromium Embedded Framework) |
| **Barcode** | JsBarcode (SVG), bwipjs API (image) |
| **QR Scanning** | html5-qrcode |

---

## ไฟล์สำคัญ / Key Files

| ไฟล์ / File | คำอธิบาย / Description |
|---|---|
| `www/items.php` | รายการครุภัณฑ์ + จัดการ parent/child / Asset list + parent/child |
| `www/requisitions.php` | เบิกพัสดุ / Requisitions |
| `www/index.php` | Dashboard |
| `www/print_barcode_pt2730.php` | พิมพ์บาร์โค้ดเดี่ยว (ออกแบบป้าย) / Single barcode print (label designer) |
| `www/print_barcodes_batch.php` | พิมพ์หลายรายการ / Batch print |
| `www/print_output.php` | ส่งออกเครื่องพิมพ์ / Send to PT-2730 |
| `www/api_print.php` | API พิมพ์จาก Modal / Print API |
| `www/includes/db.php` | เชื่อมต่อ DB + migration / DB connection |
| `www/database.sqlite` | ฐานข้อมูล / Database |

---

## โครงสร้างไฟล์ / File Structure

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
├── settings.json           # App config / ตั้งค่าโปรแกรม
├── locales/                # CEF locale packs / ภาษา CEF
└── StockApp.exe            # Launcher / ตัวรัน
```

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
