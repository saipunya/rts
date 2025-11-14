# การติดตั้ง dompdf สำหรับ XAMPP (Windows)

- เปิด Command Prompt ที่โฟลเดอร์: `C:\xampp\htdocs\rts`
- ติดตั้ง: `composer require dompdf/dompdf`
- ตรวจสอบไฟล์: `C:\xampp\htdocs\rts\vendor\autoload.php` ต้องมีอยู่
- เปิดใช้งาน PHP extensions:
  - เปิด `C:\xampp\php\php.ini` แล้ว uncomment บรรทัด: `extension=mbstring` และ `extension=gd`
  - รีสตาร์ท Apache ใน XAMPP
- ทดสอบเปิด: `http://localhost/rts/export_rubbers_pdf.php?lan=all`
  - ถ้าขึ้นข้อความ error ให้ทำตามคำแนะนำในหน้านั้น
- โหมดดีบั๊ก: เปิด `http://localhost/rts/export_rubbers_pdf.php?lan=all&debug=1` เพื่อตรวจ HTML ก่อนเรนเดอร์ PDF (ช่วยหาสาเหตุ error 500)

หมายเหตุ: หากมีตัวอักษรไทยไม่ครบ ให้ใช้ฟอนต์ `DejaVu Sans` (dompdf มีให้ในตัว) หรือเพิ่มฟอนต์ TH Sarabun แล้วกำหนดใน CSS
