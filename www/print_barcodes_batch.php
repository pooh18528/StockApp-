<?php
require_once 'includes/db.php';

// ดึงข้อมูลรายการเบิกทั้งหมดพร้อม barcode
$query = "SELECT r.id as req_id, r.quantity as req_qty, r.requester_name,
                 i.id as item_id, i.name as item_name, i.item_code, i.barcode, i.category
          FROM requisitions r 
          JOIN items i ON r.item_id = i.id 
          WHERE i.barcode IS NOT NULL AND i.barcode != ''
          ORDER BY r.requisition_date DESC";
$items = $pdo->query($query)->fetchAll();

function formatSduCode($code) {
    if (!$code) return '';
    $clean = str_replace('.', '', $code);
    if (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $clean, $matches)) {
        // New 6-part format: SDU.YY.CAT.TYPE.BUDGET.NUM
        return "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4] . "." . $matches[5];
    }
    if (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $clean, $matches)) {
        // Old 5-part format: SDU.YY.CAT.TYPE.NUM
        return "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4];
    }
    if (preg_match('/^SDU(\d)/i', $clean)) {
        return preg_replace('/^SDU(\d)/i', 'SDU.$1', $clean);
    }
    return $code;
}

$tapeWidth = $_GET['tape'] ?? '12';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>พิมพ์ Barcode รวม - รายการเบิกพัสดุ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Check theme from LocalStorage to prevent flashing
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #475569;
            --border: #cbd5e1;
            --hover: #f8fafc;
        }

        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --hover: #334155;
        }
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 2rem 1rem;
            background: var(--bg);
            color: var(--text);
        }

        /* ---- Control Panel ---- */
        .control-panel {
            max-width: 700px;
            margin: 0 auto 1.5rem;
            background: var(--card);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .control-panel h2 {
            margin: 0 0 1rem 0;
            font-size: 1.1rem;
            color: var(--text);
        }

        .control-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .control-row .field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .control-row label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .control-row select,
        .control-row input {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: 'Sarabun', sans-serif;
            background: var(--card);
            color: var(--text);
        }

        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background: #1d4ed8;
        }

        .btn-back {
            background: var(--border);
            color: var(--text);
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Sarabun', sans-serif;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back:hover {
            background: var(--text-muted);
            color: var(--bg);
        }

        /* ---- Select All / Deselect ---- */
        .select-actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .select-actions button {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            color: var(--text);
        }

        .select-actions button:hover {
            background: var(--hover);
        }

        /* ---- Barcode Cards (Screen) ---- */
        .barcode-grid {
            max-width: 700px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .barcode-card {
            background: var(--card);
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 2px solid transparent;
            transition: border-color 0.15s;
            position: relative;
        }

        .barcode-card.selected {
            border-color: #2563eb;
            background: var(--bg-hover);
        }

        .barcode-card .card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .barcode-card .card-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #2563eb;
            cursor: pointer;
        }

        .barcode-card .item-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
        }

        .barcode-card .item-code {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: monospace;
        }

        .barcode-card .barcode-svg-wrapper {
            text-align: center;
            padding: 8px 0;
        }

        .barcode-card svg {
            max-width: 100%;
        }

        /* ---- Tips ---- */
        .tips-card {
            max-width: 700px;
            margin: 1.5rem auto 0;
            background: var(--bg-hover);
            border: 1px solid var(--warning);
            border-radius: 10px;
            padding: 1.25rem;
        }

        .tips-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: #92400e;
        }

        .tips-card ul {
            margin: 0;
            padding-left: 1.2rem;
            line-height: 1.7;
            font-size: 0.82rem;
            color: #78350f;
        }

        .tips-card strong {
            color: #451a03;
        }

        /* ============================================
           Print Styles — เฉพาะ Barcode ที่เลือก
           ============================================ */
        /* Print Styles moved to dynamic JS for better performance and consistency */
    </style>
</head>

<body>

    <!-- Control Panel -->
    <div class="control-panel no-print">
        <h2>🏷️ พิมพ์ Barcode รวม — รายการเบิกพัสดุ</h2>
        <div class="control-row">
            <div class="field">
                <label>ขนาดเทป</label>
                <select id="tapeSize" onchange="regenerateAll()">
                    <option value="12" selected>12 mm</option>
                    <option value="18">18 mm</option>
                    <option value="24">24 mm</option>
                </select>
            </div>
            <div class="field">
                <label>ความกว้างของบาร์โค้ด</label>
                <select id="barWidth" onchange="regenerateAll()">
                    <option value="1">1 (บาง)</option>
                    <option value="1.5">1.5</option>
                    <option value="2">2 (ปกติ)</option>
                    <option value="2.5">2.5</option>
                    <option value="3">3 (หนา)</option>
                    <option value="3.5">3.5</option>
                    <option value="4" selected>4 (ชัดที่สุด)</option>
                </select>
            </div>
            <div class="field">
                <label>แสดงตัวเลข</label>
                <select id="showText" onchange="regenerateAll()">
                    <option value="true" selected>แสดง</option>
                    <option value="false">ไม่แสดง</option>
                </select>
            </div>
            <div class="field">
                <label>โหมดการวาง</label>
                <select id="layoutMode" onchange="regenerateAll()">
                    <option value="1" selected>1 รายการ/แผ่น (ปกติ)</option>
                    <option value="2">2 รายการ/แผ่น (แบบคู่)</option>
                </select>
            </div>
            <div class="field" style="flex-grow: 1;"></div>
            <div class="field" style="flex-grow: 1;"></div>
            <a href="requisitions.php" class="btn-back">← กลับ</a>
            <button type="button" class="btn-print" onclick="handlePrint()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7"></path>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <path d="M6 14h12v8H6z"></path>
                </svg>
                พิมพ์ที่เลือก (PT-2730)
            </button>
        </div>
    </div>

    <!-- Select All / Deselect -->
    <div class="select-actions no-print">
        <button onclick="selectAll()">✅ เลือกทั้งหมด</button>
        <button onclick="deselectAll()">❌ ยกเลิกทั้งหมด</button>
        <span style="margin-left: auto; font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center;">
            เลือกแล้ว <strong id="selectedCount" style="margin: 0 4px; color: #2563eb;">0</strong> รายการ จากทั้งหมด
            <?php echo count($items); ?> รายการ
        </span>
    </div>

    <!-- Barcode Cards for View -->
    <div class="barcode-grid">
        <?php if (empty($items)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #94a3b8;">
                ไม่พบรายการเบิกที่มี Barcode
            </div>
        <?php else: ?>
            <?php foreach ($items as $index => $item): ?>
                <div class="barcode-card selected" data-index="<?php echo $index; ?>">
                    <div class="card-header">
                        <input type="checkbox" checked onchange="toggleCard(this, <?php echo $index; ?>)">
                        <div>
                            <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div class="item-code"><?php echo htmlspecialchars(formatSduCode($item['item_code'])); ?> |
                                <?php echo htmlspecialchars($item['barcode']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="barcode-svg-wrapper">
                        <svg id="barcode-<?php echo $index; ?>"></svg>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Container for Printing Only -->
    <div id="printArea" class="print-only"></div>

    <!-- Tips -->
    <div class="tips-card no-print">
        <h3>💡 คำแนะนำการตั้งค่า Printer Driver (PT-2730)</h3>
        <ul style="margin-bottom: 1rem;">
            <li><strong>Printer:</strong> เลือก Brother PT-2730</li>
            <li><strong>Paper Size:</strong> ตรงกับเทป เช่น <strong>24mm</strong></li>
            <li><strong>Margins:</strong> <strong>None</strong></li>
            <li><strong>Scale:</strong> <strong>100%</strong> — ห้ามเลือก "Fit to page"</li>
            <li><strong>Auto Cut:</strong> เปิดใน Printer Properties</li>
            <li><strong>Headers/Footers:</strong> ปิดทั้งหมด</li>
        </ul>
        
        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 8px; padding: 10px 14px; margin-top: 10px; font-size: 0.82rem; color: #b45309; line-height: 1.5;">
            <strong style="display: block; margin-bottom: 6px; color: #92400e;">💡 วิธีตั้งค่าขนาดกระดาษใน Print Dialog (หากต้องการใช้โหมด 2 รายการ/แผ่น)</strong>
            <span style="display: block; margin-bottom: 4px; font-weight: 600;">หากท่านต้องการให้พิมพ์ 2 อันติดกันบนเทปชิ้นเดียวยาว ๆ จริง ๆ:</span>
            <ol style="margin: 0; padding-left: 1.2rem; display: flex; flex-direction: column; gap: 4px;">
                <li>ตอนที่หน้าต่างพิมพ์ของเบราว์เซอร์ (Chrome / Edge) แสดงขึ้นมา ให้เลือก <strong>More settings (การตั้งค่าเพิ่มเติม)</strong></li>
                <li>ตรวจสอบให้มั่นใจว่า <strong>Scale (อัตราส่วน)</strong> ถูกตั้งค่าเป็น <strong>100%</strong> (ไม่ใช่ Fit to page)</li>
                <li>ตั้งค่าขนาดกระดาษ <strong>(Paper Size)</strong> ในเครื่องพิมพ์ให้รองรับความยาวที่เพิ่มขึ้น (เช่น ตั้งค่าเป็นความยาวแบบ Custom หรือเลือกขนาดที่ตรงกับ 200mm)</li>
            </ol>
        </div>
    </div>

    <script src="assets/libs/JsBarcode.all.min.js"></script>
    <script>
        // ข้อมูล Barcode จาก PHP
        const barcodeData = <?php echo json_encode(array_map(function ($item, $index) {
            return [
                'index' => $index,
                'value' => $item['barcode'] ?: $item['item_code'],
                'label' => formatSduCode($item['item_code'] ?: $item['barcode']),
                'name' => $item['item_name']
            ];
        }, $items, array_keys($items))); ?>;

        function getTapeHeightPx(tapeMm) {
            const pxPerMm = 3.78;
            return Math.round((tapeMm - 2) * pxPerMm);
        }

        function regenerateAll() {
            const tape = parseInt(document.getElementById('tapeSize').value) || 12;
            const showText = document.getElementById('showText').value === 'true';
            const layoutMode = parseInt(document.getElementById('layoutMode').value) || 1;
            const singleLabelLength = 85; 
            const totalLabelLength = singleLabelLength * layoutMode;

            // อัพเดท CSS ทันทีเพื่อให้คุมขนาดได้แม่นยำและโหลด Preview เร็วขึ้น
            let pageStyle = document.getElementById('dynamicPageSize');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'dynamicPageSize';
                document.head.appendChild(pageStyle);
            }
            
            let fontSize = '11pt';
            let svgHeight = '10mm';
            if (tape === 18) {
                fontSize = '14pt';
                svgHeight = '15mm';
            } else if (tape === 24) {
                fontSize = '18pt';
                svgHeight = '20mm';
            }
            
            pageStyle.innerHTML = `
                @media screen {
                    .print-only { position: absolute; left: -9999px; visibility: hidden; }
                }
                @media print { 
                    @page { size: ${totalLabelLength}mm ${tape}mm; margin: 0; }
                    body { margin: 0; padding: 0; background: var(--card) !important; font-family: sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    .no-print, .control-panel, .select-actions, .tips-card, .barcode-grid { display: none !important; }
                    .print-only { display: block !important; margin: 0 !important; padding: 0 !important; visibility: visible !important; position: static !important; }
                    
                    .print-label { 
                        display: flex !important;
                        flex-direction: row !important;
                        width: ${totalLabelLength}mm !important;
                        height: ${tape}mm !important; 
                        margin: 0 !important;
                        padding: 0 !important; 
                        page-break-after: always !important;
                        overflow: hidden;
                        background: var(--card) !important;
                    } 
                    .print-wrapper { 
                        width: ${singleLabelLength}mm !important;
                        height: ${tape}mm !important; 
                        display: flex !important; 
                        flex-direction: row !important; 
                        align-items: center !important; 
                        justify-content: flex-start !important; 
                        padding: 0 4mm 0 5mm !important;
                        gap: 3mm !important; 
                        background: var(--card) !important; 
                        overflow: hidden;
                        flex-shrink: 0 !important;
                        border-right: 1px dashed #ccc !important;
                    }
                    .print-wrapper:last-child { border-right: none !important; }
                    .print-wrapper + .print-wrapper { padding-left: 8mm !important; }
                    
                    .barcode-text-print {
                        font-size: ${fontSize} !important;
                        font-weight: bold !important;
                        font-family: 'Sarabun', sans-serif !important;
                        line-height: 1.2 !important;
                        white-space: nowrap !important;
                        flex-shrink: 0 !important;
                        color: black !important;
                    }
                    svg { height: ${svgHeight} !important; width: auto !important; min-width: 40mm !important; flex-shrink: 0 !important; display: block !important; background: var(--card) !important; }
                }
            `;

            // เตรียมข้อมูลการพิมพ์
            const selectedItems = barcodeData.filter(item => {
                const card = document.querySelector(`.barcode-card[data-index="${item.index}"]`);
                return card.classList.contains('selected');
            });

            // สร้างโครงสร้างสำหรับพิมพ์
            const printArea = document.getElementById('printArea');
            printArea.innerHTML = '';

            for (let i = 0; i < selectedItems.length; i += layoutMode) {
                const labelDiv = document.createElement('div');
                labelDiv.className = 'print-label';
                
                for (let j = 0; j < layoutMode; j++) {
                    const item = selectedItems[i + j];
                    if (item) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'print-wrapper';
                        
                        const textEl = document.createElement('div');
                        textEl.className = 'barcode-text-print';
                        textEl.textContent = item.label || item.value;
                        
                        // สร้าง SVG ด้วย Namespace ที่ถูกต้องเพื่อให้เบราว์เซอร์รู้จักว่าเป็นรูปวาด
                        const svgEl = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                        svgEl.setAttribute("class", "print-barcode-svg");
                        svgEl.dataset.value = item.value; // เก็บค่าไว้สำหรับวาดทีหลัง
                        
                        wrapper.appendChild(textEl);
                        wrapper.appendChild(svgEl);
                        labelDiv.appendChild(wrapper);
                    }
                }
                printArea.appendChild(labelDiv);
            }

            // วาดบาร์โค้ดหลังจากที่ Element ถูกใส่เข้าไปใน DOM แล้ว (สำคัญมาก)
            printArea.querySelectorAll('.print-barcode-svg').forEach(svg => {
                try {
                    JsBarcode(svg, svg.dataset.value, {
                        format: "CODE128",
                        width: 3.2, 
                        height: 150, 
                        displayValue: true,
                        fontSize: 18,
                        margin: 0
                    });
                } catch (e) { console.error("Print barcode error:", e); }
            });

            // แสดงบาร์โค้ดในหน้าจอหลัก (Preview บนเว็บ)
            barcodeData.forEach(item => {
                try {
                    JsBarcode("#barcode-" + item.index, item.value, {
                        format: "CODE128",
                        width: 2.5,
                        height: 100,
                        displayValue: true,
                        fontSize: 14,
                        margin: 0
                    });
                } catch (e) {}
            });
        }

        function toggleCard(checkbox, index) {
            const card = document.querySelector(`.barcode-card[data-index="${index}"]`);
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            updateCount();
            regenerateAll();
        }

        function selectAll() {
            document.querySelectorAll('.barcode-card').forEach(card => {
                card.classList.add('selected');
                const cb = card.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = true;
            });
            updateCount();
            regenerateAll();
        }

        function deselectAll() {
            document.querySelectorAll('.barcode-card').forEach(card => {
                card.classList.remove('selected');
                const cb = card.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = false;
            });
            updateCount();
            regenerateAll();
        }

        function updateCount() {
            const count = document.querySelectorAll('.barcode-card.selected').length;
            document.getElementById('selectedCount').textContent = count;
        }

        function handlePrint() {
            // === วิธีแก้ขั้นสุดท้าย: เปิดในเบราว์เซอร์จริง (Chrome/Edge) ===
            // PHP Desktop (CEF) มีบั๊กที่ window.print() ใช้ไม่ได้
            // วิธีแก้: ส่งข้อมูลไป PHP → PHP สร้างไฟล์ HTML → เปิดในเบราว์เซอร์จริง
            // เบราว์เซอร์จริง window.print() ทำงานได้ 100%

            const selectedItems = barcodeData.filter(item => {
                const card = document.querySelector(`.barcode-card[data-index="${item.index}"]`);
                return card && card.classList.contains('selected');
            });

            if (selectedItems.length === 0) {
                alert('กรุณาเลือกรายการที่ต้องการพิมพ์ก่อน');
                return;
            }

            const layoutMode = parseInt(document.getElementById('layoutMode').value) || 1;
            const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;

            // แสดงสถานะกำลังเตรียม
            const btn = document.querySelector('.btn-print');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ กำลังเตรียมพิมพ์...';
            btn.disabled = true;

            // ส่ง AJAX ไป api_print.php
            fetch('api_print.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    barcodes: selectedItems,
                    layoutMode: layoutMode,
                    tapeWidth: tapeWidth
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '✅ เปิดในเบราว์เซอร์แล้ว!';
                    btn.style.backgroundColor = '#16a34a';
                    // คืนปุ่มกลับหลัง 2 วินาที
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.backgroundColor = '';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (data.error || 'ไม่ทราบสาเหตุ'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error('Print API error:', err);
                alert('ไม่สามารถเชื่อมต่อ API ได้: ' + err.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // Init
        regenerateAll();
        updateCount();
    </script>
</body>

</html>