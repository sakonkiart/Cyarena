<?php
// admin_venues.php – จัดการสนาม เฉพาะบริษัทของตน
session_start();
require_once __DIR__.'/includes/auth.php';
require_login();
require_company_scope(); // <<< บังคับมี company_id สำหรับ admin/employee

// ป้องกัน cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$COMPANY_ID = (int)($_SESSION['company_id'] ?? 0);

// ====== สร้าง/แก้ไข/ลบ (เฉพาะของบริษัทตนเอง) ======
$flash = "";

// สร้างสนาม
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
    $name = trim($_POST['VenueName'] ?? '');
    $type = (int)($_POST['VenueTypeID'] ?? 0);
    $price= (float)($_POST['PricePerHour'] ?? 0);
    $status = trim($_POST['Status'] ?? 'available');
    $open = $_POST['TimeOpen'] ?? null;
    $close= $_POST['TimeClose'] ?? null;
    $img  = $_POST['ImageURL'] ?? null;

    if ($name !== '' && $type>0 && $COMPANY_ID>0) {
        $sql = "INSERT INTO Tbl_Venue (CompanyID, VenueName, VenueTypeID, PricePerHour, Status, TimeOpen, TimeClose, ImageURL)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isidssss", $COMPANY_ID, $name, $type, $price, $status, $open, $close, $img);
            if ($stmt->execute()) { $flash = "✅ เพิ่มสนามสำเร็จ"; } else { $flash = "❌ ไม่สำเร็จ: ".h($stmt->error); }
            $stmt->close();
        } else { $flash = "❌ เตรียมคำสั่งไม่สำเร็จ"; }
    } else { $flash = "⚠️ กรอกข้อมูลไม่ครบ"; }
}

// แก้ไขสนาม
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
    $vid = (int)($_POST['VenueID'] ?? 0);
    $name = trim($_POST['VenueName'] ?? '');
    $type = (int)($_POST['VenueTypeID'] ?? 0);
    $price= (float)($_POST['PricePerHour'] ?? 0);
    $status = trim($_POST['Status'] ?? 'available');
    $open = $_POST['TimeOpen'] ?? null;
    $close= $_POST['TimeClose'] ?? null;
    $img  = $_POST['ImageURL'] ?? null;

    if ($vid>0 && $name !== '' && $type>0 && $COMPANY_ID>0) {
        $sql = "UPDATE Tbl_Venue
                SET VenueName=?, VenueTypeID=?, PricePerHour=?, Status=?, TimeOpen=?, TimeClose=?, ImageURL=?
                WHERE VenueID=? AND CompanyID=?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sidsssiii", $name, $type, $price, $status, $open, $close, $img, $vid, $COMPANY_ID);
            if ($stmt->execute()) { $flash = "✅ แก้ไขสนามสำเร็จ"; } else { $flash = "❌ ไม่สำเร็จ: ".h($stmt->error); }
            $stmt->close();
        } else { $flash = "❌ เตรียมคำสั่งไม่สำเร็จ"; }
    } else { $flash = "⚠️ กรอกข้อมูลไม่ครบ"; }
}

// ลบสนาม
if (isset($_GET['delete'])) {
    $vid = (int)$_GET['delete'];
    if ($vid>0 && $COMPANY_ID>0) {
        $sql = "DELETE FROM Tbl_Venue WHERE VenueID=? AND CompanyID=?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $vid, $COMPANY_ID);
            if ($stmt->execute()) { $flash = "✅ ลบสนามสำเร็จ"; } else { $flash = "❌ ไม่สำเร็จ: ".h($stmt->error); }
            $stmt->close();
        } else { $flash = "❌ เตรียมคำสั่งไม่สำเร็จ"; }
    }
}

// ====== ลิสต์/ค้นหาเฉพาะของบริษัท ======
$venues = [];
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $like = '%'.$search.'%';
    $sql = "SELECT v.*, t.TypeName
            FROM Tbl_Venue v
            JOIN Tbl_Venue_Type t ON t.VenueTypeID = v.VenueTypeID
            WHERE v.CompanyID = ?
              AND (v.VenueName LIKE ? OR t.TypeName LIKE ? OR v.Status LIKE ?)
            ORDER BY v.VenueID DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isss", $COMPANY_ID, $like, $like, $like);
        $stmt->execute();
        $venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $sql = "SELECT v.*, t.TypeName
            FROM Tbl_Venue v
            JOIN Tbl_Venue_Type t ON t.VenueTypeID = v.VenueTypeID
            WHERE v.CompanyID = ?
            ORDER BY v.VenueID DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $COMPANY_ID);
        $stmt->execute();
        $venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// โหลดประเภทสนามไว้กรอกฟอร์ม
$types = [];
if ($rs = $conn->query("SELECT VenueTypeID, TypeName FROM Tbl_Venue_Type ORDER BY TypeName")) {
    $types = $rs->fetch_all(MYSQLI_ASSOC);
    $rs->free();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการสนาม (บริษัทของฉัน)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
<main class="container">
  <h1>สนามของบริษัท: <?php echo h($_SESSION['company_name'] ?? ('#'.$COMPANY_ID)); ?></h1>
  <?php if (!empty($flash)) : ?>
    <article role="alert"><?php echo h($flash); ?></article>
  <?php endif; ?>

  <article>
    <form method="get" class="grid">
      <input name="q" placeholder="ค้นหาชื่อสนาม / ประเภท / สถานะ" value="<?php echo h($search); ?>">
      <button type="submit">ค้นหา</button>
      <a href="admin_venues.php" role="button" class="secondary">ล้าง</a>
    </form>
  </article>

  <article>
    <h3>เพิ่มสนามใหม่</h3>
    <form method="post" class="grid">
      <input type="hidden" name="action" value="create">
      <label>ชื่อสนาม<input name="VenueName" required></label>
      <label>ประเภทสนาม
        <select name="VenueTypeID" required>
          <option value="">— เลือกประเภท —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int)$t['VenueTypeID']; ?>"><?php echo h($t['TypeName']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>ราคา/ชั่วโมง<input type="number" name="PricePerHour" min="0" step="0.01" value="0"></label>
      <label>สถานะ
        <select name="Status">
          <option value="available">available</option>
          <option value="maintenance">maintenance</option>
          <option value="unavailable">unavailable</option>
        </select>
      </label>
      <label>เวลาเปิด (HH:MM)<input type="time" name="TimeOpen"></label>
      <label>เวลาปิด (HH:MM)<input type="time" name="TimeClose"></label>
      <label>รูปภาพ (URL)<input name="ImageURL"></label>
      <button type="submit">เพิ่มสนาม</button>
    </form>
  </article>

  <article>
    <h3>รายการสนาม</h3>
    <table role="grid">
      <thead>
        <tr>
          <th>ID</th><th>ชื่อสนาม</th><th>ประเภท</th><th>ราคา</th><th>สถานะ</th><th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($venues as $v): ?>
          <tr>
            <td><?php echo (int)$v['VenueID']; ?></td>
            <td><?php echo h($v['VenueName']); ?></td>
            <td><?php echo h($v['TypeName']); ?></td>
            <td><?php echo number_format((float)$v['PricePerHour'], 2); ?></td>
            <td><?php echo h($v['Status']); ?></td>
            <td>
              <details>
                <summary>แก้ไข</summary>
                <form method="post" class="grid">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="VenueID" value="<?php echo (int)$v['VenueID']; ?>">
                  <label>ชื่อสนาม<input name="VenueName" required value="<?php echo h($v['VenueName']); ?>"></label>
                  <label>ประเภทสนาม
                    <select name="VenueTypeID" required>
                      <?php foreach ($types as $t): ?>
                        <option value="<?php echo (int)$t['VenueTypeID']; ?>" <?php echo ((int)$t['VenueTypeID']===(int)$v['VenueTypeID']?'selected':''); ?>>
                          <?php echo h($t['TypeName']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>ราคา/ชั่วโมง<input type="number" name="PricePerHour" min="0" step="0.01" value="<?php echo h($v['PricePerHour']); ?>"></label>
                  <label>สถานะ
                    <select name="Status">
                      <?php foreach (['available','maintenance','unavailable'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($st===$v['Status']?'selected':''); ?>><?php echo $st; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>เวลาเปิด<input type="time" name="TimeOpen" value="<?php echo h($v['TimeOpen']); ?>"></label>
                  <label>เวลาปิด<input type="time" name="TimeClose" value="<?php echo h($v['TimeClose']); ?>"></label>
                  <label>รูปภาพ (URL)<input name="ImageURL" value="<?php echo h($v['ImageURL']); ?>"></label>
                  <button type="submit">บันทึก</button>
                  <a class="contrast" href="?delete=<?php echo (int)$v['VenueID']; ?>" onclick="return confirm('ยืนยันลบสนามนี้?')">ลบ</a>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </article>
</main>
</body>
</html>
