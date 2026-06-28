<?php
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid requisition ID");
}

$stmt = $pdo->prepare("SELECT r.*, r.image_path as req_image, i.name as item_name, i.item_code, i.barcode, i.category, i.item_type, i.acquisition_method, i.location, i.condition_status
                       FROM requisitions r 
                       JOIN items i ON r.item_id = i.id 
                       WHERE r.id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    die("Requisition not found");
}

$displayCode = $req['item_code'];
if (preg_match('/^SDU(\d)/', $displayCode)) {
    $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเบิกพัสดุและครุภัณฑ์ - เลขที่ <?php echo htmlspecialchars($req['order_no'] ?: $req['id']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap');
        
        * {
            box-sizing: border-box;
            font-family: 'Sarabun', 'Leelawadee UI', 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 25mm 20mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
        }

        .voucher-header {
            display: grid;
            grid-template-columns: 100px 1fr 150px;
            gap: 15px;
            align-items: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .logo-img {
            width: 80px;
            height: auto;
            object-fit: contain;
        }

        .header-title {
            text-align: center;
        }

        .header-title h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: #1e3a8a;
            letter-spacing: -0.5px;
        }

        .header-title p {
            font-size: 0.85rem;
            margin: 0;
            color: #64748b;
        }

        .header-meta {
            text-align: right;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .header-meta strong {
            color: #1e3a8a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.2rem;
        }

        .info-card h3 {
            margin: 0 0 0.75rem 0;
            font-size: 0.95rem;
            color: #1e3a8a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .table-voucher {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3rem;
            font-size: 0.9rem;
        }

        .table-voucher th {
            background: #f1f5f9;
            color: #1e3a8a;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            padding: 12px 10px;
            text-align: center;
        }

        .table-voucher td {
            border: 1px solid #cbd5e1;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .total-row {
            background: #f8fafc;
            font-weight: 700;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 4rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .signature-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .sig-image-wrapper {
            width: 180px;
            height: 70px;
            border-bottom: 1px dashed #94a3b8;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sig-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .print-btn-bar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
        }

        .btn-action {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-action:hover {
            background: #1e40af;
        }

        @media print {
            body {
                background: white;
                color: black;
            }
            .print-container {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .print-btn-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button class="btn-action" onclick="window.print()">
        🖨️ พิมพ์ใบเอกสาร / บันทึก PDF
    </button>
</div>

<div class="print-container">
    <!-- Header -->
    <div class="voucher-header">
        <img src="assets/sdu_logo.png" class="logo-img" alt="SDU Logo">
        <div class="header-title">
            <h1>ใบเบิกพัสดุและครุภัณฑ์พัสดุ</h1>
            <p>มหาวิทยาลัยสวนดุสิต (Suan Dusit University)</p>
        </div>
        <div class="header-meta">
            <div>เลขที่ใบเบิก: <strong><?php echo htmlspecialchars($req['order_no'] ?: 'REQ-' . $req['id']); ?></strong></div>
            <div>วันที่เอกสาร: <?php echo date('d/m/Y', strtotime($req['requisition_date'])); ?></div>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-card">
            <h3>ข้อมูลผู้ขอเบิก (Requester Information)</h3>
            <div><strong>ชื่อผู้เบิก:</strong> <?php echo htmlspecialchars($req['requester_name']); ?></div>
            <div><strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($req['position'] ?: '-'); ?></div>
            <div><strong>หน่วยงาน/กอง:</strong> <?php echo htmlspecialchars($req['department'] ?: '-'); ?></div>
        </div>
        <div class="info-card">
            <h3>รายละเอียดการส่งมอบ (Delivery Details)</h3>
            <div><strong>วัตถุประสงค์:</strong> <?php echo htmlspecialchars($req['purpose'] ?: '-'); ?></div>
            <div><strong>สถานที่ใช้งาน:</strong> <?php echo htmlspecialchars($req['location'] ?: '-'); ?></div>
            <div><strong>สภาพพัสดุ/ครุภัณฑ์:</strong> <?php echo htmlspecialchars($req['condition_status'] ?: 'Good'); ?></div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table-voucher">
        <thead>
            <tr>
                <th style="width: 60px;">ลำดับ</th>
                <th style="width: 150px;">รหัสครุภัณฑ์</th>
                <th>รายการพัสดุ/ครุภัณฑ์</th>
                <th style="width: 100px; text-align: center;">จำนวน</th>
                <th style="width: 130px; text-align: right;">ราคาต่อหน่วย</th>
                <th style="width: 140px; text-align: right;">ยอดรวมสุทธิ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td style="text-align: center; font-family: monospace; font-weight: 500; font-size: 0.85rem;"><?php echo htmlspecialchars($displayCode); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($req['item_name']); ?></strong>
                    <?php if ($req['category']): ?>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">ประเภท: <?php echo htmlspecialchars($req['category']); ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; font-weight: 600;"><?php echo number_format($req['quantity']); ?></td>
                <td style="text-align: right; font-family: monospace;"><?php echo number_format($req['unit_price'], 2); ?></td>
                <td style="text-align: right; font-family: monospace; font-weight: 600;"><?php echo number_format($req['amount'], 2); ?></td>
            </tr>
            <?php if ($req['discount'] > 0): ?>
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: 600; color: #64748b;">ส่วนลดพิเศษ:</td>
                    <td style="text-align: right; font-family: monospace; color: #dc2626;">-<?php echo number_format($req['discount'], 2); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">ยอดรวมราคาทั้งสิ้น (Net Total):</td>
                <td style="text-align: center;"><?php echo number_format($req['quantity']); ?></td>
                <td></td>
                <td style="text-align: right; font-family: monospace; color: #1e3a8a; font-size: 1rem;"><?php echo number_format($req['amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Signature Section -->
    <div class="signature-grid">
        <div class="signature-box">
            <div style="font-weight: 600; margin-bottom: 10px;">ลงชื่อผู้ขอเบิกพัสดุ</div>
            <div class="sig-image-wrapper">
                <?php if ($req['signature_data']): ?>
                    <img src="<?php echo htmlspecialchars($req['signature_data']); ?>" class="sig-image" alt="Requester Signature">
                <?php else: ?>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">ไม่ได้ลงลายมือชื่อ</span>
                <?php endif; ?>
            </div>
            <div style="font-weight: 600;"><?php echo htmlspecialchars($req['requester_name']); ?></div>
            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">( ผู้ขอเบิกพัสดุ )</div>
        </div>
        
        <div class="signature-box">
            <div style="font-weight: 600; margin-bottom: 10px;">ลงชื่อผู้อนุมัติจ่ายพัสดุ</div>
            <div class="sig-image-wrapper">
                <?php if ($req['status'] == 'Approved' || $req['status'] == 'Delivered'): ?>
                    <span style="font-size: 0.85rem; color: #16a34a; font-weight: 700; letter-spacing: 0.5px; border: 2px solid #16a34a; padding: 4px 12px; border-radius: 6px; transform: rotate(-5deg); text-transform: uppercase;">APPROVED BY SDU</span>
                <?php else: ?>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">รอเจ้าหน้าที่อนุมัติ</span>
                <?php endif; ?>
            </div>
            <div style="font-weight: 600;">( .................................................... )</div>
            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">ผู้อำนวยการกองคลัง / เจ้าหน้าที่พัสดุ</div>
        </div>
    </div>
</div>

<script>
    // Auto-trigger browser print dialog on load
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            window.print();
        }, 300);
    });
</script>
</body>
</html>
