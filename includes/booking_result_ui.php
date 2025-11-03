<?php
/**
 * Booking Result UI (Success / Error)
 * ใช้ Bootstrap 5 + ฟอนต์ Kanit + ไอคอน SVG
 * ตัวแปรที่ควรกำหนดก่อน include:
 *  - $ui_status  : 'success' | 'error'
 *  - $ui_title   : หัวข้อใหญ่
 *  - $ui_message : ข้อความอธิบาย
 *  - $conflicts  : (option) array รายการชนกัน [['date'=>'2025-10-30','start'=>'18:00','end'=>'19:00','venue'=>'CY Arena A'], ...]
 *  - $back_url   : ลิงก์ย้อนกลับไปแบบเดิม (เช่น booking.php?VenueID=..)
 *  - $calendar_url: (option) ลิงก์ดูปฏิทินสนาม
 *  - $dashboard_url: (option) ลิงก์กลับ Dashboard (ค่าเริ่มต้น 'dashboard.php')
 */
if (!isset($dashboard_url)) $dashboard_url = 'dashboard.php';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>สถานะการจอง</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{ --ok:#16a34a; --bad:#ef4444; --ink:#111827; --muted:#6b7280; --bg:#f8fafc; --card:#fff; }
    html,body{ background:var(--bg); color:var(--ink); font-family:"Kanit",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial }
    .container-narrow{ max-width: 820px; margin:auto; padding: 28px 16px; }
    .panel{
      background:var(--card); border:1px solid #eef2ff; border-radius:18px; padding:22px;
      box-shadow:0 18px 35px rgba(15,23,42,.06);
    }
    .icon-wrap{ width:58px; height:58px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .icon-ok{ background:rgba(22,163,74,.12); color:#059669; border:1px solid rgba(22,163,74,.2);}
    .icon-bad{ background:rgba(239,68,68,.12); color:#dc2626; border:1px solid rgba(239,68,68,.2);}
    .title{ font-weight:600; font-size:1.4rem; margin-bottom:4px }
    .sub{ color:var(--muted) }
    .table thead th{ background:#f3f4f6; }
    .btn-pill{ border-radius:999px; }
  </style>
</head>
<body>
  <div class="container-narrow">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 m-0">สถานะการจอง</h1>
      <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-sm btn-outline-primary btn-pill">← กลับ Dashboard</a>
    </div>

    <div class="panel">
      <div class="d-flex align-items-start gap-3">
        <div class="icon-wrap <?= ($ui_status==='success'?'icon-ok':'icon-bad') ?>">
          <?php if ($ui_status==='success'): ?>
            <!-- check icon -->
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
          <?php else: ?>
            <!-- x icon -->
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M18.3 5.71 12 12l6.3 6.29-1.41 1.42L10.59 13.4 4.3 19.71 2.89 18.3 9.17 12 2.89 5.71 4.3 4.29l6.29 6.3 6.29-6.3z"/></svg>
          <?php endif; ?>
        </div>
        <div class="flex-grow-1">
          <div class="title"><?= htmlspecialchars($ui_title ?? ($ui_status==='success'?'จองสำเร็จ':'จองไม่สำเร็จ')) ?></div>
          <div class="sub"><?= nl2br(htmlspecialchars($ui_message ?? '')) ?></div>

          <?php if (!empty($conflicts) && is_array($conflicts)): ?>
            <div class="mt-3">
              <div class="fw-semibold mb-1">รายละเอียดเวลาที่ชน</div>
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                  <thead><tr><th>วันที่</th><th>เริ่ม</th><th>สิ้นสุด</th><th>สนาม</th></tr></thead>
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

          <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if (!empty($back_url)): ?>
              <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-primary btn-pill">ลองเลือกเวลาใหม่</a>
            <?php endif; ?>
            <?php if (!empty($calendar_url)): ?>
              <a href="<?= htmlspecialchars($calendar_url) ?>" class="btn btn-outline-secondary btn-pill">ดูปฏิทินสนาม</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-light border btn-pill">ไปหน้า Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- floating back -->
  <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn btn-primary btn-pill"
     style="position:fixed; right:18px; bottom:18px; box-shadow:0 10px 22px rgba(15,23,42,.18);">← กลับ Dashboard</a>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
