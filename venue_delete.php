<?php
// venue_delete.php — ลบสนาม (super_admin ลบได้ทุกสนาม), กัน error ตารางที่ไม่มี

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$ME_ID    = (int)($_SESSION['user_id'] ?? 0);
$ROLE     = (string)($_SESSION['role'] ?? 'customer');
$IS_SUPER = ($ROLE === 'super_admin');

if (!in_array($ROLE, ['admin','employee','super_admin'], true)) {
  $_SESSION['flash_error'] = '❌ คุณไม่มีสิทธิ์การใช้งาน';
  header("Location: admin_venues.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['VenueID']) || !ctype_digit($_POST['VenueID'])) {
  $_SESSION['flash_error'] = '❌ คำขอไม่ถูกต้อง';
  header("Location: admin_venues.php"); exit;
}
$venueId = (int)$_POST['VenueID'];

require_once 'db_connect.php';

/* ===== helpers ===== */
function tableExists(mysqli $c, string $table): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? LIMIT 1";
  $st = $c->prepare($sql);
  $st->bind_param("s", $table);
  $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close();
  return $ok;
}
function colExists(mysqli $c, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $c->prepare($sql);
  $st->bind_param("ss", $table, $col);
  $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close();
  return $ok;
}

/* ===== ตรวจสิทธิ์ลบ ===== */
$canDelete = false;
if ($IS_SUPER) {
  $canDelete = true;
} else {
  $hasOwnerCols = colExists($conn, 'Tbl_Venue', 'CreatedByUserID') && colExists($conn, 'Tbl_Venue', 'CreatedByRole');
  if ($hasOwnerCols) {
    $st = $conn->prepare("SELECT CreatedByUserID, CreatedByRole FROM Tbl_Venue WHERE VenueID=?");
    $st->bind_param("i", $venueId);
    $st->execute(); $st->bind_result($ownerId, $ownerRole);
    if ($st->fetch() && (int)$ownerId === $ME_ID && (string)$ownerRole === $ROLE) $canDelete = true;
    $st->close();
  }
}
if (!$canDelete) {
  $_SESSION['flash_error'] = '❌ คุณไม่มีสิทธิ์ลบสนาม';
  header("Location: admin_venues.php"); exit;
}

/* ===== ลบแบบ transaction และเช็คตารางก่อนค่อยลบ ===== */
try {
  $conn->begin_transaction();

  // ตารางการจอง
  if (tableExists($conn, 'Tbl_Booking')) {
    $st = $conn->prepare("DELETE FROM Tbl_Booking WHERE VenueID=?");
    $st->bind_param("i", $venueId);
    $st->execute(); $st->close();
  }

  // ตารางรีวิว
  if (tableExists($conn, 'Tbl_Review')) {
    $st = $conn->prepare("DELETE FROM Tbl_Review WHERE VenueID=?");
    $st->bind_param("i", $venueId);
    $st->execute(); $st->close();
  }

  // ตารางรูปสนาม (บางระบบอาจไม่มี)
  if (tableExists($conn, 'Tbl_Venue_Image')) {
    $st = $conn->prepare("DELETE FROM Tbl_Venue_Image WHERE VenueID=?");
    $st->bind_param("i", $venueId);
    $st->execute(); $st->close();
  }

  // ลบตัวสนาม
  $st = $conn->prepare("DELETE FROM Tbl_Venue WHERE VenueID=? LIMIT 1");
  $st->bind_param("i", $venueId);
  $st->execute();
  $affected = $st->affected_rows;
  $st->close();

  if ($affected < 1) {
    throw new Exception('ไม่พบสนามหรือไม่สามารถลบได้');
  }

  $conn->commit();
  $_SESSION['flash_success'] = '✅ ลบสนามเรียบร้อย';
} catch (Throwable $e) {
  $conn->rollback();
  error_log('[venue_delete] '.$e->getMessage());
  // ซ่อนรายละเอียด error ที่เป็นเรื่อง schema เพื่อไม่ให้ผู้ใช้เห็นข้อความตารางไม่อยู่
  $_SESSION['flash_error'] = '❌ ลบไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
}

header("Location: admin_venues.php");
exit;
