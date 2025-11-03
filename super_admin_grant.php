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

/* ===== สร้าง role พื้นฐาน ถ้ายังไม่มี ===== */
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

/* ดึงแผนที่ role */
$roles = [];
if ($rs = $conn->query("SELECT RoleID, RoleName FROM Tbl_Role ORDER BY RoleName")) {
  while ($r = $rs->fetch_assoc()) { $roles[$r['RoleName']] = (int)$r['RoleID']; }
  $rs->close();
}

/* ===== เปลี่ยนสิทธิ์พนักงาน (ลูกค้าไม่มี RoleID) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['role_name'])) {
    $empId     = (int)$_POST['employee_id'];
    $roleName  = trim($_POST['role_name']);

    if (!isset($roles[$roleName])) {
        $message = "❌ ไม่พบสิทธิ์ที่เลือก";
    } else {
        $rid = $roles[$roleName];
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

/* ===== ดึงผู้ใช้ทั้งหมด (พนักงาน + ลูกค้า) ===== */
/* พนักงาน */
$users = [];
$sqlEmp = "
  SELECT e.EmployeeID AS id, e.FirstName, e.Username,
         COALESCE(r.RoleName,'employee') AS role_name,
         'employee' AS kind
  FROM Tbl_Employee e
  LEFT JOIN Tbl_Role r ON e.RoleID = r.RoleID
";
if ($res = $conn->query($sqlEmp)) {
    $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC));
    $res->close();
}

/* ลูกค้า (ไม่มี RoleID) */
$sqlCus = "
  SELECT c.CustomerID AS id, c.FirstName, c.Username,
         NULL AS role_name,
         'customer' AS kind
  FROM Tbl_Customer c
";
if ($res = $conn->query($sqlCus)) {
    $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC));
    $res->close();
}

/* จัดเรียง: employee มาก่อน, จากนั้น customer; แล้วค่อยเรียงตาม Username */
usort($users, function($a,$b){
    $rank = ['employee'=>0,'customer'=>1];
    $ka = $rank[$a['kind']] ?? 9;
    $kb = $rank[$b['kind']] ?? 9;
    if ($ka !== $kb) return $ka <=> $kb;
    return strcmp((string)$a['Username'], (string)$b['Username']);
});
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
.badge.cus{background:#e5e7eb;color:#374151}
.type{display:inline-block;padding:2px 8px;border-radius:8px;font-size:.82rem;margin-right:6px}
.type-emp{background:#d1fae5;color:#065f46}
.type-cus{background:#e5e7eb;color:#374151}
.actions{display:flex;gap:8px}
.btn{border:none;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer}
.btn.sa{background:#1d4ed8;color:#fff}
.btn.emp{background:#10b981;color:#fff}
.btn.dis{background:#e5e7eb;color:#6b7280;cursor:not-allowed}
.msg{margin:12px 0 16px;padding:10px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#f8fafc}
.small{color:#64748b;font-size:.9rem}
.search{margin:10px 0 16px}
.search input{width:260px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
</style>
</head>
<body>
  <h1>มอบสิทธิ์ผู้ดูแลระบบสูงสุด (super_admin)</h1>
  <div class="small">หน้ารวมผู้ใช้ทั้งหมดในระบบ (พนักงาน + ลูกค้า) — ปุ่มเปลี่ยนสิทธิ์ใช้ได้เฉพาะ “พนักงาน”</div>

  <?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="search">
      🔎 ค้นหา: <input type="text" id="q" placeholder="พิมพ์ชื่อหรือ username">
    </div>
    <table class="table" id="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>ประเภท</th>
          <th>ชื่อ (FirstName)</th>
          <th>Username</th>
          <th>สิทธิ์ปัจจุบัน</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="6">ยังไม่มีผู้ใช้ในระบบ</td></tr>
      <?php else: foreach ($users as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td>
            <?php if ($u['kind']==='employee'): ?>
              <span class="type type-emp">employee</span>
            <?php else: ?>
              <span class="type type-cus">customer</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($u['FirstName'] ?: '-') ?></td>
          <td><?= htmlspecialchars($u['Username'] ?: '-') ?></td>
          <td>
            <?php if ($u['kind']==='employee'): ?>
              <?php if (($u['role_name'] ?? 'employee') === 'super_admin'): ?>
                <span class="badge sa">super_admin</span>
              <?php else: ?>
                <span class="badge emp">employee</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge cus">-</span>
            <?php endif; ?>
          </td>
          <td class="actions">
            <?php if ($u['kind']==='employee'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="employee_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="role_name" value="super_admin">
                <button class="btn sa" type="submit">ตั้งเป็น super_admin</button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="employee_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="role_name" value="employee">
                <button class="btn emp" type="submit">ตั้งเป็น employee</button>
              </form>
            <?php else: ?>
              <button class="btn dis" type="button" disabled>ลูกค้า (อ่านอย่างเดียว)</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<script>
const q = document.getElementById('q');
const tbl = document.getElementById('tbl').querySelector('tbody');
q.addEventListener('input', () => {
  const term = q.value.toLowerCase().trim();
  for (const tr of tbl.querySelectorAll('tr')) {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(term) ? '' : 'none';
  }
});
</script>
</body>
</html>
