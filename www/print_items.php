<?php
require_once 'includes/db.php';

$fiscal_year = $_GET['year'] ?? (date('Y') + 543);
$items = $pdo->query("SELECT * FROM items ORDER BY item_code ASC")->fetchAll();

$thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค. interval"];

function formatThaiDate($date) {
    if (!$date || $date == '0000-00-00') return '-';
    $thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $d = date('j', strtotime($date));
    $m = $thai_months_short[(int)date('n', strtotime($date))];
    $y = substr((string)(date('Y', strtotime($date)) + 543), 2);
    return "$d $m $y";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสำรวจครุภัณฑ์ ประจำปีงบประมาณ <?php echo $fiscal_year; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 0;
            background: #eee;
        }
        .page {
            width: 277mm; /* A4 Landscape width minus margins */
            min-height: 190mm;
            padding: 10mm;
            margin: 10mm auto;
            background: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            color: #000;
        }
        @media print {
            body { background: #ffffff !important; }
            .page { margin: 0; box-shadow: none; width: 100%; border: none; background: #ffffff !important; }
            .no-print { display: none; }
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            font-weight: 700;
        }
        .header p {
            font-size: 14px;
            margin: 5px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
            font-size: 11px;
            line-height: 1.2;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            vertical-align: middle;
        }
        .text-left { text-align: left; padding-left: 5px; }
        .text-right { text-align: right; padding-right: 5px; }
        
        .img-cell img {
            max-width: 60px;
            max-height: 45px;
            display: block;
            margin: 0 auto;
        }

        .footer-sig {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 100px;
            padding-right: 50px;
            font-size: 12px;
        }
        .sig-block {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="no-print" style="width: 277mm; margin: 10px auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="index.php" style="padding: 8px 16px; text-decoration: none; background: #64748b; color: white; border: none; border-radius: 4px; font-weight: 500; font-family: 'Sarabun', sans-serif; font-size: 13px; display: inline-flex; align-items: center;">⬅️ ย้อนกลับหน้าหลัก</a>
        <button onclick="window.print()" style="padding: 8px 16px; cursor: pointer; background: #4f46e5; color: white; border: none; border-radius: 4px; font-weight: 500; font-family: 'Sarabun', sans-serif; font-size: 13px;">🖨️ พิมพ์รายงาน</button>
    </div>

    <div class="page">
        <div class="header">
            <h1>รายการสำรวจครุภัณฑ์ ประจำปีงบประมาณ <?php echo $fiscal_year; ?></h1>
            <p>มหาวิทยาลัยสวนดุสิต &nbsp;&nbsp;&nbsp; หน่วยงาน : โรงเรียนสาธิตละอออุทิศ ศูนย์ลำปาง</p>
            <p>ระหว่างวันที่ 1 ตุลาคม <?php echo $fiscal_year-1; ?> ถึงวันที่ 30 กันยายน <?php echo $fiscal_year; ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 30px;">ลำดับ</th>
                    <th rowspan="2" style="width: 100px;">ประเภท/ชนิด</th>
                    <th rowspan="2" style="width: 150px;">รายการ</th>
                    <th rowspan="2" style="width: 90px;">บาร์โค้ด</th>
                    <th rowspan="2" style="width: 130px;">หมายเลขครุภัณฑ์</th>
                    <th colspan="4" style="background: #f2f2f2;">ยอดรับระหว่างปี</th>
                    <th rowspan="2" style="width: 80px;">สถานที่ตั้ง</th>
                    <th rowspan="2" style="width: 80px;">ผู้รับผิดชอบ</th>
                    <th rowspan="2" style="width: 80px;">สภาพงาน</th>
                </tr>
                <tr>
                    <th style="width: 70px; font-size: 10px;">วัน เดือน ปี</th>
                    <th style="width: 80px; font-size: 10px;">วิธีได้มา/<br>ประเภทเงิน</th>
                    <th style="width: 40px; font-size: 10px;">จำนวน</th>
                    <th style="width: 90px; font-size: 10px;">จำนวนเงิน/บาท</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="12">ไม่พบข้อมูลครุภัณฑ์</td></tr>
                <?php else: 
                    $total_amount = 0;
                    foreach ($items as $index => $item): 
                        $total_amount += $item['unit_price'] * $item['quantity'];
                ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td class="text-left">
                        <span style="font-size: 9px; color: #666;"><?php echo htmlspecialchars($item['category']); ?></span><br>
                        <strong><?php echo htmlspecialchars($item['item_type']); ?></strong>
                    </td>
                    <td class="text-left">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </td>
                    <td style="text-align: center; vertical-align: middle; padding: 2px;">
                        <?php if ($item['barcode']): ?>
                            <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo urlencode($item['barcode']); ?>&scale=1&height=8&includetext" style="max-width: 85px; height: auto; display: block; margin: 0 auto;">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; font-size: 9px;">
                        <?php 
                        $displayCode = $item['item_code'];
                        if (preg_match('/^SDU(\d)/', $displayCode)) {
                            $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
                        }
                        echo htmlspecialchars($displayCode); 
                        ?>
                    </td>
                    <td><?php echo formatThaiDate($item['acquisition_date']); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['acquisition_method'] ?: '-'); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td class="text-right"><?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['location']); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['responsible_person']); ?></td>
                    <td>
                        <?php if ($item['image_path']): ?>
                            <img src="<?php echo $item['image_path']; ?>" style="max-width: 40px; max-height: 40px; border-radius: 2px; display: block; margin: 0 auto 2px;">
                        <?php endif; ?>
                        <span style="font-size: 9px;"><?php echo htmlspecialchars($item['condition_status']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="8" class="text-right" style="padding: 8px;"><strong>รวมยอดเงินทั้งสิ้น</strong></td>
                    <td class="text-right" style="padding: 8px;"><strong><?php echo number_format($total_amount, 2); ?></strong></td>
                    <td colspan="2"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-sig">
            <div class="sig-block">
                ลงชื่อ..................................................ผู้รับตรวจ<br>
                (..................................................)<br>
                วันที่......../......../........
            </div>
            <div class="sig-block">
                ลงชื่อ..................................................ผู้ตรวจ<br>
                (..................................................)<br>
                วันที่......../......../........
            </div>
        </div>
    </div>
</body>
</html>
