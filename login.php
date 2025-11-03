<?php
session_start();
if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';                      // ต้องมี $conn (mysqli)
if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn,'utf8mb4'); }

$message = "";

/** ปิดได้หลังตั้งค่าเรียบร้อย */
define('BOOTSTRAP_SUPERADMIN', true);

/** Debug ชั่วคราว: เปิดดูฐานที่เว็บกำลังใช้และจำนวน superadmin ได้ที่ /login.php?diag=1 */
if (isset($_GET['diag']) && $_GET['diag']=='1') {
  $db  = ($conn->query("SELECT DATABASE() db")->fetch_assoc()['db'] ?? '');
  $t1  = ($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Employee'")->fetch_assoc()['c'] ?? 0);
  $t2  = ($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Role'")->fetch_assoc()['c'] ?? 0);
  $cnt = 0;
  if ($t1) { $cnt = (int)($conn->query("SELECT COUNT(*) c FROM Tbl_Employee WHERE LOWER(Username)='superadmin'")->fetch_assoc()['c'] ?? 0); }
  echo "<pre>DB=$db\nTbl_Employee=$t1 Tbl_Role=$t2\nrows(superadmin)=$cnt</pre>";
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username       = trim($_POST['username'] ?? '');
  $password_plain = trim($_POST['password'] ?? '');

  if ($username === '' || $password_plain === '') {
    $message = "⚠️ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
  } elseif (!isset($conn) || $conn->connect_error) {
    $message = "❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . ($conn->connect_error ?? 'ตัวแปร $conn หาย');
  } else {

    /* ===== Bootstrap superadmin อัตโนมัติถ้ายังไม่มี ===== */
    if (BOOTSTRAP_SUPERADMIN && strtolower($username) === 'superadmin' && $password_plain === '1234') {
      $conn->query("INSERT INTO Tbl_Role (RoleName)
                    SELECT 'super_admin' WHERE NOT EXISTS
                    (SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')");
      $conn->query("SET @rid := (SELECT RoleID FROM Tbl_Role WHERE RoleName='super_admin' LIMIT 1)");
      // แทรกเฉพาะคอลัมน์ที่น่าจะมีแน่ ๆ
      $conn->query("INSERT INTO Tbl_Employee (Username, Password, RoleID)
                    SELECT 'superadmin','1234', @rid
                    WHERE NOT EXISTS (SELECT 1 FROM Tbl_Employee WHERE LOWER(Username)='superadmin')");
    }

    $found = false;

    /* ===== ตรวจพนักงาน/แอดมิน/ซุปเปอร์แอดมิน (case-insensitive) ===== */
    $sql_emp = "SELECT e.EmployeeID AS ID, e.Username, e.Password,
                       COALESCE(r.RoleName,'employee') AS RoleName
                FROM Tbl_Employee e
                LEFT JOIN Tbl_Role r ON r.RoleID = e.RoleID
                WHERE LOWER(e.Username) = LOWER(?)
                LIMIT 1";
    if ($stmt = $conn->prepare($sql_emp)) {
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $rs = $stmt->get_result();

      if ($rs && $rs->num_rows === 1) {
        $row = $rs->fetch_assoc();
        $ok  = ($password_plain === $row['Password']) || password_verify($password_plain, $row['Password']);
        if ($ok) {
          $_SESSION['user_id']     = (int)$row['ID'];
          $_SESSION['user_name']   = $row['Username'];  // ใช้ Username เป็นชื่อแสดงผล
          $_SESSION['avatar_path'] = '';
          $_SESSION['role']        = strtolower($row['RoleName'] ?? 'employee'); // คาดหวัง super_admin
          $stmt->close(); $conn->close();
          header("Location: dashboard.php"); exit;
        } else {
          $message = "❌ รหัสผ่านไม่ถูกต้อง";
          $found = true;
        }
      }
      $stmt->close();
    } else {
      $message = "❌ Query (พนักงาน) ไม่ถูกต้อง: " . htmlspecialchars($conn->error);
    }

    /* ===== ถ้าไม่ใช่พนักงาน ลองลูกค้า ===== */
    if (!$found && empty($message)) {
      $sql_cus = "SELECT c.CustomerID AS ID, c.Username, c.Password
                  FROM Tbl_Customer c
                  WHERE LOWER(c.Username) = LOWER(?)
                  LIMIT 1";
      if ($stmt2 = $conn->prepare($sql_cus)) {
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $rs2 = $stmt2->get_result();

        if ($rs2 && $rs2->num_rows === 1) {
          $row = $rs2->fetch_assoc();
          $ok  = ($password_plain === $row['Password']) || password_verify($password_plain, $row['Password']);
          if ($ok) {
            $_SESSION['user_id']     = (int)$row['ID'];
            $_SESSION['user_name']   = $row['Username'];
            $_SESSION['avatar_path'] = '';
            $_SESSION['role']        = 'customer';
            $stmt2->close(); $conn->close();
            header("Location: dashboard.php"); exit;
          } else {
            $message = "❌ รหัสผ่านไม่ถูกต้อง";
          }
        } else {
          $message = "⚠️ ไม่พบ Username นี้ในระบบ";
        }
        $stmt2->close();
      } else {
        if ($message === "") $message = "❌ Query (ลูกค้า) ไม่ถูกต้อง: " . htmlspecialchars($conn->error);
      }
    }

    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
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
:root{--primary:#2563eb;--primary-dark:#1e40af;--primary-light:#3b82f6;--gray-100:#f5f5f4;--gray-700:#44403c;--gray-900:#1c1917;--danger:#dc2626;--error:#dc2626}
body{margin:0;font-family:'Sarabun',sans-serif;background:linear-gradient(135deg,var(--primary-dark),var(--primary));display:flex;align-items:center;justify-content:center;min-height:100vh;color:var(--gray-900);padding:1.5rem;box-sizing:border-box}
.login-card{background:#fff;border-radius:20px;padding:2.5rem 2rem;max-width:420px;min-width:300px;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:fadeIn .7s ease-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.logo{display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:1.8rem}
.logo img{width:220px;max-width:80%;height:auto;display:block;margin:0 auto 10px auto;transition:transform .3s ease,filter .3s ease}
.logo img:hover{transform:scale(1.05);filter:drop-shadow(0 0 8px rgba(37,99,235,.3))}
h2{text-align:center;font-weight:800;font-family:'Kanit',sans-serif;color:var(--gray-900);margin-bottom:1rem}
p.desc{text-align:center;color:var(--gray-700);margin-bottom:2rem}
.form-group{margin-bottom:1.25rem}
label{display:block;font-weight:700;margin-bottom:.5rem}
input{width:100%;padding:.875rem 1rem;border:2px solid var(--gray-100);border-radius:12px;font-size:1rem;transition:all .3s;box-sizing:border-box}
input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.2);outline:none}
.btn{width:100%;padding:1rem;font-weight:800;font-family:'Kanit',sans-serif;border:none;border-radius:12px;cursor:pointer;transition:all .3s;font-size:1.125rem}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.4)}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(37,99,235,.6)}
.message{margin-top:1rem;color:var(--error);text-align:center;font-weight:700;padding:.75rem;border-radius:8px;background-color:rgba(220,38,38,.08);border:1px solid var(--danger)}
.footer-text{text-align:center;margin-top:1.75rem;color:var(--gray-700);font-weight:600}
.footer-text a{color:var(--primary);text-decoration:none;font-weight:700}
.footer-text a:hover{text-decoration:underline}
@media (max-width:480px){
  body{padding:0}
  .login-card{width:100vw;max-width:none;border-radius:0;padding:2rem 1rem;min-height:100vh;display:flex;flex-direction:column;justify-content:center}
  .logo img{width:160px;margin-bottom:8px}
}
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <img src="images/cy.png" alt="CY Arena Logo">
  </div>

  <h2>เข้าสู่ระบบ</h2>
  <p class="desc">กรอกชื่อผู้ใช้และรหัสผ่านของคุณเพื่อเข้าสู่ระบบ</p>

  <form method="POST">
    <div class="form-group">
      <label for="username">👤 ชื่อผู้ใช้</label>
      <input type="text" name="username" id="username" required placeholder="กรอกชื่อผู้ใช้">
    </div>
    <div class="form-group">
      <label for="password">🔒 รหัสผ่าน</label>
      <input type="password" name="password" id="password" required placeholder="กรอกรหัสผ่าน">
    </div>
    <button type="submit" class="btn btn-primary">เข้าสู่ระบบ 🚀</button>
  </form>

  <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="footer-text">
    ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกฟรี</a>
  </div>
</div>
</body>
</html>
