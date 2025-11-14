<?php
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf([
  'isRemoteEnabled' => true, // allow Google Fonts
]);

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dompdf Thai Demo</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Sarabun', sans-serif; font-size: 14pt; }
    h1 { font-weight: 700; margin-bottom: 0.5em; }
    p { margin: 0.4em 0; }
  </style>
</head>
<body>
  <h1>ทดสอบการส่งออก PDF ด้วย Dompdf</h1>
  <p>สวัสดีครับ/ค่ะ นี่คือเอกสารตัวอย่างภาษาไทยที่สร้างด้วย Dompdf โดยไม่ใช้ mPDF</p>
  <p>รองรับฟอนต์จาก Google Fonts (Sarabun) ผ่านการโหลดแบบ remote</p>
  <p>หน้าเอกสาร: A4 แนวตั้ง</p>
</body>
</html>
HTML;

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outFile = __DIR__ . '/tmp/dompdf_th_demo.pdf';
file_put_contents($outFile, $dompdf->output());
echo "Wrote: {$outFile}\n";
