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
        // 📧 แก้ไข: Gmail Address ของคุณ (ใช้ค่าจริงของคุณ)
        $mail->Username   = 'valorantwhq2548@gmail.com'; 
        // 🔑 แก้ไข: App Password 16 หลัก (ใช้ค่าจริงของคุณ)
       $mail->Password   = 'rzwx bonp logd gaug'; 
// 1. เปลี่ยนการเข้ารหัสเป็น SSL/TLS
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ⬅️ แก้ไขจาก ENCRYPTION_STARTTLS
// 2. เปลี่ยนพอร์ตจาก 587 เป็น 465
$mail->Port       = 587;
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        
        // 🚨 การทดสอบ: ส่งไปที่อีเมลทดสอบของคุณ (Valorant...)
        $testEmail = 'valorantwhq2548@gmail.com'; 
        $mail->addAddress($testEmail, "Tester");
        
        // ** (ต้องคอมเมนต์บรรทัดนี้ทิ้งไว้) **
        // $mail->addAddress($recipientEmail, $recipientName); 
        
        $mail->CharSet = 'UTF-8'; 
        
        // Content
        $mail->isHTML(true);
        // 🛠️ แก้ไข: เพิ่ม Subject และ Body ที่หายไป
        $mail->Subject = '⭐ แจ้งเตือน: การจองสนามจะเริ่มใน 30 นาที! (#'.$bookingID.')';
        $mail->Body    = "
            <h2>สวัสดีคุณ {$recipientName},</h2>
            <p>การจองสนามของคุณหมายเลข <strong>#{$bookingID}</strong>
            กำลังจะเริ่มต้นในอีก 30 นาที ข้างหน้า คือเวลา <strong>{$startTime} น.</strong></p>
            <p>โปรดเดินทางมาถึงสนามตรงเวลา ขอบคุณที่ใช้บริการครับ!</p>
        ";
        
        $mail->send();
        
        // ⚠️ ข้อควรระวัง: คอมเมนต์ส่วนนี้ทิ้งไปชั่วคราว
        // เพื่อป้องกันไม่ให้คอลัมน์ NotificationSent ถูกตั้งค่าเป็น 1 ในการทดสอบ
        /*
        // ถ้าส่งสำเร็จ: อัปเดตสถานะในฐานข้อมูล
        $update_sql = "UPDATE Tbl_Booking SET NotificationSent = 1 WHERE BookingID = ?"; // 🛠️ แก้ไข: ใช้ Tbl_Booking
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $bookingID);
        $stmt->execute();
        */

        return true;
    } catch (Exception $e) {
        // 🛠️ แก้ไข: รวมส่วนการจัดการ Error ไว้ใน catch เดียว
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
    ORDER BY b.BookingID DESC /* ดึงรายการล่าสุด */
    LIMIT 1;
";

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
