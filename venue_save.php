<?php
// venue_save.php
session_start();
require_once __DIR__ . '/db_connect.php';

// ✅ ตรวจสิทธิ์ให้เหมือน admin_venues.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$canAccess = false;
if (isset($_SESSION['employee']['RoleID']) && $_SESSION['employee']['RoleID'] == 1) $canAccess = true;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') $canAccess = true;
if (!$canAccess) { echo "❌ ไม่มีสิทธิ์"; exit; }

// ✅ ต้องมาจาก POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_venues.php");
    exit;
}

// รับค่า
$VenueID      = isset($_POST['VenueID']) ? (int)$_POST['VenueID'] : 0;
$VenueName    = trim($_POST['VenueName'] ?? '');
$VenueTypeID  = (int)($_POST['VenueTypeID'] ?? 0);
$PricePerHour = (float)($_POST['PricePerHour'] ?? 0);
$TimeOpen     = $_POST['TimeOpen'] ?? null;
$TimeClose    = $_POST['TimeClose'] ?? null;
$Address      = trim($_POST['Address'] ?? '');
$Description  = trim($_POST['Description'] ?? '');
$Status       = $_POST['Status'] ?? 'available';

// ตรวจเบื้องต้น
if ($VenueName === '' || $VenueTypeID <= 0) {
    header("Location: admin_venues.php?err=invalid");
    exit;
}

// อัปโหลดรูป (ถ้ามี)
$imageUrl = null;
if (!empty($_FILES['ImageFile']['name']) && $_FILES['ImageFile']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/uploads/venues';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $ext = pathinfo($_FILES['ImageFile']['name'], PATHINFO_EXTENSION);
    $safeExt = preg_match('/^(jpe?g|png|gif|webp)$/i', $ext) ? $ext : 'jpg';
    $filename = 'venue_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $safeExt;
    $target = $dir . '/' . $filename;
    if (move_uploaded_file($_FILES['ImageFile']['tmp_name'], $target)) {
        // เส้นทางที่เก็บไว้ใน DB (สำหรับเว็บ)
        $imageUrl = 'uploads/venues/' . $filename;
    }
}

// INSERT / UPDATE
if ($VenueID > 0) {
    // อัปเดต
    if ($imageUrl) {
        $sql = "UPDATE Tbl_Venue
                SET VenueName=?, VenueTypeID=?, PricePerHour=?, TimeOpen=?, TimeClose=?, Address=?, Description=?, Status=?, ImageURL=?
                WHERE VenueID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidssssssi",
            $VenueName, $VenueTypeID, $PricePerHour, $TimeOpen, $TimeClose, $Address, $Description, $Status, $imageUrl, $VenueID
        );
    } else {
        $sql = "UPDATE Tbl_Venue
                SET VenueName=?, VenueTypeID=?, PricePerHour=?, TimeOpen=?, TimeClose=?, Address=?, Description=?, Status=?
                WHERE VenueID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidsssssi",
            $VenueName, $VenueTypeID, $PricePerHour, $TimeOpen, $TimeClose, $Address, $Description, $Status, $VenueID
        );
    }
    $ok = $stmt->execute();
    $stmt->close();
    header("Location: admin_venues.php?" . ($ok ? "ok=updated" : "err=savefail"));
    exit;

} else {
    // เพิ่มใหม่
    $sql = "INSERT INTO Tbl_Venue (VenueName, VenueTypeID, PricePerHour, TimeOpen, TimeClose, Address, Description, Status, ImageURL)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sidssssss",
        $VenueName, $VenueTypeID, $PricePerHour, $TimeOpen, $TimeClose, $Address, $Description, $Status, $imageUrl
    );
    $ok = $stmt->execute();
    $stmt->close();
    header("Location: admin_venues.php?" . ($ok ? "ok=created" : "err=savefail"));
    exit;
}
