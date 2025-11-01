<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

// -------------------------------------------------------------
// 1. รับค่า ID การจอง (BookingID) จากฟอร์ม
// -------------------------------------------------------------
$booking_id = (int) ($_POST['booking_id'] ?? 0);

// ตรวจสอบว่ามีไฟล์สลิปส่งมาหรือไม่
if ($booking_id <= 0 || !isset($_FILES['slip_file']) || $_FILES['slip_file']['error'] !== UPLOAD_ERR_OK) {
    // โค้ดจัดการข้อผิดพลาด / Redirect
    header("Location: dashboard.php?error=MissingData");
    exit;
}

// -------------------------------------------------------------
// 2. จัดการการอัปโหลดไฟล์
// -------------------------------------------------------------
$target_dir = "uploads/payment_slips/"; // ตรวจสอบ Path นี้ให้ถูกต้อง
$file_extension = pathinfo($_FILES['slip_file']['name'], PATHINFO_EXTENSION);
// สร้างชื่อไฟล์ที่ไม่ซ้ำกัน
$new_filename = 'slip_' . $booking_id . '_' . time() . '.' . $file_extension;
$target_path = $target_dir . $new_filename;

// Path ที่จะบันทึกใน Database (ใช้เป็น Path สัมพัทธ์จาก Root ของเว็บไซต์)
$path_for_db = "/uploads/payment_slips/" . $new_filename; 

// ลองย้ายไฟล์
if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $target_path)) {
    
    // -------------------------------------------------------------
    // 3. 🎯 บันทึก Path ลงใน Database (ส่วนที่คุณต้องการ)
    // -------------------------------------------------------------
    $payment_status_id = 2; // เปลี่ยนสถานะเป็น 'รอการยืนยันชำระเงิน'

    $update_sql = "
        UPDATE Tbl_Booking 
        SET PaymentSlipPath = ?, PaymentStatusID = ? 
        WHERE BookingID = ? AND CustomerID = ?
    ";
    
    $stmt = $conn->prepare($update_sql);
    
    if ($stmt === false) {
        // จัดการ Error (เช่น SQL syntax ผิด)
        error_log("SQL Error: " . $conn->error);
        header("Location: dashboard.php?error=DB_Update_Failed");
        exit;
    }
    
    $stmt->bind_param("siii", $path_for_db, $payment_status_id, $booking_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        // สำเร็จ! Redirect ไปหน้าแจ้งผล
        header("Location: dashboard.php?payment=success");
        exit;
    } else {
        // จัดการ Error ในการรัน SQL
        error_log("Execute Error: " . $stmt->error);
        header("Location: dashboard.php?error=DB_Update_Failed");
        exit;
    }

    $stmt->close();
    
} else {
    // จัดการ Error ในการอัปโหลดไฟล์ (เช่น สิทธิ์ในการเขียนโฟลเดอร์)
    header("Location: dashboard.php?error=UploadFailed");
    exit;
}

$conn->close();
?>
