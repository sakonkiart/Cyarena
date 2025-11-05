<?php
// venue_save.php — Save/Update venue (รองรับ type_admin + บันทึกผู้สร้างสนาม + ผูก CompanyID ให้ admin เห็นร่วมกัน)

session_start();

/* >>> KEEP: ป้องกัน cache หลัง redirect */
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
$IS_ADMIN_ONLY = ($ROLE === 'admin');            // admin รายบริษัท
$IS_SUPER      = ($ROLE === 'super_admin');
$IS_TYPE_ADMIN = ($ROLE === 'type_admin');

/* >>> ADD: ดึงข้อมูล type_admin */
$TYPE_ADMIN_VTID = (int)($_SESSION['type_admin_venue_type_id'] ?? 0);
$TYPE_ADMIN_NAME = (string)($_SESSION['type_admin_type_name'] ?? '');

/* >>> KEEP: util สำหรับคืนบทบาท type_admin หากมีการสลับบทในหน้าอื่น */
function _restore_type_admin_role_before_redirect(): void {
    if (isset($_SESSION['role_backup_for_type_admin']) && $_SESSION['role_backup_for_type_admin'] === 'type_admin') {
        $_SESSION['role'] = 'type_admin';
        unset($_SESSION['role_backup_for_type_admin']);
    }
}

/* อนุญาตให้เข้ามาเฉพาะ employee/admin/type_admin/super_admin */
if (!($IS_EMPLOYEE || $IS_ADMIN_ONLY || $IS_TYPE_ADMIN || $IS_SUPER)) {
    echo "❌ ไม่มีสิทธิ์";
    exit;
}

require_once 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

/* ======================= COMPANY SCOPE HELPERS (เฉพาะที่ต้องใช้) ======================= */
function getCompanyIdForCurrentAdmin(mysqli $conn, int $userId, string $role): ?int {
    if ($role === 'super_admin') return null; // เห็นทุกบริษัท
    // สิทธิ์ admin รายบริษัทผูกที่ Tbl_Company_Admin.CustomerID = user_id (ลูกค้า/แอดมินรายบริษัท)
    $sql = "SELECT CompanyID FROM Tbl_Company_Admin WHERE CustomerID = ? LIMIT 1";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param("i", $userId);
        $st->execute();
        $rs = $st->get_result();
        if ($row = $rs->fetch_assoc()) return (int)$row['CompanyID'];
    }
    return null;
}
/* ===================================================================== */

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

/* >>> ADD: CompanyID ที่จะใช้งาน
   - ถ้า non-super (admin/type_admin/employee) จะพยายามอ่านจาก POST (ซึ่ง admin_venues.php ส่ง hidden มา)
     หากไม่มี ให้ fallback ไปคิวรีจาก Tbl_Company_Admin
   - ถ้า super_admin = null (ไม่บังคับบริษัท) */
$POST_COMPANY_ID = isset($_POST['CompanyID']) ? (int)$_POST['CompanyID'] : 0;
$MY_COMPANY_ID = $IS_SUPER ? null : ($POST_COMPANY_ID > 0 ? $POST_COMPANY_ID : getCompanyIdForCurrentAdmin($conn, (int)$_SESSION['user_id'], $ROLE));

/* >>> ADD: บังคับว่า non-super ต้องมี CompanyID */
if (!$IS_SUPER && (!$MY_COMPANY_ID || $MY_COMPANY_ID <= 0)) {
    $_SESSION['flash_error'] = "⚠️ บัญชีของคุณยังไม่ได้รับสิทธิ์บริษัทจาก super_admin";
    _restore_type_admin_role_before_redirect();
    header("Location: admin_venues.php");
    exit;
}

/* >>> KEEP: ตัวตนผู้สร้างที่ต้องบันทึกลงฐาน */
$CREATOR_USER_ID = (int)($_SESSION['user_id'] ?? 0);
/* จัดเก็บ CreatedByRole ให้สอดคล้องการกรอง:
   - type_admin เก็บเป็น 'employee' (ตามระบบเดิม)
   - admin/super_admin เก็บตามจริง
   - อื่น ๆ เป็น 'employee' */
$CREATOR_ROLE_DB = ($ROLE === 'type_admin') ? 'employee'
                 : ($IS_SUPER ? 'super_admin'
                 : ($IS_ADMIN_ONLY ? 'admin' : 'employee'));

/* >>> KEEP: บังคับสิทธิ์ของ type_admin — ต้องมีประเภทสนามสำหรับสิทธิ์ และห้ามออกนอกประเภทนั้น */
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
            $ImageURLNew = 'venues/' . $newName; // เก็บ path แบบ relative
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

/* กรณี UPDATE: ตรวจสิทธิ์พื้นฐาน + (ถ้า non-super) แถวเดิมต้องอยู่ในบริษัทเดียวกัน */
if ($VenueID > 0) {
    $q = "SELECT VenueID, VenueTypeID, ImageURL, CompanyID FROM Tbl_Venue WHERE VenueID = ?";
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

    if (!$IS_SUPER) {
        if ((int)$old['CompanyID'] !== (int)$MY_COMPANY_ID) {
            $_SESSION['flash_error'] = "❌ คุณไม่มีสิทธิ์แก้ไขสนามนี้ (ต่างบริษัท)";
            _restore_type_admin_role_before_redirect();
            header("Location: admin_venues.php");
            exit;
        }
    }

    if ($IS_TYPE_ADMIN && (int)$old['VenueTypeID'] !== $TYPE_ADMIN_VTID) {
        $_SESSION['flash_error'] = "❌ คุณไม่มีสิทธิ์แก้ไขสนามนี้ (จำกัดประเภท: {$TYPE_ADMIN_NAME})";
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

/* กรณี INSERT */
/* >>> ADD: ต้องมี CompanyID (ถ้าไม่ใช่ super_admin) เพื่อให้ admin บริษัทเดียวกันเห็นร่วมกัน */
if (!$IS_SUPER) {
    if (!$MY_COMPANY_ID || $MY_COMPANY_ID <= 0) {
        $_SESSION['flash_error'] = "⚠️ ไม่พบ CompanyID ของคุณ — โปรดให้ super_admin มอบสิทธิ์บริษัทก่อน";
        _restore_type_admin_role_before_redirect();
        header("Location: admin_venues.php");
        exit;
    }
}

/* บันทึก CreatedBy* และ CompanyID ลงฐาน */
$ImageURLFinal = $ImageURLNew; // สำหรับ insert ถ้าไม่อัปโหลดก็ปล่อย null/ว่างได้

if ($IS_SUPER) {
    // super_admin สามารถสร้างสนามให้บริษัทใดก็ได้ (CompanyID รับจาก POST; ถ้าไม่ส่งจะเป็น NULL)
    $INSERT_COMPANY_ID = ($POST_COMPANY_ID > 0) ? (int)$POST_COMPANY_ID : null;

    $sql = "INSERT INTO Tbl_Venue
            (VenueName, VenueTypeID, Description, PricePerHour, TimeOpen, TimeClose, Address, Status, ImageURL,
             CreatedByUserID, CreatedByRole, CompanyID)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    if ($stmt = $conn->prepare($sql)) {
        /* types: s i s d s s s s s i s i  => "sisdsssssisi" */
        $stmt->bind_param(
            "sisdsssssisi",
            $VenueName,
            $VenueTypeID,
            $Description,
            $PricePerHour,
            $TimeOpen,
            $TimeClose,
            $Address,
            $Status,
            $ImageURLFinal,
            $CREATOR_USER_ID,
            $CREATOR_ROLE_DB,
            $INSERT_COMPANY_ID
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

} else {
    // non-super: ต้องผูกกับบริษัทของตนเองเสมอ
    $INSERT_COMPANY_ID = (int)$MY_COMPANY_ID;

    $sql = "INSERT INTO Tbl_Venue
            (VenueName, VenueTypeID, Description, PricePerHour, TimeOpen, TimeClose, Address, Status, ImageURL,
             CreatedByUserID, CreatedByRole, CompanyID)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    if ($stmt = $conn->prepare($sql)) {
        /* types: s i s d s s s s s i s i */
        $stmt->bind_param(
            "sisdsssssisi",
            $VenueName,
            $VenueTypeID,
            $Description,
            $PricePerHour,
            $TimeOpen,
            $TimeClose,
            $Address,
            $Status,
            $ImageURLFinal,
            $CREATOR_USER_ID,
            $CREATOR_ROLE_DB,
            $INSERT_COMPANY_ID
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
}

_restore_type_admin_role_before_redirect();
header("Location: admin_venues.php");
exit;
