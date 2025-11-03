<?php
// admin_delete_booking.php
session_start();

// ปรับตามระบบคุณ: ที่หน้า manage_* ใช้ role = 'employee' เป็นผู้ดูแล
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($booking_id <= 0) {
    die('รหัสการจองไม่ถูกต้อง');
}

// ตรวจว่ามีรายการนี้จริง
$chk = $conn->prepare("SELECT BookingID FROM Tbl_Booking WHERE BookingID = ?");
$chk->bind_param("i", $booking_id);
$chk->execute();
$exists = $chk->get_result()->num_rows > 0;
$chk->close();

if (!$exists) {
    header("Location: manage_bookings.php");
    exit;
}

$conn->begin_transaction();
try {
    // พยายามลบจริง
    $del = $conn->prepare("DELETE FROM Tbl_Booking WHERE BookingID = ?");
    $del->bind_param("i", $booking_id);
    $del->execute();
    $deleted = $del->affected_rows > 0;
    $del->close();

    if (!$deleted) {
        // ถ้าลบไม่ได้ (เช่นติด FK) ให้ fallback เป็น "ยกเลิก"
        // ปรับหมายเลขสถานะให้ตรงกับระบบของคุณ (ตัวอย่าง: 3 = ยกเลิก)
        $fallback = $conn->prepare("UPDATE Tbl_Booking SET BookingStatusID = 3 WHERE BookingID = ?");
        $fallback->bind_param("i", $booking_id);
        $fallback->execute();
        $fallback->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    // ใน production ซ่อน error; dev ค่อยเปิดดู
    // die('ลบไม่สำเร็จ: ' . $e->getMessage());
}

header("Location: manage_bookings.php");
exit;
