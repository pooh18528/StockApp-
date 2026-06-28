<?php
require_once 'includes/db.php';

/**
 * api_print_report.php — API สำหรับเปิดรายงานสรุปในเบราว์เซอร์จริง
 * เพื่อแก้ปัญหา PHP Desktop (CEF) พิมพ์ไม่ได้
 */

$fiscal_year = $_GET['year'] ?? (date('Y') + 543);
$items = $pdo->query("SELECT * FROM items ORDER BY item_code ASC")->fetchAll();

$thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];

function formatThaiDate($date) {
    if (!$date || $date == '0000-00-00') return '-';
    $thai_months_short = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $d = date('j', strtotime($date));
    $m = $thai_months_short[(int)date('n', strtotime($date))];
    $y = substr((string)(date('Y', strtotime($date)) + 543), 2);
    return "$d $m $y";
}

ob_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปครุภัณฑ์ - <?php echo $fiscal_year; ?></title>
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
            background: #f0f0f0; 
            -webkit-print-color-adjust: exact; 
        }
        .page { 
            width: 277mm; 
            min-height: 190mm; 
            padding: 5mm; 
            margin: 10mm auto; 
            background: white; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            box-sizing: border-box;
        }
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
        .header p { font-size: 12pt; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="no-print-banner no-print">
        <span style="margin-right: 20px; font-size: 18px;">📄 รายงานพร้อมพิมพ์แล้ว!</span>
        <button onclick="window.print()" class="btn-print">🖨️ คลิกเพื่อเริ่มพิมพ์ (Print)</button>
        <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">(เปิดในเบราว์เซอร์จริงเพื่อรองรับการพิมพ์ 100%)</p>
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
                    <td class="text-left"><?php echo htmlspecialchars($item['name']); ?></td>
                    <td style="text-align: center; vertical-align: middle; padding: 2px;">
                        <?php if ($item['barcode']): ?>
                            <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo urlencode($item['barcode']); ?>&scale=1&height=8&includetext" style="max-width: 85px; height: auto;">
                        <?php else: ?> - <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; font-size: 9px;">
                        <?php 
                        $displayCode = $item['item_code'];
                        if (preg_match('/^SDU(\d)/', $displayCode)) $displayCode = preg_replace('/^SDU(\d)/', 'SDU.$1', $displayCode);
                        echo htmlspecialchars($displayCode); 
                        ?>
                    </td>
                    <td><?php echo formatThaiDate($item['acquisition_date']); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['acquisition_method'] ?: '-'); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td class="text-right"><?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['location']); ?></td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($item['responsible_person']); ?></td>
                    <td><span style="font-size: 9px;"><?php echo htmlspecialchars($item['condition_status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="8" class="text-right" style="padding: 8px;"><strong>รวมยอดเงินทั้งสิ้น</strong></td>
                    <td class="text-right" style="padding: 8px;"><strong><?php echo number_format($total_amount, 2); ?></strong></td>
                    <td colspan="3"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
        // Auto print after a short delay
        setTimeout(() => { window.print(); }, 1000);
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();

// Save to temp file and open in external browser
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
$filename = 'report_' . date('Ymd_His') . '.html';
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

// Redirect back with success message or just show a small confirmation
header("Location: items.php?success=report_opened");
exit;
