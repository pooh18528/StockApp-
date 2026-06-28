<?php
require_once 'includes/db.php';

include 'includes/header.php';
?>

<style>
    /* ---- Settings Panel ---- */
    .br-settings-panel {
        background: var(--card);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .br-settings-panel h2 {
        margin: 0 0 1.25rem 0;
        font-size: 1.1rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .br-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 1.5rem;
        background: var(--bg-hover);
        padding: 4px;
        border-radius: 10px;
        width: fit-content;
    }

    .br-tab {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--text-muted);
    }

    .br-tab.active {
        background: var(--card);
        color: var(--primary);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .br-textarea {
        width: 100%;
        height: 120px;
        padding: 12px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 1rem;
        background: var(--card);
        color: var(--text);
        resize: vertical;
    }

    .br-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .br-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .br-form-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 1rem;
        align-items: end;
    }

    .br-form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 1rem;
        align-items: end;
    }

    .br-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .br-field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .br-field input,
    .br-field select {
        width: 100%;
        min-width: 0;
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        font-family: 'Sarabun', sans-serif;
        background: var(--card);
        color: var(--text);
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .br-field input:focus,
    .br-field select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        background: var(--card);
    }

    .br-field input[type="number"] {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        font-size: 1rem;
        text-align: center;
    }

    /* ---- P-touch Editor Clone ---- */
    .pte-editor {
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow-x: auto;
        background: #d0d0d0;
        margin-bottom: 0.5rem;
        max-width: 100%;
    }

    /* Toolbar */
    .pte-toolbar {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        background: #f0f0f0;
        border-bottom: 1px solid #c0c0c0;
        flex-wrap: wrap;
    }
    .pte-toolbar-group { display: flex; align-items: center; gap: 3px; }
    .pte-toolbar-sep { width: 1px; height: 22px; background: #c0c0c0; margin: 0 4px; }
    .pte-select, .pte-input-num {
        padding: 3px 6px; border: 1px solid #b0b0b0; border-radius: 3px;
        font-size: 0.82rem; background: white; color: #1e293b; font-family: 'Sarabun', sans-serif;
    }
    .pte-select:focus, .pte-input-num:focus { outline: none; border-color: #3b82f6; }
    .pte-tb-btn {
        width: 28px; height: 28px; border: 1px solid transparent; border-radius: 3px;
        background: transparent; cursor: pointer; display: flex; align-items: center;
        justify-content: center; font-size: 0.85rem; transition: all 0.15s;
    }
    .pte-tb-btn:hover { background: #ddd; border-color: #bbb; }
    .pte-tb-btn.active { background: #cde; border-color: #89b; color: #1d4ed8; }
    .pte-tape-badge {
        font-size: 0.75rem; font-weight: 700; color: #555;
        background: #e8e8e8; padding: 2px 8px; border-radius: 3px; border: 1px solid #ccc;
    }

    /* Canvas Wrap (grid: ruler-corner | ruler-top / ruler-left | canvas) */
    .pte-canvas-wrap {
        display: grid;
        grid-template-columns: 28px 1fr;
        grid-template-rows: 22px 1fr;
    }
    .pte-ruler-corner {
        background: #e0e0e0; border-right: 1px solid #aaa; border-bottom: 1px solid #aaa;
    }

    /* Rulers */
    .pte-ruler-top {
        background: #f5f0e0;
        border-bottom: 1px solid #aaa;
        position: relative;
        overflow: hidden;
        height: 22px;
    }
    .pte-ruler-left {
        background: #f5f0e0;
        border-right: 1px solid #aaa;
        position: relative;
        width: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pte-tape-width-label {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        font-size: 0.6rem;
        font-weight: 700;
        color: #c00;
        letter-spacing: 1px;
    }

    /* Canvas (gray workspace) */
    .pte-canvas {
        background: #a0a0a0;
        min-height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 56px 30px 40px;
        position: relative;
        overflow-x: auto;
        min-width: 0;
    }

    /* Tape (white label) */
    .pte-tape {
        background: white;
        width: fit-content; /* ขนาดหดเข้าหาเนื้อหาจริงตามธรรมชาติ */
        min-width: auto; /* ไม่บังคับขนาดขั้นต่ำ เพื่อให้หดชิดสนิทกับเนื้อหาที่สุด */
        max-width: 95%; /* ป้องกันไม่ให้ยาวทะลุขอบจอ */
        height: 80px;
        position: relative;
        box-shadow: 2px 3px 8px rgba(0,0,0,0.25);
        display: flex;
        align-items: center;
        justify-content: center; /* จัดกึ่งกลางเพื่อความชิดกระชับสวยงามระดับมืออาชีพ */
        gap: 12px; /* ลดระยะห่างข้อความกับบาร์โค้ดลงให้ชิดสวยงามกระชับขึ้น */
        padding: 0 8px; /* ขอบด้านข้างที่ขลิบชิดสนิทตัวหนังสือและบาร์โค้ดที่สุด */
        overflow: hidden;
    }

    /* Blue selection handles */
    .pte-handle {
        position: absolute;
        width: 8px; height: 8px;
        background: #3b82f6;
        border: 1px solid #1d4ed8;
        z-index: 11;
    }
    .pte-handle-tl { top: -4px; left: -4px; }
    .pte-handle-tc { top: -4px; left: 50%; margin-left: -4px; }
    .pte-handle-tr { top: -4px; right: -4px; }
    .pte-handle-ml { top: 50%; left: -4px; margin-top: -4px; cursor: ew-resize; }
    .pte-handle-mr { top: 50%; right: -4px; margin-top: -4px; cursor: ew-resize; }
    .pte-handle-bl { bottom: -4px; left: -4px; }
    .pte-handle-bc { bottom: -4px; left: 50%; margin-left: -4px; }
    .pte-handle-br { bottom: -4px; right: -4px; }

    /* Dashed selection border */
    .pte-select-border {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        border: 1.5px dashed rgba(0,0,0,0.25);
        pointer-events: none;
        z-index: 10;
    }

    #previewBarcodeSvg {
        flex-shrink: 0;
        width: 140px !important;
        background: transparent !important;
    }

    /* Text inside tape */
    .pte-text {
        flex: 0 1 auto; /* ให้กว้างเท่าขนาดเนื้อหาจริง ไม่ยืดดึงบาร์โค้ดจนห่างกันเกินไป */
        min-width: 0;
        color: black;
        line-height: 1.2;
        font-family: 'Sarabun', sans-serif;
        font-size: 16pt;
        z-index: 1;
        word-break: break-all;
        padding: 4px;
        min-height: 1em;
    }
    .pte-text[contenteditable="true"]:focus {
        outline: 2px dashed #3b82f6;
        background: rgba(59,130,246,0.05);
        border-radius: 4px;
    }

    .br-field .prefix-input {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        font-size: 1rem;
    }

    /* ---- Preview Section ---- */
    .br-preview-info {
        background: var(--bg-hover);
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin-top: 1rem;
        font-size: 0.9rem;
    }

    .br-preview-info .range-text {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        color: var(--primary);
    }

    .br-preview-info .count-badge {
        display: inline-block;
        background: #2563eb;
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
        margin-left: 8px;
    }

    /* ---- Buttons ---- */
    .br-btn-row {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .br-btn {
        padding: 12px 28px;
        border-radius: 10px;
        font-size: 1rem;
        font-family: 'Sarabun', sans-serif;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
    }

    .br-btn-generate {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
    }

    .br-btn-generate:hover {
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(109, 40, 217, 0.3);
    }

    .br-btn-print {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
    }

    .br-btn-print:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .br-btn-print:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .br-btn-back {
        background: var(--bg-hover);
        color: var(--text);
        text-decoration: none;
        border: 1px solid var(--border);
    }

    .br-btn-back:hover {
        background: var(--border);
    }

    /* ---- Generated Barcode List ---- */
    .br-barcode-list-panel {
        background: var(--card);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
        display: none;
    }

    .br-barcode-list-panel h2 {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .br-barcode-scroll-list {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0;
    }

    .br-barcode-list-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border);
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        transition: background 0.15s;
    }

    .br-barcode-list-item:last-child {
        border-bottom: none;
    }

    .br-barcode-list-item:hover {
        background: var(--bg-hover);
    }

    .br-barcode-list-item .num {
        color: var(--text-muted);
        font-size: 0.75rem;
        width: 36px;
        flex-shrink: 0;
    }

    .br-barcode-list-item .code {
        font-weight: 600;
        color: var(--text);
    }

    /* ---- Status ---- */
    .br-status-msg {
        padding: 12px 16px;
        border-radius: 10px;
        margin-top: 1rem;
        font-weight: 600;
        text-align: center;
        display: none;
    }

    .br-status-msg.success {
        background: #dcfce7;
        color: var(--success);
        border: 1px solid var(--success);
        display: block;
    }

    .br-status-msg.error {
        background: var(--bg-hover);
        color: #991b1b;
        border: 1px solid var(--danger);
        display: block;
    }

    .br-status-msg.info {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid var(--warning);
        display: block;
    }

    /* ---- Tips ---- */
    .br-tips-card {
        background: var(--bg-hover);
        border: 1px solid var(--warning);
        border-radius: 12px;
        padding: 1.25rem;
        margin-top: 1.5rem;
    }

    .br-tips-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        color: #92400e;
    }

    .br-tips-card ul {
        margin: 0;
        padding-left: 1.2rem;
        line-height: 1.7;
        font-size: 0.82rem;
        color: var(--text);
    }

    .br-tips-card strong {
        color: var(--primary);
    }

    /* Datalist styling */
    .br-field input::-webkit-calendar-picker-indicator {
        opacity: 0.6;
    }

    /* ---- Direct Print Button ---- */
    .br-btn-direct {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }
    .br-btn-direct:hover {
        background: linear-gradient(135deg, #b91c1c, #991b1b);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .br-btn-direct:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ---- Direct Print Modal ---- */
    .dp-printer-list { max-height: 200px; overflow-y: auto; margin: 1rem 0; }
    .dp-printer-item {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; border: 1.5px solid var(--border, #e2e8f0);
        border-radius: 10px; margin-bottom: 8px; cursor: pointer;
        transition: all 0.2s;
    }
    .dp-printer-item:hover { border-color: #dc2626; background: #fef2f2; }
    .dp-printer-item.selected { border-color: #dc2626; background: #fef2f2; box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
    .dp-printer-item .dp-icon { font-size: 1.3rem; }
    .dp-printer-item .dp-name { font-weight: 600; font-size: 0.9rem; }
    .dp-printer-item .dp-badge {
        font-size: 0.7rem; padding: 2px 8px; border-radius: 20px;
        background: #dcfce7; color: #166534; font-weight: 600;
    }
    .dp-progress { text-align: center; padding: 2rem 1rem; display: none; }
    .dp-progress .dp-spinner {
        width: 48px; height: 48px; border: 4px solid #e2e8f0;
        border-top-color: #dc2626; border-radius: 50%;
        animation: dpSpin 0.8s linear infinite; margin: 0 auto 1rem;
    }
    @keyframes dpSpin { to { transform: rotate(360deg); } }
    .dp-progress .dp-prog-text { font-weight: 600; color: var(--text, #1e293b); }

    /* ---- P-touch Editor Button ---- */
    .br-btn-ptouch {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }

    .br-btn-ptouch:hover {
        background: linear-gradient(135deg, #047857, #065f46);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .br-btn-ptouch:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .br-btn-csv {
        background: linear-gradient(135deg, #d97706, #b45309);
        color: white;
    }

    .br-btn-csv:hover {
        background: linear-gradient(135deg, #b45309, #92400e);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }

    .br-btn-csv:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ---- P-touch Modal ---- */
    .ptouch-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }

    .ptouch-modal-overlay.active {
        display: flex;
    }

    .ptouch-modal {
        background: var(--card, #fff);
        border-radius: 20px;
        width: 90%;
        max-width: 620px;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: ptouchSlideUp 0.3s ease;
    }

    @keyframes ptouchSlideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .ptouch-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem 2rem 1rem;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }

    .ptouch-modal-header h2 {
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--text, #1e293b);
    }

    .ptouch-modal-header .close-btn {
        background: var(--bg-hover, #f1f5f9);
        border: none;
        width: 36px; height: 36px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .ptouch-modal-header .close-btn:hover {
        background: var(--border, #e2e8f0);
    }

    .ptouch-modal-body {
        padding: 1.5rem 2rem 2rem;
    }

    .ptouch-step {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.25rem;
        padding: 1rem;
        background: var(--bg-hover, #f8fafc);
        border-radius: 12px;
        border: 1px solid var(--border, #e2e8f0);
        transition: border-color 0.2s;
    }

    .ptouch-step:hover {
        border-color: #059669;
    }

    .ptouch-step-num {
        width: 32px; height: 32px;
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .ptouch-step-content h3 {
        margin: 0 0 4px;
        font-size: 0.95rem;
        color: var(--text, #1e293b);
    }

    .ptouch-step-content p {
        margin: 0;
        font-size: 0.82rem;
        color: var(--text-muted, #64748b);
        line-height: 1.5;
    }

    .ptouch-step-content .step-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .ptouch-step-content .btn-download-csv {
        background: linear-gradient(135deg, #d97706, #b45309);
        color: white;
    }

    .ptouch-step-content .btn-download-csv:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    }

    .ptouch-step-content .btn-open-lbx {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }

    .ptouch-step-content .btn-open-lbx:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }

    .ptouch-info-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .ptouch-count-display {
        text-align: center;
        padding: 1rem;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border-radius: 12px;
        margin-bottom: 1.25rem;
    }

    .ptouch-count-display .big-num {
        font-size: 2rem;
        font-weight: 800;
        color: #059669;
        font-family: 'Courier New', monospace;
    }

    .ptouch-count-display .small-text {
        font-size: 0.8rem;
        color: #065f46;
        margin-top: 2px;
    }

    /* ---- Dark Mode ---- */
    [data-theme="dark"] .br-textarea {
        color: #e5e7eb;
    }
    [data-theme="dark"] .br-preview-info {
        color: #e5e7eb;
        border-color: #374151;
    }
    [data-theme="dark"] .br-barcode-scroll-list {
        border-color: #374151;
    }

    /* ---- Responsive ---- */
    @media (max-width: 600px) {
        .br-editor-container {
            grid-template-columns: 1fr;
        }
        .br-form-row-2 {
            grid-template-columns: 1fr 1fr;
        }
        .ptouch-modal {
            width: 95%;
            margin: 1rem;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title">🏷️ พิมพ์ Barcode ต่อเนื่อง</h1>
    <p class="page-subtitle">กรอกรหัสครุภัณฑ์ที่ต้องการพิมพ์ และเลือกโหมดการวางที่ต้องการ</p>
</div>

<!-- Settings -->
<div class="br-settings-panel">
    <!-- Mode Tabs -->
    <div class="br-tabs">
        <div class="br-tab active" id="tabSequential" onclick="setMode('sequential')">
            <i data-lucide="hash" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
            แบบช่วงเลข (Sequential)
        </div>
        <div class="br-tab" id="tabManual" onclick="setMode('manual')">
            <i data-lucide="list" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
            แบบระบุเอง (Manual List)
        </div>
    </div>

    <div class="br-form-grid">
        <!-- Sequential Mode UI -->
        <div id="sequentialInputs">
            <div class="br-form-row" style="margin-bottom: 1rem;">
                <div class="br-field">
                    <label>รหัสนำหน้า (Prefix)</label>
                    <input type="text" id="prefix" class="prefix-input" placeholder="เช่น SDU.69.05.06.02" value="SDU.69.05.06.02" oninput="updatePreview()">
                </div>
                <div class="br-field">
                    <label>เริ่มต้น (Start)</label>
                    <input type="number" id="startNum" value="1" min="1" oninput="updatePreview()">
                </div>
                <div class="br-field">
                    <label>สิ้นสุด (End)</label>
                    <input type="number" id="endNum" value="10" min="1" oninput="updatePreview()">
                </div>
            </div>
        </div>

        <!-- Manual Mode UI (hidden by default) -->
        <div id="manualInputs" style="display: none;">
            <div class="br-field" style="margin-bottom: 1rem;">
                <label>รายการรหัสครุภัณฑ์ (หนึ่งรหัสต่อบรรทัด)</label>
                <textarea id="manualList" class="br-textarea" style="height: 100px;" placeholder="SDU.69.05.06.02.3&#10;SDU.69.05.06.02.4" oninput="updatePreview()"></textarea>
            </div>
        </div>

        <!-- ===== P-touch Editor Clone ===== -->
        <div class="pte-editor">
            <!-- Toolbar -->
            <div class="pte-toolbar">
                <div class="pte-toolbar-group">
                    <select id="manualFont" onchange="updatePreview()" class="pte-select" style="width:160px;">
                        <option value="'Sarabun', sans-serif">Sarabun</option>
                        <option value="'Arial Black', sans-serif">Arial Black</option>
                        <option value="'Courier New', monospace">Courier New</option>
                        <option value="'Arial', sans-serif">Arial</option>
                        <option value="'Tahoma', sans-serif">Tahoma</option>
                    </select>
                    <input type="number" id="manualFontSize" value="14" min="6" max="72" oninput="updatePreview()" class="pte-input-num" style="width:52px;" title="Font Size">
                </div>
                <div class="pte-toolbar-sep"></div>
                <div class="pte-toolbar-group">
                    <button type="button" class="pte-tb-btn" id="btnBold" onclick="toggleStyle('bold')" title="Bold"><strong>B</strong></button>
                    <button type="button" class="pte-tb-btn" id="btnItalic" onclick="toggleStyle('italic')" title="Italic"><em>I</em></button>
                    <button type="button" class="pte-tb-btn" id="btnUnderline" onclick="toggleStyle('underline')" title="Underline"><u>U</u></button>
                </div>
                <div class="pte-toolbar-sep"></div>
                <div class="pte-toolbar-group">
                    <button type="button" class="pte-tb-btn" id="alignLeft" onclick="setAlignment('left')" title="Align Left">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                    </button>
                    <button type="button" class="pte-tb-btn active" id="alignCenter" onclick="setAlignment('center')" title="Center">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
                    </button>
                    <button type="button" class="pte-tb-btn" id="alignRight" onclick="setAlignment('right')" title="Align Right">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                    </button>
                </div>
                <div class="pte-toolbar-sep"></div>
                <div class="pte-toolbar-group">
                    <span class="pte-tape-badge">🏷️ 12mm</span>
                </div>
            </div>

            <!-- Canvas Area with Rulers -->
            <div class="pte-canvas-wrap">
                <!-- Top Ruler (inches) -->
                <div class="pte-ruler-corner"></div>
                <div class="pte-ruler-top" id="pteRulerTop"></div>

                <!-- Left Ruler + Canvas -->
                <div class="pte-ruler-left" id="pteRulerLeft">
                    <div class="pte-tape-width-label" id="pteTapeLabel">12mm</div>
                </div>
                <div class="pte-canvas" id="pteCanvas">
                    <!-- Tape / Label -->
                    <div class="pte-tape" id="pteTape">
                        <!-- Selection handles (blue dots) -->
                        <div class="pte-handle pte-handle-tl"></div>
                        <div class="pte-handle pte-handle-tc"></div>
                        <div class="pte-handle pte-handle-tr"></div>
                        <div class="pte-handle pte-handle-ml"></div>
                        <div class="pte-handle pte-handle-mr"></div>
                        <div class="pte-handle pte-handle-bl"></div>
                        <div class="pte-handle pte-handle-bc"></div>
                        <div class="pte-handle pte-handle-br"></div>
                        <!-- Dashed border -->
                        <div class="pte-select-border"></div>
                        <!-- Text content -->
                        <div class="pte-text" id="labelPreviewContent" contenteditable="true" spellcheck="false" title="คลิกเพื่อแก้ไขข้อความอิสระ (เหมือน P-touch Editor)" oninput="document.getElementById('btnPrint').disabled = false; document.getElementById('btnPtouch').disabled = false; document.getElementById('btnDirect').disabled = false; document.getElementById('btnCsvQuick').disabled = false;">SDU.XX.XX.XX.XX.X</div>
                        <!-- Barcode SVG preview -->
                        <svg id="previewBarcodeSvg" style="height: 60px; flex-shrink: 0; display: none;"></svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barcode Settings -->
        <div id="barcodeSettings" class="br-form-row-2">
            <div class="br-field" style="display: none;">
                <label>จำนวนหลักตัวเลข</label>
                <select id="padDigits">
                    <option value="3" selected>3 หลัก (001)</option>
                </select>
            </div>
            <div class="br-field" style="display: none;">
                <label>ตัวคั่น</label>
                <select id="separator">
                    <option value="." selected>จุด (.)</option>
                </select>
            </div>
            <div class="br-field">
                <label>โหมดการวาง</label>
                <select id="layoutMode" onchange="updatePreview()">
                    <option value="1" selected>1 รายการ/แผ่น</option>
                    <option value="2">2 รายการ/แผ่น</option>
                    <option value="3">3 รายการ/แผ่น</option>
                    <option value="4">4 รายการ/แผ่น</option>
                    <option value="5">5 รายการ/แผ่น</option>
                    <option value="6">6 รายการ/แผ่น</option>
                    <option value="999">แบบต่อเนื่อง (แถวเดียว)</option>
                    <option value="998">แบบต่อเนื่อง (ซ้อนบรรทัด/ป้ายเดียว)</option>
                </select>
            </div>
            <div class="br-field">
                <label>ขนาดเทป</label>
                <select id="tapeSize" onchange="updatePreview()">
                    <option value="12" selected>12 mm</option>
                    <option value="18">18 mm</option>
                    <option value="24">24 mm</option>
                </select>
            </div>
            <div class="br-field">
                <label>แท่งบาร์โค้ด (Barcode lines)</label>
                <select id="showBarcodeOpt" onchange="updatePreview()">
                    <option value="true" selected>แสดง (แสดงข้อความคู่บาร์โค้ด)</option>
                    <option value="false">ไม่แสดง (แสดงเฉพาะข้อความ)</option>
                </select>
            </div>
            <div class="br-field">
                <label>ข้อมูลในบาร์โค้ด</label>
                <select id="barcodeDataMode" onchange="updatePreview()">
                    <option value="code" selected>รหัสครุภัณฑ์ (สแกนง่าย)</option>
                    <option value="url">ฝังลิงก์ URL (สแกนด้วยมือถือ)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Preview Info -->
    <div class="br-preview-info" id="previewInfo">
        <div style="margin-bottom: 4px; color: #475569; font-weight: 500;">สรุปรายการ:</div>
        <div>
            เริ่มต้น: <span class="range-text" id="previewStart">—</span>
            สิ้นสุด: <span class="range-text" id="previewEnd">—</span>
            <span class="count-badge" id="previewCount">0 รายการ</span>
        </div>
    </div>

    <!-- Buttons -->
    <div class="br-btn-row">
        <a href="items.php" class="br-btn br-btn-back">← กลับหน้ารายการ</a>
        <button type="button" class="br-btn br-btn-generate" onclick="generateBarcodeList()">
            ✨ สร้างรายการ Barcode
        </button>
        <button type="button" class="br-btn br-btn-print" id="btnPrint" onclick="handlePrint()" disabled>
            🖨️ พิมพ์ผ่านเบราว์เซอร์
        </button>
        <button type="button" class="br-btn br-btn-direct" id="btnDirect" onclick="openDirectPrintModal()" disabled style="display: none;">
            🖨️ พิมพ์ออกเครื่องทันที (b-PAC)
        </button>
        <button type="button" class="br-btn br-btn-ptouch" id="btnPtouch" onclick="openPtouchModal()" disabled>
            🏷️ P-touch Editor (PT-2730)
        </button>
        <button type="button" class="br-btn br-btn-csv" id="btnCsvQuick" onclick="quickExportCSV()" disabled>
            📄 Export CSV
        </button>
    </div>

    <div class="br-status-msg" id="statusMsg"></div>
</div>

<!-- Generated Barcode List -->
<div class="br-barcode-list-panel" id="barcodeListPanel">
    <h2>📋 รายการ Barcode ที่สร้าง <span id="listCount" style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400;"></span></h2>
    <div class="br-barcode-scroll-list" id="barcodeScrollList">
        <!-- Items will be generated by JS -->
    </div>
</div>

<!-- Direct Print Modal -->
<div class="ptouch-modal-overlay" id="directPrintModal">
    <div class="ptouch-modal">
        <div class="ptouch-modal-header">
            <h2>🖨️ พิมพ์ตรง PT-2730 — ต่อเนื่องไม่สิ้นสุด</h2>
            <button type="button" class="close-btn" onclick="closeDirectPrintModal()">✕</button>
        </div>
        <div class="ptouch-modal-body">
            <div class="ptouch-info-badge">
                🔴 พิมพ์ต่อเนื่องแบบยาวๆ ผ่าน b-PAC SDK — ไม่ต้องเปิด P-touch Editor
            </div>
            <div class="ptouch-count-display">
                <div class="big-num" id="dpCountNum">0</div>
                <div class="small-text">รายการที่จะพิมพ์ต่อเนื่อง</div>
            </div>

            <!-- Printer Selection -->
            <div id="dpPrinterSection">
                <div style="font-weight:600; margin-bottom:8px; font-size:0.9rem;">เลือกเครื่องพิมพ์:</div>
                <div class="dp-printer-list" id="dpPrinterList">
                    <div style="text-align:center; padding:1rem; color:var(--text-muted);">⏳ กำลังค้นหาเครื่องพิมพ์...</div>
                </div>
                <div style="text-align:center; margin-top:1rem;">
                    <button type="button" class="br-btn br-btn-direct" id="btnStartDirectPrint" onclick="startDirectPrint()" disabled>
                        🖨️ เริ่มพิมพ์ต่อเนื่อง!
                    </button>
                    <button type="button" class="br-btn br-btn-back" onclick="closeDirectPrintModal()" style="margin-left:8px;">ยกเลิก</button>
                </div>
            </div>

            <!-- Progress -->
            <div class="dp-progress" id="dpProgress">
                <div class="dp-spinner"></div>
                <div class="dp-prog-text" id="dpProgText">กำลังพิมพ์...</div>
            </div>

            <!-- SDK not available fallback -->
            <div id="dpFallback" style="display:none; text-align:center; padding:1rem;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">⚠️</div>
                <div style="font-weight:600; margin-bottom:0.5rem;">b-PAC SDK ไม่พร้อมใช้งาน</div>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">กรุณาติดตั้ง Brother b-PAC SDK (มาพร้อม P-touch Editor)<br>หรือใช้ปุ่ม <strong>"P-touch Editor"</strong> + CSV แทน</p>
                <button type="button" class="br-btn br-btn-ptouch" onclick="closeDirectPrintModal(); openPtouchModal();">
                    🏷️ ใช้ P-touch Editor แทน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- P-touch Editor Modal -->
<div class="ptouch-modal-overlay" id="ptouchModal">
    <div class="ptouch-modal">
        <div class="ptouch-modal-header">
            <h2>🏷️ P-touch Editor — ส่งไป PT-2730</h2>
            <button type="button" class="close-btn" onclick="closePtouchModal()">✕</button>
        </div>
        <div class="ptouch-modal-body">
            <div class="ptouch-info-badge">
                🖨️ Brother PT-2730 — พิมพ์ต่อเนื่องแบบยาว ไม่จำกัดจำนวน
            </div>

            <div class="ptouch-count-display">
                <div class="big-num" id="ptouchCountNum">0</div>
                <div class="small-text">รายการที่จะพิมพ์</div>
            </div>

            <div class="ptouch-step">
                <div class="ptouch-step-num">1</div>
                <div class="ptouch-step-content">
                    <h3>ดาวน์โหลดไฟล์ CSV</h3>
                    <p>ไฟล์ CSV จะมีรหัสครุภัณฑ์ทั้งหมด สำหรับนำเข้า P-touch Editor<br>ไฟล์จะถูกบันทึกใน folder <code>temp/</code></p>
                    <button type="button" class="step-action-btn btn-download-csv" id="btnStepCSV" onclick="ptouchExportCSV()">
                        📄 ดาวน์โหลด CSV
                    </button>
                </div>
            </div>

            <div class="ptouch-step">
                <div class="ptouch-step-num">2</div>
                <div class="ptouch-step-content">
                    <h3>เปิด P-touch Editor</h3>
                    <p>เปิดโปรแกรม Brother P-touch Editor บนเครื่องคอมพิวเตอร์<br>สร้าง Label ใหม่ เลือก Template แบบ <strong>Continuous Length</strong></p>
                </div>
            </div>

            <div class="ptouch-step">
                <div class="ptouch-step-num">3</div>
                <div class="ptouch-step-content">
                    <h3>เชื่อมต่อ Database</h3>
                    <p>ไปที่ <strong>File → Database → Connect...</strong><br>
                    เลือกไฟล์ CSV ที่ดาวน์โหลด → เลือกคอลัมน์ <strong>"Label"</strong><br>
                    ลาก field ลงบน label หรือ Insert → Barcode แล้ว Merge กับ field</p>
                </div>
            </div>

            <div class="ptouch-step">
                <div class="ptouch-step-num">4</div>
                <div class="ptouch-step-content">
                    <h3>พิมพ์ทั้งหมด (All Records)</h3>
                    <p>ไปที่ <strong>File → Print → All Records</strong><br>
                    เครื่อง PT-2730 จะพิมพ์ต่อเนื่องแบบยาว ๆ ไม่สิ้นสุดจนกว่าจะครบทุกรายการ!</p>
                </div>
            </div>

            <div style="text-align: center; margin-top: 0.5rem;">
                <button type="button" class="br-btn br-btn-back" onclick="closePtouchModal()">ปิด</button>
            </div>
        </div>
    </div>
</div>

<!-- Tips -->
<div class="br-tips-card">
    <h3>💡 คำแนะนำ</h3>
    <ul style="margin-bottom: 1rem;">
        <li><strong>การกรอกรหัส:</strong> พิมพ์หรือคัดลอกรหัสครุภัณฑ์ลงในช่อง (หนึ่งรหัสต่อบรรทัด)</li>
        <li><strong>โหมดการวาง:</strong> เลือก <strong>"แบบต่อเนื่อง (แถวเดียว)"</strong> เพื่อพิมพ์เป็นเส้นยาวสำหรับเครื่อง PT-2730</li>
        <li><strong>พิมพ์ผ่านเบราว์เซอร์:</strong> กด <strong>"สร้างรายการ"</strong> แล้วกด <strong>"พิมพ์ผ่านเบราว์เซอร์"</strong> (จำกัด 1,000 รายการ)</li>
        <li><strong>🏷️ P-touch Editor:</strong> กด <strong>"P-touch Editor"</strong> เพื่อ Export CSV สำหรับ P-touch Editor — <strong>ไม่จำกัดจำนวน!</strong> พิมพ์ต่อเนื่องแบบยาวๆ ได้</li>
        <li><strong>📄 Export CSV:</strong> กด <strong>"Export CSV"</strong> เพื่อดาวน์โหลดไฟล์ CSV อย่างรวดเร็ว</li>
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
    let generatedBarcodes = [];
    let currentMode = 'sequential';

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('tabSequential').classList.toggle('active', mode === 'sequential');
        document.getElementById('tabManual').classList.toggle('active', mode === 'manual');
        
        document.getElementById('sequentialInputs').style.display = (mode === 'sequential' ? 'block' : 'none');
        document.getElementById('manualInputs').style.display = (mode === 'manual' ? 'block' : 'none');
        
        updatePreview();
    }

    let manualStyle = {
        bold: false,
        italic: false,
        underline: false,
        align: 'center'
    };

    function toggleStyle(style) {
        manualStyle[style] = !manualStyle[style];
        document.getElementById('btn' + style.charAt(0).toUpperCase() + style.slice(1)).classList.toggle('active', manualStyle[style]);
        updatePreview();
    }

    function setAlignment(align) {
        manualStyle.align = align;
        ['left', 'center', 'right'].forEach(a => {
            document.getElementById('align' + a.charAt(0).toUpperCase() + a.slice(1)).classList.toggle('active', align === a);
        });
        updatePreview();
    }

    function updatePreview() {
        let previewHTML = 'SDU.XX.XX.XX.XX.X';
        const layoutModeStr = document.getElementById('layoutMode').value;
        const isMultiLine = (layoutModeStr === '998');
        
        const showBarcodeOptDiv = document.getElementById('showBarcodeOpt') ? document.getElementById('showBarcodeOpt').closest('.br-field') : null;
        if (showBarcodeOptDiv) {
            if (isMultiLine) {
                showBarcodeOptDiv.style.display = 'none';
            } else {
                showBarcodeOptDiv.style.display = 'flex';
            }
        }

        if (currentMode === 'sequential') {
            const prefix = document.getElementById('prefix').value.trim();
            const start = parseInt(document.getElementById('startNum').value) || 1;
            const end = parseInt(document.getElementById('endNum').value) || 1;
            const sep = ".";
            
            count = Math.max(0, end - start + 1);
            if (count > 0) {
                if (isMultiLine) {
                    let lines = [];
                    for(let i=start; i<=end; i++) lines.push(prefix + sep + i);
                    previewHTML = lines.join('<br>');
                } else {
                    previewHTML = prefix + sep + start;
                }
                startText = prefix + sep + start;
                endText = prefix + sep + end;
            }
        } else {
            const list = document.getElementById('manualList').value.split('\n').filter(line => line.trim() !== '');
            count = list.length;
            if (count > 0) {
                if (isMultiLine) {
                    previewHTML = list.join('<br>');
                } else {
                    previewHTML = list[0];
                }
            }
            startText = list[0] || '—';
            endText = list[count - 1] || '—';
        }
        
        // Update Label Preview
        const previewEl = document.getElementById('labelPreviewContent');
        if (previewEl) {
            previewEl.innerHTML = previewHTML;
            previewEl.style.fontFamily = document.getElementById('manualFont').value;
            previewEl.style.fontSize = document.getElementById('manualFontSize').value + 'pt';
            previewEl.style.fontWeight = manualStyle.bold ? 'bold' : 'normal';
            previewEl.style.fontStyle = manualStyle.italic ? 'italic' : 'normal';
            previewEl.style.textDecoration = manualStyle.underline ? 'underline' : 'none';
            previewEl.style.textAlign = manualStyle.align;
        }

        // Dynamically scale tape height and update ruler label on screen
        const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;
        const tapeEl = document.getElementById('pteTape');
        if (tapeEl) {
            const scaleFactor = tapeWidth / 12;
            tapeEl.style.height = (80 * scaleFactor) + 'px';
        }
        const tapeLabelEl = document.getElementById('pteTapeLabel');
        if (tapeLabelEl) {
            tapeLabelEl.textContent = tapeWidth + 'mm';
        }

        // Update barcode preview SVG
        const barcodeShowOpt = document.getElementById('showBarcodeOpt');
        const showBarcodeInPreview = barcodeShowOpt ? barcodeShowOpt.value === 'true' : true;
        const barcodeSvg = document.getElementById('previewBarcodeSvg');
        if (barcodeSvg) {
            if (showBarcodeInPreview && !isMultiLine) {
                barcodeSvg.style.display = 'block';
                // Scale barcode preview height dynamically with tape size
                const scaleFactor = tapeWidth / 12;
                barcodeSvg.style.height = (60 * scaleFactor) + 'px';
                
                // Get clean barcode value (strip dots for scanning)
                let barcodeText = (previewHTML || '').replace(/<[^>]*>/g, '').trim();
                let cleanBarcodeValue = barcodeText.replace(/\./g, '');
                if (cleanBarcodeValue.length > 2) {
                    try {
                        JsBarcode(barcodeSvg, cleanBarcodeValue, {
                            format: 'CODE128',
                            width: 1.5,
                            height: 45,
                            displayValue: true,
                            fontSize: 10,
                            margin: 2,
                            background: 'transparent',
                            lineColor: '#000000'
                        });
                    } catch(e) {
                        barcodeSvg.style.display = 'none';
                    }
                } else {
                    barcodeSvg.style.display = 'none';
                }
            } else {
                barcodeSvg.style.display = 'none';
            }
        }

        document.getElementById('previewStart').textContent = startText;
        document.getElementById('previewEnd').textContent = endText;
        document.getElementById('previewCount').textContent = count + ' รายการ';
    }

    function generateBarcodeList() {
        generatedBarcodes = [];
        
        if (currentMode === 'sequential') {
            const prefix = document.getElementById('prefix').value.trim();
            const start = parseInt(document.getElementById('startNum').value);
            const end = parseInt(document.getElementById('endNum').value);
            const sep = ".";

            if (!prefix) {
                showStatus('กรุณาใส่รหัสนำหน้า (Prefix)', 'error');
                return;
            }
            if (isNaN(start) || isNaN(end) || start > end) {
                showStatus('กรุณาตรวจสอบช่วงตัวเลขให้ถูกต้อง', 'error');
                return;
            }
            if ((end - start + 1) > 50000) {
                showStatus('จำกัดการสร้างครั้งละไม่เกิน 50,000 รายการ', 'error');
                return;
            }

            for (let i = start; i <= end; i++) {
                const val = prefix + sep + i;
                generatedBarcodes.push({
                    value: val,
                    index: i - start
                });
            }
        } else {
            const list = document.getElementById('manualList').value.trim().split('\n').filter(line => line.trim() !== '');
            if (list.length === 0) {
                showStatus('กรุณาใส่รหัสครุภัณฑ์อย่างน้อย 1 รายการ', 'error');
                return;
            }
            if (list.length > 50000) {
                showStatus('จำกัดสูงสุด 50,000 รายการต่อครั้ง', 'error');
                return;
            }
            
            list.forEach((val, idx) => {
                generatedBarcodes.push({
                    value: val.trim(),
                    index: idx
                });
            });
        }

        // Render list
        const listEl = document.getElementById('barcodeScrollList');
        listEl.innerHTML = '';
        generatedBarcodes.forEach((bc, idx) => {
            const div = document.createElement('div');
            div.className = 'br-barcode-list-item';
            div.innerHTML = `<span class="num">${idx + 1}.</span><span class="code">${bc.value}</span>`;
            listEl.appendChild(div);
        });

        // Show panel
        document.getElementById('barcodeListPanel').style.display = 'block';
        document.getElementById('listCount').textContent = `(${generatedBarcodes.length} รายการ)`;
        document.getElementById('btnPrint').disabled = false;
        document.getElementById('btnPtouch').disabled = false;
        document.getElementById('btnDirect').disabled = false;
        document.getElementById('btnCsvQuick').disabled = false;

        showStatus(`✅ สร้างรายการเรียบร้อย ${generatedBarcodes.length} รายการ`, 'success');
    }

    function handlePrint() {
        if (generatedBarcodes.length === 0) {
            showStatus('กรุณาสร้างรายการ Barcode ก่อน', 'error');
            return;
        }

        let layoutMode = parseInt(document.getElementById('layoutMode').value) || 1;
        if (layoutMode === 999) {
            layoutMode = generatedBarcodes.length;
        }
        const tapeWidth = parseInt(document.getElementById('tapeSize').value) || 12;

        // Show status
        const btn = document.getElementById('btnPrint');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '⏳ กำลังเตรียมพิมพ์...';
        btn.disabled = true;

        showStatus('🔄 กำลังส่งข้อมูลไปยังเครื่องพิมพ์...', 'info');

        // Send to api_print.php
        let printLayoutMode = document.getElementById('layoutMode').value;
        let barcodesToPrint = generatedBarcodes;

        // ถ้าเป็นโหมดป้ายเดียวอิสระ ให้นำข้อความจาก P-touch Editor บนหน้าจอไปพิมพ์เลย (ไม่ต้องสนว่า Generate อะไรไว้)
        if (printLayoutMode === '998') {
            const rawText = document.getElementById('labelPreviewContent').innerText;
            const lines = rawText.split('\n').map(l => l.trim()).filter(l => l !== '');
            if (lines.length === 0) {
                showStatus('กรุณาพิมพ์ข้อความในป้ายก่อนพิมพ์', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                return;
            }
            barcodesToPrint = lines.map(line => ({ value: '', label: line }));
        }

        let showBarcode = true;
        if (printLayoutMode === '998') {
            showBarcode = false;
        } else {
            showBarcode = document.getElementById('showBarcodeOpt') ? document.getElementById('showBarcodeOpt').value === 'true' : true;
        }

        const barcodeDataMode = document.getElementById('barcodeDataMode') ? document.getElementById('barcodeDataMode').value : 'code';
        const baseUrl = window.location.origin + window.location.pathname.replace('print_barcode_range.php', '');

        fetch('api_print.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                barcodes: barcodesToPrint,
                layoutMode: printLayoutMode,
                tapeWidth: tapeWidth,
                showBarcode: showBarcode,
                barcodeDataMode: barcodeDataMode,
                baseUrl: baseUrl,
                customStyle: {
                    font: document.getElementById('manualFont').value,
                    fontSize: document.getElementById('manualFontSize').value + 'pt',
                    bold: manualStyle.bold,
                    italic: manualStyle.italic,
                    underline: manualStyle.underline,
                    align: manualStyle.align
                }
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.url) {
                showStatus(`✅ ${data.message}`, 'success');
                
                // The backend (api_print.php) will automatically launch the external browser using the start command
                // No need to open a new tab here

                btn.innerHTML = '✅ สั่งพิมพ์แล้ว!';
                btn.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            } else {
                showStatus('❌ เกิดข้อผิดพลาด: ' + (data.error || 'ไม่ทราบสาเหตุ'), 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Print API error:', err);
            showStatus('❌ ไม่สามารถเชื่อมต่อ API ได้: ' + err.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    }

    function showStatus(msg, type) {
        const el = document.getElementById('statusMsg');
        el.textContent = msg;
        el.className = 'br-status-msg ' + type;
    }

    // ==========================================
    // P-touch Editor Functions
    // ==========================================

    function openPtouchModal() {
        if (generatedBarcodes.length === 0) {
            showStatus('กรุณาสร้างรายการ Barcode ก่อน', 'error');
            return;
        }
        document.getElementById('ptouchCountNum').textContent = generatedBarcodes.length.toLocaleString();
        document.getElementById('ptouchModal').classList.add('active');
    }

    function closePtouchModal() {
        document.getElementById('ptouchModal').classList.remove('active');
    }

    // Close modal on overlay click
    document.getElementById('ptouchModal').addEventListener('click', function(e) {
        if (e.target === this) closePtouchModal();
    });

    function ptouchExportCSV() {
        if (generatedBarcodes.length === 0) return;
        
        const btn = document.getElementById('btnStepCSV');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '⏳ กำลังสร้างไฟล์...';
        btn.disabled = true;

        // PHP Desktop: สร้าง CSV + เปิด Explorer (highlight ไฟล์)
        fetch('api_ptouch_export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'csv',
                barcodes: generatedBarcodes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '✅ เปิดโฟลเดอร์แล้ว!';
                btn.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
                showStatus(`✅ ${data.message}`, 'success');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            } else {
                showStatus('❌ ' + (data.error || 'ไม่ทราบสาเหตุ'), 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showStatus('❌ เกิดข้อผิดพลาด: ' + err.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    }

    function quickExportCSV() {
        if (generatedBarcodes.length === 0) {
            showStatus('กรุณาสร้างรายการ Barcode ก่อน', 'error');
            return;
        }

        const btn = document.getElementById('btnCsvQuick');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '⏳ กำลังสร้างไฟล์...';
        btn.disabled = true;

        // PHP Desktop: สร้าง CSV + เปิดไฟล์ด้วย Default App (Excel)
        fetch('api_ptouch_export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'csv_open',
                barcodes: generatedBarcodes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '✅ เปิดไฟล์แล้ว!';
                btn.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
                showStatus(`✅ ${data.message}`, 'success');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            } else {
                showStatus('❌ ' + (data.error || 'ไม่ทราบสาเหตุ'), 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showStatus('❌ เกิดข้อผิดพลาด: ' + err.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    }

    // ==========================================
    // Direct Print (b-PAC SDK) Functions
    // ==========================================
    let selectedPrinter = '';

    function openDirectPrintModal() {
        if (generatedBarcodes.length === 0) {
            showStatus('กรุณาสร้างรายการ Barcode ก่อน', 'error');
            return;
        }
        document.getElementById('dpCountNum').textContent = generatedBarcodes.length.toLocaleString();
        document.getElementById('dpPrinterSection').style.display = 'block';
        document.getElementById('dpProgress').style.display = 'none';
        document.getElementById('dpFallback').style.display = 'none';
        document.getElementById('directPrintModal').classList.add('active');
        checkBpacAndLoadPrinters();
    }

    function closeDirectPrintModal() {
        document.getElementById('directPrintModal').classList.remove('active');
    }

    document.getElementById('directPrintModal').addEventListener('click', function(e) {
        if (e.target === this) closeDirectPrintModal();
    });

    function checkBpacAndLoadPrinters() {
        fetch('api_ptouch_print.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.available) {
                loadPrinterList();
            } else {
                document.getElementById('dpPrinterSection').style.display = 'none';
                document.getElementById('dpFallback').style.display = 'block';
            }
        })
        .catch(() => {
            document.getElementById('dpPrinterSection').style.display = 'none';
            document.getElementById('dpFallback').style.display = 'block';
        });
    }

    function loadPrinterList() {
        fetch('api_ptouch_print.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_printers' })
        })
        .then(res => res.json())
        .then(data => {
            const listEl = document.getElementById('dpPrinterList');
            if (!data.printers || data.printers.length === 0) {
                listEl.innerHTML = '<div style="text-align:center; padding:1rem; color:var(--text-muted);">ไม่พบเครื่องพิมพ์</div>';
                return;
            }
            listEl.innerHTML = '';
            // Sort: Brother/PT printers first
            const sorted = data.printers.sort((a,b) => (b.isPtouch?1:0) - (a.isPtouch?1:0));
            sorted.forEach((p, idx) => {
                const div = document.createElement('div');
                div.className = 'dp-printer-item' + (idx === 0 && p.isPtouch ? ' selected' : '');
                div.onclick = () => selectPrinter(p.name, div);
                div.innerHTML = '<span class="dp-icon">' + (p.isPtouch ? '🏷️' : '🖨️') + '</span>' +
                    '<div style="flex:1;"><div class="dp-name">' + p.name + '</div></div>' +
                    (p.isPtouch ? '<span class="dp-badge">P-touch</span>' : '') +
                    (p.isDefault ? '<span class="dp-badge" style="background:#dbeafe;color:#1e40af;">Default</span>' : '');
                listEl.appendChild(div);
                if (idx === 0 && p.isPtouch) selectedPrinter = p.name;
            });
            document.getElementById('btnStartDirectPrint').disabled = !selectedPrinter;
        });
    }

    function selectPrinter(name, el) {
        selectedPrinter = name;
        document.querySelectorAll('.dp-printer-item').forEach(e => e.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('btnStartDirectPrint').disabled = false;
    }

    function startDirectPrint() {
        if (!selectedPrinter || generatedBarcodes.length === 0) return;
        document.getElementById('dpPrinterSection').style.display = 'none';
        document.getElementById('dpProgress').style.display = 'block';
        document.getElementById('dpProgText').textContent = '🖨️ กำลังส่งข้อมูล ' + generatedBarcodes.length + ' รายการไป ' + selectedPrinter + '...';

        let layoutMode = parseInt(document.getElementById('layoutMode').value) || 1;
        if (layoutMode === 999 || layoutMode === 998) {
            layoutMode = parseInt(document.getElementById('layoutMode').value);
        }

        let barcodesToPrint = generatedBarcodes;
        if (layoutMode === 998) {
            const rawText = document.getElementById('labelPreviewContent').innerText;
            const lines = rawText.split('\n').map(l => l.trim()).filter(l => l !== '');
            if (lines.length === 0) {
                showStatus('กรุณาพิมพ์ข้อความในป้ายก่อนพิมพ์', 'error');
                return;
            }
            barcodesToPrint = lines.map(line => ({ value: '', label: line }));
        }

        fetch('api_ptouch_print.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'print',
                barcodes: barcodesToPrint,
                layoutMode: layoutMode,
                printerName: selectedPrinter,
                tapeWidth: parseInt(document.getElementById('tapeSize').value) || 12,
                fontSize: parseInt(document.getElementById('manualFontSize').value) || 14,
                fontName: document.getElementById('manualFont').value.replace(/'/g, ''),
                fontBold: manualStyle.bold
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('dpProgText').innerHTML = '✅ ' + data.message;
                showStatus('✅ ' + data.message, 'success');
                setTimeout(() => closeDirectPrintModal(), 3000);
            } else {
                document.getElementById('dpProgress').style.display = 'none';
                document.getElementById('dpPrinterSection').style.display = 'block';
                if (data.fallback === 'csv') {
                    document.getElementById('dpPrinterSection').style.display = 'none';
                    document.getElementById('dpFallback').style.display = 'block';
                }
                showStatus('❌ ' + (data.error || 'เกิดข้อผิดพลาด'), 'error');
            }
        })
        .catch(err => {
            document.getElementById('dpProgress').style.display = 'none';
            document.getElementById('dpPrinterSection').style.display = 'block';
            showStatus('❌ เกิดข้อผิดพลาด: ' + err.message, 'error');
        });
    }

    // ==========================================
    // P-touch Editor Ruler Generation
    // ==========================================
    function buildRulers() {
        const rulerTop = document.getElementById('pteRulerTop');
        if (!rulerTop) return;
        rulerTop.innerHTML = '';
        const w = rulerTop.offsetWidth || 600;
        const ppi = 96; // pixels per inch
        const totalInches = Math.ceil(w / ppi) + 1;
        for (let i = 0; i <= totalInches; i++) {
            // Major tick (inch)
            const major = document.createElement('div');
            major.style.cssText = 'position:absolute;left:' + (i * ppi) + 'px;bottom:0;width:1px;height:12px;background:#666;';
            rulerTop.appendChild(major);
            // Label
            if (i > 0) {
                const lbl = document.createElement('div');
                lbl.style.cssText = 'position:absolute;left:' + (i * ppi + 3) + 'px;top:2px;font-size:9px;color:#666;';
                lbl.textContent = i;
                rulerTop.appendChild(lbl);
            }
            // Half tick
            if (i < totalInches) {
                const half = document.createElement('div');
                half.style.cssText = 'position:absolute;left:' + (i * ppi + ppi/2) + 'px;bottom:0;width:1px;height:8px;background:#999;';
                rulerTop.appendChild(half);
            }
            // Quarter ticks
            for (let q = 1; q <= 3; q += 2) {
                if (i < totalInches) {
                    const qt = document.createElement('div');
                    qt.style.cssText = 'position:absolute;left:' + (i * ppi + q * ppi/4) + 'px;bottom:0;width:1px;height:5px;background:#bbb;';
                    rulerTop.appendChild(qt);
                }
            }
        }
    }
    buildRulers();
    window.addEventListener('resize', buildRulers);

    // ==========================================
    // Tape Resizing (Drag to increase length)
    // ==========================================
    const pteTape = document.getElementById('pteTape');
    const handleMR = document.querySelector('.pte-handle-mr');
    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    if (handleMR && pteTape) {
        handleMR.style.cursor = 'ew-resize';
        
        handleMR.addEventListener('mousedown', function(e) {
            isResizing = true;
            startX = e.clientX;
            startWidth = parseFloat(window.getComputedStyle(pteTape).width);
            document.documentElement.style.cursor = 'ew-resize';
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!isResizing) return;
            const newWidth = startWidth + (e.clientX - startX);
            pteTape.style.maxWidth = 'none'; // allow infinite growth
            pteTape.style.width = newWidth + 'px';
        });

        document.addEventListener('mouseup', function() {
            if (isResizing) {
                isResizing = false;
                document.documentElement.style.cursor = 'default';
            }
        });
    }

    // Init preview
    setMode('sequential');
</script>

<?php include 'includes/footer.php'; ?>
