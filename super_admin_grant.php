<?php
// super_admin_grant.php (ให้สิทธิ์ลูกค้าเป็น admin/employee ราย "บริษัท")
session_start();
require_once __DIR__.'/includes/auth.php';
require_login();
require_super_admin();

if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

$message = "";

/* โหลดรายชื่อบริษัทไว้สำหรับ dropdown */
$companies = [];
if ($rs = $conn->query("SELECT CompanyID, CompanyName FROM Tbl_Company ORDER BY CompanyName")) {
  $companies = $rs->fetch_all(MYSQLI_ASSOC);
  $rs->free();
}

/* โหลด role พนักงาน (super_admin/employee คงเดิมถ้าหน้าใช้) */
$roles = [];
if ($res = $conn->query("SELECT RoleID, RoleName FROM Tbl_Role")) {
  while ($r = $res->fetch_assoc()) $roles[$r['RoleName']] = (int)$r['RoleID'];
  $res->free();
}

/* บันทึกสิทธิ์บริษัทให้ลูกค้า */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_company_admin'])) {
  $cid = (int)($_POST['customer_id'] ?? 0);
  $companyId = (int)($_POST['company_id'] ?? 0);
  $role = ($_POST['company_role'] ?? 'admin') === 'employee' ? 'employee' : 'admin';

  if ($cid > 0 && $companyId > 0) {
    $sql = "INSERT INTO Tbl_Company_Admin (CompanyID, CustomerID, Role)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE CompanyID = VALUES(CompanyID), Role = VALUES(Role)";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param("iis", $companyId, $cid, $role);
      if ($stmt->execute()) { $message = "✅ บันทึกสิทธิ์บริษัทสำเร็จ"; }
      else { $message = "❌ บันทึกสิทธิ์บริษัทไม่สำเร็จ: ".htmlspecialchars($stmt->error); }
      $stmt->close();
    } else {
      $message = "❌ เตรียมคำสั่งไม่สำเร็จ";
    }
  } else {
    $message = "⚠️ เลือกลูกค้า/บริษัทให้ครบ";
  }
}

/* ยกเลิกสิทธิ์บริษัทของลูกค้า */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_company_admin'])) {
  $cid = (int)($_POST['customer_id'] ?? 0);
  if ($cid > 0) {
    if ($stmt = $conn->prepare("DELETE FROM Tbl_Company_Admin WHERE CustomerID=?")) {
      $stmt->bind_param("i", $cid);
      if ($stmt->execute()) { $message = "✅ ยกเลิกสิทธิ์สำเร็จ"; }
      else { $message = "❌ ยกเลิกสิทธิ์ไม่สำเร็จ: ".htmlspecialchars($stmt->error); }
      $stmt->close();
    } else {
      $message = "❌ เตรียมคำสั่งไม่สำเร็จ";
    }
  }
}

/* ดึงรายชื่อลูกค้า + สิทธิ์บริษัทปัจจุบัน */
$sqlCus = "
  SELECT c.CustomerID, c.FirstName, c.Username,
         ca.Role AS CompanyRole, co.CompanyName
  FROM Tbl_Customer c
  LEFT JOIN Tbl_Company_Admin ca ON ca.CustomerID = c.CustomerID
  LEFT JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
  ORDER BY c.CustomerID
";
$users = [];
if ($res = $conn->query($sqlCus)) {
  $users = $res->fetch_all(MYSQLI_ASSOC);
  $res->free();
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>มอบสิทธิ์ผู้ดูแลระบบ | ตามบริษัท</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
<main class="container">
  <h1>มอบสิทธิ์ผู้ดูแลระบบ • ตามบริษัท</h1>
  <?php if (!empty($message)): ?>
    <article role="alert"><?php echo h($message); ?></article>
  <?php endif; ?>

  <table role="grid">
    <thead>
      <tr>
        <th>ID</th>
        <th>ชื่อ</th>
        <th>Username</th>
        <th>สิทธิ์/บริษัทปัจจุบัน</th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?php echo (int)$u['CustomerID']; ?></td>
          <td><?php echo h($u['FirstName']); ?></td>
          <td><?php echo h($u['Username']); ?></td>
          <td>
            <?php
              if ($u['CompanyRole']) {
                echo h($u['CompanyRole'])." @ ".h($u['CompanyName']);
              } else {
                echo "<em>ลูกค้าทั่วไป</em>";
              }
            ?>
          </td>
          <td>
            <form method="post" style="display:flex;gap:.5rem;align-items:center;">
              <input type="hidden" name="customer_id" value="<?php echo (int)$u['CustomerID']; ?>">
              <select name="company_id" required>
                <option value="">— เลือกบริษัท —</option>
                <?php foreach ($companies as $c): ?>
                  <option value="<?php echo (int)$c['CompanyID']; ?>">
                    <?php echo h($c['CompanyName']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <select name="company_role">
                <option value="admin">admin</option>
                <option value="employee">employee</option>
              </select>
              <button type="submit" name="grant_company_admin">บันทึกสิทธิ์บริษัท</button>
              <button type="submit" name="revoke_company_admin" class="secondary">ยกเลิกสิทธิ์</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
