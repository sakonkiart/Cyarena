<?php
session_start();
if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';
if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn,'utf8mb4'); }

$message = "";

/* ===== ตั้งค่าบูตสแตรปแอดมินครั้งแรก =====
   ล็อกอิน admin/1234 ครั้งแรก จะสร้างผู้ใช้และ role ให้อัตโนมัติ */
define('BOOTSTRAP_ENABLE', true);
define('BOOTSTRAP_ADMIN_USER', 'admin');
define('BOOTSTRAP_ADMIN_PASS', '1234');
define('BOOTSTRAP_ROLE_NAME',  'super_admin'); // คนที่เห็นปุ่มให้สิทธิ์

/* ตรวจสอบฐานข้อมูลอย่างเร็ว (เปิดดูที่ /login.php?diag=1) */
if (isset($_GET['diag']) && $_GET['diag']=='1') {
  $db  = ($conn->query("SELECT DATABASE() db")->fetch_assoc()['db'] ?? '');
  $hasEmp = (int)($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Employee'")
                    ->fetch_assoc()['c'] ?? 0);
  $hasRole= (int)($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Role'")
                    ->fetch_assoc()['c'] ?? 0);
  $adm = $hasEmp ? (int)($conn->query("SELECT COUNT(*) c FROM Tbl_Employee WHERE LOWER(Username)=LOWER('".BOOTSTRAP_ADMIN_USER."')")
                    ->fetch_assoc()['c'] ?? 0) : 0;
  echo "<pre>DB=$db\nTbl_Employee=$hasEmp Tbl_Role=$hasRole\nrows(".BOOTSTRAP_ADMIN_USER.")=$adm</pre>"; exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $u = trim($_POST['username'] ?? '');
  $p = trim($_POST['password'] ?? '');

  if ($u==='' || $p==='') {
    $message = "⚠️ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
  } elseif (!isset($conn) || $conn->connect_error) {
    $message = "❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . ($conn->connect_error ?? 'ตัวแปร $conn หาย');
  } else {

    /* ===== Bootstrap admin/1234 ให้เป็น super_admin ถ้ายังไม่มี ===== */
    if (BOOTSTRAP_ENABLE && strtolower($u)===strtolower(BOOTSTRAP_ADMIN_USER) && $p===BOOTSTRAP_ADMIN_PASS) {
      $conn->query("SET SQL_SAFE_UPDATES=0"); // กัน error 1175
      // role super_admin
      $conn->query("INSERT INTO Tbl_Role (RoleName)
                    SELECT '".BOOTSTRAP_ROLE_NAME."'
                    WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='".BOOTSTRAP_ROLE_NAME."')");
      $conn->query("SET @rid := (SELECT RoleID FROM Tbl_Role WHERE RoleName='".BOOTSTRAP_ROLE_NAME."' LIMIT 1)");

      // พยายาม insert โดยใส่ FirstName เผื่อ schema บังคับ NOT NULL
      $conn->query("INSERT INTO Tbl_Employee (FirstName, Username, Password, RoleID)
                    SELECT 'Admin','".BOOTSTRAP_ADMIN_USER."','".BOOTSTRAP_ADMIN_PASS."',@rid
                    WHERE NOT EXISTS (SELECT 1 FROM Tbl_Employee WHERE LOWER(Username)=LOWER('".BOOTSTRAP_ADMIN_USER."'))");
      if ($conn->errno) {
        // ถ้าไม่มีคอลัมน์ FirstName ให้ fallback
        $conn->query("INSERT INTO Tbl_Employee (Username, Password, RoleID)
                      SELECT '".BOOTSTRAP_ADMIN_USER."','".BOOTSTRAP_ADMIN_PASS."',@rid
                      WHERE NOT EXISTS (SELECT 1 FROM Tbl_Employee WHERE LOWER(Username)=LOWER('".BOOTSTRAP_ADMIN_USER."'))");
      }
      // บังคับอัปเดต role + รีเซ็ตรหัสผ่าน
      $conn->query("UPDATE Tbl_Employee SET RoleID=@rid, Password='".BOOTSTRAP_ADMIN_PASS."'
                    WHERE LOWER(Username)=LOWER('".BOOTSTRAP_ADMIN_USER."') LIMIT 1");
    }

    /* ===== ล็อกอินพนักงาน/แอดมิน/ซุปเปอร์แอดมิน ===== */
    $sql = "SELECT e.EmployeeID AS ID, e.Username, e.Password,
                   COALESCE(r.RoleName,'employee') AS RoleName
            FROM Tbl_Employee e
            LEFT JOIN Tbl_Role r ON r.RoleID = e.RoleID
            WHERE LOWER(e.Username) = LOWER(?) LIMIT 1";

    if ($st = $conn->prepare($sql)) {
      $st->bind_param("s", $u); $st->execute(); $rs = $st->get_result();
      if ($rs && $rs->num_rows === 1) {
        $row = $rs->fetch_assoc();
        $ok  = ($p === $row['Password']) || password_verify($p, $row['Password']);
        if ($ok) {
          $_SESSION['user_id']     = (int)$row['ID'];
          $_SESSION['user_name']   = $row['Username'];
          $_SESSION['avatar_path'] = '';
          $_SESSION['role']        = strtolower($row['RoleName'] ?? 'employee'); // คาดว่า super_admin
          $st->close(); $conn->close();
          header("Location: dashboard.php"); exit;
        } else {
          $message = "❌ รหัสผ่านไม่ถูกต้อง";
        }
      } else {
        /* ===== ลูกค้า ===== */
        $sql2 = "SELECT c.CustomerID AS ID, c.Username, c.Password
                 FROM Tbl_Customer c
                 WHERE LOWER(c.Username)=LOWER(?) LIMIT 1";
        if ($st2 = $conn->prepare($sql2)) {
          $st2->bind_param("s", $u); $st2->execute(); $rs2 = $st2->get_result();
          if ($rs2 && $rs2->num_rows === 1) {
            $row = $rs2->fetch_assoc();
            $ok  = ($p === $row['Password']) || password_verify($p, $row['Password']);
            if ($ok) {
              $_SESSION['user_id']     = (int)$row['ID'];
              $_SESSION['user_name']   = $row['Username'];
              $_SESSION['avatar_path'] = '';
              $_SESSION['role']        = 'customer';
              $st2->close(); $conn->close();
              header("Location: dashboard.php"); exit;
            } else { $message = "❌ รหัสผ่านไม่ถูกต้อง"; }
          } else { $message = "⚠️ ไม่พบ Username นี้ในระบบ"; }
        } else { $message = "❌ Query (ลูกค้า) ไม่ถูกต้อง: " . htmlspecialchars($conn->error); }
      }
      $st->close();
    } else {
      $message = "❌ Query (พนักงาน) ไม่ถูกต้อง: " . htmlspecialchars($conn->error);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&family=Kanit:wght@700;800&display=swap" rel="stylesheet">
<style>
/* ——— สไตล์เดิมของคุณ ——— */
:root{--primary:#2563eb;--primary-dark:#1e40af;--primary-light:#3b82f6;--gray-100:#f5f5f4;--gray-700:#44403c;--gray-900:#1c1917;--danger:#dc2626;}
body{margin:0;font-family:'Sarabun',sans-serif;background:linear-gradient(135deg,var(--primary-dark),var(--primary));display:flex;align-items:center;justify-content:center;min-height:100vh;color:var(--gray-900);padding:1.5rem;box-sizing:border-box}
.login-card{background:#fff;border-radius:20px;padding:2.5rem 2rem;max-width:420px;min-width:300px;box-shadow:0 8px 24px rgba(0,0,0,0.2)}
.logo{display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:1.8rem}
.logo img{width:220px;max-width:80%;height:auto;display:block;margin:0 auto 10px auto}
h2{text-align:center;font-weight:800;font-family:'Kanit',sans-serif;color:var(--gray-900);margin-bottom:1rem}
p.desc{text-align:center;color:var(--gray-700);margin-bottom:2rem}
.form-group{margin-bottom:1.25rem}
label{display:block;font-weight:700;margin-bottom:0.5rem}
input{width:100%;padding:0.875rem 1rem;border:2px solid var(--gray-100);border-radius:12px;font-size:1rem}
input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,0.2);outline:none}
.btn{width:100%;padding:1rem;font-weight:800;font-family:'Kanit',sans-serif;border:none;border-radius:12px;cursor:pointer;font-size:1.125rem}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff}
.message{margin-top:1rem;color:#dc2626;text-align:center;font-weight:700;padding:0.75rem;border-radius:8px;background:rgba(220,38,38,.08);border:1px solid #dc2626}
.footer-text{text-align:center;margin-top:1.75rem;color:var(--gray-700);font-weight:600}
.footer-text a{color:var(--primary);text-decoration:none;font-weight:700}
</style>
</head>
<body>
<div class="login-card">
  <div class="logo"><img src="images/cy.png" alt="CY Arena"></div>
  <h2>เข้าสู่ระบบ</h2>
  <p class="desc">กรอกชื่อผู้ใช้และรหัสผ่านของคุณเพื่อเข้าสู่ระบบ</p>
  <form method="POST">
    <div class="form-group">
      <label for="username">👤 ชื่อผู้ใช้</label>
      <input type="text" name="username" id="username" required placeholder="admin หรือ username อื่นๆ">
    </div>
    <div class="form-group">
      <label for="password">🔒 รหัสผ่าน</label>
      <input type="password" name="password" id="password" required placeholder="เช่น 1234">
    </div>
    <button type="submit" class="btn btn-primary">เข้าสู่ระบบ 🚀</button>
  </form>
  <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <div class="footer-text">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกฟรี</a></div>
</div>
</body>
</html>
