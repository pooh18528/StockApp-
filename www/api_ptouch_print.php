<?php
/**
 * api_ptouch_print.php — พิมพ์ตรงไป PT-2730 ผ่าน b-PAC SDK
 * 
 * ใช้ Brother b-PAC COM SDK สำหรับสั่งพิมพ์โดยตรง
 * ไม่ต้องเปิด P-touch Editor ไม่ต้องเปิดเบราว์เซอร์
 * พิมพ์ต่อเนื่องแบบยาวๆ ไม่สิ้นสุดจนกว่าจะครบทุกรายการ
 * 
 * b-PAC Print Options:
 *   bpoChainPrint  = 0x00000400 (พิมพ์ต่อเนื่องไม่ตัด)
 *   bpoCutAtEnd    = 0x00000200 (ตัดตอนท้ายสุด)
 *   bpoMirror      = 0x00000004
 *   bpoColor       = 0x00000100
 */

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'print';
$barcodes = $input['barcodes'] ?? [];
$printerName = $input['printerName'] ?? '';
$tapeWidth = intval($input['tapeWidth'] ?? 12);
$fontSize = intval($input['fontSize'] ?? 14);
$fontName = $input['fontName'] ?? 'Arial';
$fontBold = $input['fontBold'] ?? true;
$layoutMode = intval($input['layoutMode'] ?? 1);

if ($layoutMode == 998) {
    $combinedText = '';
    foreach ($barcodes as $item) {
        $label = $item['label'] ?? $item['value'] ?? '';
        $cleanLabel = str_replace('.', '', $label);
        if (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
            $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4] . "." . $matches[5];
        } elseif (preg_match('/^SDU(\d{2})(\d{2})(\d{2})(\d{1,})$/i', $cleanLabel, $matches)) {
            $label = "SDU." . $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4];
        } elseif (preg_match('/^SDU(\d)/i', $cleanLabel)) {
            $label = preg_replace('/^SDU(\d)/i', 'SDU.$1', $cleanLabel);
        }
        $combinedText .= $label . "\n";
    }
    $barcodes = [['label' => trim($combinedText), 'value' => '']];
}

if ($action === 'check') {
    // === ตรวจสอบว่า b-PAC SDK พร้อมใช้งานหรือไม่ ===
    $result = checkBpacSDK();
    echo json_encode($result);
    exit;
}

if ($action === 'list_printers') {
    // === แสดงรายชื่อเครื่องพิมพ์ที่มี ===
    $printers = listPrinters();
    echo json_encode(['success' => true, 'printers' => $printers]);
    exit;
}

if (empty($barcodes)) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล Barcode']);
    exit;
}

if ($action === 'print') {
    // === พิมพ์ตรงผ่าน b-PAC SDK ===
    $result = printViaBpac($barcodes, $printerName, $tapeWidth, $fontSize, $fontName, $fontBold);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action ไม่ถูกต้อง']);

// ============================================
// Functions
// ============================================

function checkBpacSDK() {
    try {
        if (!class_exists('COM')) {
            return [
                'success' => false,
                'available' => false,
                'error' => 'PHP COM extension ไม่พร้อมใช้งาน',
                'suggestion' => 'ใช้โหมด P-touch Editor + CSV แทน'
            ];
        }
        
        // ลองสร้าง b-PAC COM object
        $bpac = @new COM("bpac.Document");
        if ($bpac) {
            $bpac = null;
            return [
                'success' => true,
                'available' => true,
                'message' => 'b-PAC SDK พร้อมใช้งาน — สามารถพิมพ์ตรงไป PT-2730 ได้!'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'available' => false,
            'error' => 'b-PAC SDK ไม่พบ: ' . $e->getMessage(),
            'suggestion' => 'กรุณาติดตั้ง Brother b-PAC SDK หรือใช้โหมด P-touch Editor + CSV แทน'
        ];
    }
    
    return [
        'success' => false,
        'available' => false,
        'error' => 'ไม่สามารถเชื่อมต่อ b-PAC SDK ได้',
        'suggestion' => 'ใช้โหมด P-touch Editor + CSV แทน'
    ];
}

function listPrinters() {
    $printers = [];
    
    // Method 1: WMI
    try {
        $wmi = new COM("winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2");
        $printerQuery = $wmi->ExecQuery("SELECT Name, Default FROM Win32_Printer");
        
        foreach ($printerQuery as $printer) {
            $name = (string) $printer->Name;
            $isDefault = (bool) $printer->Default;
            $printers[] = [
                'name' => $name,
                'isDefault' => $isDefault,
                'isPtouch' => (stripos($name, 'brother') !== false || stripos($name, 'pt-') !== false || stripos($name, 'p-touch') !== false)
            ];
        }
        $wmi = null;
    } catch (Exception $e) {
        // Fallback: return empty list
    }
    
    return $printers;
}

function printViaBpac($barcodes, $printerName, $tapeWidth, $fontSize, $fontName, $fontBold) {
    $count = count($barcodes);
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // สร้าง template .lbx สำหรับ b-PAC
    $templatePath = createTemplate($tempDir, $tapeWidth, $fontSize, $fontName, $fontBold);
    
    if (!$templatePath) {
        return ['success' => false, 'error' => 'ไม่สามารถสร้าง template ได้'];
    }
    
    try {
        $bpac = new COM("bpac.Document");
        
        // เปิด template
        $templateWinPath = str_replace('/', '\\', $templatePath);
        if (!$bpac->Open($templateWinPath)) {
            return [
                'success' => false, 
                'error' => 'ไม่สามารถเปิด template ได้',
                'path' => $templateWinPath
            ];
        }
        
        // เริ่มพิมพ์
        // bpoChainPrint (0x400) = พิมพ์ต่อเนื่องไม่ตัดระหว่าง label
        // bpoCutAtEnd (0x200) = ตัดตอนท้ายสุดเท่านั้น
        $printOptions = 0x00000400; // chain print
        
        $started = $bpac->StartPrint($printerName, $printOptions);
        if (!$started) {
            $bpac->Close();
            return [
                'success' => false,
                'error' => 'ไม่สามารถเริ่มพิมพ์ได้ — ตรวจสอบเครื่องพิมพ์ PT-2730',
                'printer' => $printerName ?: '(Default)'
            ];
        }
        
        $printed = 0;
        foreach ($barcodes as $item) {
            $value = $item['value'] ?? '';
            $label = $item['label'] ?? $value;
            
            // ใส่ข้อมูลลง template
            $textObj = $bpac->GetObject("txtLabel");
            if ($textObj) {
                $textObj->Text = $label;
            }
            
            // พิมพ์ 1 label
            // bpoCutAtEnd สำหรับชิ้นสุดท้าย
            $isLast = ($printed === $count - 1);
            $opt = $isLast ? 0x00000200 : 0x00000400; // cut at end vs chain
            
            $bpac->PrintOut(1, $opt);
            $printed++;
        }
        
        $bpac->EndPrint();
        $bpac->Close();
        $bpac = null;
        
        return [
            'success' => true,
            'message' => "✅ พิมพ์ต่อเนื่องสำเร็จ {$printed}/{$count} รายการ ไป PT-2730!",
            'printed' => $printed,
            'total' => $count,
            'printer' => $printerName ?: '(Default)'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'b-PAC Error: ' . $e->getMessage(),
            'fallback' => 'csv',
            'suggestion' => 'ใช้โหมด P-touch Editor + CSV แทน'
        ];
    }
}

function createTemplate($tempDir, $tapeWidth, $fontSize, $fontName, $fontBold) {
    // สร้าง .lbx template (ZIP ที่มี XML ข้างใน)
    $templatePath = $tempDir . '/bpac_template.lbx';
    
    // Tape dimensions (mm to pt: 1mm ≈ 2.835pt)
    $tapeSpecs = [
        6  => ['heightPt' => 17.0,  'printPt' => 10.0],
        9  => ['heightPt' => 25.5,  'printPt' => 18.4],
        12 => ['heightPt' => 34.0,  'printPt' => 25.5],
        18 => ['heightPt' => 51.0,  'printPt' => 39.7],
        24 => ['heightPt' => 68.0,  'printPt' => 51.0],
    ];
    $spec = $tapeSpecs[$tapeWidth] ?? $tapeSpecs[12];
    
    $labelWidth = 283; // ~100mm in pt (เพิ่มความยาวเป็น 100mm รองรับรหัสยาวๆ)
    $labelHeight = $spec['heightPt'];
    $textHeight = $spec['printPt'];
    $fontWeight = $fontBold ? 'bold' : 'normal';
    
    $propXml = '<?xml version="1.0" encoding="UTF-8"?>
<pt:property xmlns:pt="http://schemas.brother.info/ptouch/2007/lbx/main"
    xmlns:style="http://schemas.brother.info/ptouch/2007/lbx/style"
    xmlns:text="http://schemas.brother.info/ptouch/2007/lbx/text">
    <pt:application>P-touch Editor</pt:application>
    <pt:version>5.4</pt:version>
</pt:property>';
    
    $labelXml = '<?xml version="1.0" encoding="UTF-8"?>
<pt:label xmlns:pt="http://schemas.brother.info/ptouch/2007/lbx/main"
    xmlns:style="http://schemas.brother.info/ptouch/2007/lbx/style"
    xmlns:text="http://schemas.brother.info/ptouch/2007/lbx/text"
    xmlns:draw="http://schemas.brother.info/ptouch/2007/lbx/draw">
    <pt:body currentLanguage="th" mediaType="continuousLength">
        <style:sheet width="' . $labelWidth . '" height="' . $labelHeight . '" 
            orientation="landscape" marginTop="1" marginBottom="1" marginLeft="3" marginRight="3"/>
        <pt:objects>
            <pt:text name="txtLabel">
                <pt:objectStyle x="3" y="1" width="' . ($labelWidth - 6) . '" height="' . ($textHeight) . '"/>
                <text:ptTextData>
                    <text:textStyle align="center" verticalAlignment="middle"/>
                    <text:stringItem>
                        <text:string>SDU.XX.XX.XX.XX.X</text:string>
                        <text:fontInfo>
                            <text:font charset="222" name="' . htmlspecialchars($fontName) . '"/>
                            <text:fontSize val="' . $fontSize . '"/>
                            <text:fontWeight val="' . $fontWeight . '"/>
                        </text:fontInfo>
                    </text:stringItem>
                </text:ptTextData>
            </pt:text>
        </pt:objects>
    </pt:body>
</pt:label>';
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($templatePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $zip->addFromString('prop.xml', $propXml);
        $zip->addFromString('label.xml', $labelXml);
        $zip->close();
        return $templatePath;
    } catch (Exception $e) {
        return false;
    }
}
