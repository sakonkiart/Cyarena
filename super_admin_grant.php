<?php
// super_admin_grant.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo "403 Forbidden – ต้องเป็น super_admin เท่านั้น";
    exit;
}

if (!file_exists('db_connect.php')) {
    die("Fatal Error: ไม่พบไฟล์ db_connect.php");
}
include 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

$message = "";

/* ===== Ensure roles exist ===== */
@$conn->query("
  INSERT INTO Tbl_Role (RoleName)
  SELECT 'employee' FROM DUAL
  WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='employee')
");
@$conn->query("
  INSERT INTO Tbl_Role (RoleName)
  SELECT 'super_admin' FROM DUAL
  WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')
");

/* Get Role map */
$roles = [];
if ($rs = $conn->query("SELECT RoleID, RoleName FROM Tbl_Role ORDER BY RoleName")) {
  while ($r = $rs->fetch_assoc()) { $roles[$r['RoleName']] = (int)$r['RoleID']; }
  $rs->close();
}

/* Handle POST: update role */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['role_name'])) {
    $empId     = (int)$_POST['employee_id'];
    $roleName  = trim($_POST['role_name']);

    if (!isset($roles[$roleName])) {
        $message = "❌ ไม่พบสิทธิ์ที่เลือก";
    } else {
        $rid = $roles[$roleName];
        // กันไม่ให้ลดสิทธิ์ตัวเองพลาด
        if ($empId === (int)$_SESSION['user_id'] && $roleName !== 'super_admin') {
            $message = "⚠️ ห้ามเปลี่ยนสิทธิ์ของตัวเองเป็นอย่างอื่นนอกจาก super_admin";
        } else {
            $stmt = $conn->prepare("UPDATE Tbl_Employee SET RoleID=? WHERE EmployeeID=?");
            $stmt->bind_param("ii", $rid, $empId);
            if ($stmt->execute()) {
                $message = "✅ อัปเดตสิทธิ์สำเร็จ";
            } else {
                $message = "❌ อัปเดตไม่สำเร็จ: " . htmlspecialchars($conn->error);
            }
            $stmt->close();
        }
    }
}

/* Load employees (ไม่อ้างอิง LastName) */
$employees = [];
$sql = "
  SELECT e.EmployeeID, e.FirstName, e.Username,
         COALESCE(r.RoleName,'employee') AS RoleName
  FROM Tbl_Employee e
  LEFT JOIN Tbl_Role r ON e.RoleID = r.RoleID
  ORDER BY e.EmployeeID
";
if ($res = $conn->query($sql)) {
    $employees = $res->fetch_all(MYSQLI_ASSOC);
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>มอบสิทธิ์ผู้ดูแลระบบสูงสุด</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Sarabun',sans-serif;background:#f6f7fb;margin:0;padding:24px;color:#0f172a}
h1{margin:0 0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 6px 18px rgba(0,0,0,.05);padding:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px 12px;border-bottom:1px solid #eef2f7;text-align:left}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.85rem;font-weight:700}
.badge.sa{background:#1d4ed8;color:#fff}
.badge.emp{background:#10b981;color:#064e3b}
.actions{display:flex;gap:8px}
.btn{border:none;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer}
.btn.sa{background:#1d4ed8;color:#fff}
.btn.emp{background:#10b981;color:#fff}
.msg{margin:12px 0 16px;padding:10px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#f8fafc}
.small{color:#64748b;font-size:.9rem}
</style>
</head>
<body>
  <h1>มอบสิทธิ์ผู้ดูแลระบบสูงสุด (super_admin)</h1>
  <div class="small">เฉพาะบัญชีที่เข้าสู่ระบบด้วยสิทธิ์ <b>super_admin</b> เท่านั้นที่เข้าหน้านี้ได้</div>

  <?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>ชื่อ (FirstName)</th>
          <th>Username</th>
          <th>สิทธิ์ปัจจุบัน</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$employees): ?>
        <tr><td colspan="5">ยังไม่มีพนักงาน</td></tr>
      <?php else: foreach ($employees as $emp): ?>
        <tr>
          <td><?= (int)$emp['EmployeeID'] ?></td>
          <td><?= htmlspecialchars($emp['FirstName'] ?: '-') ?></td>
          <td><?= htmlspecialchars($emp['Username'] ?: '-') ?></td>
          <td>
            <?php if (($emp['RoleName'] ?? 'employee') === 'super_admin'): ?>
              <span class="badge sa">super_admin</span>
            <?php else: ?>
              <span class="badge emp">employee</span>
            <?php endif; ?>
          </td>
          <td class="actions">
            <form method="post" style="display:inline">
              <input type="hidden" name="employee_id" value="<?= (int)$emp['EmployeeID'] ?>">
              <input type="hidden" name="role_name" value="super_admin">
              <button class="btn sa" type="submit">ตั้งเป็น super_admin</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="employee_id" value="<?= (int)$emp['EmployeeID'] ?>">
              <input type="hidden" name="role_name" value="employee">
              <button class="btn emp" type="submit">ตั้งเป็น employee</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
