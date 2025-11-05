<?php
// reminder_trigger_xyz123.php
// 2 โหมดในไฟล์เดียว:
//  (1) Trigger: ส่ง "อีเมลยืนยันการจอง" ทันที เมื่อ booking ถูกตั้งค่าเป็นยืนยันแล้ว + ชำระเงินแล้ว
//      เรียกแบบ: reminder_trigger_xyz123.php?token=...&booking_id=123
//  (2) Cron: ส่ง "อีเมลเตือนก่อน 1 ชั่วโมง" ให้การจองที่ถึงเวลาใน 60–61 นาทีข้างหน้า
//      เรียกแบบ: reminder_trigger_xyz123.php?token=...

/* ---------------- Security ---------------- */
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765";
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
    http_response_code(403);
    die("Access Denied: Invalid Token.");
}

/* ---------------- Env / Includes ---------------- */
date_default_timezone_set('Asia/Bangkok');

require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require __DIR__ . '/db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------------- Guard: add columns if missing (safe) ---------------- */
// ต้องมี 2 คอลัมน์เพื่อกันส่งซ้ำ
// - ConfirmationEmailSent     (กันอีเมล "ยืนยันการจอง" ส่งย้ำ)
// - Notification1hSent        (กันอีเมล "เตือนก่อน 1 ชั่วโมง" ส่งย้ำ)
@$conn->query("
  ALTER TABLE Tbl_Booking
    ADD COLUMN IF NOT EXISTS ConfirmationEmailSent TINYINT(1) NOT NULL DEFAULT 0
");
@$conn->query("
  ALTER TABLE Tbl_Booking
    ADD COLUMN IF NOT EXISTS Notification1hSent TINYINT(1) NOT NULL DEFAULT 0
");

/* ---------------- Mail helper ---------------- */
function sendEmail($recipientEmail, $recipientName, $startTime, $endTime, $bookingID, $venueName, $isConfirmation = true): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // ✉️ ใช้บัญชีเดียวกับที่คุณตั้ง App Password
        $mail->Username   = 'valorantwhq2548@gmail.com';
        $mail->Password   = 'rzwx bonp logd gaug'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ⚠️ ตั้ง from ให้ "ตรงกับ" บัญชี SMTP เพื่อลดโอกาสโดนบล็อก
        $mail->setFrom('valorantwhq2548@gmail.com', 'CY Arena Booking');

        $mail->addAddress($recipientEmail, $recipientName);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        if ($isConfirmation) {
            $mail->Subject = '🎉 ยืนยันการจองสนามสำเร็จแล้ว! (#'.$bookingID.')';
            $mail->Body = "
                <h2>สวัสดีคุณ {$recipientName},</h2>
                <p>การจองหมายเลข <strong>#{$bookingID}</strong> ได้รับการยืนยันเรียบร้อยแล้ว</p>
                <ul>
                  <li><strong>สนาม:</strong> {$venueName}</li>
                  <li><strong>เริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                  <li><strong>สิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
                </ul>
                <p>ขอบคุณที่ใช้บริการ CY Arena</p>
            ";
        } else {
            $mail->Subject = '🔔 เตือนล่วงหน้า 1 ชั่วโมง (#'.$bookingID.')';
            $mail->Body = "
                <h2>สวัสดีคุณ {$recipientName},</h2>
                <p>การจองหมายเลข <strong>#{$bookingID}</strong> ที่ <strong>{$venueName}</strong> 
                จะเริ่มภายใน <strong>1 ชั่วโมง</strong></p>
                <ul>
                  <li><strong>เริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
                </ul>
                <p>เจอกันที่สนามครับ!</p>
            ";
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error (".($isConfirmation?'CONFIRM':'REMIND').") #{$bookingID}: ".$mail->ErrorInfo);
        return false;
    }
}

/* ---------------- Constants ---------------- */
$PAID_NAMES = ['paid', 'paid_confirmed']; // Tbl_Payment_Status.StatusName ที่ถือว่า "ชำระแล้ว"

/* ======================================================================
   MODE 1: Confirmation trigger (เมื่อ admin กดเปลี่ยนสถานะแล้ว)
   เงื่อนไข:
     - b.BookingStatusID = 2 (ยืนยันแล้ว)
     - ps.StatusName IN ('paid','paid_confirmed') (ชำระแล้ว)
     - ConfirmationEmailSent = 0 (ยังไม่เคยส่ง)
   ====================================================================== */
if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
    $bookingID = (int)$_GET['booking_id'];

    $sql = "
        SELECT 
            b.BookingID,
            c.Email,
            CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
            b.StartTime, b.EndTime,
            v.VenueName,
            ps.StatusName,
            b.ConfirmationEmailSent
        FROM Tbl_Booking b
        JOIN Tbl_Customer c        ON b.CustomerID       = c.CustomerID
        JOIN Tbl_Venue v           ON b.VenueID          = v.VenueID
        JOIN Tbl_Payment_Status ps ON b.PaymentStatusID  = ps.PaymentStatusID
        WHERE b.BookingID = ?
          AND b.BookingStatusID = 2
          AND ps.StatusName IN ('paid','paid_confirmed')
          AND b.ConfirmationEmailSent = 0
        LIMIT 1
    ";
    $st = $conn->prepare($sql);
    if ($st === false) { http_response_code(500); die("DB prepare failed."); }
    $st->bind_param("i", $bookingID);
    $st->execute();
    $rs = $st->get_result();

    if ($bk = $rs->fetch_assoc()) {
        $ok = sendEmail(
            $bk['Email'],
            $bk['CustomerName'],
            $bk['StartTime'],
            $bk['EndTime'],
            $bk['BookingID'],
            $bk['VenueName'],
            true // confirmation
        );

        if ($ok) {
            $u = $conn->prepare("UPDATE Tbl_Booking SET ConfirmationEmailSent = 1 WHERE BookingID = ?");
            $u->bind_param("i", $bookingID);
            $u->execute(); $u->close();
            echo "✅ Confirmation email sent for #{$bookingID}";
        } else {
            echo "❌ Failed sending confirmation for #{$bookingID}";
        }
    } else {
        echo "ℹ️ Nothing to send (already sent / not paid / not confirmed).";
    }
    $st->close();
    $conn->close();
    exit;
}

/* ======================================================================
   MODE 2: CRON – เตือนก่อนเริ่ม 1 ชั่วโมง (ทุกนาที)
   เงื่อนไข:
     - b.BookingStatusID = 2
     - ps.StatusName IN ('paid','paid_confirmed')
     - Notification1hSent = 0
     - StartTime อยู่ในช่วง 60–61 นาทีข้างหน้า
   ====================================================================== */
$winStart = date('Y-m-d H:i:s', strtotime('+60 minutes'));
$winEnd   = date('Y-m-d H:i:s', strtotime('+61 minutes'));

$sql = "
    SELECT 
        b.BookingID,
        c.Email,
        CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
        b.StartTime, b.EndTime,
        v.VenueName
    FROM Tbl_Booking b
    JOIN Tbl_Customer c        ON b.CustomerID       = c.CustomerID
    JOIN Tbl_Venue v           ON b.VenueID          = v.VenueID
    JOIN Tbl_Payment_Status ps ON b.PaymentStatusID  = ps.PaymentStatusID
    WHERE b.BookingStatusID = 2
      AND ps.StatusName IN ('paid','paid_confirmed')
      AND b.Notification1hSent = 0
      AND b.StartTime >= ?
      AND b.StartTime <  ?
    LIMIT 200
";
$st = $conn->prepare($sql);
if ($st === false) { http_response_code(500); die("DB prepare failed."); }
$st->bind_param("ss", $winStart, $winEnd);
$st->execute();
$rs = $st->get_result();

$sent = 0; $fail = 0;
while ($bk = $rs->fetch_assoc()) {
    $ok = sendEmail(
        $bk['Email'],
        $bk['CustomerName'],
        $bk['StartTime'],
        $bk['EndTime'],
        $bk['BookingID'],
        $bk['VenueName'],
        false // reminder
    );

    if ($ok) {
        $u = $conn->prepare("UPDATE Tbl_Booking SET Notification1hSent = 1 WHERE BookingID = ?");
        $u->bind_param("i", $bk['BookingID']);
        $u->execute(); $u->close();
        $sent++;
    } else {
        $fail++;
    }
}
$st->close();

echo "⏰ 1h reminder done. Sent {$sent}, failed {$fail}.";
$conn->close();
