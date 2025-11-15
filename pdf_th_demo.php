<?php
require __DIR__ . '/vendor/autoload.php';

$fontDir = __DIR__ . '/assets/fonts'; // เปลี่ยนมาใช้ assets/fonts
$tempDir = __DIR__ . '/tmp';

$fontdata = [
  'sarabun' => [
    'R'  => 'THSarabunNew.ttf',
    'B'  => 'THSarabunNew-Bold.ttf',
    'I'  => 'THSarabunNew-italic.ttf', // แก้ชื่อไฟล์ให้ตรงกับที่มีอยู่
    'BI' => 'THSarabunNew-BoldItalic.ttf',
  ],
];

$defaultFont = file_exists("$fontDir/THSarabunNew.ttf") ? 'sarabun' : 'dejavusans';

$mpdf = new \Mpdf\Mpdf([
  'tempDir' => $tempDir,
  'fontDir' => [$fontDir],
  'fontdata' => $fontdata,
  'default_font' => $defaultFont,
]);

$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont = true;

$html = '<h1>ทดสอบ PDF ภาษาไทย</h1><p>สวัสดีครับ นี่คือการทดสอบการแสดงผลภาษาไทยด้วย mPDF และฟอนต์ THSarabunNew.</p>';
$mpdf->WriteHTML($html);

$outFile = __DIR__ . '/tmp/th_demo.pdf';
$mpdf->Output($outFile, \Mpdf\Output\Destination::FILE);
echo "Generated: $outFile\n";
