<?php
// admin_venues.php
session_start();

/* cache off */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* ===== auth ===== */
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$ROLE = $_SESSION['role'] ?? '';
$ME_ID = (int)($_SESSION['user_id'] ?? 0);
if ($ROLE !== 'employee' && $ROLE !== 'super_admin') {
  echo "<h2 style='color:red;text-align:center;margin-top:40px'>❌ ไม่มีสิทธิ์เข้าหน้านี้</h2>"; exit;
}
$IS_SUPER = ($ROLE === 'super_admin');

/* DB */
if (!file_exists('db_connect.php')) { die("Fatal Error: missing db_connect.php"); }
include 'db_connect.php';

/* ===== BOOTSTRAP (ครั้งเดียว): คอลัมน์เจ้าของสนาม ===== */
@$conn->query("
  ALTER TABLE Tbl_Venue
  ADD COLUMN IF NOT EXISTS CreatedByUserID INT NULL,
  ADD COLUMN IF NOT EXISTS CreatedByRole ENUM('super_admin','employee') NULL,
  ADD INDEX IF NOT EXISTS idx_creator (CreatedByUserID, CreatedByRole)
");

/* ดึงประเภทสนาม */
$types = [];
if ($rs = $conn->query("SELECT VenueTypeID, TypeName FROM Tbl_Venue_Type ORDER BY TypeName")) {
  $types = $rs->fetch_all(MYSQLI_ASSOC);
  $rs->close();
}

/* แก้ไข */
$editing=false; $editRow=null;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  $vid = (int)$_GET['id'];
  $st = $conn->prepare("SELECT * FROM Tbl_Venue WHERE VenueID=?");
  $st->bind_param("i", $vid);
  $st->execute();
  $editRow = $st->get_result()->fetch_assoc();
  $st->close();
  if ($editRow) {
    $editing = true;
    if (!$IS_SUPER) {
      if ((int)($editRow['CreatedByUserID'] ?? 0) !== $ME_ID || (string)($editRow['CreatedByRole'] ?? '') !== $ROLE) {
        echo "<h2 style='color:red;text-align:center;margin-top:40px'>❌ คุณไม่ใช่เจ้าของสนามนี้</h2>"; exit;
      }
    }
  }
}

/* ลิสต์สนาม — ถ้าไม่ใช่ super_admin ให้เห็นเฉพาะที่ตนสร้าง */
$search = trim($_GET['q'] ?? '');
$params=[]; $typestr=''; $where="WHERE 1=1";
if ($search !== '') {
  $where .= " AND (v.VenueName LIKE ? OR t.TypeName LIKE ? OR v.Status LIKE ?)";
  $like = "%{$search}%";
  $params[]=$like; $params[]=$like; $params[]=$like; $typestr.='sss';
}
if (!$IS_SUPER) {
  $where .= " AND v.CreatedByUserID=? AND v.CreatedByRole=?";
  $params[]=$ME_ID; $params[]=$ROLE; $typestr.='is';
}
$sql = "SELECT v.*, t.TypeName
        FROM Tbl_Venue v
        JOIN Tbl_Venue_Type t ON t.VenueTypeID = v.VenueTypeID
        $where
        ORDER BY v.VenueID DESC";
$st = $conn->prepare($sql);
if ($params) { $st->bind_param($typestr, ...$params); }
$st->execute();
$venues = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

function h($s){return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการสนาม (Admin)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f3f6ff}
.card{border:1px solid #e5e7eb;border-radius:16px}
.thumb{width:80px;height:60px;object-fit:cover;border-radius:10px}
</style>
</head>
<body class="p-3 p-md-4">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">จัดการสนาม</h3>
    <a class="btn btn-secondary" href="dashboard.php">กลับ Dashboard</a>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-md-6"><input class="form-control" name="q" placeholder="ค้นหาชื่อ/ประเภท/สถานะ" value="<?=h($search)?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">ค้นหา</button></div>
    <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="admin_venues.php">ล้างตัวกรอง</a></div>
  </form>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card p-3">
        <h5 class="mb-3"><?= $editing ? 'แก้ไขสนาม #'.(int)$editRow['VenueID'] : 'เพิ่มสนามใหม่'?></h5>
        <form action="venue_save.php" method="post" enctype="multipart/form-data">
          <?php if ($editing): ?>
            <input type="hidden" name="VenueID" value="<?= (int)$editRow['VenueID'] ?>">
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">ชื่อสนาม</label>
            <input required class="form-control" name="VenueName" value="<?= h($editRow['VenueName'] ?? '') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label">ประเภทสนาม</label>
            <select required class="form-select" name="VenueTypeID">
              <option value="">-- เลือกประเภท --</option>
              <?php foreach($types as $t): ?>
                <option value="<?=$t['VenueTypeID']?>" <?=($editing && $editRow['VenueTypeID']==$t['VenueTypeID'])?'selected':''?>><?=h($t['TypeName'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">ราคา/ชม.</label><input type="number" step="0.01" min="0" class="form-control" name="PricePerHour" value="<?=h($editRow['PricePerHour'] ?? '')?>"></div>
            <div class="col-3 mb-2"><label class="form-label">เปิด</label><input type="time" class="form-control" name="TimeOpen" value="<?=h($editRow['TimeOpen'] ?? '')?>"></div>
            <div class="col-3 mb-2"><label class="form-label">ปิด</label><input type="time" class="form-control" name="TimeClose" value="<?=h($editRow['TimeClose'] ?? '')?>"></div>
          </div>
          <div class="mb-2"><label class="form-label">ที่อยู่</label><textarea class="form-control" name="Address" rows="2"><?=h($editRow['Address'] ?? '')?></textarea></div>
          <div class="mb-2"><label class="form-label">รายละเอียด</label><textarea class="form-control" name="Description" rows="3"><?=h($editRow['Description'] ?? '')?></textarea></div>
          <div class="mb-2">
            <label class="form-label">รูปภาพ <?= $editing?'(อัปโหลดใหม่เพื่อเปลี่ยน)':'' ?></label>
            <input type="file" class="form-control" name="ImageFile" accept="image/*">
            <?php if ($editing && !empty($editRow['ImageURL'])): ?>
              <img class="thumb mt-2" src="<?=h($editRow['ImageURL'])?>">
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">สถานะ</label>
            <select class="form-select" name="Status">
              <?php
                $map=['available'=>'เปิดให้จอง','maintenance'=>'ปิดปรับปรุง','closed'=>'ปิดถาวร'];
                $cur=$editing?($editRow['Status']??'available'):'available';
                foreach($map as $k=>$v){ echo "<option value='$k' ".($cur===$k?'selected':'').">$v</option>"; }
              ?>
            </select>
          </div>
          <button class="btn btn-success w-100">บันทึก</button>
        </form>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card p-3">
        <h5 class="mb-3">รายการสนามของ<?= $IS_SUPER? 'ทุกคน (super_admin)': 'ฉัน' ?></h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>#</th><th>รูป</th><th>ชื่อ</th><th>ประเภท</th><th>ราคา/ชม.</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead>
            <tbody>
            <?php if (!$venues): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">ไม่มีข้อมูล</td></tr>
            <?php else: $i=count($venues); foreach($venues as $v): ?>
              <tr>
                <td><?=$i--?></td>
                <td><?php if(!empty($v['ImageURL'])): ?><img class="thumb" src="<?=h($v['ImageURL'])?>"><?php endif; ?></td>
                <td><?=h($v['VenueName'])?></td>
                <td><?=h($v['TypeName'])?></td>
                <td><?=number_format((float)$v['PricePerHour'],2)?></td>
                <td><?=h($map[$v['Status']] ?? $v['Status'])?></td>
                <td class="text-end">
                  <a class="btn btn-primary btn-sm" href="admin_venues.php?id=<?=$v['VenueID']?>">แก้ไข</a>
                  <form class="d-inline" method="post" action="venue_set_status.php">
                    <input type="hidden" name="VenueID" value="<?=$v['VenueID']?>">
                    <?php if(($v['Status'] ?? 'available') !== 'maintenance'): ?>
                      <input type="hidden" name="Status" value="maintenance">
                      <button class="btn btn-warning btn-sm">ปิดปรับปรุง</button>
                    <?php else: ?>
                      <input type="hidden" name="Status" value="available">
                      <button class="btn btn-success btn-sm">เปิดให้จอง</button>
                    <?php endif; ?>
                  </form>
                  <form class="d-inline" method="post" action="venue_delete.php" onsubmit="return confirm('ลบสนามนี้ถาวร?');">
                    <input type="hidden" name="VenueID" value="<?=$v['VenueID']?>">
                    <button class="btn btn-outline-danger btn-sm">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
