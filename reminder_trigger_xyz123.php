<?php
// reminder_trigger_xyz123.php

// -------------------------------------------------------------------
// 1. การตรวจสอบความปลอดภัย (สำคัญมาก! เพื่อป้องกันคนอื่นเรียกใช้)
// -------------------------------------------------------------------

// 🔑 กำหนดรหัสลับที่คาดเดายากมาก (ใช้ใน URL)
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765"; 

// 🚫 ถ้าไม่มี Token หรือ Token ไม่ตรง ให้หยุดการทำงาน
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
    http_response_code(403); // Forbidden
    die("Access Denied.");
}

// -------------------------------------------------------------------
// 2. Logic การทำงานของ Cron Job (เหมือนเดิม)
// -------------------------------------------------------------------

// นำเข้า (Require) ไฟล์ PHPMailer (พาธเดิม)
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ... (ฟังก์ชัน sendReminderEmail เหมือนเดิม) ...
// (นำโค้ดฟังก์ชัน sendReminderEmail ทั้งหมดจาก booking_reminder_cron.php มาใส่ที่นี่)

function sendReminderEmail($conn, $recipientEmail, $recipientName, $startTime, $bookingID) {
    // ... (โค้ด PHPMailer ที่มีการตั้งค่า Username และ App Password แล้ว) ...
    // ... (บรรทัดโค้ดใน image_e69107.png อยู่ตรงนี้) ...
    $mail = new PHPMailer(true);
    try {
        // --- การตั้งค่า SMTP ของ Gmail (ใช้ App Password) ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        // 📧 แก้ไข: Gmail Address ของคุณ
        $mail->Username   = 'valorantwhq2548@gmail.com'; 
        // 🔑 แก้ไข: App Password 16 หลัก
        $mail->Password   = 'flim210845';          
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->CharSet = 'UTF-8'; 
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '⭐ แจ้งเตือน: การจองสนามจะเริ่มใน 30 นาที! (#'.$bookingID.')';
        $mail->Body    = "
            <h2>สวัสดีคุณ {$recipientName},</h2>
            <p>การจองสนามของคุณหมายเลข <strong>#{$bookingID}</strong>
            กำลังจะเริ่มต้นในอีก 30 นาที ข้างหน้า คือเวลา <strong>{$startTime} น.</strong></p>
            <p>โปรดเดินทางมาถึงสนามตรงเวลา ขอบคุณที่ใช้บริการครับ!</p>
        ";

        $mail->send();
        
        // ถ้าส่งสำเร็จ: อัปเดตสถานะในฐานข้อมูล
        $update_sql = "UPDATE bookings SET NotificationSent = 1 WHERE BookingID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $bookingID);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for Booking #{$bookingID}: {$mail->ErrorInfo}");
        return false;
    }
}


// --------------------------------------------------------
// 3. Main Logic: Query และเรียก Function
// --------------------------------------------------------

// --------------------------------------------------------
// Main Logic: Query และเรียก Function (แก้ไขที่นี่)
// --------------------------------------------------------

$sql = "
    SELECT 
        b.BookingID, 
        c.Email, 
        CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName, /* แก้ไข: รวม FirstName และ LastName เข้าด้วยกัน */
        b.StartTime AS StartDateTime /* แก้ไข: ใช้ StartTime จาก DB และเปลี่ยนชื่อ (Alias) ให้เป็น StartDateTime */
    FROM 
        Tbl_Booking b   /* แก้ไข: ใช้ Tbl_Booking */
    JOIN 
        Tbl_Customer c ON b.CustomerID = c.CustomerID /* แก้ไข: ใช้ Tbl_Customer */
    WHERE 
        b.BookingStatusID = 2 /* แก้ไข: ใช้ ID 2 สำหรับ 'ยืนยันแล้ว' (Confirmed) */
        -- ตรวจสอบเวลา 25-35 นาที ก่อน StartTime
        AND b.StartTime BETWEEN DATE_ADD(NOW(), INTERVAL 25 MINUTE) AND DATE_ADD(NOW(), INTERVAL 35 MINUTE)
        AND b.NotificationSent = 0
";

// ... (ส่วนที่เหลือของโค้ด) ...

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($booking = $result->fetch_assoc()) {
        $startTime = date("H:i", strtotime($booking['StartDateTime']));
        sendReminderEmail($conn, $booking['Email'], $booking['CustomerName'], $startTime, $booking['BookingID']);
    }
}

$conn->close();
echo "Cron job finished successfully.";
?>
