<?php
require __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Show PHP errors for debugging
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Configure Dompdf with Options (enable remote to allow file:// URLs)
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Ensure tmp output directory exists and is writable
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

// Resolve font file paths and create file:// URLs (fallback to relative if not found)
$regPath = realpath(__DIR__ . '/assets/fonts/Sarabun-Regular.ttf');
$boldPath = realpath(__DIR__ . '/assets/fonts/Sarabun-Bold.ttf');
$regUrl = $regPath ? 'file://' . $regPath : 'assets/fonts/Sarabun-Regular.ttf';
$boldUrl = $boldPath ? 'file://' . $boldPath : 'assets/fonts/Sarabun-Bold.ttf';

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dompdf Thai Demo</title>
  <style>
    /* Use local TTF fonts (dompdf supports TTF/OTF). Adjust path if your fonts are in /fonts instead of /assets/fonts */
    @font-face {
      font-family: 'Sarabun';
      src: url('{$regUrl}') format('truetype');
      font-weight: 400;
      font-style: normal;
    }
    @font-face {
      font-family: 'Sarabun';
      src: url('{$boldUrl}') format('truetype');
      font-weight: 700;
      font-style: normal;
    }

    body { font-family: 'Sarabun', DejaVu Sans, sans-serif; font-size: 14pt; }
    h1 { font-weight: 700; margin-bottom: 0.5em; }
    p { margin: 0.4em 0; }
  </style>
</head>
<body>
  <h1>ทดสอบการส่งออก PDF ด้วย Dompdf</h1>
  <p>สวัสดีครับ/ค่ะ นี่คือเอกสารตัวอย่างภาษาไทยที่สร้างด้วย Dompdf โดยไม่ใช้ mPDF</p>
  <p>รองรับฟอนต์ Sarabun จากโฟลเดอร์ assets/fonts</p>
  <p>หน้าเอกสาร: A4 แนวตั้ง</p>
</body>
</html>
HTML;

try {
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $outFile = $tmpDir . '/dompdf_th_demo.pdf';
    if (file_put_contents($outFile, $dompdf->output()) === false) {
        throw new RuntimeException("Failed to write output to {$outFile}");
    }
    echo "Wrote: {$outFile}\n";
} catch (Exception $e) {
    // Make sure exceptions are visible when running the script
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
