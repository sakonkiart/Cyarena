<?php
session_start();

/* ===== สิทธิ์เข้าถึง ===== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    header("Location: login.php");
    exit;
}

require 'db_connect.php';

/* ===== ค่าคงที่สถานะ (ปรับตามระบบถ้าต่าง) =====
   BookingStatus: 1=รอยืนยัน, 2=ยืนยันแล้ว, 3=ยกเลิก, 4=ไม่สำเร็จ
   PaymentStatus: 1=รอชำระเงิน, 2=ชำระแล้ว
*/
$BOOKING_CONFIRMED_ID = 2;
$BOOKING_CANCELLED_ID = 3;
$PAYMENT_PAID_ID      = 2;
$PAYMENT_PENDING_ID   = 1;

$employee_id = (int)($_SESSION['employee_id'] ?? $_SESSION['user_id']);

/* ===== รับรหัสการจอง (จาก GET id หรือ POST booking_id) ===== */
$booking_id = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die("❌ ไม่พบรหัสการจองที่ถูกต้อง");
}

/* ===== ดึงรายละเอียดการจอง ===== */
$sql = "SELECT 
            b.BookingID,
            b.CustomerID,
            b.VenueID,
            b.StartTime,
            b.EndTime,
            b.BookingStatusID,
            b.PaymentStatusID,
            c.FirstName  AS CustomerName,
            v.VenueName
        FROM Tbl_Booking b
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Venue v    ON b.VenueID    = v.VenueID
        WHERE b.BookingID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("❌ ไม่พบข้อมูลการจองนี้");
}
$booking = $res->fetch_assoc();
$stmt->close();

/* ===== ฟังก์ชันตรวจทับกับรายการที่ล็อก (ยืนยันแล้ว + ชำระแล้ว) ===== */
function hasLockedConflict(mysqli $conn, int $venueId, string $startTime, string $endTime, int $excludeBookingId, int $confirmedId, int $paidId): bool {
    $q = $conn->prepare("
        SELECT 1
        FROM Tbl_Booking
        WHERE VenueID = ?
          AND BookingID <> ?
          AND BookingStatusID = ?      -- ยืนยันแล้ว
          AND PaymentStatusID  = ?      -- ชำระแล้ว
          AND (? < EndTime) AND (? > StartTime)  -- ชนช่วงเวลา
        LIMIT 1
    ");
    $q->bind_param("iiiiss", $venueId, $excludeBookingId, $confirmedId, $paidId, $endTime, $startTime);
    $q->execute();
    $has = $q->get_result()->num_rows > 0;
    $q->close();
    return $has;
}

/* ===== helper อัปเดตสถานะ ===== */
function updateBookingOnly(mysqli $conn, int $bookingId, int $bookingStatusId, int $employeeId): bool {
    $u = $conn->prepare("UPDATE Tbl_Booking SET BookingStatusID = ?, EmployeeID = ? WHERE BookingID = ?");
    $u->bind_param("iii", $bookingStatusId, $employeeId, $bookingId);
    $ok = $u->execute();
    $u->close();
    return $ok;
}
function updateBookingAndPayment(mysqli $conn, int $bookingId, int $bookingStatusId, int $paymentStatusId, int $employeeId): bool {
    $u = $conn->prepare("UPDATE Tbl_Booking SET BookingStatusID = ?, PaymentStatusID = ?, EmployeeID = ? WHERE BookingID = ?");
    $u->bind_param("iiii", $bookingStatusId, $paymentStatusId, $employeeId, $bookingId);
    $ok = $u->execute();
    $u->close();
    return $ok;
}

/* ===== โหมด quick-action ผ่าน GET (จากปุ่มบนตาราง) ===== */
if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];
    $back   = $_SERVER['HTTP_REFERER'] ?? 'manage_bookings.php';

    if ($action === 'confirm') {
        // ยืนยันเฉพาะการจอง (ไม่แตะสถานะเงิน)
        $conflict = hasLockedConflict($conn, (int)$booking['VenueID'], $booking['StartTime'], $booking['EndTime'], $booking_id, $BOOKING_CONFIRMED_ID, $PAYMENT_PAID_ID);
        if ($conflict) {
            $_SESSION['flash_msg'] = "❌ ไม่สามารถยืนยันได้: ช่วงเวลานี้ถูกล็อกโดยรายการที่ยืนยันและชำระเงินแล้ว";
            header("Location: {$back}");
            exit;
        }
        $ok = updateBookingOnly($conn, $booking_id, $BOOKING_CONFIRMED_ID, $employee_id);
        $_SESSION['flash_msg'] = $ok ? "✅ ยืนยันการจอง #{$booking_id} แล้ว" : "❌ ยืนยันไม่สำเร็จ";
        header("Location: {$back}");
        exit;
    }
    if ($action === 'confirm_paid') {
        // ยืนยัน + ชำระแล้ว
        $conflict = hasLockedConflict($conn, (int)$booking['VenueID'], $booking['StartTime'], $booking['EndTime'], $booking_id, $BOOKING_CONFIRMED_ID, $PAYMENT_PAID_ID);
        if ($conflict) {
            $_SESSION['flash_msg'] = "❌ ไม่สามารถบันทึกเป็น 'ยืนยัน+ชำระแล้ว' ได้: ช่วงเวลานี้ถูกล็อก";
            header("Location: {$back}");
            exit;
        }
        $ok = updateBookingAndPayment($conn, $booking_id, $BOOKING_CONFIRMED_ID, $PAYMENT_PAID_ID, $employee_id);
        $_SESSION['flash_msg'] = $ok ? "✅ ยืนยันและทำเครื่องหมายชำระเงินสำเร็จ #{$booking_id}" : "❌ อัปเดตไม่สำเร็จ";
        header("Location: {$back}");
        exit;
    }
    if ($action === 'cancel') {
        $ok = updateBookingOnly($conn, $booking_id, $BOOKING_CANCELLED_ID, $employee_id);
        $_SESSION['flash_msg'] = $ok ? "✅ ยกเลิกรายการจอง #{$booking_id} แล้ว" : "❌ ยกเลิกไม่สำเร็จ";
        header("Location: {$back}");
        exit;
    }

    // action ไม่รู้จัก
    $_SESSION['flash_msg'] = "❌ action ไม่ถูกต้อง";
    header("Location: {$back}");
    exit;
}

/* ===== โหมดฟอร์ม (POST) ===== */
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_status_id = (int)($_POST['booking_status']  ?? $booking['BookingStatusID']);
    $payment_status_id = (int)($_POST['payment_status']  ?? $booking['PaymentStatusID']);

    // ถ้าจะบันทึกเป็น "ยืนยันแล้ว + ชำระแล้ว" ต้องกันทับก่อน
    if ($booking_status_id === $BOOKING_CONFIRMED_ID && $payment_status_id === $PAYMENT_PAID_ID) {
        $conflict = hasLockedConflict($conn, (int)$booking['VenueID'], $booking['StartTime'], $booking['EndTime'], $booking_id, $BOOKING_CONFIRMED_ID, $PAYMENT_PAID_ID);
        if ($conflict) {
            $message = "❌ บันทึกไม่สำเร็จ: ช่วงเวลานี้ถูกล็อกโดยรายการที่ยืนยันและชำระเงินแล้ว";
        } else {
            $ok = updateBookingAndPayment($conn, $booking_id, $booking_status_id, $payment_status_id, $employee_id);
            $message = $ok ? "✅ อัปเดตสถานะสำเร็จ!" : "❌ เกิดข้อผิดพลาดในการอัปเดต";
            if ($ok) {
                $booking['BookingStatusID'] = $booking_status_id;
                $booking['PaymentStatusID'] = $payment_status_id;
            }
        }
    } else {
        $ok = updateBookingAndPayment($conn, $booking_id, $booking_status_id, $payment_status_id, $employee_id);
        $message = $ok ? "✅ อัปเดตสถานะสำเร็จ!" : "❌ เกิดข้อผิดพลาดในการอัปเดต";
        if ($ok) {
            $booking['BookingStatusID'] = $booking_status_id;
            $booking['PaymentStatusID'] = $payment_status_id;
        }
    }
}

/* ===== รายการสถานะ (dropdown) ===== */
$booking_statuses = $conn->query("SELECT BookingStatusID, StatusName FROM Tbl_Booking_Status ORDER BY BookingStatusID");
$payment_statuses = $conn->query("SELECT PaymentStatusID, StatusName FROM Tbl_Payment_Status ORDER BY PaymentStatusID");

/* ===== helper สำหรับข้อความ ===== */
function contains_tick($s){ return mb_strpos($s ?? '', '✅') !== false; }

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>อัปเดตสถานะการจอง | CY Arena</title>
<style>
body { font-family: "Prompt", sans-serif; background: #f4f7f6; margin: 0; }
.container { max-width: 640px; margin: 50px auto; background: #fff; padding: 28px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
h2 { text-align: center; color: #0f172a; margin: 0 0 18px; }
.row { display: grid; grid-template-columns: 140px 1fr; gap: 10px 14px; margin-bottom: 14px; }
.label { color: #475569; font-weight: 600; }
.value { color: #0f172a; }
form { margin-top: 14px; }
select, input[type="hidden"] { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 14px; }
button { background: #2563eb; color: #fff; border: none; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 700; }
button:hover { background: #1d4ed8; }
.message { text-align: center; font-weight: 700; margin-bottom: 14px; }
.success { color: #16a34a; }
.error { color: #ef4444; }
.back-btn { display: inline-block; background: #0ea5e9; color: white; padding: 8px 12px; border-radius: 10px; text-decoration: none; margin-top: 12px; }
.back-btn:hover { background: #0284c7; }
</style>
</head>
<body>

<div class="container">
  <h2>อัปเดตสถานะการจอง</h2>

  <?php if (!empty($message)): ?>
    <p class="message <?php echo contains_tick($message) ? 'success' : 'error'; ?>">
      <?php echo htmlspecialchars($message); ?>
    </p>
  <?php endif; ?>

  <div class="row"><div class="label">รหัสการจอง:</div>  <div class="value">#<?php echo (int)$booking['BookingID']; ?></div></div>
  <div class="row"><div class="label">ลูกค้า:</div>       <div class="value"><?php echo htmlspecialchars($booking['CustomerName']); ?></div></div>
  <div class="row"><div class="label">สนาม:</div>        <div class="value"><?php echo htmlspecialchars($booking['VenueName']); ?></div></div>
  <div class="row"><div class="label">เวลา:</div>        <div class="value">
      <?php echo date("d/m/Y H:i", strtotime($booking['StartTime'])); ?> - <?php echo date("H:i", strtotime($booking['EndTime'])); ?>
  </div></div>

  <form method="POST">
    <input type="hidden" name="booking_id" value="<?php echo (int)$booking_id; ?>">

    <label>สถานะการจอง:</label>
    <select name="booking_status">
      <?php while ($row = $booking_statuses->fetch_assoc()): ?>
        <option value="<?php echo (int)$row['BookingStatusID']; ?>" <?php if ((int)$row['BookingStatusID'] === (int)$booking['BookingStatusID']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($row['StatusName']); ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>สถานะการชำระเงิน:</label>
    <select name="payment_status">
      <?php while ($row = $payment_statuses->fetch_assoc()): ?>
        <option value="<?php echo (int)$row['PaymentStatusID']; ?>" <?php if ((int)$row['PaymentStatusID'] === (int)$booking['PaymentStatusID']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($row['StatusName']); ?>
        </option>
      <?php endwhile; ?>
    </select>

    <button type="submit">💾 บันทึกการเปลี่ยนแปลง</button>
  </form>

  <a href="manage_bookings.php" class="back-btn">⬅ กลับไปหน้าจัดการการจอง</a>
</div>

</body>
</html>
