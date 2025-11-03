<?php /* CY Arena Reports + KPIs + Charts + Back to Dashboard buttons */ ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CY Arena Reports</title>

  <!-- Fonts + Bootstrap -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.5/dist/chart.umd.min.js"></script>

  <style>
    :root{
      --brand:#4f46e5; --brand-2:#22c55e; --ink:#1f2937; --muted:#6b7280;
      --bg:#f8fafc; --card:#ffffff; --ring:rgba(79,70,229,.15);
    }
    html,body{ background: var(--bg); color: var(--ink); font-family: "Kanit", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; }
    .hero{ background: radial-gradient(1200px 400px at 20% -10%, rgba(79,70,229,.25), transparent 70%),
                      radial-gradient(1200px 400px at 80% -10%, rgba(34,197,94,.20), transparent 70%); border-bottom: 1px solid #eef2ff; }
    .wrapper{ max-width: 1100px; margin-inline:auto; padding: 28px 16px; }
    .tabs-wrap{ background: var(--card); border-radius: 18px; box-shadow: 0 12px 24px rgba(15,23,42,.06); border:1px solid #eef2ff; overflow: hidden; }
    .nav-pills .nav-link{ color: var(--muted); border-radius: 12px; font-weight: 500; }
    .nav-pills .nav-link.active{ background: linear-gradient(90deg, var(--brand) 0%, #6366f1 100%); color: #fff; box-shadow: 0 6px 16px var(--ring); }
    .tab-content{ padding: 18px; }
    .section{ background: var(--card); border:1px solid #eef2ff; border-radius: 16px; padding: 16px; box-shadow: 0 10px 22px rgba(15,23,42,.05); }
    .table thead th{ font-weight:600; color:#111827; background:#f3f4f6; border-bottom:1px solid #e5e7eb; }
    .table tbody tr:hover{ background:#fafafa; }
    .kpi-card{ border:1px solid #eef2ff; border-radius:16px; background:#fff; padding:16px; box-shadow:0 8px 18px rgba(15,23,42,.05)}
    .kpi-title{ color:#6b7280; font-size:.92rem; margin-bottom:6px}
    .kpi-value{ font-weight:600; font-size:1.4rem}
    .kpi-sub{ color:#6b7280; font-size:.85rem}
    /* funnel heat */
    .heat td{ position:relative }
    .heat td[data-val]{ background: linear-gradient(90deg, rgba(79,70,229,0.12) 0, rgba(79,70,229,0.12) var(--p,0%), transparent var(--p,0%)); }
    /* Floating Back Button */
    .fab-back {
      position: fixed; right: 18px; bottom: 18px; z-index: 1040;
      border-radius: 999px; box-shadow: 0 10px 24px rgba(15,23,42,.16);
    }
  </style>
</head>
<body>

<?php
require_once __DIR__ . '/db_connect.php'; // ใช้ conn เดิม
// ====== ดึง KPI เดือนล่าสุด ======
$k = ['ym'=>'-', 'revenue'=>0, 'bookings'=>0, 'avg'=>0, 'cancel'=>null];
if ($res = $conn->query("SELECT ym,revenue,bookings,avg_order_value FROM vw_monthly_revenue ORDER BY ym DESC LIMIT 1")) {
  if ($row = $res->fetch_assoc()) {
    $k['ym'] = $row['ym']; $k['revenue'] = (float)$row['revenue']; $k['bookings']=(int)$row['bookings']; $k['avg']=(float)$row['avg_order_value'];
  }
}
if ($res2 = $conn->query("SELECT ym,cancel_rate_pct FROM vw_monthly_cancellation_rate ORDER BY ym DESC LIMIT 1")) {
  if ($row2 = $res2->fetch_assoc()) { $k['cancel'] = (float)$row2['cancel_rate_pct']; }
}
function baht($n){ return number_format((float)$n,2); }
?>

  <!-- Hero header -->
  <div class="hero">
    <div class="wrapper">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="badge text-bg-light border">CY Arena • Analytics</div>
          <h1 class="m-0">รายงาน (10 วิว)</h1>
        </div>
        <!-- ปุ่มกลับ Dashboard ที่หัวเพจ -->
        
      </div>

      <p class="mt-2 mb-0 text-secondary">ภาพรวมธุรกิจเดือนล่าสุด: <strong><?php echo htmlspecialchars($k['ym']); ?></strong></p>

      <!-- KPI Row -->
      <div class="row g-3 mt-3">
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="kpi-card">
            <div class="kpi-title">รายได้เดือนนี้</div>
            <div class="kpi-value">฿<?php echo baht($k['revenue']); ?></div>
            <div class="kpi-sub">ยอดรวมจากบิลสำเร็จ</div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="kpi-card">
            <div class="kpi-title">จำนวนบิล</div>
            <div class="kpi-value"><?php echo number_format($k['bookings']); ?></div>
            <div class="kpi-sub">เฉพาะยืนยัน + ชำระเงินสำเร็จ</div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="kpi-card">
            <div class="kpi-title">บิลเฉลี่ย</div>
            <div class="kpi-value">฿<?php echo baht($k['avg']); ?></div>
            <div class="kpi-sub">Avg Order Value</div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="kpi-card">
            <div class="kpi-title">อัตรายกเลิก</div>
            <div class="kpi-value"><?php echo ($k['cancel']!==null? number_format($k['cancel'],2).'%' : '—'); ?></div>
            <div class="kpi-sub">รวมทุกสถานะยกเลิก</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main -->
  <div class="wrapper">
    <div class="tabs-wrap p-3">

      <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-2">
 <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#v1">1. รายได้รายเดือน</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v2">2. สนามทำรายได้สูงสุด</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v3">3. อัตราการใช้สนาม</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v4">4. โปรโมชัน</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v5">5. มูลค่าลูกค้า (LTV)</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v6">6. การยกเลิกรายเดือน</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v7">7. คะแนนรีวิว</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v8">8. พนักงาน</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v9">9. สรุปสถานะ (Funnel)</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#v10">10. ชั่วโมงพีค</button></li>

      </ul>

      <div class="tab-content mt-2">
        <div class="tab-pane fade show active" id="v1">
          <div class="section">
            <h5 class="mb-2">ยอดขายรายเดือน</h5>
            <canvas id="chartRevenue" height="100"></canvas>
            <hr class="my-3">
            <?php include __DIR__.'/reports/monthly_revenue.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v2">
          <div class="section">
            <h5 class="mb-2">Top Venues (90 วัน)</h5>
            <canvas id="chartTopVenues" height="120"></canvas>
            <hr class="my-3">
            <?php include __DIR__.'/reports/top_venues.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v3">
          <div class="section">
            <h5 class="mb-2">Utilization รายวัน (เฉลี่ยทุกสนาม)</h5>
            <canvas id="chartUtil" height="90"></canvas>
            <hr class="my-3">
            <?php include __DIR__.'/reports/venue_utilization_daily.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v4">
          <div class="section">
            <h5 class="mb-2">ประสิทธิภาพโปรโมชัน</h5>
            <?php include __DIR__.'/reports/promotion_performance.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v5">
          <div class="section">
            <h5 class="mb-2">Customer LTV</h5>
            <?php include __DIR__.'/reports/customer_ltv.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v6">
          <div class="section">
            <h5 class="mb-2">อัตราการยกเลิกรายเดือน</h5>
            <?php include __DIR__.'/reports/monthly_cancellation_rate.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v7">
          <div class="section">
            <h5 class="mb-2">คะแนนรีวิวตามสนาม</h5>
            <?php include __DIR__.'/reports/review_scores_by_venue.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v8">
          <div class="section">
            <h5 class="mb-2">ผลงานพนักงาน</h5>
            <?php include __DIR__.'/reports/employee_performance.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v9">
          <div class="section">
            <h5 class="mb-2">Booking Funnel (Heat)</h5>
            <?php include __DIR__.'/reports/booking_funnel.php'; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="v10">
          <div class="section">
            <h5 class="mb-2">ชั่วโมงพีคแยกประเภทสนาม</h5>
            <canvas id="chartHours" height="100"></canvas>
            <hr class="my-3">
            <?php include __DIR__.'/reports/peak_hours_by_type.php'; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- ปุ่มลอยกลับ Dashboard -->
<a href="dashboard.php" class="btn btn-primary fab-back">← กลับ Dashboard</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
// ====== Data for charts ======
// 1) Revenue chart datasets
$revRows = $conn->query("SELECT ym, revenue, bookings FROM vw_monthly_revenue ORDER BY ym")->fetch_all(MYSQLI_ASSOC);
// 2) TopVenues chart datasets
$topRows = $conn->query("SELECT VenueName, revenue_90d FROM vw_top10_venues_by_revenue ORDER BY revenue_90d DESC")->fetch_all(MYSQLI_ASSOC);
// 3) Utilization avg per day
$utilRows = $conn->query("SELECT usage_date, ROUND(AVG(utilization_pct),2) AS util_avg FROM vw_venue_utilization_daily GROUP BY usage_date ORDER BY usage_date")->fetch_all(MYSQLI_ASSOC);
// 4) Peak hours (all types together)
$hoursRows = $conn->query("SELECT hour_of_day, SUM(bookings) AS bookings FROM vw_peak_hours_by_type GROUP BY hour_of_day ORDER BY hour_of_day")->fetch_all(MYSQLI_ASSOC);

function jsonSafe($arr){ return json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
?>
<script>
  // -------- Revenue (Line + Bar) --------
  const revL = <?php echo jsonSafe(array_column($revRows,'ym')); ?>;
  const revV = <?php echo jsonSafe(array_map('floatval', array_column($revRows,'revenue'))); ?>;
  const revB = <?php echo jsonSafe(array_map('intval', array_column($revRows,'bookings'))); ?>;
  new Chart(document.getElementById('chartRevenue'), {
    type: 'bar',
    data: {
      labels: revL,
      datasets: [
        { type:'line', label:'รายได้ (฿)', data: revV, tension:.3 },
        { type:'bar',  label:'จำนวนบิล', data: revB }
      ]
    },
    options: {
      plugins:{ legend:{ display:true } },
      scales:{ y:{ beginAtZero:true } }
    }
  });

  // -------- Top Venues (Horizontal Bar) --------
  const tvN = <?php echo jsonSafe(array_column($topRows,'VenueName')); ?>;
  const tvR = <?php echo jsonSafe(array_map('floatval', array_column($topRows,'revenue_90d'))); ?>;
  new Chart(document.getElementById('chartTopVenues'), {
    type: 'bar',
    data: { labels: tvN, datasets: [{ label:'รายได้ 90 วัน (฿)', data: tvR }] },
    options: { indexAxis: 'y', plugins:{legend:{display:true}}, scales:{x:{beginAtZero:true}} }
  });

  // -------- Utilization (Line %) --------
  const utD = <?php echo jsonSafe(array_column($utilRows,'usage_date')); ?>;
  const utP = <?php echo jsonSafe(array_map('floatval', array_column($utilRows,'util_avg'))); ?>;
  new Chart(document.getElementById('chartUtil'), {
    type:'line',
    data:{ labels: utD, datasets:[{ label:'Utilization เฉลี่ย (%)', data: utP, tension:.35 }] },
    options:{ scales:{ y:{ beginAtZero:true, max:100 } } }
  });

  // -------- Peak Hours (Bar) --------
  const hrH = <?php echo jsonSafe(array_map('intval', array_column($hoursRows,'hour_of_day'))); ?>;
  const hrC = <?php echo jsonSafe(array_map('intval', array_column($hoursRows,'bookings'))); ?>;
  new Chart(document.getElementById('chartHours'), {
    type:'bar',
    data:{ labels: hrH, datasets:[{ label:'จำนวนบิล', data: hrC }] },
    options:{ scales:{ x:{ title:{display:true, text:'ชั่วโมง'} }, y:{ beginAtZero:true } } }
  });
</script>
</body>
</html>
