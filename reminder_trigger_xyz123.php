<?php
// reminder_trigger_xyz123.php
// โหมดทำงาน:
// (1) Trigger: ส่ง "ยืนยันการจอง" ทันทีเมื่อ booking_id พร้อมเงื่อนไข (ชำระเงินแล้ว + แอดมินยืนยันแล้ว)
// (2) Cron: ส่ง "เตือนก่อนเริ่ม 1 ชั่วโมง" สำหรับบิลที่ถึงเวลาในอีก 60–61 นาที (ชำระเงินแล้ว + แอดมินยืนยันแล้ว)

// ----------------------------- Security -----------------------------
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765";
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
  http_response_code(403);
  die("Access Denied: Invalid Token.");
}

// ----------------------------- Include / DB -----------------------------
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

@$conn->query("SET time_zone = '+07:00'");

// ----------------------------- Schema guard (เพิ่มคอลัมน์หากยังไม่มี) -----------------------------
function _col_exists(mysqli $c, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $c->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close();
  return $ok;
}
// ธงสำหรับกันส่งซ้ำแบบแยกเคส: ยืนยัน / เตือนล่วงหน้า
try {
  if (!_col_exists($conn, 'Tbl_Booking', 'NotificationConfirmSent')) {
    @$conn->query("ALTER TABLE Tbl_Booking ADD COLUMN NotificationConfirmSent TINYINT(1) NOT NULL DEFAULT 0");
  }
  if (!_col_exists($conn, 'Tbl_Booking', 'NotificationReminderSent')) {
    @$conn->query("ALTER TABLE Tbl_Booking ADD COLUMN NotificationReminderSent TINYINT(1) NOT NULL DEFAULT 0");
  }
} catch (Throwable $e) {
  error_log('[reminder schema guard] '.$e->getMessage());
}

// ----------------------------- Mail Helper -----------------------------
function sendEmail($recipientEmail, $recipientName, $startTime, $endTime, $bookingID, $venueName, $mode = 'confirm') {
  $mail = new PHPMailer(true);
  try {
    // SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'valorantwhq2548@gmail.com';
    $mail->Password   = 'rzwx bonp logd gaug'; // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('no-reply@cyarena.com', 'CY Arena Booking');
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    if ($mode === 'confirm') {
      $mail->Subject = '🎉 ยืนยันการจองสนามสำเร็จแล้ว! (#'.$bookingID.')';
      $mail->Body =
        "<h2>สวัสดีคุณ {$recipientName},</h2>
         <p>คำสั่งจองของคุณหมายเลข <strong>#{$bookingID}</strong> ได้รับการยืนยันแล้ว</p>
         <ul>
           <li><strong>สนาม:</strong> {$venueName}</li>
           <li><strong>เวลาเริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
           <li><strong>เวลาสิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
         </ul>
         <p>ขอบคุณที่ใช้บริการ CY Arena ครับ</p>";
    } else { // reminder
      $mail->Subject = '⏰ เตือนความจำ: อีก 1 ชั่วโมง การจองของคุณจะเริ่มแล้ว! (#'.$bookingID.')';
      $mail->Body =
        "<h2>สวัสดีคุณ {$recipientName},</h2>
         <p>การจอง <strong>#{$bookingID}</strong> ที่ <strong>{$venueName}</strong> จะเริ่มใน <strong>อีก 1 ชั่วโมง</strong></p>
         <ul>
           <li><strong>เวลาเริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
         </ul>
         <p>โปรดเผื่อเวลาเดินทางและเตรียมตัวให้พร้อมครับ 🙂</p>";
    }

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("Mailer Error ({$mode}) #{$bookingID}: ".$mail->ErrorInfo);
    return false;
  }
}

// ----------------------------- เงื่อนไข “ชำระแล้ว” แบบครอบคลุม -----------------------------
// ปรับตาม schema จริงได้ (เช่นใช้แค่ b.IsPaid = 1)
$PAID_CLAUSE = "(b.IsPaid = 1 OR b.PaymentStatus = 'paid' OR b.PaidAt IS NOT NULL)";

// =====================================================================
// Mode (1): Trigger ยืนยันการจองทันทีเมื่อ admin เปลี่ยนสถานะเป็น “ชำระเงินแล้ว + ยืนยันแล้ว”
// เรียก: reminder_trigger_xyz123.php?token=...&booking_id=123
// =====================================================================
if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
  $bookingID = (int)$_GET['booking_id'];

  $sql = "
    SELECT b.BookingID, b.StartTime, b.EndTime,
           c.Email, CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
           v.VenueName,
           b.NotificationConfirmSent
    FROM Tbl_Booking b
    JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
    JOIN Tbl_Venue    v ON b.VenueID    = v.VenueID
    WHERE b.BookingID = ?
      AND b.BookingStatusID = 2         -- แอดมินยืนยันแล้ว
      AND {$PAID_CLAUSE}                -- ชำระเงินแล้ว
    LIMIT 1
  ";
  $st = $conn->prepare($sql);
  if (!$st) { error_log("Confirm SELECT prepare error: ".$conn->error); http_response_code(500); die("DB error"); }
  $st->bind_param("i", $bookingID);
  if ($st->execute()) {
    $rs = $st->get_result();
    if ($row = $rs->fetch_assoc()) {
      if ((int)$row['NotificationConfirmSent'] === 1) {
        echo "Already sent confirmation for booking #{$bookingID}.";
      } else {
        $ok = sendEmail($row['Email'], $row['CustomerName'], $row['StartTime'], $row['EndTime'], $row['BookingID'], $row['VenueName'], 'confirm');
        if ($ok) {
          $u = $conn->prepare("UPDATE Tbl_Booking SET NotificationConfirmSent = 1 WHERE BookingID = ?");
          if ($u) { $u->bind_param("i", $bookingID); $u->execute(); $u->close(); }
          echo "Confirmation email sent for booking #{$bookingID}.";
        } else {
          echo "Failed to send confirmation for booking #{$bookingID}.";
        }
      }
    } else {
      echo "Booking #{$bookingID} is not eligible (must be confirmed AND paid).";
    }
  } else {
    error_log("Confirm SELECT exec error: ".$st->error);
    http_response_code(500); die("DB error");
  }
  $st->close();
}
// =====================================================================
// Mode (2): Cron แจ้งเตือนก่อนเริ่ม 1 ชั่วโมง (รันทุกนาทีแบบไม่มี booking_id)
// =====================================================================
else {
  echo "Starting 1-hour reminder cron...\n";
  // ช่วงเวลา 60–61 นาทีข้างหน้า
  $timeStart = date('Y-m-d H:i:s', strtotime('+60 minutes'));
  $timeEnd   = date('Y-m-d H:i:s', strtotime('+61 minutes'));

  $sql = "
    SELECT b.BookingID, b.StartTime, b.EndTime,
           c.Email, CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
           v.VenueName
    FROM Tbl_Booking b
    JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
    JOIN Tbl_Venue    v ON b.VenueID    = v.VenueID
    WHERE b.BookingStatusID = 2          -- แอดมินยืนยันแล้ว
      AND {$PAID_CLAUSE}                 -- ชำระเงินแล้ว
      AND b.NotificationReminderSent = 0 -- ยังไม่ได้ส่งเตือน 1 ชม.
      AND b.StartTime >= ? AND b.StartTime < ?
    LIMIT 200
  ";
  $st = $conn->prepare($sql);
  if (!$st) { error_log("Reminder SELECT prepare error: ".$conn->error); http_response_code(500); die("DB error"); }
  $st->bind_param("ss", $timeStart, $timeEnd);

  if ($st->execute()) {
    $rs = $st->get_result();
    $okCount = 0; $failCount = 0;

    while ($r = $rs->fetch_assoc()) {
      $ok = sendEmail($r['Email'], $r['CustomerName'], $r['StartTime'], $r['EndTime'], $r['BookingID'], $r['VenueName'], 'reminder');
      if ($ok) {
        $u = $conn->prepare("UPDATE Tbl_Booking SET NotificationReminderSent = 1 WHERE BookingID = ?");
        if ($u) { $u->bind_param("i", $r['BookingID']); $u->execute(); $u->close(); }
        $okCount++;
      } else {
        $failCount++;
      }
    }
    echo "Cron finished. Sent {$okCount}, Failed {$failCount}.";
  } else {
    error_log("Reminder SELECT exec error: ".$st->error);
    http_response_code(500);
    echo "DB error while running reminder.";
  }
  $st->close();
}

$conn->close();
