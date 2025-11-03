<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

$customer_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'ลูกค้า';
$role = $_SESSION['role'] ?? 'customer';

// Avatar logic
$avatarPath  = $_SESSION['avatar_path'] ?? '';
$avatarLocal = 'assets/avatar-default.png';

function _exists_rel($rel){ return is_file(__DIR__ . '/' . ltrim($rel, '/')); }

if ($avatarPath && _exists_rel($avatarPath)) {
  $avatarSrc = $avatarPath;
} elseif (_exists_rel($avatarLocal)) {
  $avatarSrc = $avatarLocal;
} else {
  $avatarSrc = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#2563eb"/><text x="50%" y="54%" text-anchor="middle" font-size="48" font-family="Arial" fill="#fff">⚽</text></svg>'
  );
}

// Handle booking cancellation
if (isset($_GET['cancel_booking']) && is_numeric($_GET['cancel_booking'])) {
    $booking_id = intval($_GET['cancel_booking']);
    
    $check_sql = "SELECT BookingID, BookingStatusID, StartTime FROM Tbl_Booking 
                  WHERE BookingID = ? AND CustomerID = ? AND BookingStatusID NOT IN (3, 4)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $customer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $booking = $check_result->fetch_assoc();
        $start_time = new DateTime($booking['StartTime']);
        $now = new DateTime();
        
        $diff_hours = ($start_time->getTimestamp() - $now->getTimestamp()) / 3600;
        
        if ($diff_hours >= 1) {
            $cancel_sql = "UPDATE Tbl_Booking SET BookingStatusID = 3 WHERE BookingID = ?";
            $cancel_stmt = $conn->prepare($cancel_sql);
            $cancel_stmt->bind_param("i", $booking_id);
            
            if ($cancel_stmt->execute()) {
                $_SESSION['success_message'] = "✅ ยกเลิกการจอง #$booking_id เรียบร้อยแล้ว";
            } else {
                $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการยกเลิกการจอง";
            }
            $cancel_stmt->close();
        } else {
            $_SESSION['error_message'] = "❌ ไม่สามารถยกเลิกได้ เนื่องจากเหลือเวลาน้อยกว่า 1 ชั่วโมง";
        }
    } else {
        $_SESSION['error_message'] = "❌ ไม่พบการจองหรือไม่สามารถยกเลิกได้";
    }
    $check_stmt->close();
    
    header("Location: my_bookings.php");
    exit;
}

// Handle payment confirmation with slip upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $booking_id = intval($_POST['booking_id']);
    $slip_path = null;
    $has_error = false;
    
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        
        $uploadWeb = 'uploads/payment_slips';
        $uploadAbs = __DIR__ . '/' . $uploadWeb;

        if (!is_dir($uploadAbs)) {
            mkdir($uploadAbs, 0775, true);
        }
        if (!is_writable($uploadAbs)) {
            @chmod($uploadAbs, 0775); 
        }

        $file_extension = strtolower(pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($file_extension, $allowed_extensions, true)) {
            $new_filename = 'slip_' . (int)$booking_id . '_' . time() . '.' . $file_extension;
            $abs_path = $uploadAbs . '/' . $new_filename;
            $slip_path = $uploadWeb . '/' . $new_filename;
            
            if (!move_uploaded_file($_FILES['payment_slip']['tmp_name'], $abs_path)) {
                $_SESSION['error_message'] = "❌ ไม่สามารถอัปโหลดสลิปได้ (เขียนไฟล์ไม่สำเร็จ)";
                $has_error = true;
            }
        } else {
            $_SESSION['error_message'] = "❌ รองรับเฉพาะไฟล์ JPG, JPEG, PNG, WEBP หรือ PDF เท่านั้น";
            $has_error = true;
        }
    } 
    
    if (!$has_error) {
        
        $update_sql = "UPDATE Tbl_Booking SET PaymentStatusID = 2" . 
                      ($slip_path ? ", PaymentSlipPath = ?" : "") . 
                      " WHERE BookingID = ? AND CustomerID = ?";
        
        $update_stmt = $conn->prepare($update_sql);

        if ($slip_path) {
            $update_stmt->bind_param("sii", $slip_path, $booking_id, $customer_id);
        } else {
            $update_stmt->bind_param("ii", $booking_id, $customer_id);
        }
        
        if ($update_stmt->execute()) {
            $message_suffix = $slip_path ? " (พร้อมสลิป)" : " (รอตรวจสอบการโอน)";
            $_SESSION['success_message'] = "✅ ยืนยันการชำระเงินสำเร็จ! Booking ID: #$booking_id" . $message_suffix;
        } else {
            $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
    
    header("Location: my_bookings.php");
    exit;
}

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ตรวจสอบว่ามีการส่ง auto_pay มาหรือไม่
$auto_pay_booking_id = isset($_GET['auto_pay']) ? intval($_GET['auto_pay']) : null;
$auto_pay_amount = isset($_GET['amount']) ? floatval($_GET['amount']) : null;

// ดึงข้อมูลการจองทั้งหมดของลูกค้า
$sql = "SELECT 
            b.BookingID, v.VenueName, v.VenueID, b.StartTime, b.EndTime, 
            b.HoursBooked, b.TotalPrice, b.BookingStatusID, bs.StatusName AS BookingStatus, 
            ps.StatusName AS PaymentStatus, b.PaymentStatusID
        FROM Tbl_Booking b
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID
        JOIN Tbl_Booking_Status bs ON b.BookingStatusID = bs.BookingStatusID
        JOIN Tbl_Payment_Status ps ON b.PaymentStatusID = ps.PaymentStatusID
        WHERE b.CustomerID = ?
        ORDER BY b.BookingID DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $sql_review = "SELECT ReviewID FROM Tbl_Review WHERE BookingID = ?";
        $stmt_review = $conn->prepare($sql_review);
        $stmt_review->bind_param("i", $row['BookingID']);
        $stmt_review->execute();
        $review_result = $stmt_review->get_result();
        $row['ReviewDone'] = $review_result->num_rows > 0;
        $stmt_review->close();

        $start_time = new DateTime($row['StartTime']);
        $now = new DateTime();
        $diff_hours = ($start_time->getTimestamp() - $now->getTimestamp()) / 3600;
        $row['CanCancel'] = ($diff_hours >= 1 && $row['BookingStatusID'] != 3 && $row['BookingStatusID'] != 4);

        $bookings[] = $row;
    }
}

function get_status_class($status_name) {
    $class_map = [
        'รอยืนยัน' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'ยืนยันแล้ว' => 'bg-green-100 text-green-800 border-green-300',
        'ยกเลิกแล้ว' => 'bg-red-100 text-red-800 border-red-300',
        'เข้าใช้บริการแล้ว' => 'bg-blue-100 text-blue-800 border-blue-300',
        'รอชำระ' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'รอชำระเงิน' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'ชำระแล้ว' => 'bg-teal-100 text-teal-800 border-teal-300',
        'คืนเงินแล้ว' => 'bg-gray-100 text-gray-600 border-gray-300',
    ];
    return $class_map[$status_name] ?? 'bg-gray-100 text-gray-800 border-gray-300';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ประวัติการจองของฉัน | CY Arena</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background-color: #f4f7f6;
    color: #1f2937;
  }
  .container {
    max-width: 1200px;
  }

  ._header_nav a {
    transition: color 0.2s, background-color 0.2s;
  }
  ._header_nav a:hover {
    color: #2563eb;
    background-color: #eff6ff;
    border-radius: 6px;
  }

  .status-badge {
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 9999px;
      display: inline-flex;
      align-items: center;
      font-size: 0.75rem;
      line-height: 1; 
      border-width: 1px;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  }

  .review-link-btn {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      font-weight: 600;
      color: #059669;
      background-color: #e0f2f1;
      border-radius: 8px;
      transition: background-color 0.2s;
      font-size: 0.875rem;
  }
  .review-link-btn:hover {
      background-color: #ccfbf1;
  }

  .pay-btn {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      font-weight: 600;
      color: white;
      background: linear-gradient(135deg, #2563eb, #3b82f6);
      border-radius: 8px;
      transition: all 0.2s;
      font-size: 0.875rem;
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
      border: none;
      cursor: pointer;
  }
  .pay-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
  }

  .cancel-btn {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      font-weight: 600;
      color: white;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      border-radius: 8px;
      transition: all 0.2s;
      font-size: 0.875rem;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
      text-decoration: none;
  }
  .cancel-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
      background: linear-gradient(135deg, #dc2626, #b91c1c);
  }

  .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.7);
      animation: fadeIn 0.3s;
  }

  .modal-content {
      background: white;
      margin: 2% auto;
      padding: 2rem;
      border-radius: 20px;
      max-width: 450px;
      width: 90%;
      max-height: 95vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideDown 0.4s;
      position: relative;
  }

  @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
  }

  @keyframes slideDown {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
  }

  .close-modal {
      position: absolute;
      top: 1rem;
      right: 1rem;
      font-size: 2rem;
      color: #9ca3af;
      cursor: pointer;
      transition: color 0.2s;
  }

  .close-modal:hover {
      color: #ef4444;
  }

  .qr-container {
      background: linear-gradient(135deg, #2563eb, #3b82f6);
      padding: 1.5rem;
      border-radius: 16px;
      text-align: center;
      margin: 1rem 0;
  }

  .qr-code {
      background: white;
      padding: 0.75rem;
      border-radius: 12px;
      display: inline-block;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
  }

  .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
  }

  @media (max-width: 768px) {
    .booking-table-container {
        display: none;
    }
    .booking-card-grid {
        display: grid;
    }
    ._header_nav {
      display: none;
      flex-direction: column;
      width: 100%;
      position: absolute;
      top: 64px;
      left: 0;
      background-color: white;
      border-top: 1px solid #f3f4f6;
      padding: 1rem 0;
      z-index: 10;
    }
    ._header_nav a {
      padding: 0.75rem 1.5rem;
      width: 100%;
      border-radius: 0;
    }
    .burger-menu {
      display: block;
    }
  }

  @media (min-width: 769px) {
    .booking-table-container {
        display: block;
    }
    .booking-card-grid {
        display: none;
    }
  }
</style>
</head>
<body>

<header class="bg-white shadow-md sticky top-0 z-20 border-b-3 border-blue-500">
  <div class="container mx-auto flex justify-between items-center p-4">
    <div class="text-2xl font-bold text-blue-600">CY Arena</div>
    
    <nav class="_header_nav hidden md:flex space-x-2">
      <a href="dashboard.php" class="px-3 py-2 text-gray-700 hover:text-blue-600">หน้าหลัก</a>
      <a href="#my-bookings-section" class="px-3 py-2 text-blue-600 font-semibold bg-blue-50 rounded-lg">การจองของฉัน</a>
      <a href="my_reviews.php" class="px-3 py-2 text-gray-700 hover:text-blue-600">รีวิวของฉัน</a>
      <?php if ($role === 'admin'): ?>
      <a href="admin.php" class="px-3 py-2 text-gray-700 hover:text-blue-600">จัดการระบบ</a>
      <?php endif; ?>
    </nav>
    
    <div class="flex items-center space-x-3">
      <span class="text-sm font-medium hidden sm:inline text-gray-800">สวัสดี, <?php echo htmlspecialchars($userName); ?></span>
      <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden border-2 border-blue-500">
        <img src="<?php echo $avatarSrc; ?>" alt="User Avatar" class="w-full h-full object-cover">
      </div>

    <!-- DESKTOP TABLE VIEW -->
    <div class="booking-table-container hidden md:block bg-white rounded-xl shadow-lg border border-gray-200">
      <table class="booking-table w-full text-sm text-left text-gray-700">
        <thead class="text-xs text-gray-900 uppercase bg-blue-50 border-b border-blue-200">
          <tr>
            <th scope="col" class="py-3 px-6 rounded-tl-xl">ID</th>
            <th scope="col" class="py-3 px-6">สนาม</th>
            <th scope="col" class="py-3 px-6">วัน/เวลา</th>
            <th scope="col" class="py-3 px-6">ชั่วโมง</th>
            <th scope="col" class="py-3 px-6">สถานะชำระ</th>
            <th scope="col" class="py-3 px-6">รวม (฿)</th>
            <th scope="col" class="py-3 px-6">สถานะจอง</th>
            <th scope="col" class="py-3 px-6 rounded-tr-xl">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $row): ?>
          <tr class="bg-white border-b hover:bg-blue-50/50 transition duration-150">
            <td class="py-4 px-6 font-medium text-gray-900"><?php echo $row['BookingID']; ?></td>
            <td class="py-4 px-6 font-semibold text-gray-800">
                <a href="venue_detail.php?id=<?php echo $row['VenueID']; ?>" class="text-blue-600 hover:text-blue-700 hover:underline">
                    <?php echo htmlspecialchars($row['VenueName']); ?>
                </a>
            </td>
            <td class="py-4 px-6 text-gray-600 text-xs">
                <?php echo date("d/m/Y", strtotime($row['StartTime'])); ?><br>
                <span class="font-medium text-sm"><?php echo date("H:i", strtotime($row['StartTime'])); ?> - <?php echo date("H:i", strtotime($row['EndTime'])); ?></span>
            </td>
            <td class="py-4 px-6 text-gray-600"><?php echo $row['HoursBooked']; ?> ชม.</td>
            
            <td class="py-4 px-6">
                <?php $payment_status = htmlspecialchars($row['PaymentStatus']); ?>
                <span class="status-badge <?php echo get_status_class($payment_status); ?>">
                    <?php echo $payment_status; ?>
                </span>
            </td>

            <td class="py-4 px-6 text-base font-bold text-blue-600">฿<?php echo number_format($row['TotalPrice'], 2); ?></td>
            
            <td class="py-4 px-6">
              <?php $booking_status = htmlspecialchars($row['BookingStatus']); ?>
              <span class="status-badge <?php echo get_status_class($booking_status); ?>">
                  <?php echo $booking_status; ?>
              </span>
            </td>
            
            <td class="py-4 px-6">
              <div class="action-buttons">
              <?php
                if ($row['BookingStatusID'] == 3) {
                    echo '<span class="text-red-600 font-semibold text-xs"><i class="fas fa-ban mr-1"></i> ยกเลิกแล้ว</span>';
                }
                elseif ($row['BookingStatusID'] == 4) {
                    if ($row['BookingStatus'] == 'เข้าใช้บริการแล้ว') {
                        if ($row['ReviewDone']) {
                            echo '<span class="text-blue-600 font-semibold flex items-center"><i class="fas fa-check-circle mr-1"></i> รีวิวแล้ว</span>';
                        } else {
                            echo '<a href="review.php?booking_id=' . $row['BookingID'] . '" class="review-link-btn">
                                    <i class="fas fa-star mr-1"></i> รีวิว
                                  </a>';
                        }
                    }
                }
                else {
                    if ($row['PaymentStatus'] == 'รอชำระ' || $row['PaymentStatus'] == 'รอชำระเงิน') {
                        echo '<button onclick="openPaymentModal(' . $row['BookingID'] . ', ' . $row['TotalPrice'] . ')" class="pay-btn">
                                <i class="fas fa-qrcode mr-1"></i> ชำระ
                              </button>';
                    }
                    
                    if ($row['BookingStatus'] == 'เข้าใช้บริการแล้ว') {
                        if ($row['ReviewDone']) {
                            echo '<span class="text-blue-600 font-semibold flex items-center"><i class="fas fa-check-circle mr-1"></i> รีวิวแล้ว</span>';
                        } else {
                            echo '<a href="review.php?booking_id=' . $row['BookingID'] . '" class="review-link-btn">
                                    <i class="fas fa-star mr-1"></i> รีวิว
                                  </a>';
                        }
                    }
                    
                    if ($row['CanCancel']) {
                        echo '<a href="?cancel_booking=' . $row['BookingID'] . '" 
                                 onclick="return confirm(\'❌ ยืนยันการยกเลิกการจอง #' . $row['BookingID'] . '?\\n\\n⚠️ การยกเลิกจะไม่สามารถกู้คืนได้\')" 
                                 class="cancel-btn">
                                <i class="fas fa-times-circle mr-1"></i> ยกเลิก
                              </a>';
                    }
                    
                    if (!$row['CanCancel'] && 
                        ($row['PaymentStatus'] != 'รอชำระ' && $row['PaymentStatus'] != 'รอชำระเงิน') && 
                        $row['BookingStatus'] != 'เข้าใช้บริการแล้ว') {
                        echo '<span class="text-gray-400 text-xs">-</span>';
                    }
                }
              ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php endif; ?>
  </div>
</section>

<!-- Mobile Card View - Add before closing section -->
<div class="booking-card-grid grid grid-cols-1 gap-4 sm:grid-cols-2 md:hidden container mx-auto px-4 pb-8">
  <?php if (!empty($bookings)): ?>
    <?php foreach ($bookings as $row): ?>
    <div class="bg-white rounded-xl shadow-md p-4 border border-blue-100 hover:shadow-lg transition duration-300">
      <div class="flex justify-between items-start mb-2 border-b pb-2">
          <div>
              <a href="venue_detail.php?id=<?php echo $row['VenueID']; ?>" class="text-lg font-bold text-blue-600 hover:text-blue-700 hover:underline">
                  <?php echo htmlspecialchars($row['VenueName']); ?>
              </a>
              <p class="text-xs text-gray-500 mt-0.5">Booking ID: #<?php echo $row['BookingID']; ?></p>
          </div>
          <?php $booking_status = htmlspecialchars($row['BookingStatus']); ?>
          <span class="status-badge <?php echo get_status_class($booking_status); ?> ml-2">
              <?php echo $booking_status; ?>
          </span>
      </div>

      <div class="space-y-2 text-sm">
          <div class="flex items-center text-gray-700">
              <i class="fas fa-calendar-alt w-5 text-blue-500"></i>
              <span class="font-medium ml-2">วันที่:</span>
              <span class="ml-auto"><?php echo date("d/m/Y", strtotime($row['StartTime'])); ?></span>
          </div>
          <div class="flex items-center text-gray-700">
              <i class="fas fa-clock w-5 text-blue-500"></i>
              <span class="font-medium ml-2">เวลา:</span>
              <span class="ml-auto"><?php echo date("H:i", strtotime($row['StartTime'])); ?> - <?php echo date("H:i", strtotime($row['EndTime'])); ?></span>
          </div>
          <div class="flex items-center text-gray-700">
              <i class="fas fa-hourglass-half w-5 text-blue-500"></i>
              <span class="font-medium ml-2">ชั่วโมง:</span>
              <span class="ml-auto"><?php echo $row['HoursBooked']; ?> ชม.</span>
          </div>
      </div>
      
      <hr class="my-3 border-gray-100">

      <div class="space-y-2 text-sm">
          <div class="flex justify-between items-center font-medium">
              <div class="text-gray-600 flex items-center"><i class="fas fa-wallet w-5 text-gray-500"></i> สถานะชำระ:</div>
              <?php $payment_status = htmlspecialchars($row['PaymentStatus']); ?>
              <span class="status-badge <?php echo get_status_class($payment_status); ?> ml-2">
                  <?php echo $payment_status; ?>
              </span>
          </div>
          <div class="flex justify-between items-center text-lg font-extrabold text-blue-600 pt-1">
              <div class="flex items-center"><i class="fas fa-money-bill-wave w-5 text-blue-600"></i> รวม:</div>
              <span>฿<?php echo number_format($row['TotalPrice'], 2); ?></span>
          </div>
      </div>

      <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
          <?php
              if ($row['BookingStatusID'] == 3) {
                  echo '<span class="text-red-600 font-semibold flex items-center justify-center text-base">
                          <i class="fas fa-ban mr-2"></i> การจองถูกยกเลิกแล้ว
                        </span>';
              }
              elseif ($row['BookingStatusID'] == 4) {
                  if ($row['BookingStatus'] == 'เข้าใช้บริการแล้ว') {
                      if ($row['ReviewDone']) {
                          echo '<span class="text-blue-600 font-semibold flex items-center justify-center text-base">
                                  <i class="fas fa-check-circle mr-2"></i> รีวิวเรียบร้อย
                                </span>';
                      } else {
                          echo '<a href="review.php?booking_id=' . $row['BookingID'] . '" class="review-link-btn w-full justify-center">
                                  <i class="fas fa-star mr-2"></i> ให้รีวิวสนาม
                                </a>';
                      }
                  }
              }
              else {
                  if ($row['PaymentStatus'] == 'รอชำระ' || $row['PaymentStatus'] == 'รอชำระเงิน') {
                      echo '<button onclick="openPaymentModal(' . $row['BookingID'] . ', ' . $row['TotalPrice'] . ')" class="pay-btn w-full justify-center">
                              <i class="fas fa-qrcode mr-2"></i> ชำระเงิน
                            </button>';
                  }
                  
                  if ($row['BookingStatus'] == 'เข้าใช้บริการแล้ว') {
                      if ($row['ReviewDone']) {
                          echo '<span class="text-blue-600 font-semibold flex items-center justify-center text-base">
                                  <i class="fas fa-check-circle mr-2"></i> รีวิวเรียบร้อย
                                </span>';
                      } else {
                          echo '<a href="review.php?booking_id=' . $row['BookingID'] . '" class="review-link-btn w-full justify-center">
                                  <i class="fas fa-star mr-2"></i> ให้รีวิวสนาม
                                </a>';
                      }
                  }
                  
                  if ($row['CanCancel']) {
                      echo '<a href="?cancel_booking=' . $row['BookingID'] . '" 
                               onclick="return confirm(\'❌ ยืนยันการยกเลิกการจอง #' . $row['BookingID'] . '?\\n\\n⚠️ การยกเลิกจะไม่สามารถกู้คืนได้\\n💡 ทำได้ก่อนเวลาเริ่ม 1 ชั่วโมง\')" 
                               class="cancel-btn w-full justify-center">
                              <i class="fas fa-times-circle mr-2"></i> ยกเลิกการจอง
                            </a>';
                  }
                  
                  if (!$row['CanCancel'] && 
                      ($row['PaymentStatus'] != 'รอชำระ' && $row['PaymentStatus'] != 'รอชำระเงิน') && 
                      $row['BookingStatus'] != 'เข้าใช้บริการแล้ว') {
                      echo '<span class="text-gray-400 text-center block text-sm">- ยังไม่สามารถดำเนินการได้ -</span>';
                  }
              }
          ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- QR Code Payment Modal -->
<div id="paymentModal" class="modal">
  <div class="modal-content">
    <span class="close-modal" onclick="closePaymentModal()">&times;</span>
    
    <div class="text-center mb-3">
      <i class="fas fa-mobile-alt text-blue-600 text-3xl mb-2"></i>
      <h3 class="text-xl font-bold text-gray-900">ชำระเงินผ่าน QR Code</h3>
      <p class="text-gray-600 mt-1 text-sm">สแกน QR Code ด้วยแอปธนาคารของคุณ</p>
    </div>

    <div class="qr-container">
      <div class="qr-code">
        <img id="qrCodeImage" src="" alt="PromptPay QR Code" class="w-52 h-52 mx-auto">
      </div>
      <p class="text-white font-bold text-lg mt-3">ยอดชำระ: ฿<span id="paymentAmount">0.00</span></p>
      <p class="text-white text-xs mt-1">Booking ID: #<span id="bookingIdDisplay">0</span></p>
      <p class="text-white text-xs mt-2 opacity-90 leading-relaxed">
        <i class="fas fa-info-circle mr-1"></i>
        โอนเงินผ่าน PromptPay แล้วแนบสลิปด้านล่าง
      </p>
    </div>

    <form method="POST" id="paymentForm" enctype="multipart/form-data">
      <input type="hidden" name="booking_id" id="bookingIdInput">
      <input type="hidden" name="confirm_payment" value="1">
      
      <div class="bg-gray-50 p-3 rounded-lg mb-3 border-2 border-dashed border-gray-300">
        <label class="block text-xs font-bold text-gray-700 mb-2">
          <i class="fas fa-paperclip mr-1"></i>แนบสลิปการโอนเงิน (ไม่บังคับ)
        </label>
        <input type="file" 
               name="payment_slip" 
               id="payment_slip" 
               accept="image/jpeg,image/png,image/jpg,application/pdf"
               class="block w-full text-xs text-gray-500
                      file:mr-3 file:py-2 file:px-3
                      file:rounded-lg file:border-0
                      file:text-xs file:font-semibold
                      file:bg-blue-50 file:text-blue-700
                      hover:file:bg-blue-100
                      cursor-pointer">
        <p class="text-xs text-gray-500 mt-1">
          <i class="fas fa-info-circle mr-1"></i>รองรับ JPG, PNG, PDF (ไม่เกิน 5MB)
        </p>
        
        <div id="slipPreview" class="mt-2 hidden">
          <p class="text-xs text-green-600 font-semibold mb-1">
            <i class="fas fa-check-circle mr-1"></i>เลือกไฟล์แล้ว:
          </p>
          <div id="previewContent" class="flex items-center gap-2 bg-white p-2 rounded border border-green-300">
            <i class="fas fa-file-image text-green-600 text-lg"></i>
            <span id="fileName" class="text-xs text-gray-700 font-medium flex-1 truncate"></span>
            <button type="button" onclick="clearSlip()" class="text-red-500 hover:text-red-700 text-sm">
              <i class="fas fa-times-circle"></i>
            </button>
          </div>
        </div>
      </div>
      
      <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-300 shadow-lg text-sm">
        <i class="fas fa-check-circle mr-2"></i> ยืนยันว่าโอนเงินแล้ว
      </button>
    </form>

    <p class="text-xs text-gray-500 text-center mt-3">
      <i class="fas fa-shield-alt mr-1"></i> พนักงานจะตรวจสอบยอดและอนุมัติภายใน 5-10 นาที
    </p>
  </div>
</div>

<script>
function toggleMenu() {
  const menu = document.getElementById('mobile-menu');
  menu.style.display = menu.style.display === 'flex' ? 'none' : 'flex';
}

function openPaymentModal(bookingId, amount) {
  document.getElementById('bookingIdInput').value = bookingId;
  document.getElementById('bookingIdDisplay').textContent = bookingId;
  document.getElementById('paymentAmount').textContent = amount.toFixed(2);
  
  document.getElementById('qrCodeImage').src = 'images/promptpay-qr.png';
  
  document.getElementById('payment_slip').value = '';
  document.getElementById('slipPreview').classList.add('hidden');
  
  const modal = document.getElementById('paymentModal');
  modal.style.display = 'block';
  document.body.style.overflow = 'hidden';
  
  setTimeout(() => {
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
      modalContent.scrollTop = 0;
    }
  }, 100);
}

function closePaymentModal() {
  document.getElementById('paymentModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// เปิด Modal อัตโนมัติถ้ามีการส่ง parameter auto_pay มา
document.addEventListener('DOMContentLoaded', function() {
  <?php if ($auto_pay_booking_id && $auto_pay_amount): ?>
    <?php 
    $verify_sql = "SELECT BookingID FROM Tbl_Booking 
                   WHERE BookingID = ? AND CustomerID = ? AND PaymentStatusID = 1";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $auto_pay_booking_id, $customer_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    if ($verify_result->num_rows > 0): 
    ?>
      setTimeout(function() {
        openPaymentModal(<?= $auto_pay_booking_id ?>, <?= $auto_pay_amount ?>);
        
        const bookingsSection = document.getElementById('my-bookings-section');
        if (bookingsSection) {
          bookingsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }, 500);
    <?php 
    endif;
    $verify_stmt->close();
    ?>
  <?php endif; ?>
  
  const slipInput = document.getElementById('payment_slip');
  
  if (slipInput) {
    slipInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      const preview = document.getElementById('slipPreview');
      const fileName = document.getElementById('fileName');
      
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('❌ ไฟล์มีขนาดใหญ่เกิน 5MB กรุณาเลือกไฟล์ใหม่');
          e.target.value = '';
          preview.classList.add('hidden');
          return;
        }
        
        fileName.textContent = file.name;
        preview.classList.remove('hidden');
      } else {
        preview.classList.add('hidden');
      }
    });
  }
});

function clearSlip() {
  document.getElementById('payment_slip').value = '';
  document.getElementById('slipPreview').classList.add('hidden');
}

window.onclick = function(event) {
  const modal = document.getElementById('paymentModal');
  if (event.target == modal) {
    closePaymentModal();
  }
}
</script>

<?php $conn->close(); ?>

</body>
</html>
      <a href="logout.php" class="text-red-500 hover:text-red-700 text-sm font-medium ml-4 hidden md:inline">
        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
      </a>
      <button class="burger-menu md:hidden text-gray-600 hover:text-blue-600 text-xl" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<nav id="mobile-menu" class="_header_nav md:hidden" style="display: none;">
  <a href="dashboard.php">หน้าหลัก</a>
  <a href="#my-bookings-section">การจองของฉัน</a>
  <a href="my_reviews.php">รีวิวของฉัน</a>
  <?php if ($role === 'admin'): ?>
  <a href="admin.php">จัดการระบบ</a>
  <?php endif; ?>
  <a href="logout.php" class="text-red-500">ออกจากระบบ</a>
</nav>

<?php if ($success_message): ?>
<div class="container mx-auto px-4 mt-4">
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md flex items-center animate-pulse">
        <i class="fas fa-check-circle text-2xl mr-3"></i>
        <p class="font-semibold"><?php echo htmlspecialchars($success_message); ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="container mx-auto px-4 mt-4">
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md flex items-center">
        <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
        <p class="font-semibold"><?php echo htmlspecialchars($error_message); ?></p>
    </div>
</div>
<?php endif; ?>

<section id="my-bookings-section" class="py-8 md:py-16">
  <div class="container mx-auto px-4">
    <h2 class="text-3xl md:text-4xl font-bold text-center text-blue-700 mb-8 md:mb-12">ประวัติการจองของฉัน</h2>
    
    <?php if (empty($bookings)): ?>
        <div class="bg-white rounded-xl shadow-lg p-10 text-center border-t-4 border-blue-500/50">
            <i class="fas fa-calendar-alt text-blue-500 text-4xl mb-4"></i>
            <p class="text-xl font-semibold text-gray-700">คุณยังไม่มีการจองในขณะนี้</p>
            <p class="text-gray-500 mt-2">ไปค้นหาสนามและจองวันเวลาที่ต้องการได้เลย</p>
            <a href="dashboard.php#venues" class="mt-5 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300 shadow-md">
                ค้นหาสนาม <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    <?php else: ?>
    
    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-800 p-4 mb-6 rounded-lg shadow-sm">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-lg"></i>
            <p class="text-sm font-medium">สามารถ <span class="font-bold">ชำระเงิน</span> เมื่อสถานะ 'รอชำระ' | <span class="font-bold">ให้รีวิว</span> เมื่อ 'เข้าใช้บริการแล้ว' | <span class="font-bold text-red-600">ยกเลิก</span> ได้ก่อนเวลาเริ่ม 1 ชม.</p>
        </div>
    </div>
