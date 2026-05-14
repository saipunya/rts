# RTS UI Guidelines

แนวทางนี้ใช้เป็นมาตรฐาน UI สำหรับโปรเจกต์ RTS โดยเฉพาะหน้าหลังบ้าน, dashboard, form, report และ export pages

## Design Direction

- ใช้แนว **Bootstrap 5.3 dashboard style** เป็นหลัก
- หน้าระบบหลังบ้านควรดูนิ่ง, อ่านง่าย, ทำงานซ้ำได้เร็ว และไม่เหมือน landing page
- เลี่ยง hero ใหญ่, กล่องลอยซ้อนกันหลายชั้น, gradient หนัก ๆ หรือ layout ที่ดูเป็นหน้าโปรโมต
- ให้ความสำคัญกับ spacing, card hierarchy, form ergonomics และ mobile-first behavior

## Page Structure

ใช้โครงนี้เป็นค่าเริ่มต้นสำหรับหน้า dashboard/tool/report/export:

```html
<main class="container my-4">
  <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3">
    <div>
      <h1 class="h4 mb-0"><i data-lucide="..." class="me-2"></i>ชื่อหน้า</h1>
      <div class="small text-muted">คำอธิบายสั้นหรือข้อมูลผู้ใช้</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-success btn-sm">Action</a>
      <a class="btn btn-outline-success btn-sm">Secondary</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card h-100 shadow-sm">
        <div class="card-body p-3 p-md-4">
          ...
        </div>
      </div>
    </div>
  </div>
</main>
```

## Core Components

### Dashboard Cards

- ใช้ `.card shadow-sm` เป็นพื้นฐาน
- มุมโค้งประมาณ `.9rem` ถึง `1rem`
- ใช้ `border-color: rgba(47, 110, 67, 0.12)`
- ใช้ `card-body p-3 p-md-4` สำหรับพื้นที่หายใจ
- ใช้ `h5.card-title` สำหรับหัวข้อ card
- ใช้ `small text-muted` สำหรับคำอธิบายรอง

### Summary Cards

ใช้ grid สำหรับตัวเลขสรุป:

```css
.dashboard-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .75rem;
}

.dashboard-summary-item {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  border: 1px solid #d7f3de;
  border-radius: 1rem;
  background: #f8fdf8;
  padding: 1rem;
  min-height: 92px;
}
```

- บน tablet ใช้ 2 columns
- บนมือถือใช้ 1 column
- ควรมี icon block ด้านขวาเพื่อเพิ่มน้ำหนักภาพ

### Forms

- Form ใน dashboard ควรอยู่ใน card เดียว ไม่ควรกระจัดกระจาย
- ใช้ `row g-3`
- input/select ต้องสูงอย่างน้อย `44px`
- label ใช้ `form-label mb-1`
- กลุ่ม field ที่สำคัญสามารถครอบด้วย `.field-panel`

```css
.field-panel {
  padding: .85rem;
  border: 1px solid #edf5ef;
  border-radius: .85rem;
  background: #fbfefc;
}
```

### Buttons

- Primary action: `btn btn-success btn-sm`
- Secondary action: `btn btn-outline-success btn-sm`
- Neutral navigation: `btn btn-outline-secondary btn-sm`
- ปุ่มที่มี action สำคัญควรมี lucide icon
- บนมือถือ ปุ่มใน action group ควรกว้างเต็มแถว

## Color Tokens

ใช้สีหลักเหล่านี้ให้สม่ำเสมอ:

```css
--rts-green-900: #173d26;
--rts-green-800: #245c38;
--rts-green-700: #2f6e43;
--rts-green-500: #68ae7a;
--rts-green-100: #e8f4eb;
--rts-green-050: #f8fdf8;
--rts-border: rgba(47, 110, 67, 0.12);
```

## Icons

- ใช้ Lucide icons สำหรับปุ่ม, card title, summary card และ state ที่สำคัญ
- icon ในหัวข้อใช้ `class="me-2"`
- icon block ใช้ขนาดประมาณ `2.35rem` ถึง `2.7rem`
- อย่าใช้ icon เยอะจนแย่งความสนใจจากข้อมูลหลัก

## Mobile Rules

- ทุกหน้าใหม่ต้องอ่านง่ายที่ viewport มือถือก่อน
- action buttons stack เป็นแนวตั้งและกว้างเต็มแถว
- summary cards เปลี่ยนเป็น 1 column ที่ `max-width: 576px`
- form fields เปลี่ยนเป็น full width
- card body ใช้ `padding: 1rem` บนมือถือ
- หลีกเลี่ยงข้อความยาวในปุ่ม ถ้ายาวให้ขึ้นบรรทัดหรือใช้คำสั้นลง

## What To Avoid

- หน้า tool/report/export ที่ดูเหมือน landing page
- hero card ใหญ่เกินจำเป็น
- card ซ้อน card หลายชั้น
- padding ชิดขอบจนเหมือน UI ยังไม่เสร็จ
- ปุ่มไม่มี icon ใน action สำคัญ
- สีเขียวหลายเฉดแบบสุ่มจนไม่เป็นระบบ
- gradient หนัก, glow, decorative blob หรือ effect ที่ไม่ช่วยงานจริง

## Reference Page

ใช้ `export_wang.php` เวอร์ชัน dashboard module เป็น reference สำหรับหน้า export/report ที่ต้องมี:

- page heading + actions
- dashboard hero card
- summary grid
- form card
- current condition card
- responsive stack บนมือถือ

ก่อนปรับ UI หน้าใหม่ ให้เปิดไฟล์นี้เทียบกับ `dashboard.php` และ guideline นี้ก่อนเสมอ
