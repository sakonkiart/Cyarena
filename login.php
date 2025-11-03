<?php
session_start();

// ----- DB connect -----
if (!file_exists('db_connect.php')) {
    die("Fatal Error: ไม่พบไฟล์ db_connect.php");
}
include 'db_connect.php'; // ต้องมี $conn (mysqli)

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username       = trim($_POST['username'] ?? '');
    $password_plain = trim($_POST['password'] ?? '');

    if ($username === '' || $password_plain === '') {
        $message = "⚠️ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } elseif (!isset($conn) || $conn->connect_error) {
        $message = "❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . ($conn->connect_error ?? 'ตัวแปร $conn หาย');
    } else {

        // ========= 1) พนักงาน/แอดมิน/ซุปเปอร์แอดมิน ก่อน =========
        $sql_emp = "
            SELECT 
                e.EmployeeID AS ID,
                e.Username,
                e.Password,
                COALESCE(r.RoleName,'employee') AS RoleName,
                e.FirstName AS FirstName     -- << ไม่มี LastName แล้ว
            FROM Tbl_Employee e
            LEFT JOIN Tbl_Role r ON r.RoleID = e.RoleID
            WHERE e.Username = ?
            LIMIT 1
        ";
        if ($stmt = $conn->prepare($sql_emp)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $emp_rs = $stmt->get_result();

            if ($emp_rs && $emp_rs->num_rows === 1) {
                $emp = $emp_rs->fetch_assoc();
                $pass_ok = ($password_plain === $emp['Password']) || password_verify($password_plain, $emp['Password']);

                if ($pass_ok) {
                    $displayName = trim($emp['FirstName'] ?? '');
                    if ($displayName === '') $displayName = $emp['Username'];

                    $_SESSION['user_id']     = (int)$emp['ID'];
                    $_SESSION['user_name']   = $displayName;
                    $_SESSION['avatar_path'] = ''; // Tbl_Employee ปกติไม่มี Avatar
                    $_SESSION['role']        = strtolower($emp['RoleName'] ?? 'employee'); // จะได้ 'super_admin' ถ้าตั้งใน DB

                    $stmt->close();
                    $conn->close();
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $message = "❌ รหัสผ่านไม่ถูกต้อง";
                    $stmt->close();
                    $conn->close();
                }
            } else {
                // ========= 2) ถ้าไม่พบในพนักงาน ลองลูกค้า =========
                $stmt->close();

                $sql_cus = "
                    SELECT 
                        c.CustomerID AS ID,
                        c.Username,
                        c.Password,
                        c.FirstName,
                        c.AvatarPath
                    FROM Tbl_Customer c
                    WHERE c.Username = ?
                    LIMIT 1
                ";
                if ($stmt2 = $conn->prepare($sql_cus)) {
                    $stmt2->bind_param("s", $username);
                    $stmt2->execute();
                    $cus_rs = $stmt2->get_result();

                    if ($cus_rs && $cus_rs->num_rows === 1) {
                        $cus = $cus_rs->fetch_assoc();
                        $pass_ok = ($password_plain === $cus['Password']) || password_verify($password_plain, $cus['Password']);

                        if ($pass_ok) {
                            $displayName = trim($cus['FirstName'] ?? '');
                            if ($displayName === '') $displayName = $cus['Username'];

                            $_SESSION['user_id']     = (int)$cus['ID'];
                            $_SESSION['user_name']   = $displayName;
                            $_SESSION['avatar_path'] = $cus['AvatarPath'] ?? '';
                            $_SESSION['role']        = 'customer';

                            $stmt2->close();
                            $conn->close();
                            header("Location: dashboard.php");
                            exit;
                        } else {
                            $message = "❌ รหัสผ่านไม่ถูกต้อง";
                        }
                    } else {
                        $message = "⚠️ ไม่พบ Username นี้ในระบบ";
                    }
                    $stmt2->close();
                    $conn->close();
                } else {
                    $message = "❌ Query (ลูกค้า) ผิดพลาด: " . htmlspecialchars($conn->error);
                    $conn->close();
                }
            }
        } else {
            $message = "❌ Query (พนักงาน) ผิดพลาด: " . htmlspecialchars($conn->error);
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
