<?php
require_once 'functions.php';
require_login();
include 'header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@300;600;700&display=swap" rel="stylesheet">

<?php

// Handle form submission for updating person code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_person_code') {
    $mem_id = (int)($_POST['mem_id'] ?? 0);
    $person_code = trim($_POST['mem_personcode'] ?? '');
    
    if ($mem_id > 0 && !empty($person_code)) {
        // Validate person code is 4 digits
        if (strlen($person_code) === 4 && ctype_digit($person_code)) {
            $stmt = $mysqli->prepare("UPDATE tbl_member SET mem_personcode = ? WHERE mem_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $person_code, $mem_id);
                if ($stmt->execute()) {
                    $success_msg = "อัพเดทรหัสบุคคลเรียบร้อยแล้ว";
                } else {
                    $error_msg = "เกิดข้อผิดพลาดในการอัพเดท: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . $mysqli->error;
            }
        } else {
            $error_msg = "รหัสบุคคลต้องเป็นตัวเลข 4 ตัวเท่านั้น";
        }
    } else {
        $error_msg = "ข้อมูลไม่ครบถ้วน";
    }
}

// Search functionality - show all members initially, filter when searching
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$members = [];
$editing_member = null;

// Always load data - either all members or filtered results
if ($q !== '' && mb_strlen($q) >= 2) {
    // Search with query
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member WHERE mem_fullname LIKE ? OR mem_number LIKE ? OR mem_group LIKE ? ORDER BY mem_fullname ASC");
    if ($stmt) {
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Load all members when no search query or query is too short
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member ORDER BY mem_fullname ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Handle edit request
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $mysqli->prepare("SELECT mem_id, mem_group, mem_number, mem_fullname, mem_birthtext, mem_class, mem_personcode, mem_saveby, mem_savedate FROM tbl_member WHERE mem_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing_member = $result->fetch_assoc();
        $stmt->close();
    }
}
?>
<style>
/* Match existing page styles */
html, body {
  font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 16px;
  font-weight: 200;
}
.container,
.card,
.table,
.form-control,
.form-select,
.form-label,
.btn,
.btn-sm,
.nav-link,
.alert,
.badge {
  font-family: 'Noto Serif Thai', 'THSarabunNew', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-size: 1rem;
  font-weight: 200;
}
.small, .form-text {
  font-size: 1rem !important;
  font-weight: 200 !important;
}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
  .container.mt-4 {
    padding: 0 1rem;
  }
  
  h3 {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .container.mt-4 {
    padding: 0 0.5rem;
    margin-top: 1rem !important;
  }
  
  .row.mb-3 {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch !important;
    text-align: center;
  }
  
  .col-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
  }
  
  .col-6.text-end {
    text-align: center !important;
  }
  
  .col-6.text-end .btn {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
    min-height: 44px;
  }
  
  .row.mb-3 {
    margin-bottom: 1rem !important;
  }
  
  .row.g-2.position-relative {
    gap: 0.75rem;
  }
  
  .col-auto {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .form-control {
    min-height: 44px;
    font-size: 1rem;
  }
  
  .btn {
    min-height: 44px;
    font-size: 1rem;
  }
  
  .table-responsive {
    font-size: 0.85rem;
    margin: 0 -0.5rem;
    padding: 0 0.5rem;
  }
  
  .table th,
  .table td {
    padding: 0.5rem 0.3rem;
  }
  
  .table .btn-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.4rem;
    min-height: auto;
  }
  
  .alert {
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
    margin: 0 -0.5rem 1rem;
  }
}

@media (max-width: 576px) {
  .container.mt-4 {
    padding: 0 0.25rem;
  }
  
  h3 {
    font-size: 1.2rem;
  }
  
  .table-responsive {
    font-size: 0.8rem;
    padding: 0 0.25rem;
  }
  
  .table th,
  .table td {
    padding: 0.4rem 0.2rem;
  }
  
  .table th:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)),
  .table td:not(:first-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)) {
    display: none;
  }
  
  .table .btn-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.3rem;
  }
}

/* Landscape orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .container.mt-4 {
    margin-top: 0.5rem !important;
  }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
  .table-hover tbody tr:hover td {
    background-color: transparent;
  }
  
  .btn,
  .form-control {
    min-height: 44px;
  }
}

/* Person code input styling */
.person-code-input {
  font-family: monospace;
  font-size: 1.2rem;
  text-align: center;
  letter-spacing: 0.2em;
  max-width: 150px;
}

.edit-form {
  background-color: #f8f9fa;
  padding: 1.5rem;
  border-radius: 0.5rem;
  margin-bottom: 1.5rem;
  border: 1px solid #dee2e6;
}
</style>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-6">
            <h4>เพิ่มรหัสบุคคลสมาชิก</h4>
        </div>
      
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <?php if ($editing_member): ?>
        <!-- Edit Form -->
        <div class="edit-form">
            <h4 class="mb-3">แก้ไขรหัสบุคคล: <?php echo htmlspecialchars($editing_member['mem_fullname']); ?></h4>
            <form method="post" action="add_person_code.php">
                <input type="hidden" name="action" value="update_person_code">
                <input type="hidden" name="mem_id" value="<?php echo (int)$editing_member['mem_id']; ?>">
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">ชื่อสมาชิก:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_fullname']); ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">เลขที่สมาชิก:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_number']); ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">กลุ่ม:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_group']); ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">วันเกิด:</label>
                        <input type="text" class="form-control" value="<?php echo !empty($editing_member['mem_birthtext']) ? htmlspecialchars($editing_member['mem_birthtext']) : '-'; ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ชั้น:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_class']); ?>" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">บันทึกโดย:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editing_member['mem_saveby']); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">วันที่บันทึก:</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(thai_date_format($editing_member['mem_savedate'])); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="mem_personcode" class="form-label">รหัสบุคคล <span class="text-danger">*</span> (4 ตัวเลข):</label>
                        <input type="text" 
                               id="mem_personcode" 
                               name="mem_personcode" 
                               class="form-control person-code-input" 
                               value="<?php echo htmlspecialchars($editing_member['mem_personcode']); ?>" 
                               maxlength="4" 
                               pattern="[0-9]{4}" 
                               required 
                               placeholder="1234">
                        <div class="form-text">รหัสบุคคลต้องเป็นตัวเลข 4 ตัวเท่านั้น</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">บันทึกรหัสบุคคล</button>
                        <a href="add_person_code.php" class="btn btn-secondary">ยกเลิก</a>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Search Form -->
        <div class="row mb-3">
            <div class="col-12">
                <form method="get" class="row g-2 position-relative" id="search-form">
                    <div class="col-auto" style="flex:1;">
                        <input type="text" 
                               name="q" 
                               id="search-input"
                               class="form-control" 
                               placeholder="ค้นหาชื่อสมาชิก (พิมพ์และรอผลลัพธ์)" 
                               value="<?php echo htmlspecialchars($q); ?>"
                               autocomplete="off">
                    </div>
                    <div class="col-auto">
                        <a href="add_person_code.php" class="btn btn-secondary">ล้าง</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($members)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>เลขสมาชิก</th>
                            <th>กลุ่ม</th>
                       
                            <th>ชื่อ-สกุล</th>
                            <th>ชั้น</th>
                            <th>รหัสบุคคล</th>
                          
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['mem_number']); ?></td>
                                <td><?php echo htmlspecialchars($member['mem_group']); ?></td>
                               
                                <td><?php echo htmlspecialchars($member['mem_fullname']); ?></td>

                                <td>
                                  <!-- ถ้า mem_class == member ให้แสดงข้อความว่า สมาชิก ถ้าไม่ใช่ให้แสดงว่า เกษตรกร -->
                                  <?php echo $member['mem_class'] == 'member' ? 'สมาชิก' : 'เกษตรกร'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($member['mem_personcode'])): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($member['mem_personcode']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่มี</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="add_person_code.php?edit_id=<?php echo (int)$member['mem_id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> แก้ไขรหัส
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="row mt-3">
                <div class="col-12 text-center">
                    <small class="text-muted">
                        <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
                            พบ <?php echo count($members); ?> รายการ (จากการค้นหา: "<?php echo htmlspecialchars($q); ?>")
                        <?php else: ?>
                            แสดงทั้งหมด <?php echo count($members); ?> รายการ
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        <?php elseif ($q !== '' && mb_strlen($q) >= 2): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> ไม่พบสมาชิกที่ค้นหา: "<?php echo htmlspecialchars($q); ?>"
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> ไม่พบข้อมูลสมาชิกในระบบ
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-search functionality
    const searchInput = document.getElementById('search-input');
    const searchForm = document.getElementById('search-form');
    let searchTimeout;
    
    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Wait for user to stop typing (500ms delay)
            searchTimeout = setTimeout(function() {
                if (query.length >= 2) {
                    // Update URL and submit form
                    const newUrl = 'add_person_code.php?q=' + encodeURIComponent(query);
                    window.location.href = newUrl;
                } else if (query.length === 0) {
                    // If input is cleared, go to base page
                    window.location.href = 'add_person_code.php';
                }
            }, 500);
        });
    }
    
    // Auto-format person code input
    const personCodeInput = document.getElementById('mem_personcode');
    if (personCodeInput) {
        personCodeInput.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 4 characters
            if (this.value.length > 4) {
                this.value = this.value.substring(0, 4);
            }
        });
        
        personCodeInput.addEventListener('keypress', function(e) {
            // Only allow number keys
            const char = String.fromCharCode(e.which || e.keyCode);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
