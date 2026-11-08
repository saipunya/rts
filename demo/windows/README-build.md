# สร้างชุดติดตั้ง RTS Demo

ต้องมี Python 3 และ NSIS (makensis อยู่ใน PATH) สร้างบน macOS/Linux ได้ เพราะ NSIS สร้างไฟล์ติดตั้ง Windows ได้โดยไม่ต้องรัน Windows binaries ขณะ build

```sh
python3 demo/windows/build.py
```

สคริปต์ดาวน์โหลด runtime จาก PHP, MariaDB และ Microsoft ตรวจ SHA256 เตรียมไฟล์หน้าเว็บออฟไลน์ รวมใบอนุญาตและซอร์ส MariaDB แล้วคอมไพล์ NSIS ผลลัพธ์อยู่ที่ `demo/dist/RTS-Demo-Setup-x64.exe` พร้อม checksum และคู่มือภาษาไทย ในการ build ครั้งแรกต้องใช้อินเทอร์เน็ต มีพื้นที่ว่างแนะนำ 3 GB ขึ้นไป

`runtime-lock.json` เก็บ URL และ hash ที่เลือกไว้ ห้ามเปลี่ยนเวอร์ชัน runtime โดยไม่ทดสอบการติดตั้งและข้อมูลเดิม Microsoft runtime ใช้ permalink ที่อาจเปลี่ยนไฟล์เมื่อ Microsoft อัปเดต หากล้าง cache แล้ว hash ไม่ตรง สคริปต์จะหยุด ต้องตรวจการอัปเดตจากแหล่งทางการและเปลี่ยน lock อย่างตั้งใจก่อน build ใหม่

ตัวติดตั้งบน Windows ตรวจลายเซ็น Microsoft ของ vc_redist ก่อนเรียกใช้ ใช้สิทธิ์ผู้ดูแลเพื่อติดตั้งใน Program Files และ runtime ส่วนการเปิดใช้แอปประจำวันใช้สิทธิ์ปกติ ข้อมูลและสำรองอยู่ใน LocalAppData แยกแต่ละบัญชี ไม่ติดตั้ง MariaDB เป็น service

## ทดสอบในเครื่องพัฒนา

ต้องมี PHP พร้อม mysqli, mbstring, dom, gd และคำสั่ง MariaDB สำหรับสร้าง instance ชั่วคราว:

```sh
python3 demo/windows/test_demo.py
pwsh -NoProfile -Command '$files=Get-ChildItem demo/windows/*.ps1; foreach($f in $files){$e=$null;$t=$null;[System.Management.Automation.Language.Parser]::ParseFile($f.FullName,[ref]$t,[ref]$e)>$null;if($e.Count){$e;exit 1}}'
```

การทดสอบใช้ temporary directory และฐานข้อมูลแยก ไม่ใช้ config ระบบจริง และนำชุดทดสอบขั้นตอนงานเดิมมารันกับ demo ถ้ามีอยู่ใน repository

## ตรวจรับบน Windows ก่อนแจก

ใช้ VM หรือเครื่อง Windows x64 ที่ไม่มี PHP/MySQL ติดตั้งตัวจริงและทำตามรายการตรวจรับในคู่มือ รวมกรณีชื่อบัญชี Windows ภาษาไทย พอร์ตชน เปิดซ้ำ หยุดระบบ สำรอง/กู้คืน ออฟไลน์ และถอนติดตั้ง เก็บ Windows version กับผลตรวจไว้ใน VALIDATION.md

ตัวติดตั้งยังไม่ลงลายเซ็นผู้พัฒนา และไม่มีระบบ license/วันหมดอายุ ไม่ควรถือว่าการ build ผ่านแทนการตรวจรับ Windows ได้

หลัง build ตรวจ payload และ checksum ได้ด้วย:

```sh
python3 demo/windows/verify_package.py
```

## กุญแจออกรหัสและรุ่นทดลอง 60 วัน

รุ่น 0.2.0 ต้องมี `demo/license_public.pem` ก่อน build กุญแจจริงสร้างไว้แล้วใน `demo/developer-tools/private/` ต้องเก็บและสำรองไว้เฉพาะผู้พัฒนา โดย build รวมเฉพาะ public key และไม่รวม developer-tools ทั้งโฟลเดอร์

ทดสอบกฎสิทธิ์ใช้งานและการออกรหัสโดยใช้กุญแจชั่วคราว:

```sh
php demo/tests/license_test.php
```

`test_demo.py` จะสร้างกุญแจทดสอบแยก ไม่ใช้ private key ของผู้พัฒนา แล้วตรวจ HTTP ขณะหมดอายุ การป้องกัน POST, CSRF, ข้อมูลคงอยู่ และการกลับมาใช้งานด้วยรหัสที่ลงลายเซ็น

ข้อมูล trial แยกจาก SQL backup ที่ RTSDemoLicense ใน LocalAppData ตัวเปิดระบบใช้ Windows MachineGuid แบบ hash และ checkpoint ที่เข้ารหัสด้วย DPAPI ใน HKCU เพื่อรักษาวันเริ่มเดิมเมื่อติดตั้งใหม่ตามปกติ การทำงานของ Windows registry/DPAPI ยังต้องตรวจบน Windows จริง
