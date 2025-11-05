<?php
// auth_helper.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

require_once 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

$CURRENT_USER_ID   = (int)($_SESSION['user_id']);
$CURRENT_ROLE      = $_SESSION['role'] ?? 'customer';

/**
 * ดึง CompanyID ที่ super_admin มอบสิทธิ์ให้กับ "ลูกค้า" (admin รายบริษัท)
 * ถ้าเป็นพนักงานระบบ (employee) แล้วมีแบบผูกบริษัทในอนาคต ค่อยขยายเพิ่มได้
 */
function getCompanyIdForCurrentAdmin(mysqli $conn): ?int {
  global $CURRENT_ROLE, $CURRENT_USER_ID;
  if (!in_array($CURRENT_ROLE, ['admin', 'type_admin'], true)) return null;

  // ผู้ดูแลบริษัท = บันทึกอยู่ใน Tbl_Company_Admin (CustomerID อ้างถึง Tbl_Customer)
  $sql = "SELECT ca.CompanyID
          FROM Tbl_Company_Admin ca
          WHERE ca.CustomerID = ?
          LIMIT 1";
  if ($stm = $conn->prepare($sql)) {
    $stm->bind_param("i", $CURRENT_USER_ID);
    $stm->execute();
    $rs = $stm->get_result();
    if ($row = $rs->fetch_assoc()) return (int)$row['CompanyID'];
  }
  return null;
}
