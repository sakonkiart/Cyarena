<?php
// venue_save.php — Save/Update venue (รองรับ type_admin แบบจำกัดประเภทสนาม)

session_start();

/* >>> ADD: ป้องกัน cache หลัง redirect */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* ===== ตรวจสิทธิ์ ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ROLE = $_SESSION['role'] ?? '';
$IS_EMPLOYEE   = ($ROLE === 'employee');
$IS_ADMIN      = ($ROLE === 'admin' || $ROLE === 'super_admin'); // เผื่อระบบมีสองชื่อ
$IS_TYPE_ADMIN = ($ROLE === 'type_admin');

/* >>> ADD: ดึงข้อมูล type_admin */
$TYPE_ADMIN_VTID = (int)($_SESSION['type_admin_venue_type_id'] ?? 0);
$TYPE_ADMIN_NAME = (string)($_SESSION['type_admin_type_name'] ?? '');

/* >>> ADD: util คืน role type_admin หากสวมบท employee ไว้ในหน้าอื่น */
function _restore_type_admin_role_before_redirect(): void {
    if (isset($_SESSION['role_backup_for_type_admin']) && $_SESSION['role_backup_for_type_admin'] === 'type_admin') {
        $_SESSION['role'] = 'type_admin';
        unset($_SESSION['role_backup_for_type_admin']);
    }
}

/* อนุญาตให้เข้ามาเฉพาะ employee/admin/type_admin */
if (!($IS_EMPLOYEE || $IS_ADMIN || $IS_TYPE_ADMIN)) {
    echo "❌ ไม่มีสิทธิ์";
    exit;
}

require_once 'db_connect.php';

/* ===== รับค่า POST ===== */
$VenueID      = isset($_POST['VenueID']) ? (int)$_POST['VenueID'] : 0;
$VenueName    = trim($_POST['VenueName'] ?? '');
$VenueTypeID  = (int)($_POST['VenueTypeID'] ?? 0);
$PricePerHour = (float)($_POST['PricePerHour'] ?? 0);
$TimeOpen     = trim($_POST['TimeOpen'] ?? '');
$TimeClose    = trim($_POST['TimeClose'] ?? '');
$Address      = trim($_POST['Address'] ?? '');
$Description  = trim($_POST['Description'] ?? '');
$Status       = trim($_POST['Status'] ?? 'available');

/* >>> ADD: บังคับสิทธิ์ของ type_admin — ต้องมีประเภทสนามสำหรับสิทธิ์ และห้ามออกนอกประเภทนั้น */
if ($IS_TYPE_ADMIN) {
    if ($TYPE_ADMIN_VTID <= 0) {
        $_SESSION['flash_error'] = "❌ บัญชี Type Admin ยังไม่ถูกกำหนดประเภทสนาม";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }
    // บังคับให้ใช้ประเภทที่ได้รับสิทธิ์เท่านั้น (ไม่สนค่า POST)
    $VenueTypeID = $TYPE_ADMIN_VTID;
}

/* ตรวจความถูกต้องพื้นฐาน */
if ($VenueName === '' || $VenueTypeID <= 0 || $PricePerHour < 0) {
    $_SESSION['flash_error'] = "❌ ข้อมูลไม่ครบถ้วน";
    _restore_type_admin_role_before_redirect();
    header("Location: admin_venues.php");
    exit;
}

/* ปรับเวลาให้เป็น HH:MM:SS หากส่งมาเป็น HH:MM */
$fmtTime = function($t) {
    $t = trim($t);
    if ($t === '') return null;
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
    return null;
};
$TimeOpen  = $fmtTime($TimeOpen)  ?? null;
$TimeClose = $fmtTime($TimeClose) ?? null;

/* ===== เตรียมอัปโหลดรูป (ถ้ามี) ===== */
$ImageURLNew = null;
if (!empty($_FILES['ImageFile']['name']) && is_uploaded_file($_FILES['ImageFile']['tmp_name'])) {
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($_FILES['ImageFile']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed, true)) {
        $uploadDirFs = __DIR__ . '/venues';
        if (!is_dir($uploadDirFs)) @mkdir($uploadDirFs, 0775, true);

        $newName = 'venue_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $destFs  = $uploadDirFs . '/' . $newName;
        if (@move_uploaded_file($_FILES['ImageFile']['tmp_name'], $destFs)) {
            $ImageURLNew = 'venues/' . $newName; // เก็บเป็น path แบบ relative
        } else {
            $_SESSION['flash_error'] = "❌ อัปโหลดรูปไม่สำเร็จ";
            _restore_type_admin_role_before_redirect();
            header("Location: admin_venues.php");
            exit;
        }
    } else {
        $_SESSION['flash_error'] = "❌ ไฟล์รูปไม่ถูกประเภท";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }
}

/* ===== INSERT / UPDATE ===== */

/* กรณี UPDATE: ต้องตรวจสิทธิ์ว่า type_admin แก้เฉพาะสนามในประเภทที่ตัวเองดูแลเท่านั้น */
if ($VenueID > 0) {
    $q = "SELECT VenueID, VenueTypeID, ImageURL FROM Tbl_Venue WHERE VenueID = ?";
    if (!$st = $conn->prepare($q)) {
        $_SESSION['flash_error'] = "❌ ไม่สามารถเตรียมคำสั่ง (ตรวจข้อมูลเดิม)";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }
    $st->bind_param("i", $VenueID);
    $st->execute();
    $old = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$old) {
        $_SESSION['flash_error'] = "❌ ไม่พบข้อมูลสนามที่จะอัปเดต";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }

    if ($IS_TYPE_ADMIN && (int)$old['VenueTypeID'] !== $TYPE_ADMIN_VTID) {
        $_SESSION['flash_error'] = "❌ คุณไม่มีสิทธิ์แก้ไขสนามนี้ (จำกัดประเภท: {$TYPE_ADMIN_NAME} )";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }

    // ถ้าไม่อัปโหลดรูปใหม่ ให้ใช้รูปเดิม
    $ImageURLFinal = $ImageURLNew ?? ($old['ImageURL'] ?? null);

    $sql = "UPDATE Tbl_Venue
            SET VenueName=?, VenueTypeID=?, Description=?, PricePerHour=?, 
                TimeOpen=?, TimeClose=?, Address=?, Status=?, ImageURL=?
            WHERE VenueID=?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            "sisdsssssi",
            $VenueName,
            $VenueTypeID,
            $Description,
            $PricePerHour,
            $TimeOpen,
            $TimeClose,
            $Address,
            $Status,
            $ImageURLFinal,
            $VenueID
        );
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "✅ บันทึกการแก้ไขสนาม #{$VenueID} เรียบร้อย";
        } else {
            $_SESSION['flash_error'] = "❌ อัปเดตไม่สำเร็จ: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = "❌ ไม่สามารถเตรียมคำสั่งอัปเดตได้";
    }

    _restore_type_admin_role_before_redirect();
    header("Location: admin_venues.php?id=".$VenueID);
    exit;
}

/* กรณี INSERT: type_admin จะถูกบังคับให้ใช้ประเภทที่ได้รับสิทธิ์อยู่แล้ว */
$ImageURLFinal = $ImageURLNew; // สำหรับ insert ถ้าไม่อัปโหลดก็ปล่อยว่างได้
$sql = "INSERT INTO Tbl_Venue
        (VenueName, VenueTypeID, Description, PricePerHour, TimeOpen, TimeClose, Address, Status, ImageURL)
        VALUES (?,?,?,?,?,?,?,?,?)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param(
        "sisdsssss",
        $VenueName,
        $VenueTypeID,
        $Description,
        $PricePerHour,
        $TimeOpen,
        $TimeClose,
        $Address,
        $Status,
        $ImageURLFinal
    );
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $_SESSION['flash_success'] = "✅ เพิ่มสนามใหม่สำเร็จ (#{$newId})";
    } else {
        $_SESSION['flash_error'] = "❌ เพิ่มสนามไม่สำเร็จ: " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['flash_error'] = "❌ ไม่สามารถเตรียมคำสั่งเพิ่มข้อมูลได้";
}

_restore_type_admin_role_before_redirect();
header("Location: admin_venues.php");
exit;
