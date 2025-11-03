<?php
// bookings_calendar.php - Modern Blue Design
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
require 'db_connect.php';

$tz = new DateTimeZone('Asia/Bangkok');

// รับเดือน/ปี จาก query (ค่าเริ่มต้น = เดือนปัจจุบัน)
$year  = (isset($_GET['y']) && ctype_digit($_GET['y'])) ? (int)$_GET['y'] : (int)(new DateTime('now', $tz))->format('Y');
$month = (isset($_GET['m']) && ctype_digit($_GET['m'])) ? (int)$_GET['m'] : (int)(new DateTime('now', $tz))->format('n');

$firstDay = new DateTime("$year-$month-01 00:00:00", $tz);
$lastDay  = (clone $firstDay)->modify('last day of this month')->setTime(23,59,59);

// ดึงการจองของเดือนนี้
$sql = "
  SELECT 
    b.BookingID,
    b.CustomerID,
    b.StartTime,
    b.EndTime,
    v.VenueName,
    vt.TypeName   AS VenueTypeName,
    c.FirstName   AS CustomerName
  FROM Tbl_Booking b
  JOIN Tbl_Venue        v  ON v.VenueID = b.VenueID
  JOIN Tbl_Venue_Type   vt ON vt.VenueTypeID = v.VenueTypeID
  LEFT JOIN Tbl_Customer c ON c.CustomerID = b.CustomerID
  WHERE b.BookingStatusID NOT IN (3,4)
    AND b.StartTime BETWEEN ? AND ?
  ORDER BY b.StartTime ASC
";
$stmt = $conn->prepare($sql);
$startStr = $firstDay->format('Y-m-d H:i:s');
$endStr   = $lastDay->format('Y-m-d H:i:s');
$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();

$bookingsByDate = [];
while ($row = $res->fetch_assoc()) {
  $row['CustomerName'] = trim($row['CustomerName'] ?? '');
  if ($row['CustomerName'] === '') {
    $row['CustomerName'] = 'ลูกค้า #' . (int)$row['CustomerID'];
  }
  $key = (new DateTime($row['StartTime'], $tz))->format('Y-m-d');
  $bookingsByDate[$key][] = $row;
}
$stmt->close();
$conn->close();

$daysInMonth  = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('w');
$todayKey     = (new DateTime('today', $tz))->format('Y-m-d');

// ปุ่มเลื่อนเดือน
$prev = (clone $firstDay)->modify('-1 month');
$next = (clone $firstDay)->modify('+1 month');

// เตรียม payload ให้ JS
$payload = [];
foreach ($bookingsByDate as $d => $rows) {
  foreach ($rows as $r) {
    $payload[$d][] = [
      'venue'     => $r['VenueName'],
      'type'      => $r['VenueTypeName'] ?? 'อื่น ๆ',
      'start'     => (new DateTime($r['StartTime'], $tz))->format('H:i'),
      'end'       => (new DateTime($r['EndTime'], $tz))->format('H:i'),
      'cust'      => (int)$r['CustomerID'],
      'cust_name' => $r['CustomerName'],
    ];
  }
}

// แปลงชื่อเดือนเป็นภาษาไทย
$thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$monthName = $thaiMonths[$month - 1];
$yearThai = $year + 543;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ปฏิทินการจอง - CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Kanit', sans-serif;
  background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
  min-height: 100vh;
  padding: 20px;
  position: relative;
  overflow-x: hidden;
}

/* Animated background elements */
body::before {
  content: '';
  position: fixed;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: 
    radial-gradient(circle at 20% 30%, rgba(96, 165, 250, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 80% 70%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 50% 50%, rgba(30, 58, 138, 0.1) 0%, transparent 50%);
  animation: float 20s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

@keyframes float {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  50% { transform: translate(30px, -30px) rotate(5deg); }
}

.page {
  max-width: 1400px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
  animation: slideDown 0.6s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.btn-back {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  color: #1e40af;
  padding: 12px 24px;
  border-radius: 12px;
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
}

.btn-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
  background: white;
}

.page-title {
  color: white;
  font-size: 32px;
  font-weight: 700;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  gap: 12px;
}

.calendar-wrap {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(20px);
  border-radius: 24px;
  padding: 32px;
  box-shadow: 
    0 20px 60px rgba(0, 0, 0, 0.3),
    0 0 0 1px rgba(255, 255, 255, 0.3);
  animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.cal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 3px solid #dbeafe;
}

.cal-header h3 {
  font-size: 28px;
  font-weight: 700;
  background: linear-gradient(135deg, #1e40af, #3b82f6, #60a5fa);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.cal-nav {
  color: #2563eb;
  text-decoration: none;
  font-weight: 600;
  padding: 10px 20px;
  border-radius: 10px;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(37, 99, 235, 0.05);
}

.cal-nav:hover {
  background: rgba(37, 99, 235, 0.1);
  transform: translateX(0);
  color: #1e40af;
}

.cal-nav:first-child:hover {
  transform: translateX(-3px);
}

.cal-nav:last-child:hover {
  transform: translateX(3px);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 12px;
}

.cal-dayname {
  font-weight: 700;
  color: #1e3a8a;
  text-align: center;
  padding: 14px;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-radius: 12px;
  font-size: 14px;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.cal-cell {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border: 2px solid #e0e7ff;
  border-radius: 16px;
  min-height: 140px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.cal-cell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #3b82f6, transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.cal-cell:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.2);
  border-color: #3b82f6;
}

.cal-cell:hover::before {
  opacity: 1;
}

.cal-cell.today {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-color: #3b82f6;
  box-shadow: 
    0 0 0 3px rgba(59, 130, 246, 0.2),
    0 8px 24px rgba(37, 99, 235, 0.25);
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 8px 24px rgba(37, 99, 235, 0.25); }
  50% { box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.3), 0 12px 32px rgba(37, 99, 235, 0.35); }
}

.cal-cell.empty {
  background: transparent;
  border: none;
  pointer-events: none;
}

.cal-date {
  font-weight: 700;
  font-size: 20px;
  color: #1e293b;
  margin-bottom: 8px;
}

.cal-cell.today .cal-date {
  color: #1e40af;
  font-size: 24px;
}

.badge-book {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 700;
  display: inline-block;
  align-self: flex-start;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
  animation: scaleIn 0.3s ease-out;
}

@keyframes scaleIn {
  from { transform: scale(0); }
  to { transform: scale(1); }
}

.cal-sample {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  flex: 1;
  overflow: hidden;
}

.cal-sample li {
  font-size: 13px;
  color: #475569;
  margin-bottom: 6px;
  padding: 6px 8px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border-left: 3px solid #3b82f6;
}

.cal-sample .time {
  color: #2563eb;
  font-weight: 600;
  margin-left: 6px;
}

.cal-sample .cust {
  color: #0ea5e9;
  font-weight: 500;
  margin-left: 8px;
  font-size: 12px;
}

.cal-sample .more {
  color: #94a3b8;
  font-style: italic;
  text-align: center;
  border-left: none;
  background: rgba(148, 163, 184, 0.1);
}

.cal-none {
  margin-top: 16px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
  font-style: italic;
}

.view-day {
  margin-top: 12px;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border: none;
  border-radius: 10px;
  padding: 8px 14px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
  position: relative;
  overflow: hidden;
}

.view-day::before {
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

.view-day:hover::before {
  width: 200px;
  height: 200px;
}

.view-day:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}

.view-day span {
  position: relative;
  z-index: 1;
}

/* Modal */
.cal-modal {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(8px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.cal-modal-card {
  width: min(900px, 95vw);
  background: white;
  border-radius: 24px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  animation: slideUp 0.4s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(50px) scale(0.9);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.cal-modal-head {
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: white;
  padding: 24px 28px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cal-modal-head h4 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
}

.cal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 24px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.cal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg) scale(1.1);
}

.cal-modal-body {
  padding: 28px;
  max-height: 70vh;
  overflow-y: auto;
}

.group-card {
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 16px;
  transition: all 0.3s ease;
}

.group-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.15);
}

.group-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border: 0;
  padding: 16px 20px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.group-head:hover {
  background: linear-gradient(135deg, #e0f2fe, #dbeafe);
}

.group-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  box-shadow: 0 2px 8px currentColor;
}

.gtitle {
  font-weight: 700;
  font-size: 16px;
  color: #0f172a;
}

.group-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.gcount {
  padding: 4px 12px;
  border-radius: 20px;
  color: white;
  font-weight: 700;
  font-size: 13px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.chev {
  color: #64748b;
  transition: transform 0.3s ease;
}

.group-head.collapsed .chev {
  transform: rotate(-90deg);
}

.group-body {
  padding: 16px 20px;
  border-top: 2px solid #e5e7eb;
  background: white;
}

.cal-book {
  background: linear-gradient(135deg, #f8fafc, #ffffff);
  border: 2px solid #e0e7ff;
  border-left: 4px solid #3b82f6;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  transition: all 0.3s ease;
}

.cal-book:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.15);
  border-left-color: #2563eb;
}

.cal-book h5 {
  margin: 0 0 10px;
  color: #0f172a;
  font-weight: 600;
  font-size: 16px;
}

.cal-book .meta {
  font-size: 14px;
  color: #475569;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.cal-book .meta::before {
  content: '•';
  color: #3b82f6;
  font-weight: bold;
}

.empty-note {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border: 2px dashed #cbd5e1;
  padding: 32px;
  border-radius: 16px;
  color: #64748b;
  text-align: center;
  font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
  .calendar-grid {
    gap: 8px;
  }
  
  .cal-cell {
    min-height: 100px;
    padding: 10px;
  }
  
  .cal-date {
    font-size: 16px;
  }
  
  .page-title {
    font-size: 24px;
  }
  
  .calendar-wrap {
    padding: 20px;
  }
}

/* Scrollbar */
.cal-modal-body::-webkit-scrollbar {
  width: 8px;
}

.cal-modal-body::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

.cal-modal-body::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  border-radius: 10px;
}

.cal-modal-body::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #2563eb, #1e40af);
}
</style>
</head>
<body>
<div class="page">
  <div class="header-bar">
    <a href="dashboard.php" class="btn-back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
      กลับหน้า Dashboard
    </a>
    <h2 class="page-title">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v10zM7 11h2v2H7v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2z"/>
      </svg>
      ปฏิทินการจอง
    </h2>
    <div></div>
  </div>

  <section class="calendar-wrap">
    <div class="cal-header">
      <a class="cal-nav" href="?y=<?= $prev->format('Y') ?>&m=<?= $prev->format('n') ?>">
        ‹ เดือนก่อน
      </a>
      <h3><?= $monthName ?> <?= $yearThai ?></h3>
      <a class="cal-nav" href="?y=<?= $next->format('Y') ?>&m=<?= $next->format('n') ?>">
        เดือนถัดไป ›
      </a>
    </div>

    <div class="calendar-grid">
      <div class="cal-dayname">อา</div>
      <div class="cal-dayname">จ</div>
      <div class="cal-dayname">อ</div>
      <div class="cal-dayname">พ</div>
      <div class="cal-dayname">พฤ</div>
      <div class="cal-dayname">ศ</div>
      <div class="cal-dayname">ส</div>

      <?php for ($i=0; $i<$startWeekday; $i++): ?>
        <div class="cal-cell empty"></div>
      <?php endfor; ?>

      <?php for ($d=1; $d <= $daysInMonth; $d++):
        $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $isToday = ($dateKey === $todayKey);
        $has     = !empty($bookingsByDate[$dateKey]);
        $count   = $has ? count($bookingsByDate[$dateKey]) : 0;
      ?>
      <div class="cal-cell<?= $isToday ? ' today':'' ?>">
        <div class="cal-date"><?= $d ?></div>
        <?php if ($has): ?>
          <span class="badge-book">📌 <?= $count ?> รายการ</span>
          <ul class="cal-sample">
            <?php $i=0; foreach ($bookingsByDate[$dateKey] as $bk): if ($i>=2){ ?>
              <li class="more">...ดูทั้งหมด</li>
              <?php break; } 
              $custName = htmlspecialchars(trim($bk['CustomerName']) ?: ('ลูกค้า #'.(int)$bk['CustomerID']));
            ?>
              <li>
                <strong><?= htmlspecialchars($bk['VenueName']) ?></strong>
                <span class="time">
                  <?= (new DateTime($bk['StartTime'], $tz))->format('H:i') ?>
                </span>
                <span class="cust"><?= $custName ?></span>
              </li>
            <?php $i++; endforeach; ?>
          </ul>
          <button class="view-day" data-date="<?= $dateKey ?>">
            <span>ดูรายละเอียด</span>
          </button>
        <?php else: ?>
          <div class="cal-none">ไม่มีการจอง</div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </section>
</div>

<!-- Modal -->
<div id="dayModal" class="cal-modal">
  <div class="cal-modal-card">
    <div class="cal-modal-head">
      <h4 id="modalTitle">การจอง</h4>
      <button id="modalClose" class="cal-close">×</button>
    </div>
    <div id="modalBody" class="cal-modal-body"></div>
  </div>
</div>

<script>
(function(){
  const payload = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>;

  const modal   = document.getElementById('dayModal');
  const mTitle  = document.getElementById('modalTitle');
  const mBody   = document.getElementById('modalBody');
  const mClose  = document.getElementById('modalClose');

  // สีประจำประเภท
  const typeColors = {
    'ฟุตบอล':   '#2563eb',
    'ฟุตซอล':   '#16a34a',
    'บาสเกตบอล':'#7c3aed',
    'แบดมินตัน':'#f59e0b',
    'ปิงปอง':   '#ef4444'
  };
  const defaultColor = '#64748b';

  function openDay(dateStr){
    const items = payload[dateStr] || [];
    
    // แปลงวันที่เป็นภาษาไทย
    const [y, m, d] = dateStr.split('-');
    const thaiMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    const monthThai = thaiMonths[parseInt(m) - 1];
    const yearThai = parseInt(y) + 543;
    
    mTitle.textContent = `📅 การจองวันที่ ${parseInt(d)} ${monthThai} ${yearThai}`;

    if (!items.length) {
      mBody.innerHTML = '<div class="empty-note">📭 ไม่มีการจองในวันนี้</div>';
      modal.style.display = 'flex';
      return;
    }

    // จัดกลุ่มตามประเภทสนาม
    const groups = {};
    items.forEach(it => {
      const t = it.type || 'อื่น ๆ';
      if (!groups[t]) groups[t] = [];
      groups[t].push(it);
    });

    // เรนเดอร์กลุ่มแบบ accordion
    let html = '';
    Object.keys(groups).sort().forEach((typeName, gi) => {
      const color = typeColors[typeName] || defaultColor;
      const list  = groups[typeName];
      const gid   = 'grp_' + gi + '_' + dateStr.replaceAll('-','');

      html += `
        <div class="group-card">
          <button class="group-head" data-target="#${gid}">
            <div class="group-left">
              <span class="dot" style="background:${color}"></span>
              <span class="gtitle">${escapeHtml(typeName)}</span>
            </div>
            <div class="group-right">
              <span class="gcount" style="background:${color}">${list.length}</span>
              <svg class="chev" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </div>
          </button>
          <div class="group-body" id="${gid}">
      `;

      list.forEach((it, idx) => {
        html += `
          <div class="cal-book">
            <h5>⚽ ${idx+1}. ${escapeHtml(it.venue)}</h5>
            <div class="meta">🕐 ${it.start} - ${it.end}</div>
            <div class="meta">👤 ${escapeHtml(it.cust_name || ('ลูกค้า #' + (it.cust ?? '')))}</div>
          </div>
        `;
      });

      html += `</div></div>`;
    });

    mBody.innerHTML = html;
    
    // ติด toggle ให้หัวข้อ
    mBody.querySelectorAll('.group-head').forEach(btn=>{
      const sel = btn.getAttribute('data-target');
      const body = mBody.querySelector(sel);
      
      // เปิดเริ่มต้น
      body.style.display = '';
      btn.classList.remove('collapsed');
      
      // toggle
      btn.addEventListener('click', ()=>{
        const shown = body.style.display !== 'none';
        if (shown) {
          body.style.display = 'none';
          btn.classList.add('collapsed');
        } else {
          body.style.display = '';
          btn.classList.remove('collapsed');
        }
      });
    });

    modal.style.display = 'flex';
  }

  function closeModal(){ 
    modal.style.display='none'; 
  }
  
  function escapeHtml(s){ 
    return String(s).replace(/[&<>"']/g, m=>({ 
      '&':'&amp;',
      '<':'&lt;',
      '>':'&gt;',
      '"':'&quot;',
      "'":"&#39;" 
    }[m])); 
  }

  // Event listeners
  document.querySelectorAll('.view-day').forEach(btn=>{
    btn.addEventListener('click', ()=> openDay(btn.dataset.date));
  });
  
  mClose.addEventListener('click', closeModal);
  
  modal.addEventListener('click', (e)=>{ 
    if(e.target===modal) closeModal(); 
  });

  // Keyboard shortcut
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      closeModal();
    }
  });
})();
</script>
</body>
</html>