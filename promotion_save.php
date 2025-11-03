<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// ตรวจสอบว่าล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// อนุญาตเฉพาะ super admin เท่านั้น
$role = $_SESSION['role'] ?? '';
$is_super_admin = in_array($role, ['superadmin', 'super_admin', 'super']);

if (!$is_super_admin) {
    http_response_code(403);
    $_SESSION['error_message'] = '❌ คุณไม่มีสิทธิ์ในการสร้างโปรโมชั่น (เฉพาะ Superadmin)';
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: promotion_manage.php');
    exit;
}

// รับค่าพร้อมกันคีย์หาย
$PromoName     = trim($_POST['PromoName']     ?? '');
$PromoCode     = trim($_POST['PromoCode']     ?? '');
$Description   = trim($_POST['Description']   ?? '');
$DiscountType  = trim($_POST['DiscountType']  ?? 'percent');
$DiscountValue = $_POST['DiscountValue']      ?? '';
$Conditions    = trim($_POST['Conditions']    ?? '');

// ✅ ตรวจจับประเภทเงื่อนไข
$ConditionType = 'general'; // ค่าเริ่มต้น

if (stripos($Conditions, 'จองครั้งแรกลดเลยทันที') !== false || 
    stripos($Conditions, 'จองครั้งแรก') !== false) {
    $ConditionType = 'first_booking';
} elseif (stripos($Conditions, 'จองก่อน 18:00') !== false || 
          stripos($Conditions, 'จองก่อน18:00') !== false) {
    $ConditionType = 'before_18';
} elseif (stripos($Conditions, 'โค้ดส่วนลดพิเศษ') !== false || 
          stripos($Conditions, 'ส่วนลดพิเศษ') !== false) {
    $ConditionType = 'special_discount';
}

// ตรวจสอบง่ายๆ
$errors = [];
if ($PromoName === '' || $PromoCode === '') {
    $errors[] = 'กรอกชื่อโปรโมชันและโค้ดให้ครบ';
}
$DiscountValue = is_numeric($DiscountValue) ? (float)$DiscountValue : null;
if ($DiscountValue === null) {
    $errors[] = 'ส่วนลดไม่ถูกต้อง';
}

// ✅ กำหนดวันที่อัตโนมัติ (สถานะ "รอเริ่ม")
// วันเริ่มต้น = 1 ปีในอนาคต (เพื่อให้เป็นสถานะ "รอเริ่ม")
// วันสิ้นสุด = 2 ปีในอนาคต
$StartDate = date('Y-m-d H:i:s', strtotime('+1 year'));
$EndDate   = date('Y-m-d H:i:s', strtotime('+2 year'));

if ($errors) {
    $_SESSION['error_message'] = implode("\n", $errors);
    header('Location: promotion_manage.php');
    exit;
}

// ✅ เพิ่ม ConditionType ในการบันทึก
$sql = "INSERT INTO `Tbl_Promotion`
        (`PromoCode`, `PromoName`, `Description`, `DiscountType`, `DiscountValue`, 
         `StartDate`, `EndDate`, `Conditions`, `ConditionType`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error_message'] = 'DB error: ' . $conn->error;
    header('Location: promotion_manage.php');
    exit;
}

$stmt->bind_param(
    'ssssdssss',
    $PromoCode, $PromoName, $Description, $DiscountType,
    $DiscountValue, $StartDate, $EndDate, $Conditions, $ConditionType
);

if ($stmt->execute()) {
    $_SESSION['success_message'] = '✅ สร้างโปรโมชั่นเรียบร้อยแล้ว!' . "\n\n" . 
                                    'ประเภท: ' . $ConditionType . "\n" .
                                    'สถานะ: รอเริ่ม (🔵)' . "\n\n" .
                                    'กรุณากดปุ่ม "เริ่มใช้งาน" เพื่อเปิดใช้โปรโมชั่น';
} else {
    $_SESSION['error_message'] = 'บันทึกล้มเหลว: ' . $stmt->error;
}
$stmt->close();

header('Location: promotion_manage.php');
exit;
