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

# การติดตั้งฟอนต์ภาษาไทยสำหรับ dompdf

เพื่อให้ PDF แสดงภาษาไทยถูกต้อง ให้เพิ่มฟอนต์ TH Sarabun New แบบ TTF ไว้ในโปรเจกต์:

1) สร้างโฟลเดอร์ฟอนต์: `C:\xampp\htdocs\rts\fonts`
2) คัดลอกไฟล์ฟอนต์ลงไป (อย่างน้อย Regular และ Bold):
   - `THSarabunNew.ttf`
   - `THSarabunNew-Bold.ttf`
   แหล่งดาวน์โหลดตัวอย่าง: https://github.com/cadsondemak/sarabun/releases หรือจากชุดฟอนต์ราชการ (OFL)
3) เปิดหน้า `http://localhost/rts/export_rubbers_pdf.php?lan=all`
   - หากยังไม่พบฟอนต์ จะมีข้อความแจ้งและใช้ DejaVu Sans แทน
4) ถ้ายังไม่แสดงภาษาไทย ตรวจสอบ:
   - dompdf ใช้ `chroot` เป็น `C:\xampp\htdocs\rts` และไฟล์ฟอนต์อยู่ใน `C:\xampp\htdocs\rts\fonts`
   - ไฟล์ฟอนต์สามารถอ่านได้โดย Apache (สิทธิ์ไฟล์)
   - เปิดใช้งาน `mbstring` และรีสตาร์ท Apache

หมายเหตุ: โค้ดได้ฝัง @font-face เพื่อใช้งานฟอนต์ THSarabunNew โดยอัตโนมัติเมื่อไฟล์อยู่ในโฟลเดอร์ `/fonts` เดียวกับโปรเจกต์

หมายเหตุ: หากมีตัวอักษรไทยไม่ครบ ให้ใช้ฟอนต์ `DejaVu Sans` (dompdf มีให้ในตัว) หรือเพิ่มฟอนต์ TH Sarabun แล้วกำหนดใน CSS

## ทำไมใช้ Google Fonts กับ dompdf ไม่ได้โดยตรง

- dompdf รองรับเฉพาะฟอนต์แบบ TTF/OTF
- Google Fonts ส่วนใหญ่ส่งไฟล์แบบ `woff2` ผ่าน CSS (`@font-face`) ซึ่ง dompdf ไม่รองรับ
- ถึงจะเปิด `isRemoteEnabled=true` ก็ยังไม่สามารถใช้ `woff/woff2` ได้

วิธีที่แนะนำ:
1) ใช้ไฟล์ฟอนต์ TTF แบบโลคอล วางไว้ที่ `C:\xampp\htdocs\rts\fonts`
   - ตัวอย่าง: `THSarabunNew.ttf`, `THSarabunNew-Bold.ttf`
   - หรือ `NotoSansThai-Regular.ttf`, `NotoSansThai-Bold.ttf`
2) โค้ดได้ฝัง `@font-face` อัตโนมัติเมื่อพบไฟล์ในโฟลเดอร์ `/fonts`
3) ตั้งค่า defaultFont เป็นฟอนต์ไทยอัตโนมัติ หากไม่พบจะ fallback เป็น `DejaVu Sans`

แหล่งโหลดตัวอย่าง:
- TH Sarabun New (OFL): ชุดราชการ/โครงการโอเพนซอร์ส
- Noto Sans Thai (OFL): https://github.com/googlefonts/noto-fonts/tree/main/hinted/ttf/NotoSansThai

หมายเหตุ: หลีกเลี่ยงการแปลง `woff2` เป็น `ttf` ด้วยตัวเองเพราะอาจมีปัญหาคุณภาพ/ลิขสิทธิ์ ให้ใช้ไฟล์ TTF ที่เผยแพร่ภายใต้ OFL เท่านั้น
