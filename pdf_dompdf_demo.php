<?php
require __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Show PHP errors for debugging
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Configure Dompdf with Options (enable remote to allow file:// URLs)
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->setChroot(__DIR__); // ให้โหลดไฟล์ภายในโฟลเดอร์นี้ได้
// ปิดการ subset ฟอนต์ (ลดปัญหาตัวอักษรไทยหาย/เป็นเครื่องหมายคำถาม)
$options->set('isFontSubsettingEnabled', false);
$options->set('enable_font_subsetting', false);
$options->set('defaultFont', 'THSarabunNew');
$dompdf = new Dompdf($options);

// Ensure tmp output directory exists and is writable
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

// ตรวจสอบไฟล์ฟอนต์
$fontDir = __DIR__ . '/assets/fonts';
$regular = $fontDir . '/THSarabunNew.ttf';
$bold    = $fontDir . '/THSarabunNew-Bold.ttf';
$italic  = $fontDir . '/THSarabunNew-italic.ttf';
$boldIt  = $fontDir . '/THSarabunNew-BoldItalic.ttf';
foreach ([$regular, $bold, $italic, $boldIt] as $f) {
    if (!is_file($f)) {
        die('ไม่พบฟอนต์: ' . basename($f));
    }
}

$regUrl = 'assets/fonts/THSarabunNew.ttf';
$boldUrl = 'assets/fonts/THSarabunNew-Bold.ttf';
$italicUrl = 'assets/fonts/THSarabunNew-italic.ttf';
$boldItUrl = 'assets/fonts/THSarabunNew-BoldItalic.ttf';

$html = <<<HTML
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>Dompdf Thai Demo</title>
  <style>
    @font-face {
      font-family: 'THSarabunNew';
      font-weight: 400;
      font-style: normal;
      src: url('$regUrl') format('truetype');
    }
    @font-face {
      font-family: 'THSarabunNew';
      font-weight: 700;
      font-style: normal;
      src: url('$boldUrl') format('truetype');
    }
    @font-face {
      font-family: 'THSarabunNew';
      font-weight: 400;
      font-style: italic;
      src: url('$italicUrl') format('truetype');
    }
    @font-face {
      font-family: 'THSarabunNew';
      font-weight: 700;
      font-style: italic;
      src: url('$boldItUrl') format('truetype');
    }

    body { font-family: 'THSarabunNew', 'DejaVu Sans', sans-serif; font-size: 14pt; }
    h1 { font-weight: 700; margin: 0 0 0.5em; }
    p { margin: 0.4em 0; }
  </style>
</head>
<body>
  <h1>ทดสอบการส่งออก PDF ด้วย Dompdf</h1>
  <p>สวัสดีครับ นี่คือเอกสารตัวอย่างภาษาไทย (ไม่มี ????? อีกต่อไป)</p>
  <p>ฟอนต์ที่ใช้: THSarabunNew (regular, bold, italic, bold italic)</p>
  <p>เวลา: {date('Y-m-d H:i:s')}</p>
</body>
</html>
HTML;

try {
    // ใช้ UTF-8 ชัดเจน
    $dompdf->loadHtml($html, 'UTF-8');
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
