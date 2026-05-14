# PDF Export Checklist

ใช้ checklist นี้ทุกครั้งก่อนแก้ PDF export, HTML-to-PDF, Dompdf table, Excel-like report หรือรายงานที่สร้างจาก PHP string

## First Check: Table Structure

ก่อนแก้ CSS ให้ตรวจ HTML structure ก่อนเสมอ

- นับจำนวน `<th>` ใน header
- นับจำนวน `<td>` ใน data row ทุกชนิด
- ตรวจว่า `colspan` รวมกันเท่ากับจำนวน column จริง
- ตรวจ empty state row เช่น `ไม่พบข้อมูล`
- ตรวจ total/footer row เช่น `รวม`
- ตรวจว่ามี `<tr>`, `<td>`, `<th>`, `<thead>`, `<tbody>` ปิดครบ
- ถ้า table มี 6 columns ทุก row ต้องรวมกันได้ 6 columns เท่านั้น

ตัวอย่างที่ผิด:

```php
$html .= '<th>วันที่</th><th>ลาน</th><th>เลขที่สมาชิก</th><th>ชื่อสมาชิก</th><th>กลุ่ม</th><th>กระสอบ</th>';
$html .= '<td colspan="5">รวม</td>';
$html .= '<td>77</td>';
$html .= '<td colspan="2"></td>'; // ผิด: เกินจำนวน column
```

ตัวอย่างที่ถูก:

```php
$html .= '<th>วันที่</th><th>ลาน</th><th>เลขที่สมาชิก</th><th>ชื่อสมาชิก</th><th>กลุ่ม</th><th>กระสอบ</th>';
$html .= '<td colspan="5">รวม</td>';
$html .= '<td>77</td>';
```

## Common Dompdf Symptoms

ถ้าเจออาการเหล่านี้ ให้กลับไปเช็ก table structure ก่อน:

- เส้นตารางเหมือนขาด
- เส้นขอบซ้าย/ขวาหายบางจุด
- column กว้างแปลก
- total row ดันตารางเพี้ยน
- cell background ไม่เต็มช่อง
- ข้อความเหมือนล้นหรือโดนบีบโดยไม่มีเหตุผล

## CSS Checks After Structure Is Correct

ค่อยตรวจ CSS เมื่อมั่นใจว่า HTML ถูกแล้ว:

- `border-collapse`
- `table-layout`
- `width`
- `padding`
- `line-height`
- `font-size`
- `white-space`
- margin ของ table ใกล้ขอบกระดาษ

สำหรับ Dompdf:

```css
table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

th,
td {
  border: 1px solid #c7ead0;
}
```

หลีกเลี่ยงใน PDF ถ้าไม่จำเป็น:

- `border-radius` บน table cell
- nested card/div หลายชั้นใน table
- negative margin
- `border-spacing` เมื่ออยากได้เส้นตารางที่ต่อสนิท
- CSS ที่ browser render ได้แต่ Dompdf support ไม่ดี

## Font Rules

สำหรับรายงาน PDF ภาษาไทย:

- ใช้ `Sarabun` เป็นหลัก
- มี fallback เป็น local font ใน `assets/fonts`
- ตั้ง `defaultFont` ของ Dompdf ให้ตรงกับ font หลัก

ตัวอย่าง:

```php
$options->set('defaultFont', 'Sarabun');
```

```css
@font-face {
  font-family: "Sarabun";
  font-weight: 400;
  src: url("assets/fonts/Sarabun-Regular.ttf") format("truetype");
}
```

## Compact Report Layout

เมื่อต้องการให้หนึ่งหน้าใส่ข้อมูลได้มากขึ้น:

- ลด `@page margin` อย่างระวัง
- ลด padding ใน `th`/`td`
- ใช้ `line-height` กระชับ
- กำหนด column width
- ตัด column ที่ไม่จำเป็นออก
- ย้าย metadata เช่น `พิมพ์เมื่อ` ไปไว้ header แทน footer

## Required Verification

ก่อนตอบว่างานเสร็จ:

- รัน `php -l export_file.php`
- ตรวจ generated HTML table ด้วยสายตา
- นับ column ของ header/data/empty/total row
- ถ้าแก้ PDF layout ให้ render PDF test ด้วย Dompdf ถ้าทำได้
- ถ้ามี Poppler ให้ render เป็น PNG แล้วดูภาพจริง

## Quick Column Count Template

ใช้ template นี้ตอนตรวจ:

```text
Table:
- Header columns: __
- Data row columns: __
- Empty row colspan: __
- Total row colspan + td: __
- Expected columns: __
- Result: PASS/FAIL
```
