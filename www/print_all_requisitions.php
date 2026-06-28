<?php
require_once 'includes/db.php';

// Calculate Thai Fiscal Year
$thai_year = date('Y') + 543;
$fiscal_year = (date('n') >= 10) ? $thai_year + 1 : $thai_year;

$requisitions = $pdo->query("SELECT r.*, i.name as item_name, i.item_code, i.category, i.item_type, i.barcode as item_barcode, i.acquisition_method, i.location, i.image_path 
                             FROM requisitions r 
                             JOIN items i ON r.item_id = i.id 
                             ORDER BY requisition_date DESC")->fetchAll();

function formatThaiDate($date) {
    if (!$date || $date == '0000-00-00') return '-';
    $thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $d = date('j', strtotime($date));
    $m = $thai_months_short[(int)date('n', strtotime($date))];
    $y = date('Y', strtotime($date)) + 543;
    return "$d $m $y";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปรายการเบิกพัสดุ ประจำปีงบประมาณ <?php echo $fiscal_year; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        @page {
            size: A4 landscape;
            margin: 5mm;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
            color: #000;
        }
        .page {
            width: 287mm; /* Slightly wider for A4 Landscape */
            margin: 10mm auto;
            background: #ffffff;
            padding: 10mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        @media print {
            body { background: #ffffff !important; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 5mm; background: #ffffff !important; }
            .no-print { display: none; }
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0 0 5px 0;
            font-weight: 700;
        }
        .header p {
            font-size: 14px;
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #000;
        }
        th, td {
            border: 0.5px solid #000;
            padding: 4px 2px;
            text-align: center;
            font-size: 10px;
            line-height: 1.2;
            word-wrap: break-word;
        }
        th {
            background-color: #f8fafc;
            font-weight: 700;
            font-size: 10px;
        }
        .text-left { text-align: left; padding-left: 4px; }
        .text-right { text-align: right; padding-right: 4px; }
        
        .footer-sig {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding: 0 20mm;
        }
        .sig-block {
            text-align: center;
            width: 80mm;
            font-size: 13px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="no-print" style="width: 287mm; margin: 10px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="index.php" style="padding: 10px 20px; text-decoration: none; background: #64748b; color: white; border: none; border-radius: 6px; font-weight: 500; font-family: 'Sarabun', sans-serif; font-size: 14px; display: inline-flex; align-items: center;">⬅️ ย้อนกลับหน้าหลัก</a>
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #0f172a; color: white; border: none; border-radius: 6px; font-weight: 500; font-family: 'Sarabun', sans-serif; font-size: 14px;">🖨️ พิมพ์รายงาน</button>
    </div>

    <div class="page">
        <div class="header">
            <h1>สรุปรายการเบิกพัสดุ ประจำปีงบประมาณ <?php echo $fiscal_year; ?></h1>
            <p>มหาวิทยาลัยสวนดุสิต &nbsp; • &nbsp; หน่วยงาน : โรงเรียนสาธิตละอออุทิศ ศูนย์ลำปาง</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 25px;">ลำดับ</th>
                    <th rowspan="2" style="width: 110px;">ประเภท/ชนิดครุภัณฑ์</th>
                    <th rowspan="2" style="width: 140px;">รายการ</th>
                    <th rowspan="2" style="width: 85px;">บาร์โค้ด</th>
                    <th rowspan="2" style="width: 105px;">หมายเลขครุภัณฑ์</th>
                    <th colspan="4" style="background: #f8fafc; width: 275px;">ยอดรับระหว่างปี</th>
                    <th rowspan="2" style="width: 75px;">สถานที่ใช้</th>
                    <th rowspan="2" style="width: 65px;">สภาพงาน</th>
                    <th rowspan="2" style="width: 100px;">ผู้เบิก</th>
                </tr>
                <tr>
                    <th style="width: 75px;">วัน เดือน ปี</th>
                    <th style="width: 75px;">วิธีได้มา/<br>ประเภทเงิน</th>
                    <th style="width: 40px;">จำนวน</th>
                    <th style="width: 85px;">จำนวนเงิน/บาท</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requisitions)): ?>
                <tr><td colspan="12">ไม่พบข้อมูลการเบิกพัสดุ</td></tr>
                <?php else: 
                    $total_sum = 0;
                    foreach ($requisitions as $index => $row): 
                        $total_sum += $row['amount'];
                ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td style="text-align: left; padding: 2px 4px;">
                        <div style="font-weight: 600; font-size: 9px; line-height: 1.1;"><?php echo htmlspecialchars($row['category']); ?></div>
                    </td>
                    <td class="text-left" style="font-size: 10px; line-height: 1.1;"><?php echo htmlspecialchars($row['item_name']); ?></td>
                    <td style="padding: 2px;">
                        <?php if ($row['item_barcode']): ?>
                            <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo urlencode($row['item_barcode']); ?>&scale=1&height=8&includetext" style="max-width: 80px; height: auto; display: block; margin: 0 auto;">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; font-size: 9px;"><?php echo htmlspecialchars($row['item_code']); ?></td>
                    <td><?php echo formatThaiDate($row['requisition_date']); ?></td>
                    <td style="font-size: 9px;"><?php echo htmlspecialchars($row['acquisition_method'] ?: '-'); ?></td>
                    <td style="font-weight: 600;"><?php echo $row['quantity']; ?></td>
                    <td class="text-right" style="font-family: monospace; padding-right: 5px;"><?php echo number_format($row['amount'], 2); ?></td>
                    <td style="font-size: 9px;"><?php echo htmlspecialchars($row['location'] ?: '-'); ?></td>
                    <td style="font-size: 9px;"><?php echo $row['status'] == 'Approved' ? 'ปกติ' : $row['status']; ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($row['requester_name']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f9f9f9;">
                    <td colspan="8" class="text-right" style="padding: 5px 10px;"><strong>รวมยอดเงินทั้งสิ้น</strong></td>
                    <td class="text-right" style="padding: 5px; font-family: monospace; font-weight: 600;"><strong><?php echo number_format($total_sum, 2); ?></strong></td>
                    <td colspan="3"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-sig">
            <div class="sig-block">
                ลงชื่อ..................................................ผู้รายงาน<br>
                (..................................................)<br>
                วันที่......../......../........
            </div>
            <div class="sig-block">
                ลงชื่อ.................................................ผู้อำนวยการหน่วยงาน<br>
                (..................................................)<br>
                วันที่......../......../........
            </div>
        </div>
    </div>
</body>
</html>
