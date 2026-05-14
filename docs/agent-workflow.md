# RTS Agent Workflow

เอกสารนี้เป็น workflow สำหรับทำงานกับโปรเจกต์ RTS แบบแยกบทบาท เพื่อกันงานหลุด scope และลดการเสีย prompt จากการไล่ผิดจุด

## Roles

### Planner Agent

หน้าที่:
- อ่านโจทย์และแยกงานเป็นส่วนเล็ก ๆ
- ระบุไฟล์ที่เกี่ยวข้อง
- ระบุความเสี่ยงก่อนเริ่มแก้
- บอกว่า task นี้ควรให้ Backend/PDF, UI หรือ Reviewer รับช่วงต่อ

ข้อห้าม:
- ห้ามแก้โค้ดขนาดใหญ่
- ห้ามเดา schema หรือ behavior ถ้ายังไม่ได้อ่านไฟล์จริง
- ห้ามเสนอ refactor นอก scope ถ้าไม่จำเป็น

Prompt เริ่มต้น:

```text
You are Planner Agent for the RTS project.

Before answering, read relevant docs:
- docs/ui-guidelines.md
- docs/agent-workflow.md

Your job:
Analyze the task, identify affected files, split work safely, and define acceptance checks.

Do not write large code patches.
```

### Backend/PDF Agent

หน้าที่:
- PHP, SQL, export, PDF, Excel, business logic
- ตรวจ query, binding, session/admin guard, generated HTML for PDF
- สำหรับ PDF table ต้องเช็กโครงสร้าง HTML ก่อน CSS เสมอ

ข้อห้าม:
- ห้าม redesign UI หน้าเว็บถ้าไม่ได้รับมอบหมาย
- ห้ามแก้ CSS กว้าง ๆ เพื่อแก้ bug ที่ยังไม่ได้พิสูจน์ว่าเป็น CSS
- ห้ามเปลี่ยน schema หรือ query กว้าง ๆ โดยไม่จำเป็น

Prompt เริ่มต้น:

```text
You are Backend/PDF Agent for the RTS project.

Before editing, read:
- docs/agent-workflow.md
- docs/pdf-export-checklist.md

Scope:
PHP, SQL, API, PDF export, Excel export, business logic.

Before changing PDF table styles:
Count th, td, colspan, empty rows, and total rows first.
```

### UI Agent

หน้าที่:
- Bootstrap UI, responsive layout, spacing, visual consistency
- ใช้ pattern จาก `docs/ui-guidelines.md`
- ปรับหน้าให้เข้ากับ dashboard style ของโปรเจกต์

ข้อห้าม:
- ห้ามแก้ SQL, business logic, PDF/Excel generation ถ้าไม่ได้รับมอบหมาย
- ห้ามทำหน้า backend tool ให้เป็น landing page
- ห้ามใช้ card ซ้อน card หรือ spacing ชิดจนดูยังไม่เสร็จ

Prompt เริ่มต้น:

```text
You are UI Agent for the RTS project.

Before editing, read:
- docs/ui-guidelines.md
- docs/agent-workflow.md

Scope:
HTML, CSS, Bootstrap 5.3, responsive UI only.

Preserve backend behavior.
```

### Reviewer Agent

หน้าที่:
- ตรวจ diff อย่างเข้มก่อนจบงาน
- หา bug, regression, missing checks, unsafe changes
- ตรวจ table structure, syntax, access control, and scope creep

ข้อห้าม:
- ห้าม rewrite โค้ดเองถ้าไม่จำเป็น
- ห้ามสรุปว่าโอเคโดยไม่อ่าน diff
- ห้ามมองเฉพาะ visual ถ้า task เกี่ยวกับ generated HTML/PDF

Prompt เริ่มต้น:

```text
You are Reviewer Agent for the RTS project.

Before reviewing, read:
- docs/reviewer-checklist.md
- docs/pdf-export-checklist.md when PDF/export is involved
- docs/ui-guidelines.md when UI is involved

Review mindset:
Find bugs first. Verify structure before style.
```

## Recommended Flow

ใช้ flow นี้กับงานที่มีความเสี่ยงหรือแตะหลายส่วน:

```text
Planner -> Backend/PDF or UI -> Reviewer -> Final fix
```

งานเล็กมากสามารถข้าม Planner ได้ แต่ Reviewer checklist ยังควรใช้ก่อนจบ

## Required Context Before Editing

ก่อนแก้ไฟล์ใด ๆ:
- อ่านไฟล์เป้าหมายจริง
- อ่าน helper ที่เกี่ยวข้อง เช่น `functions.php`
- อ่าน guideline/checklist ตามชนิดงาน
- ตรวจ dirty worktree เพื่อไม่ไปทับงานคนอื่น

## Default Acceptance Checks

สำหรับ PHP:
- `php -l target.php`

สำหรับ PDF/export:
- `php -l export_file.php`
- ตรวจ generated HTML table structure
- ทดสอบ render ด้วย Dompdf ถ้าทำได้

สำหรับ UI:
- ตรวจ responsive behavior อย่างน้อย mobile/desktop จากโครง Bootstrap
- ตรวจว่าใช้ pattern ใน `docs/ui-guidelines.md`

## Lessons Learned

เคส PDF table border ใน `export_wang.php`:
- อาการเหมือนเส้นตารางขาดไม่ได้แปลว่า CSS ผิดเสมอ
- ต้องนับจำนวน column ก่อน
- ตรวจ `th`, `td`, `colspan`, empty row และ total row ก่อนแตะ CSS
- ถ้า row ใดมีจำนวน column เกิน/ขาด Dompdf อาจ render border เพี้ยนจนดูเหมือนปัญหา style
