<?php
session_start();
require_once __DIR__ . '/db_connect.php';

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
$StartDate     = trim($_POST['StartDate']     ?? '');
$EndDate       = trim($_POST['EndDate']       ?? '');
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

// แปลงวันที่ให้เป็นรูปแบบที่ MySQL ยอมรับ
$StartDate = $StartDate !== '' ? date('Y-m-d H:i:s', strtotime($StartDate)) : null;
$EndDate   = $EndDate   !== '' ? date('Y-m-d H:i:s', strtotime($EndDate))   : null;

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
    $_SESSION['success_message'] = 'บันทึกโปรโมชันเรียบร้อยแล้ว (ประเภท: ' . $ConditionType . ')';
} else {
    $_SESSION['error_message'] = 'บันทึกล้มเหลว: ' . $stmt->error;
}
$stmt->close();

header('Location: promotion_manage.php');
exit;
