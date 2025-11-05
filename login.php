<?php
session_start();

// ===== เชื่อมต่อฐานข้อมูล =====
if (!file_exists('db_connect.php')) {
    die("Fatal Error: ไม่พบไฟล์ db_connect.php กรุณาตรวจสอบการตั้งชื่อไฟล์.");
}
include 'db_connect.php'; // กำหนดตัวแปร $conn (mysqli)

// ===== ฟังก์ชันเล็ก ๆ =====
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ===== สร้าง super_admin / employee เริ่มต้น (เหมือนเดิม ถ้ามีให้คงไว้) =====
if (isset($conn) && !$conn->connect_error) {
    @$conn->query("
        INSERT INTO Tbl_Role (RoleName)
        SELECT 'super_admin'
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')
    ");
    @$conn->query("
        INSERT INTO Tbl_Role (RoleName)
        SELECT 'employee'
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='employee')
    ");
    // ถ้ายังไม่มี user พนักงานเริ่มต้น (admin/1234) อนุโลมให้สร้าง
    if ($stmt = $conn->prepare("SELECT EmployeeID FROM Tbl_Employee WHERE Username=? LIMIT 1")) {
        $u = 'admin';
        $stmt->bind_param("s", $u);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            // หา role id ของ super_admin
            $rid = null;
            if ($r = $conn->query("SELECT RoleID FROM Tbl_Role WHERE RoleName='super_admin' LIMIT 1")) {
                $row = $r->fetch_assoc(); $rid = (int)$row['RoleID']; $r->free();
            }
            if ($rid) {
                // หมายเหตุ: seed รหัสผ่าน 1234 (ควรเปลี่ยนในโปรดักชัน)
                @$conn->query("INSERT INTO Tbl_Employee (FirstName, Username, Password, RoleID) VALUES ('Admin','admin','1234',$rid)");
            }
        } else { $stmt->close(); }
    }
}

// ===== การส่งฟอร์มล็อกอิน =====
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $password_plain  = (string)($_POST['password'] ?? '');

    if ($username === '' || $password_plain === '') {
        $message = "⚠️ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        $found = false;

        // --- 1) ล็อกอินเป็น “ลูกค้า” ---
        $sql_customer = "SELECT CustomerID AS ID, FirstName, Password, AvatarPath FROM Tbl_Customer WHERE Username = ?";
        $stmt = $conn->prepare($sql_customer);
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();

                // ยอมรับทั้ง password hash และ plaintext seed (เฉพาะ dev)
                if (password_verify($password_plain, $row['Password']) || $password_plain === $row['Password']) {
                    $_SESSION['user_id']    = (int)$row['ID'];
                    $_SESSION['user_name']  = (string)$row['FirstName'];
                    $_SESSION['avatar_path']= (string)($row['AvatarPath'] ?? '');
                    $_SESSION['role']       = 'customer';
                    $found = true;

                    // >>> ตั้งสิทธิ์ตาม "บริษัท" (แทน type_admin เดิม)
                    if ($co = $conn->prepare("
                        SELECT ca.CompanyID, co.CompanyName, ca.Role
                        FROM Tbl_Company_Admin ca
                        JOIN Tbl_Company co ON co.CompanyID = ca.CompanyID
                        WHERE ca.CustomerID = ?
                        LIMIT 1
                    ")) {
                        $cid = (int)$_SESSION['user_id'];
                        $co->bind_param("i", $cid);
                        $co->execute();
                        $co_rs = $co->get_result();
                        if ($co_rs && $co_rs->num_rows === 1) {
                            $co_row = $co_rs->fetch_assoc();
                            $_SESSION['company_id']   = (int)$co_row['CompanyID'];
                            $_SESSION['company_name'] = (string)$co_row['CompanyName'];
                            // ยกระดับ role เป็น admin/employee ตามบทบาทบริษัท
                            $_SESSION['role'] = ($co_row['Role'] === 'employee') ? 'employee' : 'admin';
                        } else {
                            $_SESSION['company_id'] = null; // ลูกค้าทั่วไป
                        }
                        $co->close();
                    }

                    // เข้าสู่ระบบสำเร็จ
                    $stmt->close();
                    $conn->close();
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $message = "❌ รหัสผ่านไม่ถูกต้อง";
                }
            }
            $stmt->close();
        }

        // --- 2) ล็อกอินเป็น “พนักงาน/ระบบ” ---
        if (!$found && $message === "") {
            $sql_emp = "
                SELECT e.EmployeeID AS ID, e.FirstName, e.Password, r.RoleName
                FROM Tbl_Employee e
                JOIN Tbl_Role r ON r.RoleID = e.RoleID
                WHERE e.Username = ?
                LIMIT 1
            ";
            if ($stmt = $conn->prepare($sql_emp)) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $row = $res->fetch_assoc();
                    if (password_verify($password_plain, $row['Password']) || $password_plain === $row['Password']) {
                        $_SESSION['user_id']    = (int)$row['ID'];
                        $_SESSION['user_name']  = (string)$row['FirstName'];
                        $_SESSION['avatar_path']= '';
                        $_SESSION['role']       = ($row['RoleName'] === 'super_admin') ? 'super_admin' : 'employee';

                        // ถ้าระบบของคุณยังไม่ได้ผูก employee ↔ company ให้ปล่อยเป็น null ไปก่อนได้
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
                    $message = "⚠️ ไม่พบผู้ใช้นี้ในระบบ";
                }
            } else {
                $message = "❌ เกิดข้อผิดพลาดในการเตรียม Query (พนักงาน)";
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เข้าสู่ระบบ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
<main class="container">
  <h1>เข้าสู่ระบบ</h1>
  <?php if (!empty($message)) : ?>
    <article role="alert"><?php echo h($message); ?></article>
  <?php endif; ?>
  <form method="post" autocomplete="off">
    <label>ชื่อผู้ใช้
      <input name="username" required>
    </label>
    <label>รหัสผ่าน
      <input type="password" name="password" required>
    </label>
    <button type="submit">เข้าสู่ระบบ</button>
  </form>
</main>
</body>
</html>
