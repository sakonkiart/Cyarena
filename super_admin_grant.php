<?php
// super_admin_grant.php (UI โทนอ่อน สบายตา)
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

/* ---------------- BOOTSTRAP / MIGRATION ---------------- */
mysqli_report(MYSQLI_REPORT_OFF);

// 1) Company
$conn->query("
CREATE TABLE IF NOT EXISTS Tbl_Company (
  CompanyID   INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  CompanyName VARCHAR(255) NOT NULL,
  CreatedAt   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_name (CompanyName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// 2) Company-Admin
$conn->query("
CREATE TABLE IF NOT EXISTS Tbl_Company_Admin (
  CompanyAdminID INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  CompanyID      INT NOT NULL,
  CustomerID     INT NOT NULL,
  Role           ENUM('admin','employee') NOT NULL DEFAULT 'admin',
  CreatedAt      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_customer (CustomerID),
  KEY idx_company (CompanyID),
  CONSTRAINT fk_ca_company  FOREIGN KEY (CompanyID)  REFERENCES Tbl_Company(CompanyID) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ca_customer FOREIGN KEY (CustomerID) REFERENCES Tbl_Customer(CustomerID) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// 3) Tbl_Venue.CompanyID
$hasCompanyCol = false;
if ($rs = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='Tbl_Venue' AND COLUMN_NAME='CompanyID'")) {
  $hasCompanyCol = ($rs->num_rows > 0);
  $rs->close();
}
if (!$hasCompanyCol) {
  $conn->query("ALTER TABLE Tbl_Venue ADD COLUMN CompanyID INT NULL AFTER VenueID;");
}
$conn->query("ALTER TABLE Tbl_Venue ADD INDEX idx_venue_company (CompanyID)");
$hasFk = false;
if ($rs = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='Tbl_Venue' AND REFERENCED_TABLE_NAME='Tbl_Company'")) {
  $hasFk = ($rs->num_rows > 0);
  $rs->close();
}
if (!$hasFk) {
  @$conn->query("ALTER TABLE Tbl_Venue DROP FOREIGN KEY fk_venue_company");
  @$conn->query("ALTER TABLE Tbl_Venue ADD CONSTRAINT fk_venue_company
                 FOREIGN KEY (CompanyID) REFERENCES Tbl_Company(CompanyID)
                 ON UPDATE CASCADE ON DELETE RESTRICT");
}

// 4) seed default company
$defaultCompanyId = null;
if ($r = $conn->query("SELECT CompanyID FROM Tbl_Company ORDER BY CompanyID LIMIT 1")) {
  if ($row = $r->fetch_assoc()) $defaultCompanyId = (int)$row['CompanyID'];
  $r->close();
}
if (!$defaultCompanyId) {
  $conn->query("INSERT INTO Tbl_Company (CompanyName) VALUES ('Default Company')");
  $defaultCompanyId = (int)$conn->insert_id;
}
$conn->query("UPDATE Tbl_Venue SET CompanyID = {$defaultCompanyId} WHERE CompanyID IS NULL");

/* roles เพิ่มกันลืม */
@$conn->query("INSERT INTO Tbl_Role (RoleName)
               SELECT 'employee' FROM DUAL
               WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='employee')");
@$conn->query("INSERT INTO Tbl_Role (RoleName)
               SELECT 'super_admin' FROM DUAL
               WHERE NOT EXISTS(SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')");

$roles = [];
if ($rs = $conn->query("SELECT RoleID, RoleName FROM Tbl_Role ORDER BY RoleName")) {
  while ($r = $rs->fetch_assoc()) $roles[$r['RoleName']] = (int)$r['RoleID'];
  $rs->close();
}

/* ---------------- HELPERS ---------------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function get_or_create_company_id(mysqli $conn, string $name): ?int {
  $name = trim($name);
  if ($name === '') return null;

  if ($stmt = $conn->prepare("SELECT CompanyID FROM Tbl_Company WHERE CompanyName=? LIMIT 1")) {
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $rs = $stmt->get_result();
    if ($row = $rs->fetch_assoc()) { $stmt->close(); return (int)$row['CompanyID']; }
    $stmt->close();
  }
  if ($stmt = $conn->prepare("
      INSERT INTO Tbl_Company(CompanyName)
      VALUES (?)
      ON DUPLICATE KEY UPDATE CompanyName = VALUES(CompanyName)
  ")) {
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
      $newId = (int)$conn->insert_id;
      $stmt->close();
      if ($newId) return $newId;
      if ($stmt2 = $conn->prepare("SELECT CompanyID FROM Tbl_Company WHERE CompanyName=? LIMIT 1")) {
        $stmt2->bind_param("s", $name);
        $stmt2->execute();
        $rs2 = $stmt2->get_result();
        $id = ($row2 = $rs2->fetch_assoc()) ? (int)$row2['CompanyID'] : null;
        $stmt2->close();
        return $id;
      }
    } else { $stmt->close(); }
  }
  return null;
}

/* ---------------- ACTIONS ---------------- */
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
      if ($stmt = $conn->prepare("UPDATE Tbl_Employee SET RoleID=? WHERE EmployeeID=?")) {
        $stmt->bind_param("ii", $rid, $empId);
        if ($stmt->execute()) { $message = "✅ อัปเดตสิทธิ์พนักงานสำเร็จ"; }
        else { $message = "❌ อัปเดตไม่สำเร็จ: ".h($conn->error); }
        $stmt->close();
      } else {
        $message = "❌ เตรียมคำสั่งไม่สำเร็จ (พนักงาน)";
      }
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_company'])) {
  $newName = trim($_POST['new_company_name'] ?? '');
  if ($newName === '') {
    $message = "❌ กรุณากรอกชื่อบริษัท";
  } else {
    $cid = get_or_create_company_id($conn, $newName);
    if ($cid) $message = "✅ เพิ่ม/ใช้บริษัทเรียบร้อย: ".h($newName)." (ID: ".$cid.")";
    else     $message = "❌ ไม่สามารถเพิ่มบริษัทได้";
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_admin_action'])) {
  $action = $_POST['company_admin_action'];
  $customerId = (int)($_POST['customer_id'] ?? 0);

  if ($action === 'assign') {
    $typedName = trim($_POST['company_name_typed'] ?? '');
    $companyId = get_or_create_company_id($conn, $typedName);
    $companyRole = 'admin';

    if (!$companyId) {
      $message = "❌ กรุณาพิมพ์ชื่อบริษัท";
    } else {
      $sql = "INSERT INTO Tbl_Company_Admin (CompanyID, CustomerID, Role)
              VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE CompanyID=VALUES(CompanyID), Role=VALUES(Role)";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iis", $companyId, $customerId, $companyRole);
        if ($stmt->execute()) { $message = "✅ แต่งตั้ง/ปรับสิทธิ์บริษัทสำเร็จ"; }
        else { $message = "❌ ไม่สำเร็จ: ".h($conn->error); }
        $stmt->close();
      } else {
        $message = "❌ เตรียมคำสั่งไม่สำเร็จ (แต่งตั้งบริษัท)";
      }
    }
  }
  elseif ($action === 'revoke') {
    if ($stmt = $conn->prepare("DELETE FROM Tbl_Company_Admin WHERE CustomerID=?")) {
      $stmt->bind_param("i", $customerId);
      if ($stmt->execute()) { $message = "✅ ยกเลิกสิทธิ์สำเร็จ"; }
      else { $message = "❌ ไม่สำเร็จ: ".h($conn->error); }
      $stmt->close();
    } else {
      $message = "❌ เตรียมคำสั่งไม่สำเร็จ (ยกเลิกสิทธิ์)";
    }
  }
}

/* รายชื่อบริษัท (โชว์เฉยๆ) */
$companies = [];
if ($co = $conn->query("SELECT CompanyID, CompanyName FROM Tbl_Company ORDER BY CompanyName")) {
  while ($r = $co->fetch_assoc()) $companies[] = $r;
  $co->close();
}

/* ผู้ใช้ทั้งหมด */
$users = [];
$sqlEmp = "
  SELECT e.EmployeeID AS id, e.FirstName, e.Username,
         COALESCE(r.RoleName,'employee') AS role_name,
         'employee' AS kind
  FROM Tbl_Employee e
  LEFT JOIN Tbl_Role r ON e.RoleID = r.RoleID
";
if ($res = $conn->query($sqlEmp)) { $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC)); $res->close(); }

$sqlCus = "
  SELECT c.CustomerID AS id, c.FirstName, c.Username,
         ca.Role AS CompanyRole, co.CompanyName,
         'customer' AS kind
  FROM Tbl_Customer c
  LEFT JOIN Tbl_Company_Admin ca ON ca.CustomerID = c.CustomerID
  LEFT JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
";
if ($res = $conn->query($sqlCus)) { $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC)); $res->close(); }

usort($users, function($a,$b){
  $rank = ['employee'=>0,'customer'=>1];
  $ka = $rank[$a['kind']] ?? 9; $kb = $rank[$b['kind']] ?? 9;
  if ($ka !== $kb) return $ka <=> $kb;
  return strcmp((string)$a['Username'], (string)$b['Username']);
});
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>มอบสิทธิ์ผู้ดูแลระบบ / admin รายบริษัท</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  /* โทนสีอ่อน สบายตา */
  --bg:#f5f7fa;
  --card:#ffffff;
  --text:#233044;
  --muted:#5f6c7b;
  --line:#e6e9ee;
  --shadow:0 8px 24px rgba(28,39,56,.06);

  --primary:#5c7c99;        /* ฟ้าอมเทา */
  --primary-strong:#4f6d88;
  --accent:#6aa6a1;         /* teal นุ่ม */
  --success:#3a8f7d;        /* เขียวนวล */
  --warn:#c94c4c;           /* แดงหม่น */
  --amber:#d6a15f;          /* เหลืองนวล */
}

*{box-sizing:border-box}
body{font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);margin:0}
.container{max-width:1200px;margin:88px auto 32px;padding:0 20px}

/* Top bar */
.topbar{
  position:fixed;top:0;left:0;right:0;z-index:10;background:rgba(255,255,255,.9);
  backdrop-filter:saturate(160%) blur(8px);
  border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;padding:10px 16px;
}
.topbar-left{display:flex;align-items:center;gap:10px}
.breadcrumb{font-weight:700;color:var(--muted)}
.breadcrumb a{color:var(--primary);text-decoration:none}
.back-btn{
  display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);
  color:var(--text);padding:8px 12px;border-radius:12px;box-shadow:var(--shadow);text-decoration:none
}
.back-btn:hover{border-color:var(--primary);color:var(--primary)}

/* Headings */
h1{margin:0 0 6px;font-weight:700}
.sub{color:var(--muted);margin:0 0 16px}

/* Card */
.card{
  background:var(--card);border:1px solid var(--line);border-radius:16px;
  box-shadow:var(--shadow);padding:16px;margin-bottom:16px
}

/* Table */
.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left}
.table thead th{position:sticky;top:0;background:#fbfcfe;z-index:1}
.table tbody tr:hover{background:#f7f9fc}

/* Pills & badges */
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.86rem;font-weight:700}
.badge.sa{background:var(--primary);color:#fff}
.badge.emp{background:var(--success);color:#e7f3f0}
.badge.cus{background:#e9eef5;color:#425264}
.badge.co{background:var(--amber);color:#41321d}

.type{display:inline-block;padding:3px 9px;border-radius:10px;font-size:.84rem;margin-right:6px}
.type-emp{background:#dfeeea;color:#2a6a5b;border:1px solid #cfe3dd}
.type-cus{background:#eef2f7;color:#4b596a;border:1px solid #e2e7ee}

/* Actions & Buttons */
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:none;border-radius:12px;padding:9px 12px;font-weight:700;cursor:pointer;box-shadow:var(--shadow)}
.btn.sa{background:var(--primary);color:#fff}
.btn.emp{background:var(--success);color:#f2fbf8}
.btn.warn{background:var(--warn);color:#fff}
.btn.ghost{background:#fff;border:1px solid var(--line);color:var(--text)}
.btn:hover{filter:saturate(110%)}

/* Inputs */
.select,.inline-input{
  padding:9px 10px;border:1px solid var(--line);border-radius:12px;background:#fff;
  transition:border-color .2s, box-shadow .2s
}
.inline-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(92,124,153,.12)}

/* Utils */
.search{margin:10px 0 16px}
.search input{width:280px}
.msg{
  margin:12px 0 16px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);
  background:#f4f7fb;color:var(--primary-strong)
}

/* Toast */
.toast{
  position:fixed;right:16px;bottom:16px;background:var(--accent);color:#fff;
  padding:12px 14px;border-radius:14px;box-shadow:var(--shadow);z-index:30;display:none
}

/* Small text */
.small{color:var(--muted)}
</style>
</head>
<body>
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <a href="dashboard.php" class="back-btn" title="กลับสู่หน้า Dashboard">⬅️ กลับ Dashboard</a>
      <div class="breadcrumb">มอบสิทธิ์ผู้ดูแลระบบ ▸ <span style="color:var(--primary)">ตั้งลูกค้าเป็น admin รายบริษัท</span></div>
    </div>
    <a class="back-btn" href="javascript:location.reload()">🔄 รีเฟรช</a>
  </div>

  <div class="container">
    <h1>มอบสิทธิ์ผู้ดูแลระบบ &nbsp;|&nbsp; ตั้งลูกค้าเป็น <u>admin</u> รายบริษัท</h1>
    <p class="sub">โทนสีอ่อน สบายตา • ลูกค้าพิมพ์ “ชื่อบริษัท” แล้วกดบันทึก ระบบจะสร้าง/ใช้บริษัทให้อัตโนมัติ</p>

    <?php if ($message): ?>
      <div class="msg"><?= h($message) ?></div>
      <div class="toast" id="toast"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- เพิ่มบริษัท (ตัวเลือก) -->
    <div class="card">
      <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="create_company" value="1">
        <strong>เพิ่มบริษัทใหม่ (ตัวเลือกเสริม):</strong>
        <input type="text" name="new_company_name" class="inline-input" placeholder="พิมพ์ชื่อบริษัท...">
        <button class="btn emp" type="submit">เพิ่มบริษัท</button>
        <span class="small">* หรือพิมพ์ชื่อบริษัทในแถวลูกค้าแล้ว “บันทึกสิทธิ์ (admin)” ได้เลย</span>
      </form>
    </div>

    <div class="card">
      <div class="search">🔎 ค้นหา: <input type="text" id="q" class="inline-input" placeholder="พิมพ์ชื่อหรือ username"></div>
      <div style="overflow:auto;border:1px solid var(--line);border-radius:14px;background:#fff">
        <table class="table" id="tbl">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th style="width:120px">ประเภทผู้ใช้</th>
              <th>ชื่อ (FirstName)</th>
              <th>Username</th>
              <th>สิทธิ์/สถานะปัจจุบัน</th>
              <th style="width:560px">จัดการ</th>
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
                <?php else: ?>
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
                <?php else: ?>
                  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="company_admin_action" value="assign">
                    <input type="hidden" name="customer_id" value="<?= (int)$u['id'] ?>">
                    <input type="text" name="company_name_typed" class="inline-input" placeholder="พิมพ์ชื่อบริษัท..." required>
                    <input type="hidden" name="company_role" value="admin">
                    <button class="btn emp" type="submit">บันทึกสิทธิ์ (admin)</button>
                  </form>

                  <?php if (!empty($u['CompanyRole'])): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="company_admin_action" value="revoke">
                      <input type="hidden" name="customer_id" value="<?= (int)$u['id'] ?>">
                      <button class="btn warn" type="submit">ยกเลิกสิทธิ์</button>
                    </form>
                  <?php endif; ?>

                  <div class="small">* ถ้ายังไม่มีบริษัท ระบบจะสร้างให้โดยใช้ชื่อที่พิมพ์</div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<script>
// ค้นหา
const q = document.getElementById('q');
const tb = document.getElementById('tbl')?.querySelector('tbody');
if (q && tb) {
  q.addEventListener('input', () => {
    const t = q.value.toLowerCase().trim();
    for (const tr of tb.querySelectorAll('tr')) {
      tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none';
    }
  });
}

// Toast นุ่มๆ
const toast = document.getElementById('toast');
if (toast) {
  toast.style.display = 'block';
  toast.style.opacity = '0';
  requestAnimationFrame(()=>{ toast.style.transition='opacity .4s'; toast.style.opacity='1'; });
  setTimeout(()=>{ toast.style.opacity='0'; }, 2400);
  setTimeout(()=>{ toast.remove(); }, 3000);
}
</script>
</body>
</html>
