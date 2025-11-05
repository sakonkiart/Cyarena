<?php
// admin_venues.php
// Admin page to create/edit venues, upload images, set maintenance status, and delete.

session_start();

/* >>> KEEP: ป้องกัน cache ให้โหลดข้อมูลสดหลัง redirect เสมอ */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* >>> OWNER-SCOPE: ตรวจสอบสิทธิ์ (admin/employee หรือ super_admin) */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$ME_ID    = (int)($_SESSION['user_id'] ?? 0);
$ROLE     = (string)($_SESSION['role'] ?? '');
$IS_SUPER = ($ROLE === 'super_admin');

if (!in_array($ROLE, ['admin','employee','super_admin'], true)) {
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>❌ คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h2>";
    exit;
}

include 'db_connect.php';

/* >>> OWNER-SCOPE: เตรียมคอลัมน์เจ้าของสนาม (ทำครั้งเดียว) */
include 'db_connect.php';

/* ===== Schema guard: เพิ่มคอลัมน์/ดัชนีถ้ายังไม่มี (ไม่ใช้ IF NOT EXISTS) ===== */
function colExists(mysqli $c, string $table, string $column): bool {
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1";
    $st = $c->prepare($sql);
    $st->bind_param("ss", $table, $column);
    $st->execute(); $st->store_result();
    $ok = $st->num_rows > 0;
    $st->close();
    return $ok;
}
function idxExists(mysqli $c, string $table, string $index): bool {
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1";
    $st = $c->prepare($sql);
    $st->bind_param("ss", $table, $index);
    $st->execute(); $st->store_result();
    $ok = $st->num_rows > 0;
    $st->close();
    return $ok;
}

try {
    if (!colExists($conn, 'Tbl_Venue', 'CreatedByUserID')) {
        $conn->query("ALTER TABLE `Tbl_Venue` ADD COLUMN `CreatedByUserID` INT NULL");
    }
    if (!colExists($conn, 'Tbl_Venue', 'CreatedByRole')) {
        // ใช้ ENUM ตามที่ออกแบบ
        $conn->query("ALTER TABLE `Tbl_Venue`
                      ADD COLUMN `CreatedByRole` ENUM('super_admin','employee') NULL");
    }
    if (!idxExists($conn, 'Tbl_Venue', 'idx_creator')) {
        $conn->query("ALTER TABLE `Tbl_Venue`
                      ADD INDEX `idx_creator` (`CreatedByUserID`,`CreatedByRole`)");
    }
} catch (Throwable $e) {
    // ไม่ให้เด้งหน้า – บันทึกไว้เฉยๆ
    error_log('[admin_venues schema guard] '.$e->getMessage());
}
/* ===== END Schema guard ===== */

@$conn->query("
  ALTER TABLE Tbl_Venue
  ADD COLUMN IF NOT EXISTS CreatedByUserID INT NULL,
  ADD COLUMN IF NOT EXISTS CreatedByRole ENUM('super_admin','admin','employee') NULL,
  ADD INDEX IF NOT EXISTS idx_creator (CreatedByUserID, CreatedByRole)
");

/* Fetch venue types for dropdown (ไม่จำกัดประเภทแล้ว) */
$types = [];
$typeSql = "SELECT VenueTypeID, TypeName FROM Tbl_Venue_Type ORDER BY TypeName ASC";
if ($res = $conn->query($typeSql)) {
    while ($row = $res->fetch_assoc()) { $types[] = $row; }
    $res->free();
}

/* If editing */
$editing = false;
$editRow = null;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $vid = (int)$_GET['id'];

    /* >>> OWNER-SCOPE: ถ้าไม่ใช่ super_admin ต้องเป็นเจ้าของสนามเท่านั้นถึงจะแก้ได้ */
    if ($IS_SUPER) {
        $stmt = $conn->prepare("SELECT * FROM Tbl_Venue WHERE VenueID = ?");
        $stmt->bind_param("i", $vid);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Tbl_Venue WHERE VenueID = ? AND CreatedByUserID = ? AND CreatedByRole = ?");
        $stmt->bind_param("iis", $vid, $ME_ID, $ROLE);
    }
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editRow) $editing = true;

    if (!$IS_SUPER && !$editing) {
        echo "<h2 style='color:red;text-align:center;margin-top:50px;'>❌ คุณไม่ใช่เจ้าของสนามนี้</h2>";
        exit;
    }
}

/* Fetch venues (มีค้นหา) — ถ้าไม่ใช่ super_admin ให้เห็นเฉพาะที่ตัวเองสร้าง */
$venues = [];
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($search !== '') {
    $like = '%' . $search . '%';
    if ($IS_SUPER) {
        $stmt = $conn->prepare("SELECT v.*, t.TypeName
                                FROM Tbl_Venue v
                                JOIN Tbl_Venue_Type t ON v.VenueTypeID = t.VenueTypeID
                                WHERE v.VenueName LIKE ? OR t.TypeName LIKE ? OR v.Status LIKE ?
                                ORDER BY v.VenueID DESC");
        $stmt->bind_param("sss", $like, $like, $like);
    } else {
        $stmt = $conn->prepare("SELECT v.*, t.TypeName
                                FROM Tbl_Venue v
                                JOIN Tbl_Venue_Type t ON v.VenueTypeID = t.VenueTypeID
                                WHERE (v.VenueName LIKE ? OR t.TypeName LIKE ? OR v.Status LIKE ?)
                                  AND v.CreatedByUserID = ? AND v.CreatedByRole = ?
                                ORDER BY v.VenueID DESC");
        // ✔ แก้เป็น sssis (เดิมพิมพ์ผิดเป็น sssIs)
        $stmt->bind_param("sssis", $like, $like, $like, $ME_ID, $ROLE);
    }
    $stmt->execute();
    $venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    if ($IS_SUPER) {
        $sql = "SELECT v.*, t.TypeName
                FROM Tbl_Venue v
                JOIN Tbl_Venue_Type t ON v.VenueTypeID = t.VenueTypeID
                ORDER BY v.VenueID DESC";
        if ($res = $conn->query($sql)) {
            $venues = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
    } else {
        $stmt = $conn->prepare("SELECT v.*, t.TypeName
                                FROM Tbl_Venue v
                                JOIN Tbl_Venue_Type t ON v.VenueTypeID = t.VenueTypeID
                                WHERE v.CreatedByUserID = ? AND v.CreatedByRole = ?
                                ORDER BY v.VenueID DESC");
        $stmt->bind_param("is", $ME_ID, $ROLE);
        $stmt->execute();
        $venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการสนาม (Admin)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(135deg,#667eea 0%,#2B27ECFF 100%);min-height:100vh;padding:0}
.navbar-modern{background:rgba(255,255,255,.98);backdrop-filter:blur(10px);box-shadow:0 4px 20px rgba(0,0,0,.1);padding:1rem 0;margin-bottom:2rem}
.navbar-brand-modern{font-size:1.5rem;font-weight:700;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0}
.container-main{max-width:1400px;margin:0 auto;padding:0 1rem 2rem}
.alert-modern{border:none;border-radius:15px;padding:1rem 1.5rem;margin-bottom:1.5rem;animation:slideDown .3s ease-out;box-shadow:0 4px 15px rgba(0,0,0,.1)}
@keyframes slideDown{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.search-card{background:#fff;border-radius:20px;padding:2rem;box-shadow:0 10px 40px rgba(0,0,0,.1);margin-bottom:2rem}
.search-input{border:2px solid #e0e7ff;border-radius:12px;padding:.8rem 1.2rem;transition:all .3s ease}
.search-input:focus{border-color:#667eea;box-shadow:0 0 0 4px rgba(102,126,234,.1);outline:none}
.btn-modern{border:none;border-radius:12px;padding:.8rem 2rem;font-weight:600;transition:all .3s ease}
.btn-primary-modern{background:linear-gradient(135deg,#667eea 0%,#514BA2FF 100%);color:#fff}
.btn-primary-modern:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(102,126,234,.4);color:#fff}
.btn-outline-modern{background:#fff;color:#667eea;border:2px solid #667eea}
.btn-outline-modern:hover{background:#667eea;color:#fff;transform:translateY(-2px)}
.card-modern{background:#fff;border:none;border-radius:20px;box-shadow:0 10px 40px rgba(0,0,0,.1);overflow:hidden;transition:all .3s ease;margin-bottom:2rem;animation:fadeIn .5s ease-out}
.card-modern:hover{transform:translateY(-5px);box-shadow:0 15px 50px rgba(0,0,0,.15)}
.card-header-modern{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:1.5rem;font-size:1.2rem;font-weight:700;border:none}
.card-body-modern{padding:2rem}
.form-label-modern{font-weight:600;color:#4a5568;margin-bottom:.5rem;display:block}
.form-control-modern,.form-select-modern{border:2px solid #e0e7ff;border-radius:10px;padding:.7rem 1rem;transition:all .3s ease;width:100%}
.form-control-modern:focus,.form-select-modern:focus{border-color:#667eea;box-shadow:0 0 0 4px rgba(102,126,234,.1);outline:none}
textarea.form-control-modern{resize:vertical}
.table-modern{background:#fff;margin:0}
.table-modern thead{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.table-modern thead th{border:none;padding:1rem;font-weight:600;vertical-align:middle}
.table-modern tbody tr{transition:all .3s ease;border-bottom:1px solid #f0f4ff}
.table-modern tbody tr:hover{background:#f8faff}
.table-modern tbody td{padding:1rem;vertical-align:middle}
.thumb{width:80px;height:60px;object-fit:cover;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.1);transition:all .3s ease;cursor:pointer}
.thumb:hover{transform:scale(1.1);box-shadow:0 8px 25px rgba(0,0,0,.2)}
.badge-modern{padding:.5rem 1rem;border-radius:20px;font-weight:600;font-size:.85rem;display:inline-block}
.badge-success-modern{background:linear-gradient(135deg,#48bb78 0%,#38a169 100%);color:#fff}
.badge-warning-modern{background:linear-gradient(135deg,#ed8936 0%,#dd6b20 100%);color:#fff}
.badge-secondary-modern{background:linear-gradient(135deg,#718096 0%,#4a5568 100%);color:#fff}
.btn-action{padding:.4rem 1rem;border-radius:8px;font-size:.85rem;margin:.2rem;border:none;font-weight:600;transition:all .3s ease;display:inline-block}
.btn-edit{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.btn-edit:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,.4);color:#fff}
.btn-status-warning{background:linear-gradient(135deg,#ed8936 0%,#dd6b20 100%);color:#fff}
.btn-status-warning:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(237,137,54,.4);color:#fff}
.btn-status-success{background:linear-gradient(135deg,#48bb78 0%,#38a169 100%);color:#fff}
.btn-status-success:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(72,187,120,.4);color:#fff}
.btn-delete{background:#fff;color:#f56565;border:2px solid #f56565}
.btn-delete:hover{background:#f56565;color:#fff;transform:translateY(-2px)}
.btn-submit{background:linear-gradient(135deg,#48bb78 0%,#38a169 100%);color:#fff;border:none;border-radius:12px;padding:.8rem 2rem;font-weight:700;width:100%;transition:all .3s ease;font-size:1rem}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(72,187,120,.4);color:#fff}
.empty-state{padding:3rem;text-align:center;color:#a0aec0}
.form-text-modern{font-size:.875rem;color:#718096;margin-top:.25rem}
@media (max-width:768px){
  .navbar-modern{padding:.75rem 0}
  .navbar-brand-modern{font-size:1.2rem}
  .search-card{padding:1.5rem}
  .card-body-modern{padding:1.5rem}
  .btn-action{padding:.3rem .7rem;font-size:.75rem;margin:.1rem}
  .table-modern{font-size:.85rem}
  .table-modern thead th,.table-modern tbody td{padding:.75rem .5rem}
}
@keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<!-- Navbar -->
<div class="navbar-modern">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="navbar-brand-modern mb-0">
                <i class="fas fa-futbol me-2"></i>จัดการสนาม
            </h1>
            <a href="dashboard.php" class="btn btn-primary-modern btn-modern">
                <i class="fas fa-home me-2"></i>กลับหน้า Dashboard
            </a>
        </div>
    </div>
</div>

<div class="container-main">
    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success alert-modern" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= h($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger alert-modern" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= h($_SESSION['flash_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-card">
        <form class="row g-3" method="get" action="admin_venues.php">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control search-input" placeholder="🔍 ค้นหาตามชื่อ ประเภท หรือสถานะ..." value="<?= h($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary-modern btn-modern w-100">
                    <i class="fas fa-search me-2"></i>ค้นหา
                </button>
            </div>
            <div class="col-md-3">
                <a href="admin_venues.php" class="btn btn-outline-modern btn-modern w-100">
                    <i class="fas fa-redo me-2"></i>ล้างตัวกรอง
                </a>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card-modern">
                <div class="card-header-modern">
                    <i class="fas <?= $editing ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editing ? 'แก้ไขสนาม #' . (int)$editRow['VenueID'] : 'เพิ่มสนามใหม่' ?>
                </div>
                <div class="card-body-modern">
                    <form action="venue_save.php" method="post" enctype="multipart/form-data">
                        <?php if ($editing): ?>
                            <input type="hidden" name="VenueID" value="<?= (int)$editRow['VenueID'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">ชื่อสนาม</label>
                            <input type="text" name="VenueName" class="form-control form-control-modern" required value="<?= h($editRow['VenueName'] ?? '') ?>" placeholder="กรอกชื่อสนาม">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">ประเภทสนาม</label>
                            <select name="VenueTypeID" class="form-select form-select-modern" required>
                                <option value="">-- เลือกประเภท --</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= (int)$t['VenueTypeID'] ?>" <?= ($editing && $editRow['VenueTypeID']==$t['VenueTypeID'])?'selected':'' ?>>
                                        <?= h($t['TypeName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                          <div class="col-md-6 mb-3">
                              <label class="form-label-modern">ราคา/ชั่วโมง (บาท)</label>
                              <input type="number" min="0" step="0.01" name="PricePerHour" class="form-control form-control-modern" required value="<?= h($editRow['PricePerHour'] ?? '') ?>" placeholder="0.00">
                          </div>
                          <div class="col-md-3 mb-3">
                              <label class="form-label-modern">เปิด</label>
                              <input type="time" name="TimeOpen" class="form-control form-control-modern" value="<?= h($editRow['TimeOpen'] ?? '') ?>">
                          </div>
                          <div class="col-md-3 mb-3">
                              <label class="form-label-modern">ปิด</label>
                              <input type="time" name="TimeClose" class="form-control form-control-modern" value="<?= h($editRow['TimeClose'] ?? '') ?>">
                          </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">ที่อยู่</label>
                            <textarea name="Address" class="form-control form-control-modern" rows="2" placeholder="กรอกที่อยู่สนาม"><?= h($editRow['Address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">รายละเอียด</label>
                            <textarea name="Description" class="form-control form-control-modern" rows="3" placeholder="กรอกรายละเอียดเพิ่มเติม"><?= h($editRow['Description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">รูปภาพ <?= $editing ? '(อัปโหลดใหม่เพื่อเปลี่ยน)' : '' ?></label>
                            <input type="file" name="ImageFile" accept="image/*" class="form-control form-control-modern">
                            <?php if ($editing && !empty($editRow['ImageURL'])): ?>
                                <div class="mt-2">
                                    <img src="<?= h($editRow['ImageURL']) ?>" class="thumb" alt="">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-modern">สถานะ</label>
                            <select name="Status" class="form-select form-select-modern">
                                <?php 
                                $statuses = ['available' => 'เปิดให้จอง', 'maintenance' => 'ปิดปรับปรุงชั่วคราว', 'closed' => 'ปิดถาวร'];
                                $cur = $editing ? ($editRow['Status'] ?? 'available') : 'available';
                                foreach ($statuses as $value => $label):
                                ?>
                                  <option value="<?= $value ?>" <?= ($cur === $value ? 'selected' : '') ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text-modern">
                                <i class="fas fa-info-circle me-1"></i>เลือก "ปิดปรับปรุงชั่วคราว" เพื่อไม่ให้ลูกค้าจองได้
                            </small>
                        </div>
                        
                        <button class="btn btn-submit">
                            <i class="fas fa-save me-2"></i><?= $editing ? 'บันทึกการแก้ไข' : 'เพิ่มสนาม' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-modern">
                <div class="card-header-modern">
                    <i class="fas fa-list me-2"></i>รายการสนามทั้งหมด<?= $IS_SUPER ? '' : ' (ของฉัน)' ?>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                      <table class="table table-modern mb-0">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>รูป</th>
                            <th>ชื่อ</th>
                            <th>ประเภท</th>
                            <th>ราคา/ชม.</th>
                            <th>สถานะ</th>
                            <th class="text-end">การทำงาน</th>
                          </tr>
                        </thead>
                        <tbody>
<?php $i = count($venues); ?>
<?php foreach ($venues as $v): ?>
  <tr>
    <td><?= $i-- ?></td>
    <td>
      <?php if (!empty($v['ImageURL'])): ?>
        <img class="thumb" src="<?= h($v['ImageURL']) ?>" alt="">
      <?php endif; ?>
    </td>
    <td><?= h($v['VenueName']) ?></td>
    <td><?= h($v['TypeName']) ?></td>
    <td><?= number_format((float)$v['PricePerHour'], 2) ?></td>
    <td>
      <?php 
        $map = ['available'=>'success', 'maintenance'=>'warning', 'closed'=>'secondary'];
        $label = ['available'=>'เปิดให้จอง', 'maintenance'=>'ปิดปรับปรุงชั่วคราว', 'closed'=>'ปิดถาวร'];
        $status = $v['Status'] ?? 'available';
      ?>
      <span class="badge badge-<?= $map[$status] ?? 'secondary' ?>-modern"><?= $label[$status] ?? h($status) ?></span>
    </td>
    <td class="text-end">
        <a class="btn btn-action btn-edit" href="admin_venues.php?id=<?= (int)$v['VenueID'] ?>">
            <i class="fas fa-edit me-1"></i>แก้ไข
        </a>

        <form action="venue_set_status.php" method="post" class="d-inline">
            <input type="hidden" name="VenueID" value="<?= (int)$v['VenueID'] ?>">
            <?php if (($v['Status'] ?? 'available') !== 'maintenance'): ?>
              <input type="hidden" name="Status" value="maintenance">
              <button class="btn btn-action btn-status-warning">
                  <i class="fas fa-tools me-1"></i>ตั้งเป็นปิดปรับปรุง
              </button>
            <?php else: ?>
              <input type="hidden" name="Status" value="available">
              <button class="btn btn-action btn-status-success">
                  <i class="fas fa-check me-1"></i>ตั้งเป็นเปิดให้จอง
              </button>
            <?php endif; ?>
        </form>

        <form action="venue_delete.php" method="post" class="d-inline"
              onsubmit="return confirm('ยืนยันลบสนามนี้หรือไม่? การลบไม่สามารถกู้คืนได้');">
            <input type="hidden" name="VenueID" value="<?= (int)$v['VenueID'] ?>">
            <button class="btn btn-action btn-delete">
                <i class="fas fa-trash me-1"></i>ลบ
            </button>
        </form>
    </td>
  </tr>
<?php endforeach; ?>

<?php if (empty($venues)): ?>
  <tr>
      <td colspan="7" class="empty-state">
          <i class="fas fa-inbox"></i>
          <p class="mb-0 mt-2">ไม่พบข้อมูล</p>
      </td>
  </tr>
<?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
