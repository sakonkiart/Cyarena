<?php
session_start();
// ตรวจสอบและรวมไฟล์เชื่อมต่อ
if (!file_exists('db_connect.php')) {
    die("Fatal Error: ไม่พบไฟล์ db_connect.php กรุณาตรวจสอบการตั้งชื่อไฟล์.");
}
include 'db_connect.php'; // ไฟล์นี้ต้องกำหนดตัวแปร $conn

// ===== BOOTSTRAP: สร้าง role + ผู้ใช้แอดมินเริ่มต้น (admin/1234) ถ้ายังไม่มี =====
if (isset($conn) && !$conn->connect_error) {
    // 1) สร้าง role super_admin ถ้ายังไม่มี
    @$conn->query("
        INSERT INTO Tbl_Role (RoleName)
        SELECT 'super_admin'
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')
    ");

    // (ลบส่วนสร้าง Tbl_Type_Admin เดิมทิ้ง — ไม่ใช้แล้ว)

    // 2) ดึง RoleID
    $rid = null;
    if ($res = @$conn->query("SELECT RoleID FROM Tbl_Role WHERE RoleName='super_admin' LIMIT 1")) {
        $row = $res->fetch_assoc();
        $rid = $row['RoleID'] ?? null;
        $res->free();
    }

    if ($rid) {
        // 3) ถ้ายังไม่มีผู้ใช้ admin ให้สร้างใหม่
        $u = 'admin';
        if ($stmt = $conn->prepare("SELECT EmployeeID FROM Tbl_Employee WHERE Username=? LIMIT 1")) {
            $stmt->bind_param("s", $u);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                $stmt->close();
                if ($ins = $conn->prepare("INSERT INTO Tbl_Employee (FirstName, Username, Password, RoleID) VALUES ('Admin','admin','1234',?)")) {
                    $ins->bind_param("i", $rid);
                    $ins->execute();
                    $ins->close();
                }
            } else {
                // มีแล้ว -> อัปเดตรหัส/สิทธิ์ให้เป็น super_admin
                $stmt->bind_result($eid);
                $stmt->fetch();
                $stmt->close();
                if ($upd = $conn->prepare("UPDATE Tbl_Employee SET Password='1234', RoleID=? WHERE EmployeeID=?")) {
                    $upd->bind_param("ii", $rid, $eid);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
    }
}
// ===== END BOOTSTRAP =====

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password_plain = trim($_POST['password']);
    
    // ตรวจสอบว่ามีการเชื่อมต่อ $conn สำเร็จหรือไม่
    if (!isset($conn) || $conn->connect_error) {
        $message = "❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . ($conn->connect_error ?? "ตัวแปร \$conn ไม่ถูกกำหนดใน db_connect.php");
    } else {
        $found = false;

        // --- 1. ตรวจสอบลูกค้า ---
        $sql_customer = "SELECT CustomerID AS ID, FirstName, Password, AvatarPath FROM Tbl_Customer WHERE Username = ?";
        
        $stmt = $conn->prepare($sql_customer);
        if ($stmt === FALSE) {
            // หาก Query ผิดพลาด (ชื่อตาราง/คอลัมน์ผิด)
            $message = "❌ เกิดข้อผิดพลาดในการเตรียม Query (ลูกค้า): " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password_plain, $row['Password']) || $password_plain === $row['Password']) {
                    $_SESSION['user_id'] = $row['ID'];
                    $_SESSION['user_name'] = $row['FirstName'];
                    $_SESSION['avatar_path'] = $row['AvatarPath'] ?? '';
                    $_SESSION['role'] = 'customer';

                    // >>> REPLACE: เช็คสิทธิ์บริษัท (แทนที่บล็อก Tbl_Type_Admin เดิม) <<<
                    if ($co = $conn->prepare("
                        SELECT ca.CompanyID, co.CompanyName, ca.Role
                        FROM Tbl_Company_Admin ca
                        JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
                        WHERE ca.CustomerID = ?
                        LIMIT 1
                    ")) {
                        $cid = (int)$row['ID'];
                        $co->bind_param("i", $cid);
                        $co->execute();
                        $co_rs = $co->get_result();
                        if ($co_rs && $co_rs->num_rows === 1) {
                            $co_row = $co_rs->fetch_assoc();
                            $_SESSION['company_id']   = (int)$co_row['CompanyID'];
                            $_SESSION['company_name'] = $co_row['CompanyName'];
                            // ยกระดับลูกค้าเป็น 'admin' หรือ 'employee' ตามบทบาทบริษัท
                            $_SESSION['role'] = ($co_row['Role'] === 'employee') ? 'employee' : 'admin';
                        } else {
                            // ลูกค้าทั่วไป (ไม่มีบริษัท)
                            $_SESSION['company_id'] = null;
                        }
                        $co->close();
                    }
                    // <<< END REPLACE >>>

                    $stmt->close();
                    $conn->close();
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $message = "❌ รหัสผ่านไม่ถูกต้อง";
                    $found = true;
                }
            }
            $stmt->close();
        }

        // --- 2. ตรวจสอบพนักงาน (ทำงานเมื่อไม่พบ/รหัสผ่านผิดของลูกค้า และยังไม่มีข้อผิดพลาด Query) ---
        if (!$found && empty($message)) {
            // ปรับ Query: join role เพื่อรู้สิทธิ์ (employee / super_admin)
            $sql_employee = "SELECT e.EmployeeID AS ID, e.FirstName, e.Password,
                                    COALESCE(r.RoleName,'employee') AS RoleName
                             FROM Tbl_Employee e
                             LEFT JOIN Tbl_Role r ON e.RoleID = r.RoleID
                             WHERE e.Username = ?";
            
            $stmt = $conn->prepare($sql_employee);

            if ($stmt === FALSE) {
                // หาก Query ผิดพลาด (ชื่อตาราง/คอลัมน์ผิด)
                $message = "❌ เกิดข้อผิดพลาดในการเตรียม Query (พนักงาน): " . htmlspecialchars($conn->error);
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $row = $result->fetch_assoc();
                    if (password_verify($password_plain, $row['Password']) || $password_plain === $row['Password']) {
                        $_SESSION['user_id'] = $row['ID'];
                        $_SESSION['user_name'] = $row['FirstName'];
                        $_SESSION['avatar_path'] = $row['AvatarPath'] ?? ''; // ไม่มีใน SELECT จะได้ค่าว่าง
                        $_SESSION['role'] = ($row['RoleName'] === 'super_admin') ? 'super_admin' : 'employee';

                        // พนักงาน: หากระบบยังไม่ผูก employee ↔ company ให้คงค่า company_id เดิม/เป็น null
                        if (!isset($_SESSION['company_id'])) {
                            $_SESSION['company_id'] = null;
                        }

                        $stmt->close();
                        $conn->close();
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $message = "❌ รหัสผ่านไม่ถูกต้อง";
                    }
                } else {
                    // หากไม่พบทั้งลูกค้าและพนักงาน
                    $message = "⚠️ ไม่พบ Username นี้ในระบบ";
                }
                $stmt->close();
            }
        }
        
        if (isset($conn)) {
            $conn->close();
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
:root {
  --primary: #2563eb;
  --primary-dark: #1e40af;
  --primary-light: #3b82f6;
  --gray-100: #f5f5f4;
  --gray-700: #44403c;
  --gray-900: #1c1917;
  --danger: #dc2626;
  --spacing: 1.5rem;
  --error: #dc2626; 
}

body {
  margin: 0;
  font-family: 'Sarabun', sans-serif;
  background: linear-gradient(135deg, var(--primary-dark), var(--primary));
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  color: var(--gray-900);
  padding: 1.5rem; 
  box-sizing: border-box;
}

/* ===== CARD ===== */
.login-card {
  background: #fff;
  border-radius: 20px;
  padding: 2.5rem 2rem;
  max-width: 420px; 
  min-width: 300px; 
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  animation: fadeIn 0.7s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ===== LOGO ===== */
.logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.8rem;
}

.logo img {
  width: 220px;
  max-width: 80%;
  height: auto;
  display: block;
  margin: 0 auto 10px auto;
  transition: transform 0.3s ease, filter 0.3s ease;
}

.logo img:hover {
  transform: scale(1.05);
  filter: drop-shadow(0 0 8px rgba(37,99,235,0.3));
}

/* ===== FORM ===== */
h2 {
  text-align: center;
  font-weight: 800;
  font-family: 'Kanit', sans-serif;
  color: var(--gray-900);
  margin-bottom: 1rem;
}
p.desc {
  text-align: center;
  color: var(--gray-700);
  margin-bottom: 2rem;
}
.form-group {
  margin-bottom: 1.25rem;
}
label {
  display: block;
  font-weight: 700;
  margin-bottom: 0.5rem;
}
input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 2px solid var(--gray-100);
  border-radius: 12px;
  font-size: 1rem;
  transition: all 0.3s;
  box-sizing: border-box; 
}
input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
  outline: none;
}

/* ===== BUTTON ===== */
.btn {
  width: 100%;
  padding: 1rem;
  font-weight: 800;
  font-family: 'Kanit', sans-serif;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s;
  font-size: 1.125rem;
}
.btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  box-shadow: 0 4px 12px rgba(37,99,235,0.4);
}
.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(37,99,235,0.6);
}

/* ===== MESSAGE & FOOTER ===== */
.message {
  margin-top: 1rem;
  color: var(--error);
  text-align: center;
  font-weight: 700;
  padding: 0.75rem;
  border-radius: 8px;
  background-color: rgba(220, 38, 38, 0.08); 
  border: 1px solid var(--danger);
}
.footer-text {
  text-align: center;
  margin-top: 1.75rem;
  color: var(--gray-700);
  font-weight: 600;
}
.footer-text a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
}
.footer-text a:hover { text-decoration: underline; }

@media (max-width: 480px) {
  body { padding: 0; } 
  .login-card { 
      width: 100vw; 
      max-width: none;
      border-radius: 0; 
      padding: 2rem 1rem;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
  }
  .logo img { width: 160px; margin-bottom: 8px; }
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
