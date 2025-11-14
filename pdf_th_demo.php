<?php
require __DIR__ . '/vendor/autoload.php';

$fontDir = __DIR__ . '/fonts';
$tempDir = __DIR__ . '/tmp';

$fontdata = [
  'sarabun' => [
    'R' => 'THSarabunNew.ttf',
    'B' => 'THSarabunNew-Bold.ttf',
    'I' => 'THSarabunNew-Italic.ttf',
    'BI' => 'THSarabunNew-BoldItalic.ttf',
  ],
  'noto-thai' => [
    'R' => 'NotoSansThai-Regular.ttf',
    'B' => 'NotoSansThai-Bold.ttf',
  ],
];

$defaultFont = file_exists("$fontDir/THSarabunNew.ttf")
  ? 'sarabun'
  : (file_exists("$fontDir/NotoSansThai-Regular.ttf") ? 'noto-thai' : 'dejavusans');

$mpdf = new \Mpdf\Mpdf([
  'tempDir' => $tempDir,
  'fontDir' => [$fontDir],
  'fontdata' => $fontdata,
  'default_font' => $defaultFont,
]);

$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont = true;

$html = '<h1>ทดสอบ PDF ภาษาไทย</h1><p>สวัสดีครับ นี่คือการทดสอบการแสดงผลภาษาไทยด้วย mPDF.</p>';
$mpdf->WriteHTML($html);

$outFile = __DIR__ . '/tmp/th_demo.pdf';
$mpdf->Output($outFile, \Mpdf\Output\Destination::FILE);
echo "Generated: $outFile\n";
