<?php
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid request");
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    die("Item not found");
}

$barcodeValue = $item['barcode'] ?: $item['item_code'];
$tapeWidth = $_GET['tape'] ?? '12'; 

include 'includes/header.php';
?>

<style>
    /* ============================================
       Screen Styles (WYSIWYG Single Designer)
       ============================================ */
    .print-page-container {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 1.5rem;
        max-width: 1100px;
        margin: 0 auto;
        padding-bottom: 3rem;
    }

    h2 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .settings-panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        height: fit-content;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .panel-section-title {
        background: #e2e8f0;
        padding: 8px 12px;
        font-size: 0.8rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .panel-content {
        padding: 12px;
        border-bottom: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .panel-content:last-child {
        border-bottom: none;
    }

    .settings-panel label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        display: block;
        margin-bottom: 4px;
    }

    .settings-panel select,
    .settings-panel input[type="number"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
        font-family: 'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif;
        background: var(--bg-subtle);
        color: var(--text);
    }

    /* Style button group */
    .ac-btn-style {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px;
        font-weight: 600;
        cursor: pointer;
        color: #475569;
        transition: all 0.2s;
    }
    .ac-btn-style:hover {
        background: #e2e8f0;
    }
    .ac-btn-style.active {
        background: var(--primary) !important;
        color: white !important;
        border-color: var(--primary) !important;
    }

    .preview-container-box {
        background: #94a3b8;
        border-radius: 12px;
        padding: 2.5rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 180px;
        overflow-x: auto;
        box-shadow: inset 0 3px 10px rgba(0,0,0,0.15);
        margin-bottom: 1.5rem;
    }

    .preview-container {
        background: white;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        position: relative;
        overflow: hidden;
        border: 1px dashed #cbd5e1;
        transition: width 0.2s, height 0.2s;
    }

    .wysiwyg-element {
        position: absolute;
        color: black;
        white-space: nowrap;
        user-select: none;
        cursor: move;
    }

    .btn-group {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        justify-content: flex-end;
    }

    .btn-print {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-family: 'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
    }

    .btn-print:hover {
        background: var(--primary-hover);
    }

    .btn-back {
        background: #e2e8f0;
        color: #334155;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-family: 'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s;
    }

    .tips-card {
        background: var(--bg-subtle);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 1.25rem;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .item-info {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 1rem 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 1rem;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .item-info .item-name {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text);
        margin-bottom: 4px;
    }

    .item-info code {
        background: var(--bg-hover);
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        color: var(--text);
    }

    @media print {
        @page { margin: 0; }
        body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; }
        .sidebar, .nav-links, .logo, .toggle-btn, .no-print, .settings-panel, .btn-group, .tips-card, .item-info, .preview-label, h2, .ac-btn-style, .btn-back, span, #pPreviewContainer { 
            display: none !important; 
        }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .print-page-container { display: block !important; padding: 0 !important; }
        .preview-container-box { 
            display: block !important; 
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
        .preview-container { 
            display: block !important; 
            border: none !important;
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
        #wysiwyg_code {
            color: black !important;
        }
        #wysiwyg_name {
            color: black !important;
        }
        #barcode {
            color: black !important;
            image-rendering: crisp-edges !important;
            shape-rendering: crispEdges !important;
        }
    }
</style>

<div class="print-page-container">
    <!-- Sidebar Controls -->
    <div class="settings-panel no-print">
        <!-- Font Selection -->
        <div class="panel-section-title">แบบอักษร (Font Family)</div>
        <div class="panel-content">
            <select id="printFont" onchange="updatePrintPreview()">
                <option value="'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif">Sarabun (Standard)</option>
                <option value="'Arial', sans-serif">Arial (Sleek)</option>
                <option value="'Courier New', monospace">Courier New (Mono)</option>
            </select>
        </div>

        <!-- Code Settings -->
        <div class="panel-section-title">รหัสครุภัณฑ์ (Code Settings)</div>
        <div class="panel-content">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">ขนาดอักษร</span>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="number" id="printCodeFontSize" value="14" min="6" max="72" oninput="updatePrintPreview()" style="width: 55px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; text-align: center;">
                    <span style="font-size: 0.7rem; color: #94a3b8;">pt</span>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                <button type="button" class="ac-btn-style active" id="printCodeBold" onclick="toggleElementStyle('printCodeBold')">B</button>
                <button type="button" class="ac-btn-style" id="printCodeItalic" onclick="toggleElementStyle('printCodeItalic')">I</button>
                <button type="button" class="ac-btn-style" id="printCodeUnderline" onclick="toggleElementStyle('printCodeUnderline')">U</button>
            </div>
        </div>

        <!-- Name Settings -->
        <div class="panel-section-title">ชื่อครุภัณฑ์ (Name Settings)</div>
        <div class="panel-content">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">แสดงชื่อครุภัณฑ์</span>
                <input type="checkbox" id="printShowName" onchange="resetWYSIWYGPositions()" style="width: 18px; height: 18px; cursor: pointer;">
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">ขนาดอักษร</span>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="number" id="printNameFontSize" value="9" min="6" max="72" oninput="updatePrintPreview()" style="width: 55px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 4px; text-align: center;">
                    <span style="font-size: 0.7rem; color: #94a3b8;">pt</span>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">
                <button type="button" class="ac-btn-style" id="printNameBold" onclick="toggleElementStyle('printNameBold')">B</button>
                <button type="button" class="ac-btn-style" id="printNameItalic" onclick="toggleElementStyle('printNameItalic')">I</button>
                <button type="button" class="ac-btn-style" id="printNameUnderline" onclick="toggleElementStyle('printNameUnderline')">U</button>
            </div>
        </div>

        <!-- Barcode Settings -->
        <div class="panel-section-title">แท่งบาร์โค้ด (Barcode)</div>
        <div class="panel-content">
            <div>
                <label>แสดงแท่งบาร์โค้ด</label>
                <select id="printShowBarcode" onchange="resetWYSIWYGPositions()">
                    <option value="false">ไม่แสดง (แสดงเฉพาะข้อความ)</option>
                    <option value="true" selected>แสดงบาร์โค้ด (มีแท่งสแกน)</option>
                </select>
            </div>
            <div>
                <label>ข้อมูลในบาร์โค้ด</label>
                <select id="printBarcodeData" onchange="regenerateBarcode()">
                    <option value="code" selected>รหัสครุภัณฑ์ (สแกนง่าย)</option>
                    <option value="url">ฝังลิงก์ URL (สแกนด้วยมือถือ)</option>
                </select>
            </div>
        </div>

        <!-- Tape Width selection -->
        <div class="panel-section-title">ขนาดเทป</div>
        <div class="panel-content">
            <select id="tapeSize" onchange="resetWYSIWYGPositions()">
                <option value="12" selected>12 mm</option>
                <option value="18">18 mm</option>
                <option value="24">24 mm</option>
            </select>
        </div>
    </div>

    <!-- Preview & Action Area -->
    <div style="display: flex; flex-direction: column;">
        <div class="item-info no-print">
            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
            <div>รหัสครุภัณฑ์: <code><?php echo htmlspecialchars($item['item_code']); ?></code></div>
            <?php if ($item['barcode']): ?>
                <div>Barcode: <code><?php echo htmlspecialchars($item['barcode']); ?></code></div>
            <?php endif; ?>
        </div>

        <h2 class="no-print">
            <i data-lucide="printer" style="width: 22px; height: 22px; color: var(--primary);"></i> ออกแบบรูปแบบป้ายเฉพาะชิ้นนี้ (WYSIWYG Mode)
        </h2>

        <!-- Interactive WYSIWYG Box -->
        <div class="preview-container-box">
            <div class="preview-container" id="previewContainer">
                <div id="wysiwyg_code" class="wysiwyg-element" style="font-family: 'Sarabun', sans-serif; font-size: 14pt; font-weight: bold;">
                    <?php 
                        $dl = $item['item_code'] ?: $item['barcode'];
                        if (preg_match("/^SDU(\d)/", $dl)) {
                            $dl = preg_replace("/^SDU(\d)/", "SDU.$1", $dl);
                        }
                        echo htmlspecialchars($dl);
                    ?>
                </div>
                <div id="wysiwyg_name" class="wysiwyg-element" style="font-family: 'Sarabun', sans-serif; font-size: 9pt; display: none;">
                    <?php echo htmlspecialchars($item['name']); ?>
                </div>
                <!-- Custom SVG barcode -->
                <svg id="barcode" class="wysiwyg-element" style="width: 160px; height: 40px;"></svg>
            </div>
        </div>

        <span class="no-print" style="font-size: 0.75rem; color: #64748b; background: #f1f5f9; padding: 8px 12px; border-radius: 6px; text-align: center; font-weight: 500; margin-bottom: 1.5rem; border: 1px solid var(--border);">
            💡 แนะนำ: คุณสามารถเอาเมาส์คลิกเพื่อขยับ "รหัส" "ชื่อ" หรือ "บาร์โค้ด" สลับตำแหน่งกันได้อิสระบนเทปจำลอง!
        </span>

        <div class="btn-group no-print">
            <a href="items.php" class="btn-back">← กลับหน้าพัสดุ</a>
            <button class="btn-print" onclick="window.print()">
                <i data-lucide="printer" style="width: 20px; height: 20px;"></i>
                พิมพ์ Barcode (PT-2730)
            </button>
        </div>

        <div class="tips-card no-print">
            <h3 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: #1e3a8a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="info" style="width: 18px; height: 18px; color: var(--primary);"></i> คำแนะนำการพิมพ์ (Brother PT-2730 / PT-P900)
            </h3>
            <ul style="margin: 0; padding-left: 1.2rem; line-height: 1.7; font-size: 0.85rem;">
                <li><strong>Paper Size:</strong> เลือกขนาดให้ตรงกับเทปที่ท่านใส่ในเครื่อง เช่น 12mm, 18mm, 24mm</li>
                <li><strong>Margins:</strong> เลือกเป็น <strong>None (ไม่มีระยะขอบ)</strong> เพื่อป้องกันตำแหน่งเบี้ยว</li>
                <li><strong>Scale:</strong> เลือกเป็น <strong>100%</strong></li>
                <li><strong>Headers/Footers (หัวกระดาษและท้ายกระดาษ):</strong> ปิดใช้งาน</li>
            </ul>
        </div>
    </div>
</div>

<script src="assets/libs/JsBarcode.all.min.js"></script>
<script>
    let wysiwygInitialized = false;

    function toggleElementStyle(btnId) {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.classList.toggle('active');
            updatePrintPreview();
        }
    }

    function makeElementDraggable(el, container) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        el.addEventListener('mousedown', dragMouseDown);

        function dragMouseDown(e) {
            e = e || window.event;
            // Prevent dragging wrapper instead of barcode SVG in chrome
            if (e.target.tagName === 'rect' || e.target.tagName === 'g' || e.target.tagName === 'path') {
                // drag parent svg instead
                return;
            }
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.addEventListener('mouseup', closeDragElement);
            document.addEventListener('mousemove', elementDrag);
        }

        function elementDrag(e) {
            e = e || window.event;
            e.preventDefault();
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            
            let newTop = el.offsetTop - pos2;
            let newLeft = el.offsetLeft - pos1;
            
            const maxTop = container.clientHeight - el.clientHeight;
            const maxLeft = container.clientWidth - el.clientWidth;
            
            if (newTop < 0) newTop = 0;
            if (newTop > maxTop) newTop = maxTop;
            if (newLeft < 0) newLeft = 0;
            if (newLeft > maxLeft) newLeft = maxLeft;
            
            el.style.top = newTop + "px";
            el.style.left = newLeft + "px";
            
            savePrintStyles();
        }

        function closeDragElement() {
            document.removeEventListener('mouseup', closeDragElement);
            document.removeEventListener('mousemove', elementDrag);
        }
    }

    function initWYSIWYGDragging() {
        if (wysiwygInitialized) return;
        const container = document.getElementById('previewContainer');
        if (container) {
            makeElementDraggable(document.getElementById('wysiwyg_code'), container);
            makeElementDraggable(document.getElementById('wysiwyg_name'), container);
            makeElementDraggable(document.getElementById('barcode'), container);
            wysiwygInitialized = true;
        }
    }

    function resetWYSIWYGPositions() {
        const showBarcode = document.getElementById('printShowBarcode').value === 'true';
        const showName = document.getElementById('printShowName').checked;
        const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;
        
        let labelLength = 85; // 85mm standard single label length
        
        const w = labelLength * 5;
        const h = tapeWidth * 5;
        
        const codeEl = document.getElementById('wysiwyg_code');
        const nameEl = document.getElementById('wysiwyg_name');
        const barcodeEl = document.getElementById('barcode');
        
        if (showBarcode && showName) {
            codeEl.style.top = "4px";
            codeEl.style.left = "10px";
            
            nameEl.style.top = (h - 22) + "px";
            nameEl.style.left = "10px";
            
            barcodeEl.style.top = "6px";
            barcodeEl.style.left = (w - 180) + "px";
            barcodeEl.style.width = "160px";
        } else if (showBarcode && !showName) {
            codeEl.style.top = ((h - 24) / 2) + "px";
            codeEl.style.left = "15px";
            
            barcodeEl.style.top = "6px";
            barcodeEl.style.left = (w - 180) + "px";
            barcodeEl.style.width = "160px";
        } else if (!showBarcode && showName) {
            codeEl.style.top = "4px";
            codeEl.style.left = "20px";
            
            nameEl.style.top = (h - 22) + "px";
            nameEl.style.left = "20px";
        } else {
            codeEl.style.top = ((h - 24) / 2) + "px";
            codeEl.style.left = "20px";
        }
        
        updatePrintPreview();
    }

    function updatePrintPreview() {
        const previewContainer = document.getElementById('previewContainer');
        if (!previewContainer) return;
        
        const showBarcode = document.getElementById('printShowBarcode').value === 'true';
        const showName = document.getElementById('printShowName').checked;
        const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;
        
        let labelLength = 85; 
        
        const previewWidth = labelLength * 5;
        const previewHeight = tapeWidth * 5;
        
        previewContainer.style.width = previewWidth + 'px';
        previewContainer.style.height = previewHeight + 'px';
        
        const codeEl = document.getElementById('wysiwyg_code');
        const nameEl = document.getElementById('wysiwyg_name');
        const barcodeEl = document.getElementById('barcode');
        
        nameEl.style.display = showName ? 'block' : 'none';
        barcodeEl.style.display = showBarcode ? 'block' : 'none';
        
        // Font adjustments
        const font = document.getElementById('printFont').value;
        
        codeEl.style.fontFamily = font;
        codeEl.style.fontSize = document.getElementById('printCodeFontSize').value + 'pt';
        codeEl.style.fontWeight = document.getElementById('printCodeBold').classList.contains('active') ? 'bold' : 'normal';
        codeEl.style.fontStyle = document.getElementById('printCodeItalic').classList.contains('active') ? 'italic' : 'normal';
        codeEl.style.textDecoration = document.getElementById('printCodeUnderline').classList.contains('active') ? 'underline' : 'none';
        
        nameEl.style.fontFamily = font;
        nameEl.style.fontSize = document.getElementById('printNameFontSize').value + 'pt';
        nameEl.style.fontWeight = document.getElementById('printNameBold').classList.contains('active') ? 'bold' : 'normal';
        nameEl.style.fontStyle = document.getElementById('printNameItalic').classList.contains('active') ? 'italic' : 'normal';
        nameEl.style.textDecoration = document.getElementById('printNameUnderline').classList.contains('active') ? 'underline' : 'none';
        
        // Barcode dimensions based on tape
        let barcodeH = (tapeWidth - 2) * 5;
        if (barcodeH < 15) barcodeH = 15;
        barcodeEl.style.height = barcodeH + 'px';
        
        clampElementPosition(codeEl, previewWidth, previewHeight);
        clampElementPosition(nameEl, previewWidth, previewHeight);
        clampElementPosition(barcodeEl, previewWidth, previewHeight);
        
        savePrintStyles();
    }

    function clampElementPosition(el, containerWidth, containerHeight) {
        let top = parseInt(el.style.top) || 0;
        let left = parseInt(el.style.left) || 0;
        
        const maxTop = containerHeight - el.clientHeight;
        const maxLeft = containerWidth - el.clientWidth;
        
        if (top < 0) top = 0;
        if (top > maxTop && maxTop > 0) top = maxTop;
        if (left < 0) left = 0;
        if (left > maxLeft && maxLeft > 0) left = maxLeft;
        
        el.style.top = top + 'px';
        el.style.left = left + 'px';
    }

    function regenerateBarcode() {
        const rawCode = '<?php echo $item['barcode'] ?: $item['item_code']; ?>'.replace(/\./g, '');
        const dataMode = document.getElementById('printBarcodeData').value;
        
        let barcodeValue = rawCode;
        if (dataMode === 'url') {
            const baseUrl = window.location.origin + window.location.pathname.replace('print_barcode_pt2730.php', '');
            barcodeValue = baseUrl + 'items.php?scan=' + encodeURIComponent(rawCode);
        }
        
        try {
            const formatCode = '<?php echo $item['item_code']; ?>';
            JsBarcode("#barcode", barcodeValue, {
                format: "CODE128",
                width: 2.2, 
                height: 100,
                displayValue: true,
                text: formatCode,
                fontSize: 14,
                margin: 0
            });
        } catch (e) {
            console.error(e);
        }
        
        updatePrintPreview();
    }

    function savePrintStyles() {
        const showBarcode = document.getElementById('printShowBarcode').value === 'true';
        const showName = document.getElementById('printShowName').checked;
        const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;
        let labelLength = 85; 
        
        const codeEl = document.getElementById('wysiwyg_code');
        const nameEl = document.getElementById('wysiwyg_name');
        const barcodeEl = document.getElementById('barcode');
        
        let printStyleSheet = document.getElementById('dynamicWysiwygPrint');
        if (!printStyleSheet) {
            printStyleSheet = document.createElement('style');
            printStyleSheet.id = 'dynamicWysiwygPrint';
            document.head.appendChild(printStyleSheet);
        }
        
        // CSS Style rules in mm for print (scaled: px / 5)
        const codeStyle = `
            top: ${(parseInt(codeEl.style.top) || 0) / 5}mm !important;
            left: ${(parseInt(codeEl.style.left) || 0) / 5}mm !important;
            font-size: ${document.getElementById('printCodeFontSize').value}pt !important;
            font-weight: ${codeEl.style.fontWeight} !important;
            font-style: ${codeEl.style.fontStyle} !important;
            text-decoration: ${codeEl.style.textDecoration} !important;
            font-family: ${document.getElementById('printFont').value} !important;
        `;
        
        const nameStyle = `
            top: ${(parseInt(nameEl.style.top) || 0) / 5}mm !important;
            left: ${(parseInt(nameEl.style.left) || 0) / 5}mm !important;
            font-size: ${document.getElementById('printNameFontSize').value}pt !important;
            font-weight: ${nameEl.style.fontWeight} !important;
            font-style: ${nameEl.style.fontStyle} !important;
            text-decoration: ${nameEl.style.textDecoration} !important;
            font-family: ${document.getElementById('printFont').value} !important;
        `;
        
        const barcodeStyle = `
            top: ${(parseInt(barcodeEl.style.top) || 0) / 5}mm !important;
            left: ${(parseInt(barcodeEl.style.left) || 0) / 5}mm !important;
            width: ${(parseInt(barcodeEl.style.width) || 160) / 5}mm !important;
            height: ${(parseInt(barcodeEl.style.height) || 40) / 5}mm !important;
        `;
        
        printStyleSheet.innerHTML = `@media print { 
            @page { size: ${labelLength}mm ${tapeWidth}mm; margin: 0; }
            .preview-container { 
                width: ${labelLength}mm !important; 
                height: ${tapeWidth}mm !important; 
                position: relative !important;
                border: none !important;
                background: #ffffff !important;
                overflow: hidden !important;
            }
            #wysiwyg_code {
                position: absolute !important;
                line-height: 1.1 !important;
                white-space: nowrap !important;
                margin: 0 !important;
                ${codeStyle}
            }
            #wysiwyg_name {
                position: absolute !important;
                line-height: 1.1 !important;
                white-space: nowrap !important;
                margin: 0 !important;
                ${showName ? nameStyle : 'display: none !important;'}
            }
            #barcode {
                position: absolute !important;
                margin: 0 !important;
                ${showBarcode ? barcodeStyle : 'display: none !important;'}
            }
        }`;
    }

    // Initialize Page
    window.addEventListener('DOMContentLoaded', () => {
        initWYSIWYGDragging();
        regenerateBarcode();
        resetWYSIWYGPositions();
        lucide.createIcons();
    });
</script>

<?php include 'includes/footer.php'; ?>
