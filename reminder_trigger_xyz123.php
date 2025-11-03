<?php
// booking_confirmation_trigger.php

// -------------------------------------------------------------------
// 1. การตรวจสอบความปลอดภัยและการรับ BookingID
// -------------------------------------------------------------------

// 🔑 กำหนดรหัสลับที่คาดเดายากมาก (ใช้ใน URL เหมือนเดิม)
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765"; 

// 🚫 ถ้าไม่มี Token หรือ Token ไม่ตรง ให้หยุดการทำงาน
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
    http_response_code(403); 
    die("Access Denied: Invalid Token.");
}

// ⚠️ ต้องมี BookingID ใน URL
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    http_response_code(400); 
    die("Error: Missing or Invalid Booking ID.");
}

$bookingID = (int)$_GET['booking_id'];

// -------------------------------------------------------------------
// 2. Logic การทำงาน 
// -------------------------------------------------------------------

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php'; // ใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิม

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------------------------------------
// Function: sendConfirmationEmail (ใช้ Logic SMTP เดิม)
// --------------------------------------------------------
function sendConfirmationEmail($conn, $recipientEmail, $recipientName, $startTime, $endTime, $bookingID) {
    $mail = new PHPMailer(true);
    try {
        // --- การตั้งค่า SMTP ที่สำเร็จแล้ว ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'valorantwhq2548@gmail.com'; 
        $mail->Password   = 'rzwx bonp logd gaug'; // App Password ชุดใหม่
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        $mail->addAddress($recipientEmail, $recipientName); // ส่งหาลูกค้าจริง
        $mail->CharSet = 'UTF-8'; 
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '🎉 ยืนยันการจองสนามสำเร็จแล้ว! (#'.$bookingID.')';
        
        $mail->Body    = "
            <h2>สวัสดีคุณ {$recipientName},</h2>
            <p>การจองสนามของคุณหมายเลข <strong>#{$bookingID}</strong>
            ได้รับการยืนยันเรียบร้อยแล้ว รายละเอียดการจอง:</p>
            <ul>
                <li><strong>เวลาเริ่มต้น:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                <li><strong>เวลาสิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
            </ul>
            <p>หากมีข้อสงสัยใด ๆ กรุณาติดต่อเรา ขอบคุณครับ!</p>
        ";
        
        $mail->send();
        // 🚨 หมายเหตุ: ไม่ต้อง UPDATE NotificationSent เพราะคอลัมน์นี้ใช้สำหรับแจ้งเตือน 30 นาทีเท่านั้น
        
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for Booking #{$bookingID}: {$mail->ErrorInfo}");
        return false;
    }
}


// --------------------------------------------------------
// 3. Main Logic: ดึงข้อมูลและเรียก Function
// --------------------------------------------------------

$sql = "
    SELECT 
        b.BookingID, 
        c.Email, 
        CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName, 
        b.StartTime,
        b.EndTime
    FROM 
        Tbl_Booking b   
    JOIN 
        Tbl_Customer c ON b.CustomerID = c.CustomerID 
    WHERE 
        b.BookingID = ?
        AND b.BookingStatusID = 2 
    LIMIT 1;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    sendConfirmationEmail(
        $conn, 
        $booking['Email'], 
        $booking['CustomerName'], 
        $booking['StartTime'],
        $booking['EndTime'],
        $booking['BookingID']
    );
    echo "Booking confirmation email sent successfully for ID: {$bookingID}.";
} else {
    echo "No valid booking found for ID: {$bookingID}.";
}

$conn->close();
?>
