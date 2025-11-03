<?php
session_start();

/* =========================
   >>> ADD: รองรับสิทธิ์ type_admin (ลูกค้าที่ถูกแต่งตั้งให้จัดการการจองได้เฉพาะ 1 ประเภทสนาม)
   แนวคิด:
   - ถ้า role เป็น 'type_admin' ให้ "สวมบทชั่วคราว" เป็น employee เพื่อผ่าน if เดิม
   - บันทึกสถานะไว้ใน $IS_TYPE_ADMIN และจำ VenueTypeID ที่ได้รับมอบสิทธิ์
   - ทุกการกระทำ (อัปเดต/ยกเลิก/ลบ) จะตรวจสอบสิทธิ์ว่าการจองนั้นอยู่ในประเภทสนามที่ได้รับมอบสิทธิ์จริง
   - การแสดงรายการจองจะถูกกรองเฉพาะประเภทสนามที่ได้รับมอบสิทธิ์
   ========================= */
$IS_TYPE_ADMIN   = false;
$TYPE_ADMIN_VTID = 0;
$TYPE_ADMIN_NAME = '';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'type_admin') {
    $IS_TYPE_ADMIN   = true;
    $TYPE_ADMIN_VTID = (int)($_SESSION['type_admin_venue_type_id'] ?? 0);
    $TYPE_ADMIN_NAME = (string)($_SESSION['type_admin_type_name'] ?? '');

    // สวมบท employee ชั่วคราวเพื่อให้ผ่านการตรวจสิทธิ์เดิม (ด้านล่าง)
    $_SESSION['role_backup_for_type_admin'] = 'type_admin';
    $_SESSION['role'] = 'employee';
}

// ✅ ตรวจสอบสิทธิ์พนักงาน (type_admin ที่สวมบทเป็น employee ก็จะผ่านได้)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';

$employee_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'พนักงาน';

// Avatar logic
$avatarPath = $_SESSION['avatar_path'] ?? '';
$avatarLocal = 'assets/avatar-default.png';

function _exists_rel($rel) {
    return is_file(__DIR__ . '/' . ltrim($rel, '/'));
}

if ($avatarPath && _exists_rel($avatarPath)) {
    $avatarSrc = $avatarPath;
} elseif (_exists_rel($avatarLocal)) {
    $avatarSrc = $avatarLocal;
} else {
    $avatarSrc = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#2563eb"/><text x="50%" y="54%" text-anchor="middle" font-size="48" font-family="Arial" fill="#fff">👤</text></svg>'
    );
}

/* >>> ADD: ฟังก์ชันตรวจสอบสิทธิ์ของ type_admin ว่าสามารถจัดการ booking นี้ได้หรือไม่ */
function _type_admin_can_manage(mysqli $conn, int $booking_id, int $vtid): bool {
    if ($vtid <= 0) return false;
    $q = "SELECT 1
          FROM Tbl_Booking b
          JOIN Tbl_Venue v ON v.VenueID = b.VenueID
          WHERE b.BookingID = ? AND v.VenueTypeID = ?";
    if (!$st = $conn->prepare($q)) return false;
    $st->bind_param("ii", $booking_id, $vtid);
    $st->execute();
    $rs = $st->get_result();
    $ok = $rs && $rs->num_rows === 1;
    $st->close();
    return $ok;
}

// ✅ จัดการการอัปเดตสถานะ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $booking_status = intval($_POST['booking_status']);
    $payment_status = intval($_POST['payment_status']);

    /* >>> ADD: ถ้าเป็น type_admin ต้องเช็คสิทธิ์ และอัปเดตโดย "ไม่แตะต้อง EmployeeID" */
    if ($IS_TYPE_ADMIN) {
        if (!_type_admin_can_manage($conn, $booking_id, $TYPE_ADMIN_VTID)) {
            $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์แก้ไขการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
            header("Location: manage_bookings.php");
            exit;
        }
        $sql_ta = "UPDATE Tbl_Booking 
                   SET BookingStatusID = ?, PaymentStatusID = ?
                   WHERE BookingID = ?";
        if ($stmt = $conn->prepare($sql_ta)) {
            $stmt->bind_param("iii", $booking_status, $payment_status, $booking_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "✅ อัปเดตสถานะเรียบร้อยแล้ว! (Booking #$booking_id)";
            } else {
                $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "❌ ไม่สามารถเตรียมคำสั่งอัปเดตได้";
        }
        header("Location: manage_bookings.php");
        exit; // กันไม่ให้ไหลไปใช้โค้ดเดิมของ employee ด้านล่าง
    }

    // ----- โค้ดเดิมของพนักงาน (คงไว้) -----
    $update_sql = "UPDATE Tbl_Booking 
                   SET BookingStatusID = ?, PaymentStatusID = ?, EmployeeID = ?
                   WHERE BookingID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiii", $booking_status, $payment_status, $employee_id, $booking_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "✅ อัปเดตสถานะเรียบร้อยแล้ว! (Booking #$booking_id)";
    } else {
        $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
    }
    $stmt->close();
    header("Location: manage_bookings.php");
    exit;
}

// ✅ จัดการการยกเลิกการจอง
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);

    /* >>> ADD: ป้องกัน type_admin ยกเลิกข้ามประเภทสนาม */
    if ($IS_TYPE_ADMIN && !_type_admin_can_manage($conn, $cancel_id, $TYPE_ADMIN_VTID)) {
        $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์ยกเลิกการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
        header("Location: manage_bookings.php");
        exit;
    }

    $conn->query("UPDATE Tbl_Booking SET BookingStatusID = 3 WHERE BookingID = $cancel_id");
    $_SESSION['success_message'] = "✅ ยกเลิกการจองเรียบร้อยแล้ว! (Booking #$cancel_id)";
    header("Location: manage_bookings.php");
    exit;
}

// ✅ จัดการการลบการจอง (ใหม่)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    /* >>> ADD: ป้องกัน type_admin ลบข้ามประเภทสนาม */
    if ($IS_TYPE_ADMIN && !_type_admin_can_manage($conn, $delete_id, $TYPE_ADMIN_VTID)) {
        $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์ลบการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
        header("Location: manage_bookings.php");
        exit;
    }
    
    // ลบข้อมูลจากฐานข้อมูล
    $delete_sql = "DELETE FROM Tbl_Booking WHERE BookingID = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "🗑️ ลบการจองเรียบร้อยแล้ว! (Booking #$delete_id)";
    } else {
        $_SESSION['error_message'] = "❌ ไม่สามารถลบการจองได้: " . $stmt->error;
    }
    $stmt->close();
    
    header("Location: manage_bookings.php");
    exit;
}

// Get messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ✅ ฟิลเตอร์การค้นหา
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_payment = $_GET['payment'] ?? '';
$filter_date = $_GET['date'] ?? '';

$sql = "SELECT 
            b.BookingID, b.VenueID, v.VenueName, c.FirstName, c.LastName, c.Phone,
            b.StartTime, b.EndTime, b.HoursBooked, b.TotalPrice,
            bs.StatusName AS BookingStatus, b.BookingStatusID,
            ps.StatusName AS PaymentStatus, b.PaymentStatusID,
            b.PaymentSlipPath
        FROM Tbl_Booking b
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Booking_Status bs ON b.BookingStatusID = bs.BookingStatusID
        JOIN Tbl_Payment_Status ps ON b.PaymentStatusID = ps.PaymentStatusID
        WHERE 1=1";

/* >>> ADD: จำกัดรายการเฉพาะประเภทสนามของ type_admin */
if ($IS_TYPE_ADMIN && $TYPE_ADMIN_VTID > 0) {
    $sql .= " AND v.VenueTypeID = " . (int)$TYPE_ADMIN_VTID;
}

if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $sql .= " AND (c.FirstName LIKE '%$search_safe%' OR c.LastName LIKE '%$search_safe%' OR v.VenueName LIKE '%$search_safe%' OR b.BookingID LIKE '%$search_safe%')";
}

if (!empty($filter_status)) {
    $sql .= " AND b.BookingStatusID = " . intval($filter_status);
}

if (!empty($filter_payment)) {
    $sql .= " AND b.PaymentStatusID = " . intval($filter_payment);
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(b.StartTime) = '" . $conn->real_escape_string($filter_date) . "'";
}

$sql .= " ORDER BY b.BookingID DESC";

$result = $conn->query($sql);

$bookings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// ดึงข้อมูลสถานะทั้งหมด
$booking_statuses = $conn->query("SELECT * FROM Tbl_Booking_Status")->fetch_all(MYSQLI_ASSOC);
$payment_statuses = $conn->query("SELECT * FROM Tbl_Payment_Status")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการรายการจอง | CY Arena</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    min-height: 100vh;
  }

  .glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }

  .status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
  }

  .status-pending { background: #fef3c7; color: #92400e; }
  .status-confirmed { background: #d1fae5; color: #065f46; }
  .status-cancelled { background: #fee2e2; color: #991b1b; }
  .status-completed { background: #dbeafe; color: #1e40af; }
  
  .payment-pending { background: #fef3c7; color: #92400e; }
  .payment-paid { background: #d1fae5; color: #065f46; }
  .payment-refunded { background: #e5e7eb; color: #374151; }

  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    animation: fadeIn 0.3s;
  }

  .modal-content {
    background: white;
    margin: 3% auto;
    padding: 0;
    border-radius: 20px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    animation: slideDown 0.4s;
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  .slip-modal-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .slip-modal-body {
    padding: 2rem;
    max-height: calc(90vh - 100px);
    overflow-y: auto.
  }

  .slip-image-container {
    text-align: center;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 12px;
    border: 2px solid #3b82f6;
  }

  .slip-image {
    max-width: 100%;
    height: auto;
    max-height: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    object-fit: contain;
  }

  .btn-view-slip {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
    margin-top: 6px;
    white-space: nowrap;
  }

  .btn-view-slip:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    background: linear-gradient(135deg, #059669, #047857);
  }

  .no-slip-text {
    color: #9ca3af;
    font-size: 0.75rem;
    font-style: italic;
    display: block;
    margin-top: 4px;
  }

  .close-modal {
    color: white;
    font-size: 2rem;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
  }

  .close-modal:hover {
    transform: rotate(90deg) scale(1.1);
  }

  table {
    font-size: 0.875rem;
  }

  table td {
    vertical-align: middle;
  }

  .payment-cell {
    min-width: 140px;
  }

  /* ปุ่มลบแบบใหม่ */
  .btn-delete {
    background: linear-gradient(135deg, #dc2626, #991b1b);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
    white-space: nowrap;
  }

  .btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5);
    background: linear-gradient(135deg, #991b1b, #7f1d1d);
  }

  .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
  }
</style>
</head>
<body>

<!-- Header -->
<header class="bg-white shadow-lg sticky top-0 z-50">
  <div class="container mx-auto px-4 py-3 flex justify-between items-center">
    <div class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent">
      CY Arena Admin
    </div>
    <div class="flex items-center space-x-4">
      <span class="text-sm font-medium text-gray-700">👤 <?php echo htmlspecialchars($userName); ?></span>
      <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-blue-500">
        <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="w-full h-full object-cover">
      </div>
      <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
        <i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ
      </a>
    </div>
  </div>
</header>

<!-- >>> ADD: แถบแจ้งโหมด type_admin -->
<?php if ($IS_TYPE_ADMIN): ?>
<div class="container mx-auto px-4 mt-4">
  <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-800 p-4 rounded-lg shadow-md">
    <i class="fas fa-shield-alt mr-2"></i>
    โหมด <strong>Type Admin</strong> — จัดการได้เฉพาะ <strong>ประเภทสนาม: <?php echo htmlspecialchars($TYPE_ADMIN_NAME ?: ('ID '.$TYPE_ADMIN_VTID)); ?></strong>
  </div>
</div>
<?php endif; ?>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="container mx-auto px-4 mt-4">
  <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md animate-pulse">
    <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="container mx-auto px-4 mt-4">
  <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
  </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<div class="container mx-auto px-4 py-8">
  <div class="glass-card p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-clipboard-list mr-2 text-blue-600"></i>รายการจองทั้งหมด
      </h2>
      <a href="dashboard.php" class="bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 text-white px-6 py-2 rounded-lg font-semibold shadow-lg transition">
        <i class="fas fa-arrow-left mr-2"></i>กลับหน้า Dashboard
      </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <input type="text" name="search" placeholder="🔍 ค้นหา: ลูกค้า / สนาม / เบอร์" 
             value="<?php echo htmlspecialchars($search); ?>"
             class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      
      <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="">สถานะการจอง: ทั้งหมด</option>
        <?php foreach ($booking_statuses as $status): ?>
          <option value="<?php echo $status['BookingStatusID']; ?>" 
                  <?php echo ($filter_status == $status['BookingStatusID']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($status['StatusName']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="payment" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="">สถานะชำระเงิน: ทั้งหมด</option>
        <?php foreach ($payment_statuses as $status): ?>
          <option value="<?php echo $status['PaymentStatusID']; ?>"
                  <?php echo ($filter_payment == $status['PaymentStatusID']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($status['StatusName']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>"
             class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
      
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
        <i class="fas fa-search mr-2"></i>ค้นหา
      </button>
      
      <a href="manage_bookings.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold text-center transition">
        <i class="fas fa-redo mr-2"></i>รีเซ็ต
      </a>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto">
      <?php if (empty($bookings)): ?>
        <div class="text-center py-12 text-gray-500">
          <i class="fas fa-inbox text-6xl mb-4 text-blue-300"></i>
          <p class="text-xl font-semibold">ไม่พบรายการจอง</p>
        </div>
      <?php else: ?>
        <table class="w-full text-sm">
          <thead class="bg-gradient-to-r from-blue-600 to-blue-500 text-white">
            <tr>
              <th class="py-3 px-4 text-left">รหัส</th>
              <th class="py-3 px-4 text-left">สนาม</th>
              <th class="py-3 px-4 text-left">ลูกค้า</th>
              <th class="py-3 px-4 text-left">เริ่ม</th>
              <th class="py-3 px-4 text-left">สิ้นสุด</th>
              <th class="py-3 px-4 text-left">ชั่วโมง</th>
              <th class="py-3 px-4 text-left">ราคา</th>
              <th class="py-3 px-4 text-left">สถานะ</th>
              <th class="py-3 px-4 text-left">การชำระเงิน</th>
              <th class="py-3 px-4 text-left">จัดการ</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <?php foreach ($bookings as $row): ?>
            <tr class="border-b hover:bg-blue-50 transition">
              <td class="py-3 px-4 font-bold text-blue-600">#<?php echo $row['BookingID']; ?></td>
              <td class="py-3 px-4">
                <a href="venue_detail.php?id=<?php echo $row['VenueID']; ?>" 
                   class="text-blue-600 hover:underline font-semibold">
                  <?php echo htmlspecialchars($row['VenueName']); ?>
                </a>
              </td>
              <td class="py-3 px-4">
                <div class="font-semibold"><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></div>
                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['Phone']); ?></div>
              </td>
              <td class="py-3 px-4 text-xs">
                <?php echo date("d/m/Y", strtotime($row['StartTime'])); ?><br>
                <span class="font-semibold"><?php echo date("H:i", strtotime($row['StartTime'])); ?></span>
              </td>
              <td class="py-3 px-4 text-xs">
                <?php echo date("d/m/Y", strtotime($row['EndTime'])); ?><br>
                <span class="font-semibold"><?php echo date("H:i", strtotime($row['EndTime'])); ?></span>
              </td>
              <td class="py-3 px-4"><?php echo $row['HoursBooked']; ?> ชม.</td>
              <td class="py-3 px-4 font-bold text-green-600">฿<?php echo number_format($row['TotalPrice'], 2); ?></td>
              <td class="py-3 px-4">
                <?php
                $status_class = match($row['BookingStatusID']) {
                  1 => 'status-pending',
                  2 => 'status-confirmed',
                  3 => 'status-cancelled',
                  4 => 'status-completed',
                  default => 'status-pending'
                };
                ?>
                <span class="status-badge <?php echo $status_class; ?>">
                  <?php echo htmlspecialchars($row['BookingStatus']); ?>
                </span>
              </td>
              <td class="py-3 px-4 payment-cell">
                <?php
                $payment_class = match($row['PaymentStatusID']) {
                  1 => 'payment-pending',
                  2 => 'payment-paid',
                  3 => 'payment-refunded',
                  default => 'payment-pending'
                };
                ?>
                <span class="status-badge <?php echo $payment_class; ?>">
                  <?php echo htmlspecialchars($row['PaymentStatus']); ?>
                </span>
                
                <?php if (!empty($row['PaymentSlipPath'])): ?>
                  <button type="button"
                          onclick="viewSlip('<?php echo addslashes($row['PaymentSlipPath']); ?>', <?php echo $row['BookingID']; ?>, '<?php echo addslashes($row['VenueName']); ?>', <?php echo $row['TotalPrice']; ?>)" 
                          class="btn-view-slip">
                    <i class="fas fa-receipt"></i> ดูสลิป
                  </button>
                <?php else: ?>
                  <span class="no-slip-text">
                    <i class="fas fa-times-circle"></i> ยังไม่แนบสลิป
                  </span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4">
                <div class="action-buttons">
                  <button type="button" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                          class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-semibold">
                    <i class="fas fa-edit"></i> แก้ไข
                  </button>
                  <a href="?cancel=<?php echo $row['BookingID']; ?>" 
                     onclick="return confirm('❌ ยกเลิกการจอง #<?php echo $row['BookingID']; ?> หรือไม่?\n\n⚠️ การจองจะถูกเปลี่ยนสถานะเป็น \"ยกเลิก\"')"
                     class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-xs font-semibold inline-block">
                    <i class="fas fa-times"></i> ยกเลิก
                  </a>
                  <button type="button"
                          onclick="confirmDelete(<?php echo $row['BookingID']; ?>)"
                          class="btn-delete">
                    <i class="fas fa-trash-alt"></i> ลบ
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Edit Status Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="slip-modal-header">
      <h3 class="text-xl font-bold"><i class="fas fa-edit mr-2"></i>แก้ไขสถานะการจอง</h3>
      <span class="close-modal" onclick="closeEditModal()">&times;</span>
    </div>
    <div class="slip-modal-body">
      <form method="POST" id="editForm">
        <input type="hidden" name="booking_id" id="edit_booking_id">
        <input type="hidden" name="update_status" value="1">
        
        <div class="mb-4">
          <label class="block font-semibold mb-2 text-gray-700">Booking ID</label>
          <input type="text" id="display_booking_id" disabled 
                 class="w-full px-4 py-2 border rounded-lg bg-gray-100">
        </div>

        <div class="mb-4">
          <label class="block font-semibold mb-2 text-gray-700">สถานะการจอง</label>
          <select name="booking_status" id="edit_booking_status" 
                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            <?php foreach ($booking_statuses as $status): ?>
              <option value="<?php echo $status['BookingStatusID']; ?>">
                <?php echo htmlspecialchars($status['StatusName']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-4">
          <label class="block font-semibold mb-2 text-gray-700">สถานะการชำระเงิน</label>
          <select name="payment_status" id="edit_payment_status"
                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            <?php foreach ($payment_statuses as $status): ?>
              <option value="<?php echo $status['PaymentStatusID']; ?>">
                <?php echo htmlspecialchars($status['StatusName']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" 
                class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold shadow-lg transition">
          <i class="fas fa-check-circle mr-2"></i>บันทึกการเปลี่ยนแปลง
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Payment Slip Modal -->
<div id="slipModal" class="modal">
  <div class="modal-content">
    <div class="slip-modal-header">
      <h3 class="text-xl font-bold">
        <i class="fas fa-receipt mr-2"></i>สลิปการโอนเงิน
      </h3>
      <span class="close-modal" onclick="closeSlipModal()">&times;</span>
    </div>
    <div class="slip-modal-body">
      <!-- Booking Info -->
      <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-lg">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-gray-600">Booking ID:</span>
            <strong class="text-blue-700 ml-2">#<span id="slip_booking_id">-</span></strong>
          </div>
          <div>
            <span class="text-gray-600">สนาม:</span>
            <strong class="text-gray-800 ml-2" id="slip_venue_name">-</strong>
          </div>
          <div class="col-span-2">
            <span class="text-gray-600">ยอดชำระ:</span>
            <strong class="text-green-600 ml-2 text-lg">฿<span id="slip_amount">0.00</span></strong>
          </div>
        </div>
      </div>

      <!-- Slip Image -->
      <div class="slip-image-container">
        <img id="slipImage" src="" alt="Payment Slip" class="slip-image">
      </div>
      
      <div class="mt-4 text-center">
        <p class="text-sm text-gray-600 mb-3">
          <i class="fas fa-info-circle mr-1"></i>
          กรุณาตรวจสอบยอดเงินและข้อมูลการโอนให้ถูกต้อง
        </p>
        <button type="button" onclick="closeSlipModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
          <i class="fas fa-check mr-2"></i>ตรวจสอบแล้ว
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Edit Modal Functions
function openEditModal(booking) {
  console.log('Opening edit modal for:', booking);
  document.getElementById('edit_booking_id').value = booking.BookingID;
  document.getElementById('display_booking_id').value = '#' + booking.BookingID;
  document.getElementById('edit_booking_status').value = booking.BookingStatusID;
  document.getElementById('edit_payment_status').value = booking.PaymentStatusID;
  document.getElementById('editModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Slip Modal Functions
function viewSlip(slipPath, bookingId, venueName, amount) {
  console.log('Opening slip modal:', {slipPath, bookingId, venueName, amount});
  
  // Set booking info
  document.getElementById('slip_booking_id').textContent = bookingId;
  document.getElementById('slip_venue_name').textContent = venueName;
  document.getElementById('slip_amount').textContent = parseFloat(amount).toFixed(2);
  
  // Set slip image
  document.getElementById('slipImage').src = slipPath;
  
  // Show modal
  document.getElementById('slipModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeSlipModal() {
  document.getElementById('slipModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// ฟังก์ชันยืนยันการลบ (ใหม่)
function confirmDelete(bookingId) {
  const message = `🗑️ ต้องการลบการจองนี้ออกจากระบบหรือไม่?\n\n` +
                  `📌 Booking ID: #${bookingId}\n\n` +
                  `⚠️ คำเตือน:\n` +
                  `• ข้อมูลจะถูกลบออกจากระบบถาวร\n` +
                  `• ไม่สามารถกู้คืนข้อมูลได้\n` +
                  `• หากต้องการเก็บประวัติ ให้ใช้ "ยกเลิก" แทน\n\n` +
                  `❓ ยืนยันการลบ?`;
  if (confirm(message)) {
    window.location.href = `?delete=${bookingId}`;
  }
}

// Close modals when clicking outside
window.onclick = function(event) {
  const editModal = document.getElementById('editModal');
  const slipModal = document.getElementById('slipModal');
  if (event.target == editModal) closeEditModal();
  if (event.target == slipModal) closeSlipModal();
}

// Prevent event bubbling on modal content
document.addEventListener('DOMContentLoaded', function() {
  const modalContents = document.querySelectorAll('.modal-content');
  modalContents.forEach(function(content) {
    content.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  });
});
</script>

</body>
</html>

<?php
/* >>> ADD (สำคัญ): คืนค่า role เดิม ถ้าสวมบท employee ชั่วคราวไว้ในหน้านี้ <<< */
if (isset($_SESSION['role_backup_for_type_admin']) && $_SESSION['role_backup_for_type_admin'] === 'type_admin') {
    $_SESSION['role'] = 'type_admin';
    unset($_SESSION['role_backup_for_type_admin']);
}
/* <<< END ADD */
?>
