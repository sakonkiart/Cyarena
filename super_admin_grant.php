<?php
// super_admin_grant.php
session_start();
require_once 'db_connect.php';

// ====== 0) ตรวจสิทธิ์ ต้องเป็น SUPER ADMIN ======
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    die('Forbidden: Super Admin only.');
}

$errors = [];
$success = '';

// โหลดข้อมูล dropdown
function fetchAll($conn, $sql, $types = '', ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$customers = fetchAll($conn, "SELECT CustomerID, FirstName, LastName, Email FROM Tbl_Customer ORDER BY FirstName, LastName");
$employees = fetchAll($conn, "SELECT EmployeeID, FirstName, LastName, Username, RoleID FROM Tbl_Employee ORDER BY FirstName, LastName");
$roles     = fetchAll($conn, "SELECT RoleID, RoleName FROM Tbl_Role");
$roleMap   = [];
foreach ($roles as $r) $roleMap[$r['RoleName']] = (int)$r['RoleID'];

$venueTypes = fetchAll($conn, "SELECT VenueTypeID, TypeName FROM Tbl_Venue_Type ORDER BY TypeName");

// โหลดสนามสำหรับประเภทที่เลือก (ถ้า POST/GET มีค่า)
$selectedVenueType = (int)($_POST['VenueTypeID'] ?? $_GET['VenueTypeID'] ?? 0);
$venues = $selectedVenueType
    ? fetchAll($conn, "SELECT VenueID, VenueName FROM Tbl_Venue WHERE VenueTypeID = ? ORDER BY VenueName", "i", $selectedVenueType)
    : [];

// ====== 1) เมื่อกด Grant ======
if (isset($_POST['action']) && $_POST['action'] === 'grant') {
    $grantedByEmpId = (int)($_SESSION['employee_id'] ?? 0); // แนะนำให้เก็บไว้ใน session ตอนล็อกอินแอดมิน
    if ($grantedByEmpId <= 0) $errors[] = 'ไม่พบ EmployeeID ของผู้ให้สิทธิ์ใน session';

    $customerId = (int)($_POST['CustomerID'] ?? 0);
    $employeeId = (int)($_POST['EmployeeID'] ?? 0);
    $venueTypeId = (int)($_POST['VenueTypeID'] ?? 0);
    $allowedVenues = array_map('intval', $_POST['AllowedVenues'] ?? []);

    if ($venueTypeId <= 0) $errors[] = 'กรุณาเลือกประเภทกีฬา';
    if (empty($allowedVenues)) $errors[] = 'กรุณาเลือกอย่างน้อย 1 สนาม';

    // หา RoleID ของ admin / super_admin
    $adminRoleId = $roleMap['admin'] ?? null;
    if (!$adminRoleId) $errors[] = 'ไม่พบ Role "admin"';

    if (!$errors) {
        $conn->begin_transaction();
        try {
            // 1. ถ้าเลือกจาก Customer → สร้าง/อัปเดต Employee ที่ผูกกับ Customer
            if ($customerId > 0 && $employeeId === 0) {
                // ค้นลูกค้า
                $stmt = $conn->prepare("SELECT FirstName, LastName, Email FROM Tbl_Customer WHERE CustomerID = ?");
                $stmt->bind_param("i", $customerId);
                $stmt->execute(); $cust = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$cust) throw new Exception('ไม่พบบัญชีลูกค้า');

                // มี employee record ที่ลิงก์ลูกค้านี้อยู่แล้วไหม
                $stmt = $conn->prepare("SELECT EmployeeID FROM Tbl_Employee WHERE CustomerID = ?");
                $stmt->bind_param("i", $customerId);
                $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

                if ($row) {
                    $employeeId = (int)$row['EmployeeID'];
                    // อัปเดตให้เป็น admin
                    $stmt = $conn->prepare("UPDATE Tbl_Employee SET RoleID = ? WHERE EmployeeID = ?");
                    $stmt->bind_param("ii", $adminRoleId, $employeeId);
                    $stmt->execute(); $stmt->close();
                } else {
                    // สร้าง employee ใหม่จากข้อมูลลูกค้า (สุ่มรหัสผ่านชั่วคราว)
                    $tmpPass = bin2hex(random_bytes(6));
                    $username = $cust['Email']; // ใช้อีเมลเป็น username
                    $stmt = $conn->prepare("
                        INSERT INTO Tbl_Employee (FirstName, LastName, Phone, RoleID, Username, Password, CustomerID)
                        VALUES (?, ?, '', ?, ?, ?, ?)
                    ");
                    // แนะนำ: เปลี่ยน Password ให้เป็น hash ในภายหลัง (admin เปลี่ยนเอง)
                    $stmt->bind_param("ssiisi",
                        $cust['FirstName'], $cust['LastName'], $adminRoleId, $username, $tmpPass, $customerId
                    );
                    $stmt->execute();
                    $employeeId = $stmt->insert_id;
                    $stmt->close();
                }
            }

            // 2. ถ้าเลือก Employee โดยตรง → อัปเดตบทบาทให้เป็น admin
            if ($employeeId > 0) {
                $stmt = $conn->prepare("UPDATE Tbl_Employee SET RoleID = ? WHERE EmployeeID = ?");
                $stmt->bind_param("ii", $adminRoleId, $employeeId);
                $stmt->execute(); $stmt->close();
            } else {
                throw new Exception('ไม่พบ Employee ที่จะให้สิทธิ์');
            }

            // 3. Upsert Assignment (หนึ่งคนต่อหนึ่งประเภท)
            //    ถ้ามีอยู่แล้วจะอัปเดตประเภทกีฬาใหม่ และจะเคลียร์สนามที่เคยเลือก
            $stmt = $conn->prepare("
                INSERT INTO Tbl_Admin_Assignment (EmployeeID, VenueTypeID, GrantedByEmpID)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE VenueTypeID = VALUES(VenueTypeID), GrantedByEmpID = VALUES(GrantedByEmpID), GrantedAt = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param("iii", $employeeId, $venueTypeId, $grantedByEmpId);
            $stmt->execute(); $stmt->close();

            // 4. เคลียร์รายการสนามเดิม แล้วใส่ชุดใหม่ (Trigger จะบล็อกกรณีเลือกผิดประเภท)
            $stmt = $conn->prepare("DELETE FROM Tbl_Admin_Allowed_Venue WHERE EmployeeID = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute(); $stmt->close();

            $stmt = $conn->prepare("INSERT INTO Tbl_Admin_Allowed_Venue (EmployeeID, VenueID) VALUES (?, ?)");
            foreach ($allowedVenues as $vid) {
                $stmt->bind_param("ii", $employeeId, $vid);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            $success = "ให้สิทธิ์สำเร็จ: Admin #{$employeeId} ดูแลประเภทกีฬา {$venueTypeId} และสนามที่เลือกแล้ว";
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// ====== ฟังก์ชันช่วยดึงชื่อ role (สวยงามเวลาแสดงผล) ======
function roleName($roleId, $roles) {
    foreach ($roles as $r) if ((int)$r['RoleID'] === (int)$roleId) return $r['RoleName'];
    return 'unknown';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Grant Admin (Super Admin)</title>
<style>
body{font-family:system-ui,Segoe UI,Arial;padding:16px;max-width:900px;margin:auto}
fieldset{margin-bottom:16px}
label{display:block;margin:8px 0 4px}
select,button{padding:8px}
.success{background:#e8fff1;border:1px solid #38b000;padding:10px;margin-bottom:10px}
.error{background:#fff1f1;border:1px solid #d00000;padding:10px;margin-bottom:10px}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #ddd;padding:6px}
</style>
</head>
<body>

<h2>Grant สิทธิ์ Admin (เฉพาะ Super Admin)</h2>

<?php if ($success): ?><div class="success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="error"><?=htmlspecialchars($er)?></div><?php endforeach; ?>

<form method="post">
  <fieldset>
    <legend>เลือกผู้รับสิทธิ์</legend>
    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
      <div>
        <label>จากลูกค้า (โปรโมตเป็นแอดมิน)</label>
        <select name="CustomerID">
          <option value="0">-- ไม่เลือก --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?=$c['CustomerID']?>"><?=htmlspecialchars($c['FirstName'].' '.$c['LastName'].' ('.$c['Email'].')')?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:12px;color:#666">ถ้าเลือกตรงนี้ ไม่ต้องเลือก Employee ด้านขวา</div>
      </div>

      <div>
        <label>หรือเลือกพนักงานที่มีอยู่</label>
        <select name="EmployeeID">
          <option value="0">-- ไม่เลือก --</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?=$e['EmployeeID']?>">
              <?=htmlspecialchars($e['FirstName'].' '.$e['LastName'].' ('.$e['Username'].') [role='.roleName($e['RoleID'],$roles).']')?>
            </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:12px;color:#666">ถ้าเลือกตรงนี้ ไม่ต้องเลือก Customer</div>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>กำหนดประเภทกีฬา + เลือกสนามที่ดูแล</legend>
    <label>ประเภทกีฬา</label>
    <select name="VenueTypeID" onchange="this.form.submit()">
      <option value="0">-- เลือกประเภทกีฬา --</option>
      <?php foreach ($venueTypes as $vt): ?>
        <option value="<?=$vt['VenueTypeID']?>" <?= $selectedVenueType===$vt['VenueTypeID']?'selected':''?>>
          <?=htmlspecialchars($vt['TypeName'])?>
        </option>
      <?php endforeach; ?>
    </select>

    <label style="margin-top:10px">สนามที่อนุญาต (กด Ctrl/Shift เพื่อเลือกหลายรายการ)</label>
    <select name="AllowedVenues[]" multiple size="8" style="min-width:360px">
      <?php foreach ($venues as $v): ?>
        <option value="<?=$v['VenueID']?>"><?=htmlspecialchars($v['VenueName'])?></option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <input type="hidden" name="action" value="grant">
  <button type="submit">ให้สิทธิ์</button>
</form>

<hr>
<h3>ตัวอย่างพนักงานที่มีอยู่</h3>
<table>
  <tr><th>ID</th><th>ชื่อ</th><th>Username</th><th>Role</th></tr>
  <?php foreach ($employees as $e): ?>
  <tr>
    <td><?=$e['EmployeeID']?></td>
    <td><?=htmlspecialchars($e['FirstName'].' '.$e['LastName'])?></td>
    <td><?=htmlspecialchars($e['Username'])?></td>
    <td><?=htmlspecialchars(roleName($e['RoleID'],$roles))?></td>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
