<?php
/**
 * print_output.php — หน้าพิมพ์ Barcode สำหรับ Brother PT-2730
 * 
 * ✅ Optimized สำหรับ Brother PT-2730 Label Printer:
 *    - ความละเอียด: 180 dpi
 *    - เทปที่รองรับ: 3.5mm, 6mm, 9mm, 12mm, 18mm, 24mm
 *    - ความสูงพิมพ์สูงสุด: 18mm (0.71")
 *    - ความยาวลาเบล: 30mm – 300mm
 *    - ใช้ TZe tape cassettes
 * 
 * วิธีการ: รับข้อมูล barcode ผ่าน POST แล้วสั่ง window.print() อัตโนมัติ
 * เนื่องจาก PHP Desktop (CEF) จำกัดให้ window.print() ทำงานได้แค่ 1 ครั้งต่อ page load
 * การนำทางมาหน้าใหม่ทุกครั้งจะทำให้ได้ fresh page = พิมพ์ได้ทุกครั้ง
 */

// รับข้อมูลจาก POST
$barcodes = json_decode($_POST['barcodes'] ?? '[]', true);
$layoutMode = intval($_POST['layoutMode'] ?? 1);
$tapeWidth = intval($_POST['tapeWidth'] ?? 12); // ขนาดเทป mm

// === PT-2730 Tape Specifications ===
// แต่ละขนาดเทปมีพื้นที่พิมพ์ได้จริง (ลบขอบเทปออก)
$tapeSpecs = [
    '6'  => ['height' => 6,  'printable' => 3.5, 'fontSize' => '6pt',  'barcodeHeight' => 40,  'textSize' => '5pt'],
    '9'  => ['height' => 9,  'printable' => 6.5, 'fontSize' => '7pt',  'barcodeHeight' => 70,  'textSize' => '6pt'],
    '12' => ['height' => 12, 'printable' => 9.0, 'fontSize' => '8pt',  'barcodeHeight' => 100, 'textSize' => '7pt'],
    '18' => ['height' => 18, 'printable' => 14,  'fontSize' => '10pt', 'barcodeHeight' => 140, 'textSize' => '9pt'],
    '24' => ['height' => 24, 'printable' => 18,  'fontSize' => '11pt', 'barcodeHeight' => 180, 'textSize' => '10pt'],
];

$spec = $tapeSpecs[$tapeWidth] ?? $tapeSpecs['12'];
$tapeHeight = $spec['height'];
$printableHeight = $spec['printable'];

// ความยาวลาเบลเดี่ยว (mm) — ปรับตามขนาดเทป
$singleLabelLength = 85;
$totalLabelLength = $singleLabelLength * $layoutMode;

// จำกัดความยาวสูงสุดตาม PT-2730 spec (300mm)
if ($totalLabelLength > 300) {
    $totalLabelLength = 300;
    $singleLabelLength = intval(300 / $layoutMode);
}

if (empty($barcodes)) {
    echo '<script>alert("ไม่มีข้อมูล Barcode"); window.location.href = "print_barcodes_batch.php";</script>';
    exit;
}

$barcodeCount = count($barcodes);
$labelCount = ceil($barcodeCount / $layoutMode);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์ Barcode — Brother PT-2730</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; }
        
        /* ======================================
           หน้าจอ: UI แสดงสถานะ + ตั้งค่า
           ====================================== */
        .screen-ui {
            max-width: 600px;
            margin: 2rem auto;
            text-align: center;
            background: var(--card);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .printer-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-hover);
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            padding: 6px 16px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
        }
        .printer-badge .dot {
            width: 8px; height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .screen-ui .icon { font-size: 3rem; margin-bottom: 0.75rem; }
        .screen-ui h2 { color: #1e293b; margin-bottom: 0.5rem; font-size: 1.3rem; }
        .screen-ui p { color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem; line-height: 1.6; }
        
        /* ---- ข้อมูลสรุป ---- */
        .print-summary {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 1rem 0 1.5rem;
            flex-wrap: wrap;
        }
        .summary-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 18px;
            text-align: center;
        }
        .summary-item .label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-item .value { font-size: 1.2rem; font-weight: 700; color: #1e293b; }
        
        /* ---- ปุ่ม ---- */
        .btn-group { display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 24px; border-radius: 8px; font-size: 0.95rem;
            font-family: 'Sarabun', sans-serif; font-weight: 600;
            cursor: pointer; border: none; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-print {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }
        .btn-print:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); transform: translateY(-1px); }
        .btn-back { background: #e2e8f0; color: #334155; }
        .btn-back:hover { background: #cbd5e1; }
        .btn-retry { background: #f59e0b; color: white; }
        .btn-retry:hover { background: #d97706; }

        /* ---- คำแนะนำ PT-2730 ---- */
        .driver-tips {
            max-width: 600px;
            margin: 1rem auto 0;
            background: var(--bg-hover);
            border: 1px solid var(--warning);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: left;
        }
        .driver-tips h3 { font-size: 0.9rem; color: #92400e; margin-bottom: 0.5rem; }
        .driver-tips table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .driver-tips td { padding: 4px 8px; color: #78350f; border-bottom: 1px solid #fef3c7; }
        .driver-tips td:first-child { font-weight: 600; white-space: nowrap; width: 40%; color: #451a03; }
        .driver-tips .highlight { background: #fef3c7; font-weight: 700; color: #b45309; padding: 1px 6px; border-radius: 3px; }

        /* ======================================
           Print Area (ซ่อนบนหน้าจอ)
           ====================================== */
        .print-area { 
            position: absolute; 
            left: -9999px; 
            visibility: hidden; 
        }

        /* ======================================
           Print Styles — Optimized for PT-2730
           180 dpi thermal transfer
           ====================================== */
        @media print {
            /* ซ่อน UI ทั้งหมด */
            body { 
                margin: 0 !important; 
                padding: 0 !important; 
                background: var(--card) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .screen-ui, .driver-tips { display: none !important; }
            
            /* แสดง Print Area */
            .print-area { 
                display: block !important; 
                position: static !important; 
                visibility: visible !important;
                margin: 0 !important; 
                padding: 0 !important; 
            }

            /* === @page: ขนาดลาเบลตรงกับเทป PT-2730 === */
            @page { 
                size: <?php echo $totalLabelLength; ?>mm <?php echo $tapeHeight; ?>mm; 
                margin: 0 !important; 
            }

            /* === แต่ละแถว label === */
            .print-label {
                display: flex !important;
                flex-direction: row !important;
                width: <?php echo $totalLabelLength; ?>mm !important;
                height: <?php echo $tapeHeight; ?>mm !important;
                margin: 0 !important;
                padding: 0 !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
                background: var(--card) !important;
            }
            /* ไม่ต้อง page-break สำหรับอันสุดท้าย */
            .print-label:last-child {
                page-break-after: auto !important;
            }

            /* === แต่ละ barcode wrapper === */
            .print-wrapper {
                width: <?php echo $singleLabelLength; ?>mm !important;
                height: <?php echo $tapeHeight; ?>mm !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: flex-start !important;
                /* PT-2730: เว้นขอบซ้าย 3mm สำหรับ auto-cut margin */
                padding: 0 2mm 0 3mm !important;
                gap: 2mm !important;
                background: var(--card) !important;
                overflow: hidden !important;
                flex-shrink: 0 !important;
            }
            /* เส้นแบ่งสำหรับโหมดคู่ */
            .print-wrapper + .print-wrapper {
                border-left: 0.5px dashed #999 !important;
            }

            /* === ข้อความ barcode value === */
            .barcode-text-print {
                font-size: <?php echo $spec['textSize']; ?> !important;
                font-weight: bold !important;
                font-family: 'Sarabun', 'Arial', sans-serif !important;
                line-height: 1 !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                color: black !important;
                /* ป้องกันข้อความล้น */
                max-width: 25mm !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* === Barcode SVG — optimize for 180 dpi === */
            .print-barcode-svg {
                height: <?php echo $printableHeight; ?>mm !important;
                width: auto !important;
                min-width: 35mm !important;
                max-width: 55mm !important;
                flex-shrink: 1 !important;
                flex-grow: 0 !important;
                display: block !important;
                background: var(--card) !important;
                /* คมชัดสำหรับ thermal printer */
                image-rendering: crisp-edges !important;
                shape-rendering: crispEdges !important;
            }
            /* Barcode bars ต้องคมชัด ไม่มี anti-aliasing */
            .print-barcode-svg rect {
                shape-rendering: crispEdges !important;
            }
        }
    </style>
</head>
<body>
    <!-- ======================================
         หน้าจอ: แสดงสถานะและปุ่มควบคุม
         ====================================== -->
    <div class="screen-ui" id="screenUI">
        <div class="printer-badge">
            <span class="dot"></span>
            Brother PT-2730 — เทป <?php echo $tapeHeight; ?>mm
        </div>
        <div class="icon">🖨️</div>
        <h2>พร้อมพิมพ์ Barcode แล้ว!</h2>
        <p>Barcode ถูกสร้างเรียบร้อยแล้ว กดปุ่มด้านล่างเพื่อเริ่มพิมพ์</p>
        
        <div class="print-summary">
            <div class="summary-item">
                <div class="label">จำนวน Barcode</div>
                <div class="value"><?php echo $barcodeCount; ?></div>
            </div>
            <div class="summary-item">
                <div class="label">จำนวนแผ่นเทป</div>
                <div class="value"><?php echo $labelCount; ?></div>
            </div>
            <div class="summary-item">
                <div class="label">โหมด</div>
                <div class="value"><?php echo $layoutMode == 2 ? 'คู่' : 'เดี่ยว'; ?></div>
            </div>
            <div class="summary-item">
                <div class="label">ขนาด</div>
                <div class="value"><?php echo $totalLabelLength; ?>×<?php echo $tapeHeight; ?>mm</div>
            </div>
        </div>

        <div class="btn-group">
            <!-- ปุ่มนี้ต้องให้ผู้ใช้กดเอง เพราะ CEF บล็อก window.print() ที่ไม่ได้มาจาก user gesture -->
            <button class="btn btn-print" onclick="doPrint()" style="font-size: 1.1rem; padding: 14px 36px;">🖨️ กดเพื่อพิมพ์</button>
            <a href="print_barcodes_batch.php" class="btn btn-back">← กลับไปเลือก</a>
        </div>
    </div>

    <!-- คำแนะนำตั้งค่า Printer Driver -->
    <div class="driver-tips" id="driverTips" style="display: none;">
        <h3>⚙️ ตั้งค่า Printer Driver สำหรับ Brother PT-2730</h3>
        <table>
            <tr><td>Printer</td><td>Brother PT-2730</td></tr>
            <tr><td>Tape Width</td><td><span class="highlight"><?php echo $tapeHeight; ?> mm</span></td></tr>
            <tr><td>Label Length</td><td><span class="highlight"><?php echo $totalLabelLength; ?> mm</span></td></tr>
            <tr><td>Margins</td><td><span class="highlight">None (0mm)</span></td></tr>
            <tr><td>Scale</td><td><span class="highlight">100%</span> — ห้าม Fit to page</td></tr>
            <tr><td>Auto Cut</td><td>เปิด (ใน Printer Properties)</td></tr>
            <tr><td>Half Cut</td><td>เปิด (ถ้ามี — ประหยัดเทป)</td></tr>
            <tr><td>Headers/Footers</td><td>ปิดทั้งหมด</td></tr>
            <tr><td>Print Quality</td><td>Standard หรือ High</td></tr>
        </table>

        <?php if ($layoutMode == 2): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 10px 14px; margin-top: 10px; font-size: 0.8rem; color: #b45309; line-height: 1.5;">
            <strong style="display: block; margin-bottom: 6px; color: #92400e;">💡 วิธีตั้งค่าขนาดกระดาษใน Print Dialog (หากต้องการใช้โหมด 2 รายการ/แผ่น)</strong>
            <span style="display: block; margin-bottom: 4px; font-weight: 600;">หากท่านต้องการให้พิมพ์ 2 อันติดกันบนเทปชิ้นเดียวยาว ๆ จริง ๆ:</span>
            <ol style="margin: 0; padding-left: 1.2rem; display: flex; flex-direction: column; gap: 4px;">
                <li>ตอนที่หน้าต่างพิมพ์ของเบราว์เซอร์ (Chrome / Edge) แสดงขึ้นมา ให้เลือก <strong>More settings (การตั้งค่าเพิ่มเติม)</strong></li>
                <li>ตรวจสอบให้มั่นใจว่า <strong>Scale (อัตราส่วน)</strong> ถูกตั้งค่าเป็น <strong>100%</strong> (ไม่ใช่ Fit to page)</li>
                <li>ตั้งค่าขนาดกระดาษ <strong>(Paper Size)</strong> ในเครื่องพิมพ์ให้รองรับความยาวที่เพิ่มขึ้น (เช่น ตั้งค่าเป็นความยาวแบบ Custom หรือเลือกขนาดที่ตรงกับ 200mm)</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>

    <!-- ======================================
         Print Area — Barcode Labels
         ====================================== -->
    <div class="print-area" id="printArea">
        <?php
        $chunks = array_chunk($barcodes, $layoutMode);
        foreach ($chunks as $chunk): ?>
            <div class="print-label">
                <?php foreach ($chunk as $item): ?>
                    <div class="print-wrapper">
                        <div class="barcode-text-print"><?php echo htmlspecialchars($item['value']); ?></div>
                        <svg class="print-barcode-svg" data-value="<?php echo htmlspecialchars($item['value']); ?>"></svg>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="assets/libs/JsBarcode.all.min.js"></script>
    <script>
        // === PT-2730 Barcode Settings ===
        // ปรับค่า barcode ให้เหมาะกับ 180 dpi thermal printer
        var PT2730_SETTINGS = {
            format: "CODE128",
            width: 3,           // ความกว้างของแท่ง bar (pixel) — ปรับให้ 180 dpi อ่านได้
            height: <?php echo $spec['barcodeHeight']; ?>,  // ความสูง barcode (pixel)
            displayValue: true,
            fontSize: 14,
            margin: 0,
            background: "#ffffff",
            lineColor: "#000000"
        };

        // วาด barcode ทั้งหมดด้วย settings ที่ optimize สำหรับ PT-2730
        document.querySelectorAll('.print-barcode-svg').forEach(function(svg) {
            try {
                JsBarcode(svg, svg.dataset.value, PT2730_SETTINGS);
            } catch(e) { 
                console.error("Barcode error:", e); 
            }
        });

        // ฟังก์ชันพิมพ์ซ้ำ (navigate ไปหน้าใหม่ = fresh page = window.print() ทำงานได้)
        function retryPrint() {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'print_output.php';
            
            var inputBarcodes = document.createElement('input');
            inputBarcodes.type = 'hidden';
            inputBarcodes.name = 'barcodes';
            inputBarcodes.value = <?php echo json_encode(json_encode($barcodes)); ?>;
            form.appendChild(inputBarcodes);
            
            var inputLayout = document.createElement('input');
            inputLayout.type = 'hidden';
            inputLayout.name = 'layoutMode';
            inputLayout.value = '<?php echo $layoutMode; ?>';
            form.appendChild(inputLayout);

            var inputTape = document.createElement('input');
            inputTape.type = 'hidden';
            inputTape.name = 'tapeWidth';
            inputTape.value = '<?php echo $tapeWidth; ?>';
            form.appendChild(inputTape);
            
            document.body.appendChild(form);
            form.submit();
        }

        // === ฟังก์ชันพิมพ์ — ต้องเรียกจาก user click เท่านั้น ===
        // สำคัญ: CEF ใน PHP Desktop บล็อก window.print() ที่เรียกจาก setTimeout
        // ดังนั้นต้องให้ผู้ใช้กดปุ่มเอง (user gesture) จึงจะทำงานได้
        function doPrint() {
            window.print();
            // หลังพิมพ์เสร็จ เปลี่ยนปุ่มเป็น "พิมพ์อีกครั้ง" (navigate ไป fresh page)
            // เพราะ CEF จำกัดให้ window.print() ใช้ได้แค่ 1 ครั้งต่อ page load
            setTimeout(function() {
                var printBtn = document.querySelector('.btn-print');
                if (printBtn) {
                    printBtn.textContent = '🔄 พิมพ์อีกครั้ง (โหลดหน้าใหม่)';
                    printBtn.onclick = retryPrint;
                    printBtn.style.background = '#f59e0b';
                }
                document.getElementById('driverTips').style.display = 'block';
            }, 500);
        }
    </script>
</body>
</html>
