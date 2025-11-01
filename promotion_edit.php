<?php
// บันทึกเป็น UTF-8 (no BOM) และอย่าใส่ช่องว่างก่อน/หลังแท็ก PHP
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

// ใช้ backtick รอบชื่อคอลัมน์ โดยเฉพาะ `Conditions`
$sql = "INSERT INTO `Tbl_Promotion`
        (`PromoCode`, `PromoName`, `Description`, `DiscountType`, `DiscountValue`, `StartDate`, `EndDate`, `Conditions`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error_message'] = 'DB error: ' . $conn->error;
    header('Location: promotion_manage.php');
    exit;
}

// d = double/float, s = string
$stmt->bind_param(
    'ssssdsss',
    $PromoCode, $PromoName, $Description, $DiscountType,
    $DiscountValue, $StartDate, $EndDate, $Conditions
);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'บันทึกโปรโมชันเรียบร้อยแล้ว';
} else {
    $_SESSION['error_message'] = 'บันทึกล้มเหลว: ' . $stmt->error;
}
$stmt->close();

header('Location: promotion_manage.php');
exit;
