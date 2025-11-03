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

// ... (โค้ดก่อนหน้า) ...
function sendReminderEmail($conn, $recipientEmail, $recipientName, $startTime, $bookingID) {
    $mail = new PHPMailer(true);
    try {
        // ... (การตั้งค่า SMTP ของ Gmail) ...
        
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        
        // 🚨 ส่วนที่ต้องแก้ไข: ส่งไปที่อีเมลทดสอบของคุณ
        $testEmail = 'YOUR_TEST_EMAIL@example.com'; 
        $mail->addAddress($testEmail, "Tester");
        
        // **(สำคัญมาก) ลบหรือคอมเมนต์บรรทัดนี้ทิ้งไปชั่วคราว:**
        // $mail->addAddress($recipientEmail, $recipientName); 
        
        $mail->CharSet = 'UTF-8'; 
        
        // Content
        $mail->isHTML(true);
        // ... (เนื้อหาอีเมล) ...

        $mail->send();
        
        // ⚠️ ข้อควรระวัง: คอมเมนต์ส่วนนี้ทิ้งไปชั่วคราว
        // เพื่อป้องกันไม่ให้คอลัมน์ NotificationSent ถูกตั้งค่าเป็น 1
        /*
        $update_sql = "UPDATE Tbl_Booking SET NotificationSent = 1 WHERE BookingID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $bookingID);
        $stmt->execute();
        */

        return true;
    } catch (Exception $e) {
// ... (โค้ดที่เหลือ) ...

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
