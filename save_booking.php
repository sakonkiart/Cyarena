<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Access Denied: ไม่มีข้อมูลที่ส่งมา");
}

// รับค่าจำเป็น
$venue_id     = isset($_POST['venue_id']) ? (int)$_POST['venue_id'] : 0;
$booking_date = trim($_POST['booking_date'] ?? '');
$start_time   = trim($_POST['start_time'] ?? '');
$hours        = (float)($_POST['hours'] ?? 1);
$total_price  = (float)($_POST['total_price'] ?? 0);
$customer_id  = (int)$_SESSION['user_id'];

if ($venue_id <= 0 || $booking_date === '' || $start_time === '') {
    die("❌ ข้อมูลไม่ครบ กรุณากลับไปกรอกใหม่");
}

// 🔒 เช็กสถานะสนามอีกครั้งก่อน INSERT (กันยิงตรง)
// ต้องเป็น available เท่านั้น
$sql = "SELECT Status FROM Tbl_Venue WHERE VenueID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$st = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$st || $st['Status'] !== 'available') {
    die("⚠️ สนามนี้ปิดปรับปรุงชั่วคราวหรือไม่พร้อมให้บริการ");
}

// คำนวณเวลาสิ้นสุด
$end_time = date("H:i:s", strtotime($start_time) + ($hours * 3600));

// สถานะเริ่มต้น
$booking_status_id = 1;  // 'รอยืนยัน'
$payment_status_id = 1;  // 'รอชำระเงิน'

// สร้าง DATETIME
$start_datetime = $booking_date . ' ' . $start_time;
$end_datetime   = $booking_date . ' ' . $end_time;

// บันทึก
$sql = "INSERT INTO Tbl_Booking 
(CustomerID, VenueID, BookingStatusID, PaymentStatusID, StartTime, EndTime, HoursBooked, TotalPrice, NetPrice)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiiissddd",
    $customer_id,
    $venue_id,
    $booking_status_id,
    $payment_status_id,
    $start_datetime,
    $end_datetime,
    $hours,
    $total_price,
    $total_price // NetPrice = รวมหลังส่วนลดที่ส่งมาแล้ว (ถ้าต้องการ ตรวจคำนวณซ้ำฝั่งเซิร์ฟเวอร์ได้)
);

if ($stmt->execute()) {
    $message = "✅ การจองของคุณถูกบันทึกเรียบร้อยแล้ว!";
} else {
    $message = "❌ บันทึกการจองไม่สำเร็จ: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ผลการจอง</title>
<style>
    body { font-family: "Prompt", sans-serif; background: #f4f7f6; text-align: center; padding-top: 100px; }
    .card { background: #fff; max-width: 500px; margin: auto; padding: 30px; border-radius: 12px; box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
    h2 { color: #2c3e50; }
    p { font-size: 1.1em; }
    .btn { display: inline-block; margin-top: 20px; background: #3498db; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; }
    .btn:hover { background: #2980b9; }
</style>
</head>
<body>
<div class="card">
    <h2><?= $message ?></h2>
    <p>ขอบคุณที่ใช้บริการ CY Arena!</p>
    <a href="dashboard.php" class="btn">กลับไปหน้าหลัก</a>
</div>
</body>
</html>
