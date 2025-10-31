<?php
// venue_delete.php — ลบสนามแบบปลอดภัย พร้อมตรวจสอบสิทธิ์และเงื่อนไข

session_start();

// ✅ ต้องล็อกอินและเป็นพนักงานถึงจะลบได้
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    $_SESSION['flash_error'] = '❌ คุณไม่มีสิทธิ์ลบสนาม';
    header("Location: admin_venues.php");
    exit;
}

require_once __DIR__ . '/db_connect.php';

// ✅ รับค่าและตรวจสอบ
$venueId = isset($_POST['VenueID']) && ctype_digit($_POST['VenueID']) ? (int)$_POST['VenueID'] : 0;
if ($venueId <= 0) {
    $_SESSION['flash_error'] = 'ไม่พบรหัสสนามที่ต้องการลบ';
    header("Location: admin_venues.php");
    exit;
}

// ดึงข้อมูลสนาม (เพื่อใช้ตรวจสอบ/ลบไฟล์รูปหากจำเป็น)
$stmt = $conn->prepare("SELECT VenueName, ImageURL FROM tbl_venue WHERE VenueID = ?");
$stmt->bind_param("i", $venueId);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venue) {
    $_SESSION['flash_error'] = 'ไม่พบบันทึกสนามนี้ในระบบ';
    header("Location: admin_venues.php");
    exit;
}

// ✅ ไม่อนุญาตให้ลบถ้ามีการจองที่ยังไม่จบ/ไม่ถูกยกเลิก
// อิงตามตรรกะก่อนหน้า: BookingStatusID NOT IN (3,4) = ยังไม่จบ/ไม่ถูกยกเลิก
$active = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_booking WHERE VenueID = ? AND BookingStatusID NOT IN (3,4)");
$stmt->bind_param("i", $venueId);
$stmt->execute();
$stmt->bind_result($active);
$stmt->fetch();
$stmt->close();

if ($active > 0) {
    $_SESSION['flash_error'] = 'ไม่สามารถลบได้: มีการจองที่ยังไม่สิ้นสุด/ไม่ได้ยกเลิกสำหรับสนามนี้';
    header("Location: admin_venues.php");
    exit;
}

// ✅ ถ้าต้องการกัน foreign key จากตารางรีวิว ให้ลบรีวิวก่อน (ถ้ามี)
$stmt = $conn->prepare("DELETE FROM tbl_review WHERE VenueID = ?");
$stmt->bind_param("i", $venueId);
$stmt->execute();
$stmt->close();

// หมายเหตุ: ถ้ามีการตั้งค่า FK บังคับกับตารางอื่น ๆ ให้จัดการลำดับลบตามความจำเป็น
// ที่นี่เราไม่ลบประวัติการจอง (ถ้ามี) เพื่อเก็บประวัติไว้ — และเราได้บล็อกกรณีที่ยังมีการจองค้างไว้แล้ว

// ✅ ลบสนาม
$stmt = $conn->prepare("DELETE FROM tbl_venue WHERE VenueID = ? LIMIT 1");
$stmt->bind_param("i", $venueId);
$ok = $stmt->execute();
$stmt->close();

// (ออปชัน) ลบไฟล์รูปในโฟลเดอร์ถ้าเป็นไฟล์ในเซิร์ฟเวอร์ของเรา
if ($ok && !empty($venue['ImageURL'])) {
    $path = $venue['ImageURL'];
    // ลบเฉพาะไฟล์ภายใต้โฟลเดอร์ images/ เพื่อลดความเสี่ยง
    if (preg_match('~^images/[^\\0]+$~', $path) && file_exists($path)) {
        @unlink($path);
    }
}

if ($ok) {
    $_SESSION['flash_success'] = '✅ ลบสนาม “' . $venue['VenueName'] . '” สำเร็จแล้ว';
} else {
    $_SESSION['flash_error'] = 'ลบสนามไม่สำเร็จ: ' . $conn->error;
}

header("Location: admin_venues.php");
exit;
