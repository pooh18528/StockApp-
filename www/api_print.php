<?php
/**
 * api_print.php — API สำหรับสร้างไฟล์พิมพ์และเปิดในเบราว์เซอร์จริง
 * 
 * แก้ปัญหา: PHP Desktop (CEF) บล็อก window.print()
 * วิธีแก้: สร้างไฟล์ HTML ลงดิสก์ แล้วเปิดในเบราว์เซอร์จริง (Chrome/Edge)
 * ซึ่ง window.print() ทำงานได้ปกติ 100%
 */

header('Content-Type: application/json; charset=utf-8');

// รับข้อมูลจาก POST
$input = json_decode(file_get_contents('php://input'), true);
$barcodes = $input['barcodes'] ?? [];
$layoutMode = intval($input['layoutMode'] ?? 1);
$tapeWidth = intval($input['tapeWidth'] ?? 12);
$showBarcode = isset($input['showBarcode']) ? (bool)$input['showBarcode'] : true;
$barcodeDataMode = $input['barcodeDataMode'] ?? 'code';
$baseUrl = $input['baseUrl'] ?? '';
$customStyle = $input['customStyle'] ?? null;

if (empty($barcodes)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล Barcode']);
    exit;
}

// === PT-2730 Tape Specs ===
$tapeSpecs = [
    '6'  => ['height' => 6,  'printable' => 4.5, 'textSize' => '5pt',  'barcodeHeight' => 40],
    '9'  => ['height' => 9,  'printable' => 7.5, 'textSize' => '6pt',  'barcodeHeight' => 70],
    '12' => ['height' => 12, 'printable' => 12,  'textSize' => '12pt', 'barcodeHeight' => 100],
    '18' => ['height' => 18, 'printable' => 14,  'textSize' => '9pt',  'barcodeHeight' => 140],
    '24' => ['height' => 24, 'printable' => 18,  'textSize' => '10pt', 'barcodeHeight' => 180],
];
$spec = $tapeSpecs[$tapeWidth] ?? $tapeSpecs['12'];
$tapeHeight = $spec['height'];
$printableHeight = $spec['printable'];

// Set default or custom text style
$textStyleCSS = '';
if ($customStyle) {
    $font = $customStyle['font'] ?? "'Sarabun', sans-serif";
    $fontSize = $customStyle['fontSize'] ?? '12pt';
    $fontWeight = ($customStyle['bold'] ?? false) ? '900' : 'normal';
    $fontStyle = ($customStyle['italic'] ?? false) ? 'italic' : 'normal';
    $textDecoration = ($customStyle['underline'] ?? false) ? 'underline' : 'none';
    $textAlign = $customStyle['align'] ?? 'left';
    
    // Automatically boost small font sizes (e.g. from the 11pt UI default) so it reads clearly on the label
    if (intval($fontSize) <= 13) {
        $fontSize = '14pt';
    }

    // Safeguard: Limit max font size
    if ($layoutMode > 1 && intval($fontSize) > 16) {
        $fontSize = '16pt';
    }
    
    $textStyleCSS = "font-family: {$font} !important; font-size: {$fontSize} !important; font-weight: {$fontWeight} !important; font-style: {$fontStyle} !important; text-decoration: {$textDecoration} !important; text-align: {$textAlign} !important;";
}

// กำหนดความกว้างต่อ 1 รายการ (ดึงค่าจาก UI ที่เลือกความยาวฉลากเดี่ยว)
$singleLabelLength = intval($input['labelLength'] ?? 100);

if ($layoutMode == 998) {
    // สำหรับแบบต่อเนื่อง (พิมพ์ยาวรวดเดียว) ให้คำนวณความยาวตามจำนวนบาร์โค้ดจริง
    $totalLabelLength = $singleLabelLength * count($barcodes);
} else {
    $totalLabelLength = $singleLabelLength * $layoutMode;
}

// จำกัดความยาวสูงสุดต่อหนึ่งแผ่น (Browser/Printer Limit)
// ปรับเพิ่มเป็น 5000mm (5 เมตร) เพื่อรองรับการพิมพ์แบบต่อเนื่องยาวๆ
if ($totalLabelLength > 5000) { 
    $totalLabelLength = 5000;
}

$barcodeCount = count($barcodes);
$labelCount = ceil($barcodeCount / ($layoutMode == 998 ? $barcodeCount : $layoutMode));

$layoutInstructionsHTML = '';
if ($layoutMode == 2) {
    $layoutInstructionsHTML = <<<HTML
        <!-- คำแนะนำสำหรับโหมด 2 รายการ/แผ่น -->
        <div style="margin-top: 1.5rem; text-align: left; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 1.25rem; font-size: 0.85rem; color: #78350f; line-height: 1.6;">
            <h4 style="margin: 0 0 0.5rem 0; color: #92400e; font-size: 0.9rem; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                ⚙️ วิธีตั้งค่าขนาดกระดาษใน Print Dialog (หากต้องการใช้โหมด 2 รายการ/แผ่น)
            </h4>
            <p style="margin-bottom: 0.5rem; color: #b45309; font-weight: 600;">
                หากท่านต้องการให้พิมพ์ 2 อันติดกันบนเทปชิ้นเดียวยาว ๆ จริง ๆ:
            </p>
            <ol style="margin: 0; padding-left: 1.2rem; display: flex; flex-direction: column; gap: 4px;">
                <li>ตอนที่หน้าต่างพิมพ์ของเบราว์เซอร์ (Chrome / Edge) แสดงขึ้นมา ให้เลือก <strong>More settings (การตั้งค่าเพิ่มเติม)</strong></li>
                <li>ตรวจสอบให้มั่นใจว่า <strong>Scale (อัตราส่วน)</strong> ถูกตั้งค่าเป็น <strong>100%</strong> (ไม่ใช่ Fit to page)</li>
                <li>ตั้งค่าขนาดกระดาษ <strong>(Paper Size)</strong> ในเครื่องพิมพ์ให้รองรับความยาวที่เพิ่มขึ้น (เช่น ตั้งค่าเป็นความยาวแบบ Custom หรือเลือกขนาดที่ตรงกับ 200mm)</li>
            </ol>
        </div>
HTML;
}

// สร้าง barcode labels HTML
$labelsHTML = '';

if ($layoutMode == 998) {
    $combinedLabel = '';
    foreach ($barcodes as $item) {
        $label = htmlspecialchars($item['label'] ?? $item['value'] ?? '');
        // If the label doesn't contain dots, format it automatically
        if (strpos($label, '.') === false) {
            $cleanLabel = str_replace('.', '', $label);
            if (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
                $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4] . "." . $matches[5];
            } elseif (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
                $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4];
            } elseif (preg_match('/^SDU(\d)/i', $cleanLabel)) {
                $label = preg_replace('/^SDU(\d)/i', 'SDU.$1', $cleanLabel);
            }
        }
        $combinedLabel .= $label . '<br>';
    }
    
    $labelsHTML .= '<div class="print-label" style="width: auto !important; height: auto !important; min-height: 12mm !important;">';
    $labelsHTML .= <<<HTML
        <div class="print-wrapper no-barcode" style="width: auto !important; height: auto !important; align-items: flex-start !important; padding: 2mm 5mm !important;">
            <div class="barcode-text-print text-only custom-style" style="white-space: normal !important; line-height: 1.2 !important; text-align: left !important; word-wrap: break-word !important;">{$combinedLabel}</div>
        </div>
    </div>
HTML;

} else {
    $chunks = array_chunk($barcodes, $layoutMode);
    foreach ($chunks as $chunk) {
        $labelsHTML .= '<div class="print-label">';
        foreach ($chunk as $item) {
            $value = htmlspecialchars($item['value'] ?? '');
            // Strip dots for barcode scanning ease and compatibility (especially with Thai layouts and high contrast)
            $cleanValue = str_replace('.', '', $value);
            
            $label = htmlspecialchars($item['label'] ?? $item['value'] ?? '');
            
            // If the label doesn't contain dots, format it automatically
            if (strpos($label, '.') === false) {
                $cleanLabel = str_replace('.', '', $label);
                if (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
                    // New 6-part format: SDU.69.01.01.06.1
                    $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4] . "." . $matches[5];
                } elseif (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
                    // Old 5-part format: SDU.69.01.01.1
                    $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4];
                } elseif (preg_match('/^SDU(\d)/i', $cleanLabel)) {
                    $label = preg_replace('/^SDU(\d)/i', 'SDU.$1', $cleanLabel);
                }
            }
            $customLayout = $input['customLayout'] ?? null;
            if ($customLayout) {
                $showCode = isset($customLayout['showCode']) ? (bool)$customLayout['showCode'] : true;
                $showName = isset($customLayout['showName']) ? (bool)$customLayout['showName'] : false;
                $showBarcode = isset($customLayout['showBarcode']) ? (bool)$customLayout['showBarcode'] : true;
                
                $codeStyle = $customLayout['codeStyle'] ?? '';
                $nameStyle = $customLayout['nameStyle'] ?? '';
                $barcodeStyle = $customLayout['barcodeStyle'] ?? '';
                
                $name = htmlspecialchars($item['name'] ?? $item['subtype_name'] ?? $item['item_type'] ?? 'ครุภัณฑ์');

                $labelsHTML .= '<div class="print-wrapper" style="position: relative !important; display: block !important; padding: 0 !important; width: ' . $singleLabelLength . 'mm !important; height: ' . $tapeHeight . 'mm !important; overflow: hidden !important;">';
                if ($showCode) {
                    $labelsHTML .= '<div class="barcode-text-print wysiwyg-print-el" style="position: absolute !important; line-height: 1.1 !important; white-space: nowrap !important; margin: 0 !important; ' . $codeStyle . '">' . $label . '</div>';
                }
                if ($showName) {
                    $labelsHTML .= '<div class="barcode-text-print wysiwyg-print-el" style="position: absolute !important; line-height: 1.1 !important; white-space: nowrap !important; margin: 0 !important; ' . $nameStyle . '">' . $name . '</div>';
                }
                if ($showBarcode) {
                    $labelsHTML .= '<svg class="print-barcode-svg" data-value="' . $cleanValue . '" data-mode="' . $barcodeDataMode . '" data-url="' . $baseUrl . '" style="position: absolute !important; margin: 0 !important; ' . $barcodeStyle . '"></svg>';
                }
                $labelsHTML .= '</div>';
            } else {
                if ($showBarcode) {
                    $labelsHTML .= <<<HTML
                <div class="print-wrapper">
                    <div class="barcode-text-print custom-style">{$label}</div>
                    <svg class="print-barcode-svg" data-value="{$cleanValue}" data-mode="{$barcodeDataMode}" data-url="{$baseUrl}"></svg>
                </div>
HTML;
                } else {
                    $labelsHTML .= <<<HTML
                <div class="print-wrapper no-barcode">
                    <div class="barcode-text-print text-only custom-style">{$label}</div>
                </div>
HTML;
                }
            }
        }
        $labelsHTML .= '</div>';
    }
}

$borderLeftCSS = $showBarcode ? '0.5px dashed #999' : 'none';
$paddingLeftCSS = $showBarcode ? '8mm' : '4mm';

// สร้าง HTML ไฟล์ที่จะเปิดในเบราว์เซอร์จริง
$htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์ Barcode — Brother PT-2730</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; }
        .screen-ui {
            max-width: 550px; margin: 2rem auto; text-align: center;
            background: #ffffff; border-radius: 16px; padding: 2rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .printer-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: #f8fafc; border: 1px solid #bfdbfe; border-radius: 20px;
            padding: 6px 16px; margin-bottom: 1rem; font-size: 0.85rem;
            color: #2563eb; font-weight: 600;
        }
        .screen-ui .icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .screen-ui h2 { color: #1e293b; margin-bottom: 0.5rem; }
        .screen-ui p { color: #64748b; margin-bottom: 1rem; font-size: 0.9rem; }
        .info { display: flex; justify-content: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .info-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 14px; }
        .info-item .lbl { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; }
        .info-item .val { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .status { padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; }
        .status.ready { background: #dcfce7; color: #16a34a; }
        .status.printing { background: #fef3c7; color: #92400e; }
        .status.done { background: #dbeafe; color: #1e40af; }
        
        .print-area { position: absolute; left: -9999px; visibility: hidden; }

        @page {
            size: {$totalLabelLength}mm {$tapeHeight}mm;
            margin: 0 !important;
        }

        @media print {
            body { margin: 0 !important; padding: 0 !important; background: #ffffff !important;
                   -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .screen-ui { display: none !important; }
            .print-area { display: block !important; position: static !important; 
                          visibility: visible !important; margin: 0 !important; padding: 0 !important; }
            .print-label {
                display: flex !important; flex-direction: row !important;
                width: {$totalLabelLength}mm !important; height: {$tapeHeight}mm !important;
                margin: 0 !important; padding: 0 !important;
                page-break-after: always !important; page-break-inside: avoid !important;
                overflow: hidden !important; background: #ffffff !important;
            }
            .print-label:last-child { page-break-after: auto !important; }
            .print-wrapper {
                width: {$singleLabelLength}mm !important; height: {$tapeHeight}mm !important;
                display: flex !important; flex-direction: row !important;
                align-items: center !important; justify-content: flex-start !important;
                padding: 0 1mm 0 2mm !important; 
                gap: 2mm !important;
                background: #ffffff !important; overflow: hidden !important; flex-shrink: 0 !important;
            }
            .print-wrapper + .print-wrapper { 
                border-left: {$borderLeftCSS} !important; 
                padding-left: {$paddingLeftCSS} !important; 
            }
            .barcode-text-print {
                font-size: 14pt !important; font-weight: bold !important;
                font-family: 'Sarabun', sans-serif !important; line-height: 1 !important;
                white-space: nowrap !important; flex-shrink: 0 !important; color: black !important;
                overflow: visible !important; 
                transform: scale(0.9, 2.3) !important;
                transform-origin: left center !important;
                max-width: 38mm !important;
                margin-right: 0 !important;
                position: relative !important;
                top: -1.5mm !important; /* ยกตัวอักษรขึ้นเพื่อชดเชยช่องว่างสระ/วรรณยุกต์ของฟอนต์ไทย */
            }
            .barcode-text-print.wysiwyg-print-el {
                transform: none !important;
                width: auto !important;
                max-width: none !important;
                top: auto; left: auto;
            }
            .print-barcode-svg {
                height: {$printableHeight}mm !important; 
                width: auto !important; /* รักษาอัตราส่วนสแกนของบาร์โค้ดไม่ให้เพี้ยน! */
                min-width: 28mm !important;
                display: block !important; background: transparent !important;
                image-rendering: crisp-edges !important; shape-rendering: crispEdges !important;
            }
            .print-barcode-svg rect { shape-rendering: crispEdges !important; }
            .print-wrapper.no-barcode {
                justify-content: center !important;
                padding: 0 2mm !important;
            }
            .print-wrapper.no-barcode .barcode-text-print {
                width: auto !important;
                max-width: none !important;
                transform: none !important;
                font-size: 14pt !important;
                font-weight: bold !important;
                text-align: center !important;
            }
            .barcode-text-print.text-only {
                max-width: none !important;
                width: auto !important;
                min-width: auto !important;
                white-space: nowrap !important;
                overflow: visible !important;
                letter-spacing: -0.1px !important;
            }
            .barcode-text-print.custom-style {
                {$textStyleCSS}
            }
        }
    </style>
</head>
<body>
    <div class="screen-ui">
        <div class="printer-badge">Brother PT-2730 — เทป {$tapeHeight}mm</div>
        <div class="icon">🖨️</div>
        <h2>พิมพ์ Barcode</h2>
        <div class="info">
            <div class="info-item"><div class="lbl">Barcode</div><div class="val">{$barcodeCount}</div></div>
            <div class="info-item"><div class="lbl">แผ่นเทป</div><div class="val">{$labelCount}</div></div>
            <div class="info-item"><div class="lbl">ขนาด</div><div class="val">{$totalLabelLength}×{$tapeHeight}mm</div></div>
        </div>
        <div class="status ready" id="status">✅ กำลังเปิดหน้าต่างพิมพ์...</div>
        <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 1rem;">หน้าต่างพิมพ์จะเปิดอัตโนมัติ หากไม่เปิด กด Ctrl+P</p>
        {$layoutInstructionsHTML}
    </div>

    <div class="print-area" id="printArea">
        {$labelsHTML}
    </div>

    <script src="../assets/libs/JsBarcode.all.min.js"></script>
    <script>
        // วาด barcode
        document.querySelectorAll('.print-barcode-svg').forEach(function(svg) {
            try {
                var codeValue = svg.dataset.value;
                var mode = svg.dataset.mode;
                var baseUrl = svg.dataset.url;
                var barcodeContent = codeValue;
                
                if (mode === 'url' && baseUrl) {
                    barcodeContent = baseUrl + 'items.php?scan=' + encodeURIComponent(codeValue);
                }
                
                JsBarcode(svg, barcodeContent, {
                    format: "CODE128", width: 2.2, height: {$spec['barcodeHeight']},
                    displayValue: true, fontSize: 14, margin: 0, background: "transparent", lineColor: "#000000"
                });
                // เอาการยืดหดแนวนอนออก เพื่อคงสัดส่วนที่ถูกต้องและสแกนได้จริง 100%
            } catch(e) { console.error("Barcode error:", e); }
        });

        // สั่งพิมพ์อัตโนมัติ พร้อมวัดขนาด content จริงและปรับ @page size ก่อนพิมพ์
        setTimeout(function() {
            // === วัดขนาดจริงของ label หลัง barcode render เสร็จ ===
            var tapeHeightMm = {$tapeHeight};
            var firstLabel = document.querySelector('.print-label');
            var actualLengthMm = {$totalLabelLength}; // fallback ค่าเดิม

            if (firstLabel) {
                // ใช้ getBoundingClientRect เพราะแม่นยำกว่า scrollWidth สำหรับ SVG
                var rect = firstLabel.getBoundingClientRect();
                var pxPerMm = 96 / 25.4; // screen resolution: 96dpi
                var measuredMm = Math.ceil(rect.width / pxPerMm);

                // ถ้าวัดได้สมเหตุสมผล (ไม่น้อยกว่า 10mm) ให้ใช้ค่าที่วัดได้
                if (measuredMm > 10) {
                    actualLengthMm = measuredMm + 2; // +2mm เผื่อขอบนิดหน่อย ป้องกันขาด
                }
            }

            // เขียน @page size ใหม่ให้ตรงกับขนาดจริง (override ค่า PHP ที่ตั้งไว้เดิม)
            var dynStyle = document.createElement('style');
            dynStyle.setAttribute('id', 'dynamic-page-size');
            dynStyle.innerHTML =
                '@page { size: ' + actualLengthMm + 'mm ' + tapeHeightMm + 'mm; margin: 0 !important; }' +
                '@media print { .print-label { width: ' + actualLengthMm + 'mm !important; } }';
            document.head.appendChild(dynStyle);

            document.getElementById('status').textContent = '🖨️ กำลังพิมพ์...';
            document.getElementById('status').className = 'status printing';
            window.print();
            setTimeout(function() {
                document.getElementById('status').textContent = '✅ เสร็จสิ้น — ปิดหน้านี้ได้เลย';
                document.getElementById('status').className = 'status done';
            }, 1000);
        }, 800); // เพิ่มจาก 500 → 800ms เพื่อให้ barcode SVG render เสร็จก่อนวัดขนาด
    </script>
</body>
</html>
HTML;

// สร้างโฟลเดอร์ temp สำหรับเก็บไฟล์พิมพ์
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// บันทึกไฟล์ HTML
$filename = 'print_' . date('Ymd_His') . '_' . uniqid() . '.html';
$filepath = $tempDir . '/' . $filename;
file_put_contents($filepath, $htmlContent);

// ลบไฟล์เก่า (เก่ากว่า 1 ชั่วโมง)
foreach (glob($tempDir . '/print_*.html') as $oldFile) {
    if (filemtime($oldFile) < time() - 3600 && $oldFile !== $filepath) {
        @unlink($oldFile);
    }
}

// เปิดในเบราว์เซอร์ภายนอก (เน้นเปิดด้วย Google Chrome เป็นหลักเพื่อป้องกันการเด้งเข้า IE)
$fullPath = str_replace('/', '\\', realpath($filepath));

$chromePath = null;
$possibleChromePaths = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    (getenv('LOCALAPPDATA') ?: 'C:\\Users\\' . getenv('USERNAME') . '\\AppData\\Local') . '\\Google\\Chrome\\Application\\chrome.exe'
];
foreach ($possibleChromePaths as $path) {
    if (file_exists($path)) {
        $chromePath = $path;
        break;
    }
}

if ($chromePath) {
    $command = '"' . $chromePath . '" "' . $fullPath . '"';
} else {
    $command = 'start "" "' . $fullPath . '"';
}
pclose(popen($command, 'r'));

echo json_encode([
    'success' => true, 
    'message' => "ส่งข้อมูลไปที่เครื่องพิมพ์แล้ว ({$barcodeCount} barcode, {$labelCount} แผ่น)",
    'url' => 'temp/' . $filename
]);
