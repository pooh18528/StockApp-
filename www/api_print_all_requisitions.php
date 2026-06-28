<?php
require_once 'includes/db.php';

/**
 * api_print_all_requisitions.php — API สำหรับเปิดรายงานสรุปการเบิกในเบราว์เซอร์จริง
 * เพื่อแก้ปัญหา PHP Desktop (CEF) พิมพ์ไม่ได้
 */

// Calculate Thai Fiscal Year
$thai_year = date('Y') + 543;
$fiscal_year = (date('n') >= 10) ? $thai_year + 1 : $thai_year;

$requisitions = $pdo->query("SELECT r.*, i.name as item_name, i.item_code, i.category, i.item_type, i.barcode as item_barcode, i.acquisition_method, i.location, i.image_path 
                             FROM requisitions r 
                             JOIN items i ON r.item_id = i.id")->fetchAll();

// Sort by Requisition Number (เลขที่ใบเบิก) ascending numerically
usort($requisitions, function($a, $b) {
    $a_num = 0; $a_year = 0;
    if (!empty($a['order_no'])) {
        $parts = explode('/', $a['order_no']);
        $a_num = isset($parts[0]) ? intval(trim($parts[0])) : 0;
        $a_year = isset($parts[1]) ? intval(trim($parts[1])) : 0;
    }
    $b_num = 0; $b_year = 0;
    if (!empty($b['order_no'])) {
        $parts = explode('/', $b['order_no']);
        $b_num = isset($parts[0]) ? intval(trim($parts[0])) : 0;
        $b_year = isset($parts[1]) ? intval(trim($parts[1])) : 0;
    }
    if ($a_year !== $b_year) {
        return $a_year <=> $b_year;
    }
    return $a_num <=> $b_num;
});

function formatThaiDate($date) {
    if (!$date || $date == '0000-00-00') return '-';
    $thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $d = date('j', strtotime($date));
    $m = $thai_months_short[(int)date('n', strtotime($date))];
    $y = date('Y', strtotime($date)) + 543;
    return "$d $m $y";
}

ob_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปรายการเบิกพัสดุ ประจำปีงบประมาณ <?php echo $fiscal_year; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: 'Sarabun', sans-serif; margin: 0; padding: 0; background: #f0f0f0; -webkit-print-color-adjust: exact; }
        .page { width: 277mm; min-height: 190mm; padding: 5mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        @media print { 
            body { background: none; padding: 0; margin: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; height: 100%; border: none; padding: 0; }
            .no-print { display: none !important; }
        }
        .no-print-banner { background: #4f46e5; color: white; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn-print { background: #ffffff; color: #4f46e5; border: none; padding: 10px 25px; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; transition: transform 0.1s; }
        .btn-print:active { transform: scale(0.95); }
        table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; table-layout: fixed; word-wrap: break-word; }
        th, td { border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 10pt; line-height: 1.2; overflow: hidden; }
        th { background-color: #f2f2f2 !important; font-weight: 600; }
        .text-left { text-align: left; padding-left: 5px; }
        .text-right { text-align: right; padding-right: 5px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 18pt; margin: 0; }
        .footer-sig { margin-top: 40px; display: flex; justify-content: space-between; padding: 0 20mm; font-size: 12pt; }
        .sig-block { text-align: center; width: 80mm; }
    </style>
</head>
<body>
    <div class="no-print-banner no-print">
        <span style="margin-right: 20px; font-size: 18px;">📄 รายงานสรุปการเบิกพร้อมพิมพ์!</span>
        <button onclick="window.print()" class="btn-print">🖨️ คลิกเพื่อเริ่มพิมพ์ (Print)</button>
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
                    <th rowspan="2" style="width: 100px;">ประเภท/ชนิด</th>
                    <th rowspan="2" style="width: 130px;">รายการ</th>
                    <th rowspan="2" style="width: 85px;">บาร์โค้ด</th>
                    <th rowspan="2" style="width: 110px;">หมายเลขครุภัณฑ์</th>
                    <th colspan="4" style="background: #f2f2f2;">ยอดรับระหว่างปี</th>
                    <th rowspan="2" style="width: 70px;">สถานที่ใช้</th>
                    <th rowspan="2" style="width: 60px;">สภาพงาน</th>
                    <th rowspan="2" style="width: 90px;">ผู้เบิก</th>
                </tr>
                <tr>
                    <th style="width: 70px;">วัน เดือน ปี</th>
                    <th style="width: 70px;">วิธีได้มา/เงิน</th>
                    <th style="width: 35px;">จำนวน</th>
                    <th style="width: 80px;">จำนวนเงิน</th>
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
                    <td class="text-left">
                        <span style="font-size: 8pt; color: #666;"><?php echo htmlspecialchars($row['category']); ?></span><br>
                        <strong><?php echo htmlspecialchars($row['item_type']); ?></strong>
                    </td>
                    <td class="text-left"><?php echo htmlspecialchars($row['item_name']); ?></td>
                    <td style="padding: 2px;">
                        <?php if ($row['item_barcode']): ?>
                            <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo urlencode($row['item_barcode']); ?>&scale=1&height=8&includetext" style="max-width: 80px; height: auto;">
                        <?php else: ?> - <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; font-size: 9pt;">
                        <?php 
                        $displayCode = $row['item_code'];
                        if (preg_match('/^SDU(\d)/', $displayCode)) $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
                        echo htmlspecialchars($displayCode); 
                        ?>
                    </td>
                    <td><?php echo formatThaiDate($row['requisition_date']); ?></td>
                    <td style="font-size: 9pt;"><?php echo htmlspecialchars($row['acquisition_method'] ?: '-'); ?></td>
                    <td style="font-weight: 600;"><?php echo $row['quantity']; ?></td>
                    <td class="text-right"><?php echo number_format($row['amount'], 2); ?></td>
                    <td style="font-size: 9pt;"><?php echo htmlspecialchars($row['location'] ?: '-'); ?></td>
                    <td style="font-size: 9pt;"><?php echo $row['status'] == 'Approved' ? 'ปกติ' : $row['status']; ?></td>
                    <td style="font-size: 9pt;"><?php echo htmlspecialchars($row['requester_name']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="8" class="text-right"><strong>รวมยอดเงินทั้งสิ้น</strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_sum, 2); ?></strong></td>
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
    <script>
        setTimeout(() => { window.print(); }, 1000);
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
$filename = 'req_report_' . date('Ymd_His') . '.html';
$filepath = $tempDir . '/' . $filename;
file_put_contents($filepath, $htmlContent);
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
header("Location: requisitions.php?success=report_opened");
exit;
