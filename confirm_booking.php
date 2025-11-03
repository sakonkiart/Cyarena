<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db_connect.php';

/* --------------------------- UI Helper (Modern Blue Design) --------------------------- */
function render_booking_result_ui(array $opt = []) {
    $status        = $opt['status']        ?? 'error';
    $title         = $opt['title']         ?? (($status==='success') ? 'จองสำเร็จ' : 'จองไม่สำเร็จ');
    $message       = $opt['message']       ?? '';
    $conflicts     = $opt['conflicts']     ?? [];
    $back_url      = $opt['back_url']      ?? 'booking.php';
    $calendar_url  = $opt['calendar_url']  ?? null;
    $dashboard_url = $opt['dashboard_url'] ?? 'dashboard.php';
    $my_bookings_url = $opt['my_bookings_url'] ?? 'my_bookings.php';
    ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>สถานะการจอง</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root {
      --blue-50: #eff6ff;
      --blue-100: #dbeafe;
      --blue-200: #bfdbfe;
      --blue-300: #93c5fd;
      --blue-400: #60a5fa;
      --blue-500: #3b82f6;
      --blue-600: #2563eb;
      --blue-700: #1d4ed8;
      --blue-800: #1e40af;
      --blue-900: #1e3a8a;
      --success-500: #10b981;
      --success-600: #059669;
      --error-500: #ef4444;
      --error-600: #dc2626;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --gray-900: #111827;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: "Kanit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(135deg, #637ADDFF 0%, #0812A1FF 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Animated background particles */
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: 
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
      animation: float 20s ease-in-out infinite;
      pointer-events: none;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }
    
    .container-result {
      max-width: 600px;
      width: 100%;
      position: relative;
      z-index: 1;
    }
    
    .result-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 48px;
      box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.1);
      animation: slideUp 0.5s ease-out;
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .icon-container {
      width: 80px;
      height: 80px;
      margin: 0 auto 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      animation: scaleIn 0.5s ease-out 0.2s both;
    }
    
    @keyframes scaleIn {
      from {
        transform: scale(0);
        opacity: 0;
      }
      to {
        transform: scale(1);
        opacity: 1;
      }
    }
    
    .icon-success {
      background: linear-gradient(135deg, var(--success-500), var(--success-600));
      box-shadow: 
        0 10px 40px rgba(16, 185, 129, 0.4),
        0 0 0 8px rgba(16, 185, 129, 0.1);
    }
    
    .icon-error {
      background: linear-gradient(135deg, var(--error-500), var(--error-600));
      box-shadow: 
        0 10px 40px rgba(239, 68, 68, 0.4),
        0 0 0 8px rgba(239, 68, 68, 0.1);
    }
    
    .icon-container svg {
      width: 40px;
      height: 40px;
      color: white;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    
    .result-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--gray-900);
      text-align: center;
      margin-bottom: 12px;
      letter-spacing: -0.5px;
    }
    
    .result-message {
      font-size: 16px;
      color: var(--gray-700);
      text-align: center;
      line-height: 1.6;
      margin-bottom: 32px;
      white-space: pre-line;
    }
    
    .conflicts-section {
      background: var(--gray-50);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 32px;
    }
    
    .conflicts-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--gray-900);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .conflicts-title::before {
      content: '⚠️';
      font-size: 20px;
    }
    
    .table-modern {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .table-modern thead th {
      background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
      color: white;
      padding: 14px 16px;
      font-weight: 600;
      font-size: 14px;
      text-align: left;
      border: none;
    }
    
    .table-modern thead th:first-child {
      border-top-left-radius: 12px;
    }
    
    .table-modern thead th:last-child {
      border-top-right-radius: 12px;
    }
    
    .table-modern tbody td {
      padding: 14px 16px;
      border-bottom: 1px solid var(--gray-100);
      color: var(--gray-700);
      font-size: 14px;
    }
    
    .table-modern tbody tr:last-child td {
      border-bottom: none;
    }
    
    .table-modern tbody tr:hover {
      background: var(--blue-50);
    }
    
    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .btn-modern {
      padding: 14px 28px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 16px;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      position: relative;
      overflow: hidden;
    }
    
    .btn-modern::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    
    .btn-modern:hover::before {
      width: 300px;
      height: 300px;
    }
    
    .btn-modern span {
      position: relative;
      z-index: 1;
    }
    
    .btn-primary-modern {
      background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
      color: white;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
    }
    
    .btn-primary-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
    }

    .btn-success-modern {
      background: linear-gradient(135deg, var(--success-500), var(--success-600));
      color: white;
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
    }
    
    .btn-success-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
    }
    
    .btn-outline-modern {
      background: white;
      color: var(--blue-600);
      border: 2px solid var(--blue-200);
    }
    
    .btn-outline-modern:hover {
      background: var(--blue-50);
      border-color: var(--blue-300);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    }
    
    .btn-light-modern {
      background: var(--gray-100);
      color: var(--gray-700);
    }
    
    .btn-light-modern:hover {
      background: var(--gray-200);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .result-card {
        padding: 32px 24px;
      }
      
      .result-title {
        font-size: 24px;
      }
      
      .icon-container {
        width: 64px;
        height: 64px;
      }
      
      .icon-container svg {
        width: 32px;
        height: 32px;
      }
      
      .table-modern {
        font-size: 13px;
      }
      
      .table-modern thead th,
      .table-modern tbody td {
        padding: 10px 12px;
      }
    }
    
    /* Celebration animation for success */
    @keyframes celebrate {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    .icon-success {
      animation: scaleIn 0.5s ease-out 0.2s both, celebrate 2s ease-in-out 0.7s infinite;
    }
  </style>
</head>
<body>
  <div class="container-result">
    <div class="result-card">
      <div class="icon-container <?= ($status==='success' ? 'icon-success' : 'icon-error') ?>">
        <?php if ($status==='success'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        <?php endif; ?>
      </div>
      
      <h1 class="result-title"><?= htmlspecialchars($title) ?></h1>
      <p class="result-message"><?= htmlspecialchars($message) ?></p>
      
      <?php if (!empty($conflicts) && is_array($conflicts)): ?>
        <div class="conflicts-section">
          <div class="conflicts-title">รายละเอียดเวลาที่ชน</div>
          <div class="table-responsive">
            <table class="table-modern">
              <thead>
                <tr>
                  <th>วันที่</th>
                  <th>เริ่ม</th>
                  <th>สิ้นสุด</th>
                  <th>สนาม</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($conflicts as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['start'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['end'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['venue'] ?? '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
      
      <div class="btn-group">
        <?php if ($status === 'success' && $my_bookings_url): ?>
          <a href="<?= htmlspecialchars($my_bookings_url) ?>" class="btn-modern btn-success-modern">
            <span>💳 ไปหน้าชำระเงิน</span>
          </a>
        <?php endif; ?>
        
        <?php if ($back_url): ?>
          <a href="<?= htmlspecialchars($back_url) ?>" class="btn-modern btn-primary-modern">
            <span>🎯 ลองเลือกเวลาใหม่</span>
          </a>
        <?php endif; ?>
        
        <?php if ($calendar_url): ?>
          <a href="<?= htmlspecialchars($calendar_url) ?>" class="btn-modern btn-outline-modern">
            <span>📅 ดูปฏิทินสนาม</span>
          </a>
        <?php endif; ?>
        
        <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn-modern btn-light-modern">
          <span>🏠 ไปหน้า Dashboard</span>
        </a>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    exit;
}
/* ------------------------- End UI Helper ------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_booking_result_ui([
        'status'  => 'error',
        'title'   => 'Access Denied',
        'message' => 'ไม่มีข้อมูลที่ส่งมา',
        'back_url'=> 'booking.php'
    ]);
}

$tz = new DateTimeZone('Asia/Bangkok');

/* ---------- รับค่าฟอร์ม (ยืดหยุ่นชื่อ field) ---------- */
function pick($arr, $keys, $default=null){
  foreach ($keys as $k){ if (isset($arr[$k]) && $arr[$k] !== '') return $arr[$k]; }
  return $default;
}

$venue_id     = (int) pick($_POST, ['venue_id','VenueID'], 0);
$booking_date = trim(pick($_POST, ['booking_date','date','BookingDate'], ''));
$start_time   = trim(pick($_POST, ['start_time','time_start','StartTime'], ''));
$hours        = (float) pick($_POST, ['hours','duration','HoursBooked'], 0);
$total_price  = (float) pick($_POST, ['total_price','TotalPrice','price'], 0);
$customer_id  = (int) $_SESSION['user_id'];
$promotion_id = pick($_POST, ['promotion_id','PromotionID'], null);
$promotion_id = ($promotion_id === null || $promotion_id === '') ? null : (int)$promotion_id;
$promo_code   = trim(pick($_POST, ['promo_code','PromoCode'], ''));

$back = 'booking.php' . ($venue_id ? ('?venue_id='.$venue_id) : '');

/* เช็คความครบ */
$missing = [];
if ($venue_id <= 0)       $missing[] = 'รหัสสนาม';
if ($booking_date === '') $missing[] = 'วันที่จอง';
if ($start_time === '')   $missing[] = 'เวลาเริ่ม';
if ($hours <= 0)          $missing[] = 'จำนวนชั่วโมง';
if ($missing){
    render_booking_result_ui([
        'status'=>'error',
        'title'=>'ข้อมูลไม่ครบ',
        'message'=>"กรุณากลับไปกรอกให้ครบ:\n• ".implode("\n• ", $missing),
        'back_url'=>$back
    ]);
}

/* ============================ สนาม + เวลาทำการ ============================ */
$st = $conn->prepare("
  SELECT VenueName, TimeOpen, TimeClose, PricePerHour
  FROM Tbl_Venue
  WHERE VenueID = ?
  LIMIT 1
");
$st->bind_param("i", $venue_id);
$st->execute();
$venueRow = $st->get_result()->fetch_assoc();
$st->close();

if (!$venueRow) {
    $back = 'booking.php' . ($venue_id ? ('?venue_id=' . $venue_id) : '');

    render_booking_result_ui([
        'status'  => 'error',
        'title'   => 'ไม่พบข้อมูลสนาม',
        'message' => 'กรุณาลองใหม่อีกครั้งหรือเลือกสนามอื่น',
        'back_url'=> $back,
    ]);
}


/* ===================== แปลงวัน/เวลา + ไม่ย้อนหลัง ===================== */
$selDate = DateTime::createFromFormat('!Y-m-d', $booking_date, $tz);
if (!$selDate) {
    render_booking_result_ui([
        'status'=>'error','title'=>'รูปแบบวันที่ไม่ถูกต้อง',
        'message'=>'โปรดระบุวันที่ในรูปแบบ YYYY-MM-DD',
        'back_url'=>$back
    ]);
}

$startDT = DateTime::createFromFormat('Y-m-d H:i', $selDate->format('Y-m-d').' '.$start_time, $tz);
if (!$startDT) {
    render_booking_result_ui([
        'status'=>'error','title'=>'เวลาเริ่มไม่ถูกต้อง',
        'message'=>'โปรดเลือกเวลาเริ่มใหม่',
        'back_url'=>$back
    ]);
}

$now   = new DateTime('now', $tz);
$today = new DateTime('today', $tz);
if ($startDT < $today) {
    render_booking_result_ui([
        'status'=>'error','title'=>'วันที่ย้อนหลัง',
        'message'=>'วันที่จองต้องไม่น้อยกว่าวันนี้',
        'back_url'=>$back
    ]);
}
if ($startDT->format('Y-m-d') === $now->format('Y-m-d')) {
    $minStart = (clone $now)->modify('+30 minutes');
    if ($startDT < $minStart) {
        render_booking_result_ui([
            'status'=>'error','title'=>'เวลายังเร็วเกินไป',
            'message'=>'เวลาจองวันนี้ต้องเริ่มล่วงหน้าอย่างน้อย 30 นาที',
            'back_url'=>$back
        ]);
    }
}

/* เวลาสิ้นสุด > เริ่ม */
$endDT = clone $startDT;
$endDT->modify("+{$hours} hours");
if ($endDT <= $startDT) {
    render_booking_result_ui([
        'status'=>'error','title'=>'เวลาสิ้นสุดไม่ถูกต้อง',
        'message'=>'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม',
        'back_url'=>$back
    ]);
}

/* ================== อยู่ในช่วงเวลาเปิด—ปิดสนาม ================== */
$openDT  = DateTime::createFromFormat('Y-m-d H:i:s', $selDate->format('Y-m-d').' '.$venueRow['TimeOpen'],  $tz);
$closeDT = DateTime::createFromFormat('Y-m-d H:i:s', $selDate->format('Y-m-d').' '.$venueRow['TimeClose'], $tz);
if (!$openDT || !$closeDT) {
    render_booking_result_ui([
        'status'=>'error','title'=>'เวลาเปิด—ปิดไม่ถูกต้อง',
        'message'=>'ข้อมูลเวลาเปิด—ปิดของสนามผิดรูปแบบ',
        'back_url'=>$back
    ]);
}
if ($startDT < $openDT || $endDT > $closeDT) {
    render_booking_result_ui([
        'status'=>'error','title'=>'นอกเวลาให้บริการ',
        'message'=>'เวลาที่เลือกอยู่นอกช่วงเวลาเปิดให้บริการของสนาม',
        'back_url'=>$back,
        'calendar_url'=>'bookings_calendar.php?venue_id='.$venue_id
    ]);
}

/* ============ กันชนสนามเดียวกัน ============ */
$endStr   = $endDT->format('Y-m-d H:i:s');
$startStr = $startDT->format('Y-m-d H:i:s');

$conflictRows = [];
$chkVenue = $conn->prepare("
  SELECT StartTime, EndTime
  FROM Tbl_Booking
  WHERE VenueID = ?
    AND BookingStatusID NOT IN (3,4)
    AND NOT ( ? <= StartTime OR ? >= EndTime )
  ORDER BY StartTime
  LIMIT 5
");
$chkVenue->bind_param("iss", $venue_id, $endStr, $startStr);
$chkVenue->execute();
$r = $chkVenue->get_result();
$venueOverlap = $r->num_rows > 0;
if ($venueOverlap) {
    while ($row = $r->fetch_assoc()) {
        $stO = new DateTime($row['StartTime'], $tz);
        $enO = new DateTime($row['EndTime'],   $tz);
        $conflictRows[] = [
            'date'  => $stO->format('Y-m-d'),
            'start' => $stO->format('H:i'),
            'end'   => $enO->format('H:i'),
            'venue' => $venueRow['VenueName'] ?? 'สนามนี้'
        ];
    }
}
$chkVenue->close();

if ($venueOverlap) {
    render_booking_result_ui([
        'status'   => 'error',
        'title'    => 'ช่วงเวลานี้ถูกจองแล้ว',
        'message'  => "ไม่อนุญาตให้จองทับเวลา กรุณาเลือกช่วงเวลาใหม่หรือตรวจสอบปฏิทินสนาม",
        'conflicts'=> $conflictRows,
        'back_url' => $back,
        'calendar_url' => 'bookings_calendar.php?venue_id='.$venue_id
    ]);
}

/* ============ กันชนของลูกค้า (สนามเดียวกัน) ============ */
$conflictRows = [];
$chkCust = $conn->prepare("
  SELECT b.StartTime, b.EndTime, v.VenueName
  FROM Tbl_Booking b
  JOIN Tbl_Venue v ON v.VenueID = b.VenueID
  WHERE b.CustomerID = ?
    AND b.VenueID = ?
    AND b.BookingStatusID NOT IN (3,4)
    AND NOT ( ? <= b.StartTime OR ? >= b.EndTime )
  ORDER BY b.StartTime
  LIMIT 5
");
$chkCust->bind_param("iiss", $customer_id, $venue_id, $endStr, $startStr);
$chkCust->execute();
$rc = $chkCust->get_result();
$custOverlap = $rc->num_rows > 0;
if ($custOverlap) {
    while ($row = $rc->fetch_assoc()) {
        $stO = new DateTime($row['StartTime'], $tz);
        $enO = new DateTime($row['EndTime'],   $tz);
        $conflictRows[] = [
            'date'  => $stO->format('Y-m-d'),
            'start' => $stO->format('H:i'),
            'end'   => $enO->format('H:i'),
            'venue' => $row['VenueName'] ?? '-'
        ];
    }
}
$chkCust->close();

if ($custOverlap) {
    render_booking_result_ui([
        'status'   => 'error',
        'title'    => 'คุณมีการจองช่วงเวลานี้อยู่แล้ว (สนามเดียวกัน)',
        'message'  => 'ไม่สามารถจองทับเวลากับการจองเดิมในสนามเดียวกันได้',
        'conflicts'=> $conflictRows,
        'back_url' => $back,
        'calendar_url' => 'bookings_calendar.php?venue_id='.$venue_id
    ]);
}

/* ============================ ราคา + โปรโมชัน ============================ */
if ($total_price <= 0) {
    $total_price = ((float)$venueRow['PricePerHour']) * $hours;
}

if (!$promotion_id && $promo_code !== '') {
    $q = $conn->prepare("
      SELECT PromotionID
      FROM Tbl_Promotion
      WHERE PromoCode = ?
        AND (StartDate IS NULL OR NOW() >= StartDate)
        AND (EndDate   IS NULL OR NOW() <= EndDate)
      LIMIT 1
    ");
    $q->bind_param("s", $promo_code);
    $q->execute();
    $rowPromo = $q->get_result()->fetch_assoc();
    $promotion_id = $rowPromo['PromotionID'] ?? null;
    $q->close();
}

$netPrice = $total_price;
if ($promotion_id) {
    $pq = $conn->prepare("SELECT * FROM Tbl_Promotion WHERE PromotionID = ? LIMIT 1");
    $pq->bind_param("i", $promotion_id);
    if ($pq->execute()) {
        $prow = $pq->get_result()->fetch_assoc();
        if ($prow) {
            $discPercent = isset($prow['DiscountPercent']) ? (float)$prow['DiscountPercent'] : null;
            $discAmount  = isset($prow['DiscountAmount'])  ? (float)$prow['DiscountAmount']  : null;

            if ($discPercent !== null && $discPercent > 0) {
                $netPrice = max(0, $total_price * (1 - ($discPercent/100)));
            } elseif ($discAmount !== null && $discAmount > 0) {
                $netPrice = max(0, $total_price - $discAmount);
            }
        }
    }
    $pq->close();
}

/* ============================ INSERT ============================ */
$booking_status_id = 1; // รอยืนยัน
$payment_status_id = 1; // รอชำระเงิน

$ins = $conn->prepare("
  INSERT INTO Tbl_Booking
  (CustomerID, VenueID, BookingStatusID, PaymentStatusID, StartTime, EndTime, HoursBooked, TotalPrice, NetPrice, PromotionID)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$startDB = $startDT->format('Y-m-d H:i:s');
$endDB   = $endDT->format('Y-m-d H:i:s');

$ins->bind_param(
  "iiiissdddi",
  $customer_id,
  $venue_id,
  $booking_status_id,
  $payment_status_id,
  $startDB,
  $endDB,
  $hours,
  $total_price,
  $netPrice,
  $promotion_id
);

// เก็บไว้เป็น fallback ด้วย (ถ้ายังไม่ได้เซ็ต)
$_SESSION['last_venue_id'] = (int)$venue_id;

// ลิงก์สำหรับย้อนกลับ/ดูปฏิทิน
$back_url      = 'booking.php?' . http_build_query(['venue_id' => (int)$venue_id]);
$calendar_url  = 'bookings_calendar.php?' . http_build_query(['venue_id' => (int)$venue_id]);
$my_bookings_url = 'my_bookings.php';

if ($ins->execute()) {
    $venueName = $venueRow['VenueName'] ?? '-';
    $dateText  = (new DateTime($startDB, $tz))->format('d/m/Y');
    $timeStart = (new DateTime($startDB, $tz))->format('H:i');
    $timeEnd   = (new DateTime($endDB, $tz))->format('H:i');

    render_booking_result_ui([
        'status'       => 'success',
        'title'        => 'จองสำเร็จแล้ว 🎉',
        'message'      => "สนาม: {$venueName}\nวันที่: {$dateText}\nเวลา: {$timeStart} - {$timeEnd}\nยอดชำระ: ฿" . number_format($netPrice, 2),
        'back_url'     => $back_url,
        'calendar_url' => $calendar_url,
        'my_bookings_url' => $my_bookings_url,
    ]);
} else {
    render_booking_result_ui([
        'status'   => 'error',
        'title'    => 'บันทึกการจองไม่สำเร็จ',
        'message'  => 'เกิดข้อผิดพลาดทางเทคนิค กรุณาลองใหม่อีกครั้ง',
        'back_url' => $back_url,
    ]);
}

$ins->close();
$conn->close();
