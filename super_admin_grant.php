<?php
// super_admin_grant.php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (($_SESSION['role'] ?? '') !== 'super_admin') {
  http_response_code(403);
  echo "403 Forbidden – ต้องเป็น super_admin เท่านั้น";
  exit;
}

if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

$message = "";

/* ===== role พื้นฐานของพนักงาน (กันลืม) ===== */
@$conn->query("INSERT INTO Tbl_Role (RoleName)
               SELECT 'employee' FROM DUAL
               WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='employee')");
@$conn->query("INSERT INTO Tbl_Role (RoleName)
               SELECT 'super_admin' FROM DUAL
               WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')");

/* แผนที่ role */
$roles = [];
if ($rs = $conn->query("SELECT RoleID, RoleName FROM Tbl_Role ORDER BY RoleName")) {
  while ($r = $rs->fetch_assoc()) $roles[$r['RoleName']] = (int)$r['RoleID'];
  $rs->close();
}

/* ===== จัดการสิทธิ์พนักงาน (คงเดิม) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['role_name'])) {
  $empId    = (int)$_POST['employee_id'];
  $roleName = trim($_POST['role_name']);
  if (!isset($roles[$roleName])) {
    $message = "❌ ไม่พบสิทธิ์ที่เลือก";
  } else {
    $rid = $roles[$roleName];
    if ($empId === (int)$_SESSION['user_id'] && $roleName !== 'super_admin') {
      $message = "⚠️ ห้ามเปลี่ยนสิทธิ์ของตัวเองเป็นอย่างอื่นนอกจาก super_admin";
    } else {
      $stmt = $conn->prepare("UPDATE Tbl_Employee SET RoleID=? WHERE EmployeeID=?");
      $stmt->bind_param("ii", $rid, $empId);
      if ($stmt->execute()) { $message = "✅ อัปเดตสิทธิ์พนักงานสำเร็จ"; }
      else { $message = "❌ อัปเดตไม่สำเร็จ: ".htmlspecialchars($conn->error); }
      $stmt->close();
    }
  }
}

/* ===== แต่งตั้ง/ถอน ลูกค้าเป็น admin/employee ราย "บริษัท" ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_admin_action'])) {
  $action = $_POST['company_admin_action'];
  $cid    = (int)($_POST['customer_id'] ?? 0);

  if ($action === 'assign') {
    $companyId = (int)($_POST['company_id'] ?? 0);
    $companyRole = ($_POST['company_role'] ?? 'admin') === 'employee' ? 'employee' : 'admin';
    if ($companyId <= 0) {
      $message = "❌ กรุณาเลือกบริษัท";
    } else {
      // ลูกค้าหนึ่งคนได้ 1 บริษัท (UPSERT ตาม CustomerID) — ถ้าต้องการหลายบริษัท ให้แก้ UNIQUE ที่ DB
      $stmt = $conn->prepare("
        INSERT INTO Tbl_Company_Admin (CompanyID, CustomerID, Role)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE CompanyID = VALUES(CompanyID), Role = VALUES(Role)
      ");
      $stmt->bind_param("iis", $companyId, $cid, $companyRole);
      if ($stmt->execute()) { $message = "✅ แต่งตั้ง/ปรับสิทธิ์บริษัทสำเร็จ"; }
      else { $message = "❌ ไม่สำเร็จ: ".htmlspecialchars($conn->error); }
      $stmt->close();
    }
  } elseif ($action === 'revoke') {
    $stmt = $conn->prepare("DELETE FROM Tbl_Company_Admin WHERE CustomerID=?");
    $stmt->bind_param("i", $cid);
    if ($stmt->execute()) { $message = "✅ ยกเลิกสิทธิ์สำเร็จ"; }
    else { $message = "❌ ไม่สำเร็จ: ".htmlspecialchars($conn->error); }
    $stmt->close();
  }
}

/* รายชื่อบริษัท (ให้ super_admin เลือกตอนแต่งตั้ง) */
$companies = [];
if ($co = $conn->query("SELECT CompanyID, CompanyName FROM Tbl_Company ORDER BY CompanyName")) {
  while ($r = $co->fetch_assoc()) $companies[] = $r;
  $co->close();
}

/* ===== ดึงผู้ใช้ทั้งหมด (พนักงาน + ลูกค้า + สิทธิ์บริษัท ถ้ามี) ===== */
$users = [];

/* พนักงาน */
$sqlEmp = "
  SELECT e.EmployeeID AS id, e.FirstName, e.Username,
         COALESCE(r.RoleName,'employee') AS role_name,
         'employee' AS kind
  FROM Tbl_Employee e
  LEFT JOIN Tbl_Role r ON e.RoleID = r.RoleID
";
if ($res = $conn->query($sqlEmp)) { $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC)); $res->close(); }

/* ลูกค้า + สิทธิ์บริษัท (ถ้ามี) */
$sqlCus = "
  SELECT c.CustomerID AS id, c.FirstName, c.Username,
         ca.Role AS CompanyRole, co.CompanyName,
         'customer' AS kind
  FROM Tbl_Customer c
  LEFT JOIN Tbl_Company_Admin ca ON ca.CustomerID = c.CustomerID
  LEFT JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
";
if ($res = $conn->query($sqlCus)) { $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC)); $res->close(); }

/* เรียง: employee ก่อน, แล้ว customer; ต่อด้วย username */
usort($users, function($a,$b){
  $rank = ['employee'=>0,'customer'=>1];
  $ka = $rank[$a['kind']] ?? 9; $kb = $rank[$b['kind']] ?? 9;
  if ($ka !== $kb) return $ka <=> $kb;
  return strcmp((string)$a['Username'], (string)$b['Username']);
});

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>มอบสิทธิ์ผู้ดูแลระบบสูงสุด / admin รายบริษัท</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Sarabun',sans-serif;background:#f6f7fb;margin:0;padding:24px;color:#0f172a}
h1{margin:0 0 10px} .sub{color:#64748b;margin:0 0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 6px 18px rgba(0,0,0,.05);padding:16px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px 12px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.85rem;font-weight:700}
.badge.sa{background:#1d4ed8;color:#fff}
.badge.emp{background:#10b981;color:#064e3b}
.badge.cus{background:#e5e7eb;color:#374151}
.badge.co{background:#f59e0b;color:#7c2d12}
.type{display:inline-block;padding:2px 8px;border-radius:8px;font-size:.82rem;margin-right:6px}
.type-emp{background:#d1fae5;color:#065f46}
.type-cus{background:#e5e7eb;color:#374151}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:none;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer}
.btn.sa{background:#1d4ed8;color:#fff}
.btn.emp{background:#10b981;color:#fff}
.btn.warn{background:#ef4444;color:#fff}
.select{padding:6px 8px;border:1px solid #e5e7eb;border-radius:8px}
.msg{margin:12px 0 16px;padding:10px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#f8fafc}
.search{margin:10px 0 16px} .search input{width:260px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
.small{color:#64748b}
</style>
</head>
<body>
  <h1>มอบสิทธิ์ผู้ดูแลระบบ &nbsp;|&nbsp; ตั้งลูกค้าเป็น admin/employee รายบริษัท</h1>
  <p class="sub">พนักงานใช้ปุ่มด้านขวาเพื่อสลับสิทธิ์ ส่วนลูกค้าเลือก “บริษัท” + “บทบาท (admin/employee)” เพื่อแต่งตั้ง/เปลี่ยนสิทธิ์</p>

  <?php if ($message): ?>
    <div class="msg"><?= h($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="search">🔎 ค้นหา: <input type="text" id="q" placeholder="พิมพ์ชื่อหรือ username"></div>
    <table class="table" id="tbl">
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th style="width:120px">ประเภทผู้ใช้</th>
          <th>ชื่อ (FirstName)</th>
          <th>Username</th>
          <th>สิทธิ์/สถานะปัจจุบัน</th>
          <th style="width:520px">จัดการ</th>
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
          <td><?= h($u['FirstName'] ?: '-') ?></td>
          <td><?= h($u['Username'] ?: '-') ?></td>
          <td>
            <?php if ($u['kind']==='employee'): ?>
              <?php if (($u['role_name'] ?? 'employee') === 'super_admin'): ?>
                <span class="badge sa">super_admin</span>
              <?php else: ?>
                <span class="badge emp">employee</span>
              <?php endif; ?>
            <?php else: /* customer */ ?>
              <?php if (!empty($u['CompanyRole'])): ?>
                <span class="badge co"><?= h($u['CompanyRole']) ?> @ <?= h($u['CompanyName']) ?></span>
              <?php else: ?>
                <span class="badge cus">ลูกค้าทั่วไป</span>
              <?php endif; ?>
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
            <?php else: /* customer: แต่งตั้ง admin/employee รายบริษัท */ ?>
              <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="company_admin_action" value="assign">
                <input type="hidden" name="customer_id" value="<?= (int)$u['id'] ?>">
                <select name="company_id" class="select" required>
                  <option value="">— เลือกบริษัท —</option>
                  <?php foreach ($companies as $c): ?>
                    <option value="<?= (int)$c['CompanyID'] ?>">
                      <?= h($c['CompanyName']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="company_role" class="select">
                  <option value="admin" <?= (!empty($u['CompanyRole']) && $u['CompanyRole']==='admin')?'selected':''; ?>>admin</option>
                  <option value="employee" <?= (!empty($u['CompanyRole']) && $u['CompanyRole']==='employee')?'selected':''; ?>>employee</option>
                </select>
                <button class="btn emp" type="submit">บันทึกสิทธิ์บริษัท</button>
              </form>
              <?php if (!empty($u['CompanyRole'])): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="company_admin_action" value="revoke">
                  <input type="hidden" name="customer_id" value="<?= (int)$u['id'] ?>">
                  <button class="btn warn" type="submit">ยกเลิกสิทธิ์</button>
                </form>
              <?php endif; ?>
              <div class="small">* ลูกค้าหนึ่งคนมีได้ 1 บริษัท (ค่าเริ่มต้น) หากต้องการหลายบริษัท ให้ปรับ UNIQUE ที่ตาราง Tbl_Company_Admin</div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<script>
const q = document.getElementById('q');
const tb = document.getElementById('tbl').querySelector('tbody');
q && q.addEventListener('input', () => {
  const t = q.value.toLowerCase().trim();
  for (const tr of tb.querySelectorAll('tr')) {
    tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none';
  }
});
</script>
</body>
</html>
