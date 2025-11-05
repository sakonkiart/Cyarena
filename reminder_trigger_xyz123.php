<?php
// reminder_trigger_xyz123.php
// (1) Trigger: ส่ง "ยืนยันการจอง" ทันทีเมื่อมี booking_id และบิลถูกชำระ + แอดมินยืนยันแล้ว
// (2) Cron: ส่ง "เตือนก่อนเริ่ม 1 ชั่วโมง" สำหรับบิลที่จะเริ่มใน 60–61 นาทีข้างหน้า (ครั้งเดียว/บิล)

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

@$conn->query("SET time_zone = '+07:00'");

// --- รับพารามิเตอร์ได้ทั้งจาก URL และ CLI ---------------------------------
$argv_token = null; $argv_booking = null; $argv_debug = null;
if (PHP_SAPI === 'cli' && isset($argv)) {
  foreach ($argv as $arg) {
    if (preg_match('/^token=(.+)$/', $arg, $m))   $argv_token = $m[1];
    if (preg_match('/^booking_id=(\d+)$/', $arg, $m)) $argv_booking = (int)$m[1];
    if ($arg === 'debug=1') $argv_debug = 1;
  }
}
$GET = $_GET ?? [];
$TOKEN      = $GET['token']      ?? $argv_token      ?? '';
$BOOKING_ID = isset($GET['booking_id']) ? (int)$GET['booking_id'] : ($argv_booking ?? null);
$DEBUG      = isset($GET['debug']) ? (int)$GET['debug'] : ($argv_debug ?? 0);

// --- Security --------------------------------------------------------
$SECRET_TOKEN = "your_ultra_secret_cron_key_98765";
if ($TOKEN !== $SECRET_TOKEN) {
  if (PHP_SAPI !== 'cli') { http_response_code(403); }
  die("Access Denied: Invalid Token.\n");
}

// --- Schema guard: ธงกันส่งซ้ำ (คงเดิม/เพิ่มเท่าที่จำเป็น) ------------------
function _col_exists(mysqli $c, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $c->prepare($sql); $st->bind_param("ss",$table,$col); $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close(); return $ok;
}
try {
  if (!_col_exists($conn,'Tbl_Booking','NotificationConfirmSent')) {
    @$conn->query("ALTER TABLE Tbl_Booking ADD COLUMN NotificationConfirmSent TINYINT(1) NOT NULL DEFAULT 0");
  }
  if (!_col_exists($conn,'Tbl_Booking','NotificationReminderSent')) {
    @$conn->query("ALTER TABLE Tbl_Booking ADD COLUMN NotificationReminderSent TINYINT(1) NOT NULL DEFAULT 0");
  }
} catch (Throwable $e) { error_log('[reminder schema guard] '.$e->getMessage()); }

// --- Mail helper -----------------------------------------------------
function sendEmail($recipientEmail,$recipientName,$startTime,$endTime,$bookingID,$venueName,$mode='confirm'){
  $mail = new PHPMailer(true);
  try{
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'valorantwhq2548@gmail.com';
    $mail->Password   = 'rzwx bonp logd gaug'; // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Gmail แนะนำให้ from เป็นผู้ใช้เดียวกับ SMTP
    $mail->setFrom('valorantwhq2548@gmail.com', 'CY Arena Booking');
    $mail->addReplyTo('no-reply@cyarena.com', 'CY Arena');
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    if($mode==='confirm'){
      $mail->Subject = '🎉 ยืนยันการจองสำเร็จ (#'.$bookingID.')';
      $mail->Body =
        "<h2>สวัสดีคุณ {$recipientName},</h2>
         <p>การจองหมายเลข <strong>#{$bookingID}</strong> ได้รับการยืนยันแล้ว</p>
         <ul>
           <li><strong>สนาม:</strong> {$venueName}</li>
           <li><strong>เริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li>
           <li><strong>สิ้นสุด:</strong> ".date('d/m/Y H:i', strtotime($endTime))." น.</li>
         </ul>
         <p>ขอบคุณที่ใช้บริการ CY Arena</p>";
    }else{
      $mail->Subject = '⏰ เตือนความจำ: อีก 1 ชั่วโมงการจองจะเริ่มแล้ว! (#'.$bookingID.')';
      $mail->Body =
        "<h2>สวัสดีคุณ {$recipientName},</h2>
         <p>การจอง <strong>#{$bookingID}</strong> ที่ <strong>{$venueName}</strong> จะเริ่มใน <strong>อีก 1 ชั่วโมง</strong></p>
         <ul><li><strong>เริ่ม:</strong> ".date('d/m/Y H:i', strtotime($startTime))." น.</li></ul>
         <p>โปรดเตรียมตัวให้พร้อมครับ 🙂</p>";
    }

    $mail->send(); return true;
  }catch(Exception $e){
    error_log("Mailer Error ({$mode}) #{$bookingID}: ".$mail->ErrorInfo);
    return false;
  }
}

// --- เงื่อนไข “ต้องชำระแล้ว” (ปรับตามสคีมาใช้งานจริงได้) -------------------
$PAID_CLAUSE = "(b.IsPaid = 1 OR b.PaymentStatus = 'paid' OR b.PaidAt IS NOT NULL)";

// ================= (1) TRIGGER: ส่งยืนยันทันที =====================
if ($BOOKING_ID){
  $sql = "
    SELECT b.BookingID,b.StartTime,b.EndTime,
           c.Email, CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
           v.VenueName,
           b.NotificationConfirmSent
    FROM Tbl_Booking b
    JOIN Tbl_Customer c ON b.CustomerID=c.CustomerID
    JOIN Tbl_Venue v    ON b.VenueID=v.VenueID
    WHERE b.BookingID=?
      AND b.BookingStatusID=2
      AND {$PAID_CLAUSE}
    LIMIT 1";
  $st = $conn->prepare($sql);
  if(!$st){ if(!$DEBUG){http_response_code(500);} die("DB prepare error\n"); }
  $st->bind_param("i",$BOOKING_ID);
  if($st->execute()){
    $rs=$st->get_result();
    if($row=$rs->fetch_assoc()){
      if((int)$row['NotificationConfirmSent']===1){
        echo "Already sent confirmation for #{$BOOKING_ID}.\n";
      }else{
        $ok = sendEmail($row['Email'],$row['CustomerName'],$row['StartTime'],$row['EndTime'],$row['BookingID'],$row['VenueName'],'confirm');
        if($ok){
          $u=$conn->prepare("UPDATE Tbl_Booking SET NotificationConfirmSent=1 WHERE BookingID=?");
          if($u){ $u->bind_param("i",$BOOKING_ID); $u->execute(); $u->close(); }
          echo "Confirmation email sent for #{$BOOKING_ID}.\n";
        }else{
          echo "Failed to send confirmation for #{$BOOKING_ID}.\n";
        }
      }
    }else{
      echo "Booking #{$BOOKING_ID} not eligible (must be confirmed AND paid).\n";
    }
  }else{
    if(!$DEBUG){http_response_code(500);} echo "DB exec error\n";
  }
  $st->close();
}
// ================= (2) CRON: เตือนก่อน 1 ชั่วโมง ====================
else{
  if($DEBUG) echo "Cron: 1-hour reminder scanning...\n";
  $timeStart = date('Y-m-d H:i:s', strtotime('+60 minutes'));
  $timeEnd   = date('Y-m-d H:i:s', strtotime('+61 minutes'));

  $sql = "
    SELECT b.BookingID,b.StartTime,b.EndTime,
           c.Email, CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
           v.VenueName
    FROM Tbl_Booking b
    JOIN Tbl_Customer c ON b.CustomerID=c.CustomerID
    JOIN Tbl_Venue v    ON b.VenueID=v.VenueID
    WHERE b.BookingStatusID=2
      AND {$PAID_CLAUSE}
      AND b.NotificationReminderSent=0
      AND b.StartTime>=? AND b.StartTime<?
    LIMIT 200";
  $st=$conn->prepare($sql);
  if(!$st){ if(!$DEBUG){http_response_code(500);} die("DB prepare error\n"); }
  $st->bind_param("ss",$timeStart,$timeEnd);

  if($st->execute()){
    $rs=$st->get_result(); $okCount=0; $failCount=0;
    while($r=$rs->fetch_assoc()){
      $ok = sendEmail($r['Email'],$r['CustomerName'],$r['StartTime'],$r['EndTime'],$r['BookingID'],$r['VenueName'],'reminder');
      if($ok){
        $u=$conn->prepare("UPDATE Tbl_Booking SET NotificationReminderSent=1 WHERE BookingID=?");
        if($u){ $u->bind_param("i",$r['BookingID']); $u->execute(); $u->close(); }
        $okCount++;
      }else{ $failCount++; }
    }
    echo "Cron finished. Sent {$okCount}, Failed {$failCount}.\n";
  }else{
    if(!$DEBUG){http_response_code(500);} echo "DB exec error\n";
  }
  $st->close();
}

$conn->close();
