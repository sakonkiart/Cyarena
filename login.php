<?php
session_start();
if (!file_exists('db_connect.php')) { die("Fatal Error: ไม่พบไฟล์ db_connect.php"); }
include 'db_connect.php';
if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn,'utf8mb4'); }
$message = "";

/* เปิดโหมด bootstrap ซุปเปอร์แอดมิน (ปิดทีหลังได้) */
define('BOOTSTRAP_SUPERADMIN', true);

/* หน้าตรวจสอบฐานที่เว็บกำลังใช้: /login.php?diag=1 */
if (isset($_GET['diag']) && $_GET['diag']=='1') {
  $db  = ($conn->query("SELECT DATABASE() db")->fetch_assoc()['db'] ?? '');
  $t1  = ($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Employee'")->fetch_assoc()['c'] ?? 0);
  $t2  = ($conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='Tbl_Role'")->fetch_assoc()['c'] ?? 0);
  $cnt = $t1 ? (int)($conn->query("SELECT COUNT(*) c FROM Tbl_Employee WHERE LOWER(Username)='superadmin'")->fetch_assoc()['c'] ?? 0) : 0;
  echo "<pre>DB=$db\nTbl_Employee=$t1 Tbl_Role=$t2\nrows(superadmin)=$cnt</pre>"; exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $u = trim($_POST['username'] ?? '');
  $p = trim($_POST['password'] ?? '');

  if ($u==='' || $p==='') {
    $message = "⚠️ กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
  } elseif (!isset($conn) || $conn->connect_error) {
    $message = "❌ เชื่อมต่อฐานข้อมูลไม่ได้: " . ($conn->connect_error ?? 'ตัวแปร $conn หาย');
  } else {

    /* ===== Bootstrap superadmin เมื่อพิมพ์ superadmin/1234 ===== */
    if (BOOTSTRAP_SUPERADMIN && strtolower($u)==='superadmin' && $p==='1234') {
      $conn->query("SET SQL_SAFE_UPDATES=0"); /* กัน safe update mode */
      $conn->query("INSERT INTO Tbl_Role (RoleName)
                    SELECT 'super_admin'
                    WHERE NOT EXISTS (SELECT 1 FROM Tbl_Role WHERE RoleName='super_admin')");
      $conn->query("SET @rid := (SELECT RoleID FROM Tbl_Role WHERE RoleName='super_admin' LIMIT 1)");

      /* พยายามแทรกโดยใส่ FirstName ก่อน (กรณีเป็น NOT NULL) */
      $conn->query("INSERT INTO Tbl_Employee (FirstName, Username, Password, RoleID)
                    SELECT 'Super','superadmin','1234',@rid
                    WHERE NOT EXISTS (SELECT 1 FROM Tbl_Employee WHERE LOWER(Username)='superadmin')");
      if ($conn->errno) {
        /* ถ้าตารางไม่ต้องการ FirstName ให้ fallback */
        $conn->query("INSERT INTO Tbl_Employee (Username, Password, RoleID)
                      SELECT 'superadmin','1234',@rid
                      WHERE NOT EXISTS (SELECT 1 FROM Tbl_Employee WHERE LOWER(Username)='superadmin')");
      }
      /* บังคับอัปเกรดสิทธิ์และรีเซ็ตรหัสผ่าน */
      $conn->query("UPDATE Tbl_Employee
                    SET RoleID=@rid, Password='1234'
                    WHERE LOWER(Username)='superadmin' LIMIT 1");
    }

    /* ===== เช็คพนักงาน/แอดมิน/ซุปเปอร์แอดมิน ===== */
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
        /* ===== ไม่ใช่พนักงาน -> ลองลูกค้า ===== */
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
