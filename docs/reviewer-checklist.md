# Reviewer Checklist

ใช้ checklist นี้ก่อนสรุปงานทุกครั้ง โดยเฉพาะงานที่แก้ PHP, PDF export, UI หรือไฟล์ที่กระทบผู้ใช้จริง

## Review Mindset

- หา bug ก่อนชมงาน
- ตรวจ behavior ก่อน visual polish
- ตรวจ structure ก่อน CSS
- ตรวจ scope ว่าไม่ได้แก้เกินโจทย์
- ถ้างานเกี่ยวกับ PDF/table ให้ใช้ `docs/pdf-export-checklist.md` เสมอ
- ถ้างานเกี่ยวกับ UI ให้ใช้ `docs/ui-guidelines.md` เสมอ

## PHP Checklist

- รัน `php -l file.php`
- ไม่มี redeclare function ซ้ำกับ `functions.php`
- ใช้ `require_once` เมื่อ include helper
- ตรวจ admin/auth guard ถ้าเป็นหน้า admin
- ตรวจ input จาก `$_GET`/`$_POST` ว่ามี validation
- ตรวจ SQL prepare/bind เมื่อมี user input
- ตรวจ redirect/header ก่อนมี output
- ตรวจ encoding ภาษาไทยเป็น UTF-8

## Export/PDF Checklist

- นับ column ของ table ทุกแถว
- ตรวจ `colspan` ใน empty row และ total row
- ตรวจว่า HTML string ปิด tag ครบ
- ตรวจ filename export
- ตรวจ header download
- ตรวจ font ไทยใน PDF
- ตรวจว่า PDF/Excel ใช้ข้อมูล filter เดียวกัน
- ตรวจว่า query ไม่ดึงข้อมูลเกิน scope ที่ผู้ใช้เลือก

## UI Checklist

- ใช้ pattern จาก `docs/ui-guidelines.md`
- ใช้ Bootstrap 5.3 dashboard style
- card spacing อ่านง่าย ไม่ชิดขอบ
- mobile stack ถูกต้อง
- action buttons กว้างเต็มแถวบนมือถือเมื่อจำเป็น
- input/select สูงอย่างน้อย 44px
- ใช้ lucide icon ใน action สำคัญ
- ไม่มี card ซ้อน card โดยไม่จำเป็น

## Diff Checklist

ก่อนจบงานให้ดู diff:

- ไฟล์ที่เปลี่ยนตรงกับโจทย์หรือไม่
- มี unrelated changes หรือไม่
- มี debug code, console log, var_dump, print_r ค้างหรือไม่
- มี hardcoded path หรือข้อมูลส่วนตัวหรือไม่
- มี syntax issue จาก quote/string concatenation หรือไม่
- มี function/helper ใหม่ที่ชื่อชนกับของเดิมหรือไม่

## Regression Checklist

ถามตัวเองก่อนตอบ final:

- งานเดิมยังทำได้ไหม
- ปุ่ม/ฟอร์มเดิมยังส่งค่าเดิมไหม
- query ยังคืนข้อมูลตาม filter เดิมไหม
- export PDF และ Excel ยังใช้ parameter เดิมไหม
- mobile layout ยังใช้งานได้ไหม

## Final Response Checklist

ในคำตอบ final:

- บอกไฟล์ที่แก้
- บอกสิ่งที่แก้แบบสั้นและตรง
- บอกผล verification เช่น `php -l`
- ถ้ายังไม่ได้ทดสอบบางอย่าง ต้องพูดตรง ๆ
- อย่าพูดมั่นใจเกินผลตรวจจริง

## Stop Rule

ถ้าพบอาการผิดปกติที่แก้หลายรอบแล้วยังไม่หาย:

- หยุดไล่เดาสุ่ม
- กลับไปตรวจ structure/checklist
- ระบุสมมติฐานใหม่ก่อนแก้
- ถ้างานเป็น table/PDF ให้เริ่มจาก column count ใหม่ทันที
