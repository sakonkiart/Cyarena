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

// 4) seed default company (ครั้งแรก)
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

/* ----- roles (กันลืม) ----- */
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

/** คืน CompanyID ถ้ามีอยู่ / สร้างใหม่ถ้าไม่เจอ (จากชื่อบริษัท) */
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

/* เปลี่ยนสิทธิ์พนักงาน (คงเดิม) */
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

/* เพิ่มบริษัทใหม่แบบกดปุ่มเฉพาะ (ฟอร์มด้านบน) */
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

/* แต่งตั้ง/ถอน ลูกค้าเป็น admin รายบริษัท (ไม่มี employee แล้ว) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_admin_action'])) {
  $action = $_POST['company_admin_action'];
  $customerId = (int)($_POST['customer_id'] ?? 0);

  if ($action === 'assign') {
    $typedName = trim($_POST['company_name_typed'] ?? '');
    $companyId = get_or_create_company_id($conn, $typedName); // ต้องพิมพ์ชื่อ
    $companyRole = 'admin'; // บังคับเป็น admin เท่านั้น

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

/* รายชื่อบริษัทเพื่อโชว์ (ยังคงดึงไว้แสดงปัจจุบัน) */
$companies = [];
if ($co = $conn->query("SELECT CompanyID, CompanyName FROM Tbl_Company ORDER BY CompanyName")) {
  while ($r = $co->fetch_assoc()) $companies[] = $r;
  $co->close();
}

/* รวมผู้ใช้ทั้งหมด */
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

/* ลูกค้า + สิทธิ์บริษัท */
$sqlCus = "
  SELECT c.CustomerID AS id, c.FirstName, c.Username,
         ca.Role AS CompanyRole, co.CompanyName,
         'customer' AS kind
  FROM Tbl_Customer c
  LEFT JOIN Tbl_Company_Admin ca ON ca.CustomerID = c.CustomerID
  LEFT JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
";
if ($res = $conn->query($sqlCus)) { $users = array_merge($users, $res->fetch_all(MYSQLI_ASSOC)); $res->close(); }

/* เรียง */
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
<title>มอบสิทธิ์ผู้ดูแลระบบสูงสุด / admin รายบริษัท</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f6f7fb;--text:#0f172a;--muted:#64748b;--card:#fff;--line:#e5e7eb;
  --primary:#2563eb;--primary-600:#1d4ed8;--success:#10b981;--warn:#ef4444;--amber:#f59e0b;
}
*{box-sizing:border-box} body{font-family:'Sarabun',sans-serif;background:var(--bg);margin:0;color:var(--text)}
.container{max-width:1200px;margin:88px auto 32px;padding:0 20px}
.topbar{
  position:fixed;top:0;left:0;right:0;background:#fff;border-bottom:1px solid var(--line);
  display:flex;align-items:center;gap:12px;justify-content:space-between;padding:10px 16px;z-index:20
}
.topbar-left{display:flex;align-items:center;gap:10px}
.breadcrumb{font-weight:700}
.breadcrumb a{color:var(--primary);text-decoration:none}
.back-btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);padding:8px 12px;border-radius:10px;background:#fff;cursor:pointer;text-decoration:none;color:var(--text)}
.back-btn:hover{border-color:var(--primary);transform:translateY(-1px)}
.action-row{display:flex;gap:8px}
.icon{font-size:18px}
h1{margin:0 0 6px}
.sub{color:var(--muted);margin:0 0 16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 6px 18px rgba(0,0,0,.05);padding:16px;margin-bottom:16px}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table thead th{position:sticky;top:0;background:#fafafa;border-bottom:1px solid var(--line);z-index:1}
.table th,.table td{padding:12px 14px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}
.table tbody tr:nth-child(odd){background:#fbfdff}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.85rem;font-weight:700}
.badge.sa{background:var(--primary-600);color:#fff}
.badge.emp{background:var(--success);color:#064e3b}
.badge.cus{background:#e5e7eb;color:#374151}
.badge.co{background:var(--amber);color:#7c2d12}
.type{display:inline-block;padding:2px 8px;border-radius:8px;font-size:.82rem;margin-right:6px}
.type-emp{background:#d1fae5;color:#065f46}
.type-cus{background:#e5e7eb;color:#374151}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:none;border-radius:10px;padding:9px 12px;font-weight:700;cursor:pointer}
.btn.sa{background:var(--primary);color:#fff}
.btn.emp{background:var(--success);color:#fff}
.btn.warn{background:var(--warn);color:#fff}
.btn.ghost{background:#fff;border:1px solid var(--line);color:var(--text)}
.select,.inline-input{padding:7px 9px;border:1px solid var(--line);border-radius:8px}
.msg{margin:12px 0 16px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#f8fafc}
.search{margin:10px 0 16px} .search input{width:280px;padding:9px 10px;border:1px solid var(--line);border-radius:8px}
.small{color:var(--muted)}
.toast{
  position:fixed;right:16px;bottom:16px;background:#0ea5e9;color:#fff;padding:12px 14px;
  border-radius:12px;box-shadow:0 8px 22px rgba(0,0,0,.15);z-index:30;display:none
}
</style>
</head>
<body>
  <!-- Topbar + Breadcrumb + Back -->
  <div class="topbar">
    <div class="topbar-left">
      <a href="dashboard.php" class="back-btn" title="กลับสู่หน้า Dashboard">⬅️ <span>กลับ Dashboard</span></a>
      <div class="breadcrumb">มอบสิทธิ์ผู้ดูแลระบบ ▸ <span style="color:var(--primary)">ตั้งลูกค้าเป็น admin รายบริษัท</span></div>
    </div>
    <div class="action-row">
      <a class="back-btn" href="javascript:location.reload()"><span class="icon">🔄</span>รีเฟรช</a>
    </div>
  </div>

  <div class="container">
    <h1>มอบสิทธิ์ผู้ดูแลระบบ &nbsp;|&nbsp; ตั้งลูกค้าเป็น <u>admin</u> รายบริษัท</h1>
    <p class="sub">พนักงานใช้ปุ่มด้านขวาเพื่อสลับสิทธิ์ ส่วนลูกค้าพิมพ์ “ชื่อบริษัท” แล้วบันทึก ระบบจะสร้าง/ใช้บริษัทให้อัตโนมัติ</p>

    <?php if ($message): ?>
      <div class="msg"><?= h($message) ?></div>
      <div class="toast" id="toast"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- กล่องเพิ่มบริษัทใหม่ (ทางเลือก) -->
    <div class="card">
      <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="create_company" value="1">
        <strong>เพิ่มบริษัทใหม่ (ตัวเลือกเสริม):</strong>
        <input type="text" name="new_company_name" class="inline-input" placeholder="พิมพ์ชื่อบริษัท...">
        <button class="btn emp" type="submit">เพิ่มบริษัท</button>
        <span class="small">* ถ้าไม่เพิ่มจากตรงนี้ ก็พิมพ์ชื่อบริษัทในแถวลูกค้าได้เลย</span>
      </form>
    </div>

    <div class="card">
      <div class="search">🔎 ค้นหา: <input type="text" id="q" placeholder="พิมพ์ชื่อหรือ username"></div>
      <div style="overflow:auto;border:1px solid var(--line);border-radius:12px">
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
                <?php else: /* customer: แต่งตั้ง admin รายบริษัท ด้วยการ "พิมพ์ชื่อบริษัท" เท่านั้น */ ?>
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

                  <div class="small">* ระบบจะสร้างบริษัทใหม่ให้อัตโนมัติหากยังไม่มีชื่อที่พิมพ์ไว้</div>
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
// ค้นหาแถว
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

// Toast แสดงผลลัพธ์ แล้วค่อยๆหาย
const toast = document.getElementById('toast');
if (toast) {
  toast.style.display = 'block';
  setTimeout(()=>{ toast.style.opacity = '0'; toast.style.transition='opacity .6s'; }, 2200);
  setTimeout(()=>{ toast.remove(); }, 3000);
}
</script>
</body>
</html>
