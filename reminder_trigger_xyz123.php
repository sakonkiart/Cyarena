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
// 2. Logic การทำงานของ Cron Job 
// -------------------------------------------------------------------

// นำเข้า (Require) ไฟล์ PHPMailer (พาธที่แก้ไขแล้ว)
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------------------------------------
// Function: sendReminderEmail 
// --------------------------------------------------------
function sendReminderEmail($conn, $recipientEmail, $recipientName, $startTime, $bookingID) {
    $mail = new PHPMailer(true);
    try {
        // --- การตั้งค่า SMTP ของ Gmail ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        // 📧 Gmail Address ของคุณ (บัญชีที่ใช้ส่ง)
        $mail->Username   = 'valorantwhq2548@gmail.com'; 
        // 🔑 App Password 16 หลักชุดใหม่ล่าสุด
        $mail->Password   = 'rzwx bonp logd gaug'; 
        // การเข้ารหัสและพอร์ต (ใช้ 587 ที่คุณต้องการ)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        
        // 🚨 **(ลบโค้ดทดสอบผู้รับทิ้ง)**
        // $testEmail = 'valorantwhq2548@gmail.com'; 
        // $mail->addAddress($testEmail, "Tester");
        
        // 🟢 ผู้รับจริง: ลูกค้าที่จอง
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
        
        // 🟢 **(เปิดใช้งาน UPDATE STATUS)**
        // ถ้าส่งสำเร็จ: อัปเดตสถานะในฐานข้อมูล
        $update_sql = "UPDATE Tbl_Booking SET NotificationSent = 1 WHERE BookingID = ?"; 
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

$sql = "
    SELECT 
        b.BookingID, 
        c.Email, 
        CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName, 
        b.StartTime AS StartDateTime 
    FROM 
        Tbl_Booking b   
    JOIN 
        Tbl_Customer c ON b.CustomerID = c.CustomerID 
    WHERE 
        b.BookingStatusID = 2 /* ดึงเฉพาะสถานะยืนยันแล้ว */
        AND b.NotificationSent = 0
        AND b.StartTime BETWEEN DATE_ADD(NOW(), INTERVAL 25 MINUTE) AND DATE_ADD(NOW(), INTERVAL 35 MINUTE) /* ⬅️ เงื่อนไขเวลาใช้งานจริง */
";
// **ลบ ORDER BY และ LIMIT ที่เคยใช้ในการทดสอบทิ้งไป**

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
