<?php
// booking_confirmation_trigger.php
// สคริปต์นี้มีไว้เพื่อส่งอีเมลยืนยันการจองทันทีเมื่อลูกค้าทำการจองสำเร็จ

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
        
        // 🌟 แก้ไขเพื่อทดสอบ: บังคับส่งไปยังอีเมลของคุณโดยตรงชั่วคราว
        // ให้นำ recipientEmail เดิมมาใช้แทน 'test@example.com' 
        // หากต้องการบังคับส่งไปหาอีเมลอื่น ให้เปลี่ยน 'valorantwhq2548@gmail.com' เป็นอีเมลทดสอบของคุณ
        $mail->addAddress('valorantwhq2548@gmail.com', $recipientName); // <--- แก้ไขตรงนี้
        
        $mail->CharSet = 'UTF-8'; 
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '🎉 [TEST] ยืนยันการจองสนามสำเร็จแล้ว! (#'.$bookingID.')';
        
        $mail->Body    = "
            <h2>สวัสดีคุณ {$recipientName},</h2>
            <p>การจองสนามของคุณหมายเลข <strong>#{$bookingID}</strong>
            ได้รับการยืนยันเรียบร้อยแล้ว รายละเอียดการจอง:</p>
            <ul>
                <li><strong>เวลาเริ่มต้น:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                <li><strong>เวลาสิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
            </ul>
            <p>หากมีข้อสงสัยใด ๆ กรุณาติดต่อเรา ขอบคุณครับ!</p>
            <p style='color:red;'>-- ข้อความนี้ถูกส่งไปยังอีเมลทดสอบโดยตรง --</p>
        ";
        
        $mail->send();
        // 🚨 หมายเหตุ: ไม่ต้อง UPDATE NotificationSent เพราะคอลัมน์นี้ใช้สำหรับแจ้งเตือน 30 นาทีเท่านั้น
        
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error (Confirmation) for Booking #{$bookingID}: {$mail->ErrorInfo}");
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

// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // บันทึกข้อผิดพลาดหาก prepare ล้มเหลว
    error_log("Confirmation SELECT Prepare Error: " . $conn->error);
    http_response_code(500);
    die("Internal Server Error: Database prepare failed.");
}

$stmt->bind_param("i", $bookingID);

// รันคำสั่ง SQL
if (!$stmt->execute()) {
    // บันทึกข้อผิดพลาดหาก execute ล้มเหลว
    error_log("Confirmation SELECT Execute Error for ID {$bookingID}: " . $stmt->error);
    http_response_code(500);
    die("Internal Server Error: Database execute failed.");
}

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    $send_success = sendConfirmationEmail( // เก็บผลลัพธ์การส่ง
        $conn, 
        $booking['Email'], 
        $booking['CustomerName'], 
        $booking['StartTime'],
        $booking['EndTime'],
        $booking['BookingID']
    );
    
    // ตรวจสอบผลลัพธ์การส่งและแสดงข้อความที่ชัดเจนขึ้น
    if ($send_success) {
        echo "Booking confirmation email sent successfully to valorantwhq2548@gmail.com (TEST MODE) for ID: {$bookingID}.";
    } else {
        // ถ้าส่งไม่สำเร็จ แต่ DB ดึงข้อมูลได้ ให้แสดงข้อความแจ้ง Mailer Error
        echo "Booking confirmation email FAILED to send for ID: {$bookingID}. Check Render Logs for Mailer Error details.";
    }
} else {
    echo "No valid booking found for ID: {$bookingID} or BookingStatusID is not 2.";
}

$stmt->close();
$conn->close();
?>
