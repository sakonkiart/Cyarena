<?php
// venue_set_status.php
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

$VenueID = (int)($_POST['VenueID'] ?? 0);
$Status  = $_POST['Status'] ?? 'available';

$allow = ['available','maintenance','closed'];
if ($VenueID <= 0 || !in_array($Status, $allow, true)) {
    header("Location: admin_venues.php?err=badparam");
    exit;
}

$stmt = $conn->prepare("UPDATE tbl_venue SET Status=? WHERE VenueID=?");
$stmt->bind_param("si", $Status, $VenueID);
$ok = $stmt->execute();
$stmt->close();

header("Location: admin_venues.php?" . ($ok ? "ok=status" : "err=savefail"));
exit;
