<?php
session_start();

// >>> ADD: เสริม Security Header (ไม่มีผลกับ PHP logic เดิม)
@header('X-Frame-Options: DENY');
@header('X-Content-Type-Options: nosniff');
@header('Referrer-Policy: no-referrer-when-downgrade');

// ตรวจสอบและรวมไฟล์เชื่อมต่อ
if (!file_exists('db_connect.php')) {
    die("Fatal Error: ไม่พบไฟล์ db_connect.php กรุณาตรวจสอบการตั้งชื่อไฟล์.");
}
include 'db_connect.php'; // ไฟล์นี้ต้องกำหนดตัวแปร $conn

// >>> ADD: บังคับ charset เพื่อกันปัญหา collation/emoji
if (isset($conn) && !$conn->connect_error) { @$conn->set_charset('utf8mb4'); }

// ===== BOOTSTRAP: สร้าง role + ผู้ใช้แอดมินเริ่มต้น (admin/1234) ถ้ายังไม่มี =====
if (isset($conn) && !$conn->connect_error) {
    // 1) สร้าง role super_admin ถ้ายังไม่มี
    @$conn->query("
        INSERT INTO Tbl_Role (RoleName)
        SELECT 'super_admin'
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')
    ");

    // >>> ADD: สร้างตารางสิทธิ์ลูกค้าแบบ admin รายประเภทสนาม (ครั้งเดียว)
    @$conn->query("
        CREATE TABLE IF NOT EXISTS Tbl_Type_Admin (
            TypeAdminID INT AUTO_INCREMENT PRIMARY KEY,
            CustomerID  INT NOT NULL,
            VenueTypeID INT NOT NULL,
            CreatedAt   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_type_admin_customer (CustomerID),
            KEY idx_type_admin_type (VenueTypeID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // >>> ADD: ตารางบันทึกประวัติการล็อกอิน (audit log)
    @$conn->query("
        CREATE TABLE IF NOT EXISTS Tbl_Login_Log (
            LogID INT AUTO_INCREMENT PRIMARY KEY,
            UserType  VARCHAR(32)  NOT NULL,
            UserID    INT          NOT NULL,
            Username  VARCHAR(100) NOT NULL,
            IP        VARCHAR(64)  NULL,
            UserAgent VARCHAR(255) NULL,
            LoggedAt  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_login_user (UserType,UserID),
            KEY idx_login_time (LoggedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // <<< END ADD

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

    // >>> ADD: เก็บข้อมูลสำหรับ log
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

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

                    // >>> ADD: อัปเกรดให้เป็น hash ทันที หากยังเป็น plain หรือควร rehash
                    $need_rehash = false;
                    $info = password_get_info($row['Password']);
                    if ($info['algo'] === 0 || password_needs_rehash($row['Password'], PASSWORD_DEFAULT)) {
                        $need_rehash = true;
                    }
                    if ($need_rehash) {
                        if ($u = $conn->prepare("UPDATE Tbl_Customer SET Password=? WHERE CustomerID=?")) {
                            $new_hash = password_hash($password_plain, PASSWORD_DEFAULT);
                            $uid = (int)$row['ID'];
                            $u->bind_param("si", $new_hash, $uid);
                            @$u->execute();
                            $u->close();
                        }
                    }
                    // <<< END ADD

                    $_SESSION['user_id'] = $row['ID'];
                    $_SESSION['user_name'] = $row['FirstName'];
                    $_SESSION['avatar_path'] = $row['AvatarPath'] ?? '';
                    $_SESSION['role'] = 'customer';

                    // >>> ADD: เช็คสิทธิ์ลูกค้าที่ถูกแต่งตั้งเป็น admin ราย "ประเภทสนาม"
                    if ($ta = $conn->prepare("
                        SELECT t.VenueTypeID, vt.TypeName
                        FROM Tbl_Type_Admin t
                        JOIN Tbl_Venue_Type vt ON vt.VenueTypeID = t.VenueTypeID
                        WHERE t.CustomerID = ?
                        LIMIT 1
                    ")) {
                        $cid = (int)$row['ID'];
                        $ta->bind_param("i", $cid);
                        $ta->execute();
                        $ta_rs = $ta->get_result();
                        if ($ta_rs && $ta_rs->num_rows === 1) {
                            $ta_row = $ta_rs->fetch_assoc();
                            $_SESSION['role'] = 'type_admin'; // ยกระดับลูกค้าคนนี้
                            $_SESSION['type_admin_venue_type_id'] = (int)$ta_row['VenueTypeID'];
                            $_SESSION['type_admin_type_name']     = $ta_row['TypeName'];
                        }
                        $ta->close();
                    }
                    // <<< END ADD

                    // >>> ADD: ป้องกัน Session Fixation + บันทึกล็อกอิน
                    @session_regenerate_id(true);
                    if ($log = $conn->prepare("INSERT INTO Tbl_Login_Log (UserType,UserID,Username,IP,UserAgent) VALUES (?,?,?,?,?)")) {
                        $ut = $_SESSION['role']; $uid = (int)$_SESSION['user_id'];
                        $log->bind_param("sisss", $ut, $uid, $username, $client_ip, $user_agent);
                        @$log->execute();
                        $log->close();
                    }
                    // <<< END ADD

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

                        // >>> ADD: อัปเกรดรหัสผ่านพนักงานเป็น hash หากยังไม่ใช่
                        $need_rehash_emp = false;
                        $info_emp = password_get_info($row['Password']);
                        if ($info_emp['algo'] === 0 || password_needs_rehash($row['Password'], PASSWORD_DEFAULT)) {
                            $need_rehash_emp = true;
                        }
                        if ($need_rehash_emp) {
                            if ($u = $conn->prepare("UPDATE Tbl_Employee SET Password=? WHERE EmployeeID=?")) {
                                $new_hash = password_hash($password_plain, PASSWORD_DEFAULT);
                                $uid = (int)$row['ID'];
                                $u->bind_param("si", $new_hash, $uid);
                                @$u->execute();
                                $u->close();
                            }
                        }
                        // <<< END ADD

                        $_SESSION['user_id'] = $row['ID'];
                        $_SESSION['user_name'] = $row['FirstName'];
                        // เนื่องจากลบ AvatarPath ออกจาก SELECT จึงต้องมั่นใจว่ามีการกำหนดค่าเริ่มต้น
                        $_SESSION['avatar_path'] = $row['AvatarPath'] ?? ''; // จะได้ค่าว่าง (nullish coalescing)
                        $_SESSION['role'] = ($row['RoleName'] === 'super_admin') ? 'super_admin' : 'employee';

                        // >>> ADD: ป้องกัน Session Fixation + บันทึกล็อกอิน
                        @session_regenerate_id(true);
                        if ($log = $conn->prepare("INSERT INTO Tbl_Login_Log (UserType,UserID,Username,IP,UserAgent) VALUES (?,?,?,?,?)")) {
                            $ut = $_SESSION['role']; $uid = (int)$_SESSION['user_id'];
                            $log->bind_param("sisss", $ut, $uid, $username, $client_ip, $user_agent);
                            @$log->execute();
                            $log->close();
                        }
                        // <<< END ADD

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
/* ... (CSS เดิมของคุณทั้งหมด) ... */
</style>
</head>
<body>
<!-- ... (HTML เดิมของคุณทั้งหมด) ... -->
</body>
</html>
