<?php
// reminder_trigger_xyz123.php (final, patched)
session_start();

/* ---------- SECURITY TOKEN ---------- */
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765";
if (!isset($_GET['token']) || $_GET['token'] !== $SECRET_TOKEN) {
  http_response_code(403);
  die("Access Denied: Invalid Token.");
}

/* ---------- DEPENDENCIES ---------- */
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

@$conn->query("SET time_zone = '+07:00'");

/* ---------- ENSURE COLUMNS/INDEX EXIST ---------- */
function ensureColumn(mysqli $c, $table, $col, $def) {
  $q = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  if ($st = $c->prepare($q)) {
    $st->bind_param("ss",$table,$col);
    $st->execute(); $st->store_result();
    $exists = $st->num_rows > 0;
    $st->close();
    if (!$exists) { @$c->query("ALTER TABLE `$table` ADD COLUMN `$col` $def"); }
  }
}
function ensureIndex(mysqli $c, $table, $index, $cols) {
  $q = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1";
  if ($st = $c->prepare($q)) {
    $st->bind_param("ss",$table,$index);
    $st->execute(); $st->store_result();
    $exists = $st->num_rows > 0;
    $st->close();
    if (!$exists) { @$c->query("CREATE INDEX `$index` ON `$table` ($cols)"); }
  }
}
ensureColumn($conn, 'Tbl_Booking', 'ConfirmationEmailSent', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumn($conn, 'Tbl_Booking', 'Notification1hSent',   "TINYINT(1) NOT NULL DEFAULT 0");
ensureIndex ($conn, 'Tbl_Booking', 'idx_booking_1h',
  "BookingStatusID, PaymentStatusID, StartTime, ConfirmationEmailSent, Notification1hSent");

/* ---------- MAILER ---------- */
function sendEmail($toEmail, $toName, $subject, $html) {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'valorantwhq2548@gmail.com';     // Gmail ของคุณ
    $mail->Password   = 'rzwx bonp logd gaug';           // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(true);

    // ใช้ From เดียวกับ SMTP (กัน DMARC/ SPF)
    $mail->setFrom('valorantwhq2548@gmail.com', 'CY Arena Booking');
    $mail->addAddress($toEmail, $toName);

    $mail->Subject = $subject;
    $mail->Body    = $html;

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("[MAIL] ".$mail->ErrorInfo);
    return false;
  }
}

/* ---------- HELPERS ---------- */
function bookingRow(mysqli $c, int $bookingID) {
  $sql = "SELECT b.BookingID, b.StartTime, b.EndTime,
                 c.Email, CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
                 v.VenueName,
                 ps.StatusName AS PayStatus,
                 b.ConfirmationEmailSent, b.Notification1hSent
          FROM Tbl_Booking b
          JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
          JOIN Tbl_Venue v    ON v.VenueID    = b.VenueID
          JOIN Tbl_Payment_Status ps ON ps.PaymentStatusID = b.PaymentStatusID
          WHERE b.BookingID=? LIMIT 1";
  $st = $c->prepare($sql);
  $st->bind_param("i",$bookingID);
  $st->execute(); $rs = $st->get_result();
  $row = $rs ? $rs->fetch_assoc() : null;
  $st->close();
  return $row;
}

/* ---------- ALLOWED "PAID" NAMES (TH/EN) ---------- */
$PAID_NAMES = ["paid","paid_confirmed","ชำระเงินสำเร็จ","ชำระเงินแล้ว"];

/* ---------- MODE 1: CONFIRMATION TRIGGER ---------- */
if (isset($_GET['booking_id']) && ctype_digit($_GET['booking_id'])) {
  $bookingID = (int)$_GET['booking_id'];

  // ใช้รายชื่อสถานะชำระเงินที่อนุญาตแบบคงที่ใน SQL
  $q = "SELECT b.BookingID, b.StartTime, b.EndTime,
               c.Email, CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
               v.VenueName,
               ps.StatusName AS PayStatus
        FROM Tbl_Booking b
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Venue v    ON v.VenueID    = b.VenueID
        JOIN Tbl_Payment_Status ps ON ps.PaymentStatusID = b.PaymentStatusID
        WHERE b.BookingID = ?
          AND b.BookingStatusID = 2
          AND ps.StatusName IN ('paid','paid_confirmed','ชำระเงินสำเร็จ','ชำระเงินแล้ว')
          AND b.ConfirmationEmailSent = 0
        LIMIT 1";
  $st = $conn->prepare($q);
  if (!$st) { http_response_code(500); die("DB prepare failed"); }
  $st->bind_param("i",$bookingID);
  $st->execute(); $rs = $st->get_result();
  if ($row = $rs->fetch_assoc()) {
    $ok = sendEmail(
      $row['Email'],
      $row['CustomerName'],
      '🎉 ยืนยันการจองสนามสำเร็จแล้ว! (#'.$row['BookingID'].')',
      "<h2>สวัสดีคุณ {$row['CustomerName']}</h2>
       <p>หมายเลขการจอง <strong>#{$row['BookingID']}</strong> ได้รับการยืนยันและชำระเงินเรียบร้อยแล้ว</p>
       <ul>
         <li><strong>สนาม:</strong> {$row['VenueName']}</li>
         <li><strong>เริ่ม:</strong> ".date('d/m/Y H:i',strtotime($row['StartTime']))." น.</li>
         <li><strong>สิ้นสุด:</strong> ".date('d/m/Y H:i',strtotime($row['EndTime']))." น.</li>
       </ul>"
    );
    if ($ok) {
      $u = $conn->prepare("UPDATE Tbl_Booking SET ConfirmationEmailSent=1 WHERE BookingID=?");
      $u->bind_param("i",$bookingID); $u->execute(); $u->close();
      echo "Confirmation email sent for #{$bookingID}";
    } else {
      echo "Send mail failed for #{$bookingID} (see logs)";
    }
  } else {
    echo "No eligible booking (need: confirmed + paid + not yet mailed)";
  }
  $st->close();
  $conn->close();
  exit;
}

/* ---------- MODE 2: 1-HOUR REMINDER (CRON) ---------- */
/* ขยายหน้าต่างเวลาให้ครอบคลุมความคลาดเคลื่อนของ cron */
$winStart = date('Y-m-d H:i:00', strtotime('+60 minutes'));
$winEnd   = date('Y-m-d H:i:59', strtotime('+66 minutes'));

$sql = "SELECT b.BookingID, b.StartTime, b.EndTime,
               c.Email, CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
               v.VenueName
        FROM Tbl_Booking b
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Venue v    ON v.VenueID    = b.VenueID
        JOIN Tbl_Payment_Status ps ON ps.PaymentStatusID = b.PaymentStatusID
        WHERE b.BookingStatusID = 2
          AND ps.StatusName IN ('paid','paid_confirmed','ชำระเงินสำเร็จ','ชำระเงินแล้ว')
          AND b.Notification1hSent = 0
          AND b.StartTime >= ? AND b.StartTime <= ?
        LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { http_response_code(500); die("DB prepare failed"); }
$st->bind_param("ss", $winStart, $winEnd);
$st->execute(); $rs = $st->get_result();

$sent = 0; $fail = 0;
while ($row = $rs->fetch_assoc()) {
  $ok = sendEmail(
    $row['Email'],
    $row['CustomerName'],
    '🔔 แจ้งเตือน: อีก 1 ชั่วโมง สนามของคุณจะเริ่มแล้ว! (#'.$row['BookingID'].')',
    "<h2>สวัสดีคุณ {$row['CustomerName']}</h2>
     <p>การจองหมายเลข <strong>#{$row['BookingID']}</strong> ที่ <strong>{$row['VenueName']}</strong> จะเริ่มใน ~ <strong>1 ชั่วโมง</strong></p>
     <ul><li><strong>เริ่ม:</strong> ".date('d/m/Y H:i',strtotime($row['StartTime']))." น.</li></ul>"
  );
  if ($ok) {
    $u = $conn->prepare("UPDATE Tbl_Booking SET Notification1hSent=1 WHERE BookingID=?");
    $u->bind_param("i",$row['BookingID']); $u->execute(); $u->close();
    $sent++;
  } else { $fail++; }
}
$st->close();
$conn->close();

echo "1h reminder done. sent={$sent}, failed={$fail}, window=[{$winStart}..{$winEnd}]";
