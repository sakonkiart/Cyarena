<?php
// reminder_trigger_xyz123.php
// สคริปต์นี้ทำหน้าที่ 2 อย่าง:
// 1. Trigger: ส่งอีเมลยืนยันการจองทันทีเมื่อมีการยืนยันสถานะ (ต้องมี booking_id)
// 2. Cron Job: ส่งอีเมลแจ้งเตือน 5 นาทีก่อนเวลาเริ่ม (รันโดยอัตโนมัติทุกนาที)

// -------------------------------------------------------------------
// 1. การตรวจสอบความปลอดภัยและการเตรียมการ
// -------------------------------------------------------------------

// 🔑 กำหนดรหัสลับที่คาดเดายากมาก
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765"; 

// 🚫 ถ้าไม่มี Token หรือ Token ไม่ตรง ให้หยุดการทำงาน
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
    http_response_code(403); 
    die("Access Denied: Invalid Token.");
}

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php'; // ใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิม

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------------------------------------
// Function: sendEmail (รวม Logic การส่งอีเมล)
// --------------------------------------------------------
// ใช้ $venueName ที่ดึงมาจาก Tbl_Venue
function sendEmail($conn, $recipientEmail, $recipientName, $startTime, $endTime, $bookingID, $venueName, $isConfirmation = true) {
    $mail = new PHPMailer(true);
    try {
        // --- การตั้งค่า SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'valorantwhq2548@gmail.com'; 
        $mail->Password   = 'rzwx bonp logd gaug'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        
        // Sender/Recipient
        $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
        $mail->addAddress($recipientEmail, $recipientName); 
        $mail->CharSet = 'UTF-8'; 
        $mail->isHTML(true);

        if ($isConfirmation) {
            // โหมดยืนยันการจอง (Confirmation)
            $mail->Subject = '🎉 ยืนยันการจองสนามสำเร็จแล้ว! (#'.$bookingID.')';
            $mail->Body    = "
                <h2>สวัสดีคุณ {$recipientName},</h2>
                <p>การจองของคุณหมายเลข <strong>#{$bookingID}</strong>
                ได้รับการยืนยันเรียบร้อยแล้ว รายละเอียดการจอง:</p>
                <ul>
                    <li><strong>สถานที่/สนามที่จอง:</strong> {$venueName}</li>
                    <li><strong>เวลาเริ่มต้น:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                    <li><strong>เวลาสิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
                </ul>
                <p>หากมีข้อสงสัยใด ๆ กรุณาติดต่อเรา ขอบคุณครับ!</p>
            ";

        } else {
            // โหมดแจ้งเตือน (Reminder)
            $mail->Subject = '🔔 แจ้งเตือน: อีก 5 นาที สนามของคุณจะเริ่มแล้ว! (#'.$bookingID.')';
            $mail->Body    = "
                <h2>สวัสดีคุณ {$recipientName},</h2>
                <p>เราขอแจ้งเตือนว่าการจองของคุณหมายเลข <strong>#{$bookingID}</strong>
                ที่ <strong>{$venueName}</strong> จะเริ่มต้นในอีกประมาณ <strong>5 นาที</strong> นี้แล้ว</p>
                <ul>
                    <li><strong>เวลาเริ่มต้น:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                </ul>
                <p>ขอให้สนุกกับการใช้บริการครับ!</p>
            ";
        }
        
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error (Mode: " . ($isConfirmation ? "CONFIRM" : "REMINDER") . ") for Booking #{$bookingID}: {$mail->ErrorInfo}");
        return false;
    }
}


// --------------------------------------------------------
// 3. Main Logic: แยกโหมดการทำงาน
// --------------------------------------------------------

// 🚀 โหมดที่ 1: Trigger (ส่งยืนยันการจองทันที) - ต้องมี booking_id
if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
    
    $bookingID = (int)$_GET['booking_id'];
    
    // ดึงข้อมูลการจองที่ได้รับการยืนยันแล้ว (BookingStatusID = 2) - ใช้ Tbl_Venue
    $sql = "
        SELECT 
            b.BookingID, c.Email, CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName, 
            b.StartTime, b.EndTime, v.VenueName
        FROM 
            Tbl_Booking b   
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID 
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID  -- JOIN ที่ยืนยันตาม Schema
        WHERE 
            b.BookingID = ? AND b.BookingStatusID = 2 
        LIMIT 1;
    ";

    $stmt = $conn->prepare($sql);
    // ตรวจสอบความผิดพลาดในการเตรียมคำสั่ง
    if ($stmt === false) {
        error_log("Confirmation SELECT Prepare Error: " . $conn->error);
        http_response_code(500);
        die("Internal Server Error: Database prepare failed.");
    }

    $stmt->bind_param("i", $bookingID);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            
            $send_success = sendEmail(
                $conn, 
                $booking['Email'], 
                $booking['CustomerName'], 
                $booking['StartTime'],
                $booking['EndTime'],
                $booking['BookingID'],
                $booking['VenueName'], // ส่ง VenueName
                true // isConfirmation = true
            );
            
            if ($send_success) {
                echo "Booking confirmation email sent successfully for ID: {$bookingID}.";
            } else {
                echo "Booking confirmation email FAILED to send for ID: {$bookingID}. Check Render Logs for Mailer Error details.";
            }
        } else {
            echo "No valid booking found for ID: {$bookingID} or BookingStatusID is not 2.";
        }
    } else {
        error_log("Confirmation SELECT Execute Error for ID {$bookingID}: " . $stmt->error);
        http_response_code(500);
        die("Internal Server Error: Database execute failed.");
    }
    
    $stmt->close();

} 
// 🕒 โหมดที่ 2: Cron Job (ส่งแจ้งเตือนก่อน 5 นาที) - ไม่มี booking_id
else {
    echo "Starting 5-minute reminder cron job...\n";
    
    // กำหนดช่วงเวลา: 5 นาทีถึง 6 นาทีข้างหน้า
    $timeStart = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $timeEnd = date('Y-m-d H:i:s', strtotime('+6 minutes'));

    // ค้นหาการจองที่ยืนยันแล้ว, ยังไม่ถูกแจ้งเตือน, และเวลาเริ่มอยู่ในช่วง 5-6 นาทีข้างหน้า - ใช้ Tbl_Venue
    $sql = "
        SELECT 
            b.BookingID, c.Email, CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName, 
            b.StartTime, b.EndTime, v.VenueName
        FROM 
            Tbl_Booking b   
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID 
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID -- JOIN ที่ยืนยันตาม Schema
        WHERE 
            b.BookingStatusID = 2 
            AND b.NotificationSent = 0
            AND b.StartTime >= ? 
            AND b.StartTime < ? 
        LIMIT 100; // จำกัดจำนวนเพื่อป้องกัน Load
    ";
    
    $stmt = $conn->prepare($sql);

    // ตรวจสอบความผิดพลาดในการเตรียมคำสั่ง
    if ($stmt === false) {
        error_log("Reminder SELECT Prepare Error: " . $conn->error);
        http_response_code(500);
        die("Internal Server Error: Database prepare failed.");
    }

    $stmt->bind_param("ss", $timeStart, $timeEnd);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $count = 0;
        $failedCount = 0;

        while ($booking = $result->fetch_assoc()) {
            
            $send_success = sendEmail(
                $conn, 
                $booking['Email'], 
                $booking['CustomerName'], 
                $booking['StartTime'],
                $booking['EndTime'],
                $booking['BookingID'],
                $booking['VenueName'], // ส่ง VenueName
                false // isConfirmation = false
            );

            if ($send_success) {
                $count++;
                // อัปเดต NotificationSent เป็น 1 เพื่อไม่ให้ส่งซ้ำ
                $updateSql = "UPDATE Tbl_Booking SET NotificationSent = 1 WHERE BookingID = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $booking['BookingID']);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $failedCount++;
            }
        }
        
        echo "Cron job finished successfully. Sent {$count} reminder(s). Failed to send {$failedCount}.";

    } else {
        error_log("Reminder SELECT Execute Error: " . $stmt->error);
        http_response_code(500);
        echo "Internal Server Error: Database execute failed for reminder job.";
    }
    
    if (isset($stmt)) $stmt->close();
}

$conn->close();
?>
