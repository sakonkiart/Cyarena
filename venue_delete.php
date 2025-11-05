<?php
// venue_delete.php — ลบสนาม (super_admin ลบได้ทุกสนาม)

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ME_ID = (int)($_SESSION['user_id'] ?? 0);
$ROLE  = (string)($_SESSION['role'] ?? 'customer');
$IS_SUPER = ($ROLE === 'super_admin');

if (!in_array($ROLE, ['admin','employee','super_admin'], true)) {
    $_SESSION['flash_error'] = '❌ คุณไม่มีสิทธิ์การใช้งาน';
    header("Location: admin_venues.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['VenueID']) || !ctype_digit($_POST['VenueID'])) {
    $_SESSION['flash_error'] = '❌ คำขอไม่ถูกต้อง';
    header("Location: admin_venues.php");
    exit;
}
$venueId = (int)$_POST['VenueID'];

require_once 'db_connect.php';

/* --- helper: ตรวจว่าคอลัมน์ owner มีอยู่ไหม --- */
function colExists(mysqli $c, string $table, string $col): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $c->prepare($sql);
    $st->bind_param("ss", $table, $col);
    $st->execute(); $st->store_result();
    $ok = $st->num_rows > 0; $st->close();
    return $ok;
}

/* --- ตรวจสิทธิ์ลบ --- */
$canDelete = false;

if ($IS_SUPER) {
    // super admin ลบได้ทุกสนาม
    $canDelete = true;
} else {
    // admin/employee ต้องเป็นเจ้าของสนาม
    $hasOwnerCols = colExists($conn, 'Tbl_Venue', 'CreatedByUserID') && colExists($conn, 'Tbl_Venue', 'CreatedByRole');

    if ($hasOwnerCols) {
        $st = $conn->prepare("SELECT CreatedByUserID, CreatedByRole FROM Tbl_Venue WHERE VenueID=?");
        $st->bind_param("i", $venueId);
        $st->execute();
        $st->bind_result($ownerId, $ownerRole);
        if ($st->fetch()) {
            if ((int)$ownerId === $ME_ID && (string)$ownerRole === $ROLE) $canDelete = true;
        }
        $st->close();
    } else {
        // ถ้ายังไม่มีคอลัมน์เจ้าของ ให้กันไว้เฉพาะ super_admin เท่านั้น
        $canDelete = false;
    }
}

if (!$canDelete) {
    $_SESSION['flash_error'] = '❌ คุณไม่มีสิทธิ์ลบสนาม';
    header("Location: admin_venues.php");
    exit;
}

/* --- ลบข้อมูลแบบทรานแซกชัน --- */
try {
    $conn->begin_transaction();

    // ลบการจองของสนามนี้ (ถ้าไม่มีตารางจะข้าม)
    @$delBk = $conn->prepare("DELETE FROM Tbl_Booking WHERE VenueID=?");
    if ($delBk) {
        $delBk->bind_param("i", $venueId);
        $delBk->execute();
        $delBk->close();
    }

    // ลบรีวิวของสนามนี้ (ถ้าไม่มีตารางจะข้าม)
    @$delRv = $conn->prepare("DELETE FROM Tbl_Review WHERE VenueID=?");
    if ($delRv) {
        $delRv->bind_param("i", $venueId);
        $delRv->execute();
        $delRv->close();
    }

    // ถ้ามีตารางรูปหลายรูป เช่น Tbl_Venue_Image ก็ลบทิ้งได้ (ไม่บังคับมีตาราง)
    @$delImgTbl = $conn->prepare("DELETE FROM Tbl_Venue_Image WHERE VenueID=?");
    if ($delImgTbl) {
        $delImgTbl->bind_param("i", $venueId);
        $delImgTbl->execute();
        $delImgTbl->close();
    }

    // ลบตัวสนาม
    $stDel = $conn->prepare("DELETE FROM Tbl_Venue WHERE VenueID=? LIMIT 1");
    $stDel->bind_param("i", $venueId);
    $stDel->execute();
    $affected = $stDel->affected_rows;
    $stDel->close();

    if ($affected < 1) {
        throw new Exception('ไม่พบสนามหรือไม่สามารถลบได้');
    }

    $conn->commit();
    $_SESSION['flash_success'] = '✅ ลบสนามเรียบร้อย';
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[venue_delete] '.$e->getMessage());
    $_SESSION['flash_error'] = '❌ ลบไม่สำเร็จ: '.$e->getMessage();
}

header("Location: admin_venues.php");
exit;
