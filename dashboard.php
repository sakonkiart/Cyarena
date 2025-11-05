<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';
@$conn->query("SET time_zone = '+07:00'");

$userName = $_SESSION['user_name'];
$role     = $_SESSION['role'] ?? 'customer';

/* ===== Role helpers (FIXED) ===== */
$isSuper    = ($role === 'super_admin');
$isAdmin    = in_array($role, ['admin','type_admin'], true);   // แอดมินรวม type_admin
$isEmployee = ($role === 'employee');
$isStaffUIHide = ($isAdmin || $isEmployee);                    // ใช้บล็อก STAFF VIEW (แต่เราจะแสดง “ของฉัน” แทนการซ่อน)

/* Avatar */
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

/* ===== ดึงรายการสนาม (ลูกค้า/ซูเปอร์แอดมินเห็นปกติ) ===== */
$venues = [];
if (!$isStaffUIHide) {
  $sql = "
  SELECT 
      v.*,
      vt.TypeName,
      IFNULL(ROUND(AVG(r.Rating),1), 0) AS AvgRating,
      COUNT(r.ReviewID) AS ReviewCount,
      CASE
        WHEN v.Status = 'closed' THEN 'closed'
        WHEN v.Status = 'maintenance' THEN 'maintenance'
        WHEN EXISTS (
          SELECT 1 FROM Tbl_Booking b
          WHERE b.VenueID = v.VenueID
            AND DATE(b.StartTime) = CURDATE()
            AND NOW() BETWEEN b.StartTime AND b.EndTime
            AND b.BookingStatusID NOT IN (3,4)
        ) THEN 'unavailable'
        WHEN EXISTS (
          SELECT 1 FROM Tbl_Booking b
          WHERE b.VenueID = v.VenueID
            AND DATE(b.StartTime) = CURDATE()
            AND b.StartTime > NOW()
            AND b.BookingStatusID NOT IN (3,4)
        ) THEN 'upcoming'
        ELSE 'available'
      END AS StatusNow
  FROM Tbl_Venue v
  JOIN Tbl_Venue_Type vt ON v.VenueTypeID = vt.VenueTypeID
  LEFT JOIN Tbl_Review r ON v.VenueID = r.VenueID
  GROUP BY v.VenueID
  ORDER BY v.VenueName;
  ";
  if ($res = $conn->query($sql)) {
    $venues = $res->fetch_all(MYSQLI_ASSOC);
  }
}

/* ===== (NEW) ดึง “สนามของฉัน” สำหรับ staff (admin/type_admin/employee) ===== */
$myVenues = [];
if ($isStaffUIHide) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  $sqlMy = "
    SELECT v.VenueID, v.VenueName, v.ImageURL, v.PricePerHour, v.Status,
           v.TimeOpen, v.TimeClose, v.Address,
           vt.TypeName
    FROM Tbl_Venue v
    JOIN Tbl_Venue_Type vt ON vt.VenueTypeID = v.VenueTypeID
    WHERE v.CreatedByUserID = ?
    ORDER BY v.VenueName
  ";
  if ($st = $conn->prepare($sqlMy)) {
    $st->bind_param("i", $uid);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $myVenues[] = $r;
    $st->close();
  }
}

/* --- compute readyCount --- */
@$conn->query("SET time_zone = '+07:00'");
$readyCount = 0;
$readySql = "
  SELECT COUNT(*) AS c
  FROM Tbl_Venue v
  WHERE v.Status = 'available'
    AND NOT EXISTS (
      SELECT 1
      FROM Tbl_Booking b
      WHERE b.VenueID = v.VenueID
        AND b.BookingStatusID NOT IN (3,4)
        AND DATE(b.StartTime) = CURDATE()
        AND b.EndTime > NOW()
    )
";
if ($__rc = $conn->query($readySql)) {
    $readyCount = (int) ((($__rc->fetch_assoc())['c'] ?? 0));
}

/* ✅ โปรโมชั่นทั้งหมด */
$activePromoCount = 0;
$promoSql = "SELECT COUNT(*) as count FROM Tbl_Promotion WHERE NOW() BETWEEN StartDate AND EndDate";
$promoRes = $conn->query($promoSql);
if ($promoRes) {
    $activePromoCount = $promoRes->fetch_assoc()['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CY Arena - จองสนามกีฬาออนไลน์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
<?php echo preg_replace('/^/m','',<<<'CSS'
/* ====== (สไตล์ทั้งหมดคงเดิม) ====== */
:root{--primary:#2563eb;--primary-dark:#1e40af;--primary-light:#3b82f6;--secondary:#eab308;--accent:#f97316;--danger:#dc2626;--dark:#1c1917;--white:#ffffff;--gray-50:#fafaf9;--gray-100:#f5f5f4;--gray-200:#e7e5e4;--gray-700:#44403c;--gray-900:#1c1917;--turf-green:#16a34a;--court-orange:#f97316;--field-blue:#0ea5e9}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Sarabun','Kanit',sans-serif;background:var(--gray-50);color:var(--gray-900);line-height:1.6}
.top-bar{background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 100%);color:#fff;padding:.5rem 0;font-size:.875rem}
.top-bar-container{max-width:1400px;margin:0 auto;padding:0 2rem;display:flex;justify-content:space-between;align-items:center}
.top-bar-info{display:flex;gap:2rem}
.top-bar-item{display:flex;align-items:center;gap:.5rem}
.header{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);position:sticky;top:0;z-index:1000;border-bottom:3px solid var(--primary)}
.header-container{max-width:1400px;margin:0 auto;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;gap:2rem}
.logo{display:flex;align-items:center;gap:1rem;text-decoration:none}
.logo-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:2rem;box-shadow:0 4px 12px rgba(37,99,235,.3);position:relative}
.logo-icon::after{content:'';position:absolute;inset:-2px;background:linear-gradient(45deg,transparent,rgba(255,255,255,.3));border-radius:12px;z-index:-1}
.logo-text{display:flex;flex-direction:column}
.logo-title{font-family:'Kanit',sans-serif;font-size:1.75rem;font-weight:900;color:var(--primary);line-height:1;letter-spacing:-.5px}
.logo-subtitle{font-size:.75rem;color:var(--gray-700);font-weight:600;text-transform:uppercase;letter-spacing:1px}
.nav-menu{display:flex;align-items:center;gap:1rem;flex:1;justify-content:center}
.nav-link{color:var(--gray-900);text-decoration:none;font-weight:600;padding:.75rem 1.5rem;border-radius:8px;transition:.3s;display:flex;align-items:center;gap:.5rem;font-size:1rem;position:relative}
.nav-link:hover{background:var(--primary);color:#fff;transform:translateY(-2px)}
.nav-link.promo-link{background:linear-gradient(135deg,var(--secondary) 0%,var(--accent) 100%);color:#fff;box-shadow:0 4px 12px rgba(234,179,8,.3)}
.nav-link.promo-link:hover{background:linear-gradient(135deg,var(--accent) 0%,var(--secondary) 100%);box-shadow:0 6px 16px rgba(234,179,8,.4)}
.promo-badge{background:rgba(255,255,255,.3);padding:2px 8px;border-radius:10px;font-size:.75rem;font-weight:800}
.user-section{display:flex;align-items:center;gap:1rem}
.user-menu{position:relative}
.user-trigger{display:flex;align-items:center;gap:.75rem;padding:.5rem 1rem;background:var(--gray-50);border:2px solid var(--primary);border-radius:50px;cursor:pointer;transition:.3s}
.user-trigger:hover{background:var(--primary);transform:translateY(-2px);box-shadow:0 4px 12px rgba(37,99,235,.3)}
.user-trigger:hover .user-name{color:#fff}
.user-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)}
.user-name{font-weight:700;color:var(--gray-900);font-size:1rem}
.user-dropdown{position:absolute;top:calc(100% + .75rem);right:0;background:#fff;border:2px solid var(--primary);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.15);min-width:250px;opacity:0;visibility:hidden;transform:translateY(-10px);transition:.3s}
.user-menu:hover .user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.dropdown-header{padding:1rem 1.25rem;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border-radius:10px 10px 0 0}
.dropdown-header-name{font-weight:800;font-size:1.125rem;margin-bottom:.25rem}
.dropdown-header-role{font-size:.875rem;opacity:.9}
.dropdown-item{display:flex;align-items:center;gap:.75rem;padding:.875rem 1.25rem;color:var(--gray-900);text-decoration:none;border-bottom:1px solid var(--gray-200);transition:.2s;font-weight:600}
.dropdown-item:hover{background:var(--gray-50);color:var(--primary);padding-left:1.5rem}
.dropdown-item:last-child{border-bottom:none;color:var(--danger)}
.promo-bar{background:linear-gradient(90deg,var(--secondary) 0%,var(--accent) 50%,var(--secondary) 100%);background-size:200% 100%;animation:gradientShift 4s ease-in-out infinite;color:#fff;padding:1rem 0;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
@keyframes gradientShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
.promo-content{display:flex;white-space:nowrap}
.promo-text{display:inline-block;padding-left:100%;animation:scrollPromo 40s linear infinite;font-weight:800;font-size:1rem;letter-spacing:.5px;text-shadow:0 2px 4px rgba(0,0,0,.2)}
@keyframes scrollPromo{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.hero{background:linear-gradient(135deg,rgba(37,99,235,.95) 0%,rgba(30,64,175,.95) 100%),url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grass" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect fill="%232563eb" width="20" height="20"/><path d="M0 10h20M10 0v20" stroke="%231e40af" stroke-width="0.5" opacity="0.3"/></pattern></defs><rect width="100" height="100" fill="url(%23grass)"/></svg>');color:#fff;padding:4rem 2rem;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:repeating-linear-gradient(0deg,transparent,transparent 40px,rgba(255,255,255,.03) 40px,rgba(255,255,255,.03) 80px)}
.hero-container{max-width:1400px;margin:0 auto;position:relative;z-index:1}
.hero-content{max-width:900px;text-align:center;margin:0 auto}
.hero-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.5rem;background:rgba(255,255,255,.25);backdrop-filter:blur(10px);border-radius:50px;font-size:.875rem;font-weight:700;margin-bottom:1.5rem;border:2px solid rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1px}
.hero-title{font-family:'Kanit',sans-serif;font-size:4rem;font-weight:900;margin-bottom:1rem;line-height:1.1;text-shadow:0 4px 20px rgba(0,0,0,.3);letter-spacing:-1px}
.hero-highlight{color:var(--secondary);text-shadow:0 0 30px rgba(234,179,8,.5)}
.hero-subtitle{font-size:1.375rem;margin-bottom:2.5rem;line-height:1.6;font-weight:500;opacity:.95}
.search-box{max-width:700px;margin:0 auto;position:relative}
.search-input{width:100%;padding:1.25rem 5rem 1.25rem 1.75rem;border:3px solid #fff;border-radius:50px;font-size:1.0625rem;box-shadow:0 8px 24px rgba(0,0,0,.2);background:#fff;transition:.3s;font-weight:600}
.search-input:focus{outline:none;box-shadow:0 12px 32px rgba(0,0,0,.3),0 0 0 4px rgba(255,255,255,.3);transform:translateY(-2px)}
.search-btn{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border:none;padding:.875rem 2rem;border-radius:50px;font-weight:800;cursor:pointer;transition:.3s;font-size:1rem}
.search-btn:hover{transform:translateY(-50%) scale(1.05);box-shadow:0 4px 12px rgba(37,99,235,.4)}
.quick-actions{max-width:1400px;margin:-2rem auto 3rem;padding:0 2rem;position:relative;z-index:10}
.actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem}
.action-card{background:#fff;padding:2rem;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,.1);text-align:center;transition:.3s;border:2px solid transparent;cursor:pointer}
.action-card:hover{transform:translateY(-8px);box-shadow:0 12px 24px rgba(0,0,0,.15);border-color:var(--primary)}
.action-icon{width:80px;height:80px;margin:0 auto 1rem;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;box-shadow:0 8px 16px rgba(37,99,235,.3)}
.action-title{font-size:1.25rem;font-weight:800;color:var(--gray-900);margin-bottom:.5rem}
.action-desc{font-size:.9375rem;color:var(--gray-700);font-weight:500}
.filters-section,.venues-section{max-width:1400px;margin:0 auto 3rem;padding:0 2rem}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem}
.section-title{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:900;color:var(--gray-900);display:flex;align-items:center;gap:.75rem}
.section-title::before{content:'';width:5px;height:40px;background:linear-gradient(to bottom,var(--primary),var(--primary-light));border-radius:10px}
.filter-tabs{display:flex;flex-wrap:wrap;gap:1rem;padding:1.5rem;background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:2px solid var(--gray-200)}
.filter-btn{padding:.875rem 1.75rem;border:2px solid var(--gray-200);background:#fff;border-radius:50px;font-weight:800;cursor:pointer;transition:.3s;font-size:1rem;font-family:'Sarabun',sans-serif}
.filter-btn:hover{border-color:var(--primary);color:var(--primary);transform:translateY(-3px);box-shadow:0 4px 12px rgba(37,99,235,.2)}
.filter-btn.active{background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border-color:var(--primary);box-shadow:0 4px 12px rgba(37,99,235,.4);transform:translateY(-2px)}
.venue-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:2rem}
.venue-card{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.1);transition:.4s;border:3px solid transparent;opacity:0;animation:fadeInUp .6s ease-out forwards}
.venue-card:hover{transform:translateY(-12px);box-shadow:0 16px 32px rgba(0,0,0,.15);border-color:var(--primary)}
.venue-image-wrapper{position:relative;overflow:hidden;height:260px;background:var(--primary)}
.venue-image{width:100%;height:100%;object-fit:cover;transition:transform .6s}
.venue-card:hover .venue-image{transform:scale(1.1) rotate(1deg)}
.venue-badge{position:absolute;top:1.25rem;right:1.25rem;padding:.625rem 1.25rem;border-radius:50px;font-weight:900;font-size:.8125rem;backdrop-filter:blur(10px);box-shadow:0 4px 12px rgba(0,0,0,.2);border:2px solid rgba(255,255,255,.3);letter-spacing:.5px;text-transform:uppercase}
.venue-badge.available{background:rgba(22,163,74,.95);color:#fff}
.venue-badge.upcoming{background:rgba(234,179,8,.95);color:#fff}
.venue-badge.unavailable,.venue-badge.maintenance,.venue-badge.closed{background:rgba(220,38,38,.95);color:#fff}
.venue-type-badge{position:absolute;bottom:1rem;left:1rem;padding:.5rem 1rem;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-radius:50px;font-weight:800;font-size:.875rem;color:var(--primary);box-shadow:0 4px 12px rgba(0,0,0,.2)}
.venue-content{padding:1.75rem}
.venue-name{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:1rem;text-decoration:none;display:block;transition:.3s;line-height:1.3}
.venue-name:hover{color:var(--primary)}
.venue-info{display:flex;flex-direction:column;gap:.75rem;margin-bottom:1rem;padding:1rem;background:var(--gray-50);border-radius:12px;border-left:4px solid var(--primary)}
.info-row{display:flex;align-items:center;gap:.75rem;color:var(--gray-900);font-size:1rem;font-weight:600}
.info-icon{font-size:1.25rem;min-width:24px}
.venue-price{background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;padding:1rem;border-radius:12px;text-align:center;margin-bottom:1rem;box-shadow:0 4px 12px rgba(37,99,235,.3)}
.price-label{font-size:.875rem;opacity:.9;margin-bottom:.25rem}
.price-value{font-size:2rem;font-weight:900;font-family:'Kanit',sans-serif}
.venue-rating{display:flex;align-items:center;justify-content:space-between;padding:.875rem;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;margin-bottom:1.25rem;border:2px solid #fbbf24}
.stars{color:#f59e0b;font-size:1.125rem}
.rating-text{font-size:.875rem;color:var(--gray-900);font-weight:700}
.venue-actions{display:flex;gap:1rem}
.btn{flex:1;padding:1rem;border:none;border-radius:12px;font-weight:800;cursor:pointer;transition:.3s;text-decoration:none;text-align:center;display:inline-flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:1rem}
.btn-primary{background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.4);border:2px solid var(--primary-light)}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(37,99,235,.5)}
.btn-secondary{background:#fff;color:var(--primary);border:2px solid var(--primary)}
.btn-secondary:hover{background:var(--primary);color:#fff;transform:translateY(-2px)}
.btn.disabled{background:var(--gray-200);color:var(--gray-700);cursor:not-allowed;opacity:.6;pointer-events:none;border-color:var(--gray-200)}
.footer{background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 100%);color:#fff;padding:3rem 2rem 2rem;margin-top:5rem}
.footer-container{max-width:1400px;margin:0 auto}
.footer-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:3rem;margin-bottom:2rem}
.footer-section h3{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:800;margin-bottom:1rem}
.footer-link{display:block;color:rgba(255,255,255,.9);text-decoration:none;font-weight:600;margin-bottom:.75rem;transition:.3s}
.footer-link:hover{color:var(--secondary);padding-left:.5rem}
.footer-bottom{padding-top:2rem;border-top:2px solid rgba(255,255,255,.2);text-align:center;font-weight:600}
@media (max-width:1024px){.venue-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}}
@media (max-width:768px){.top-bar{display:none}.header-container{flex-direction:column;gap:1rem}.nav-menu{flex-wrap:wrap;justify-content:center}.nav-link{font-size:.875rem;padding:.625rem 1rem}.hero-title{font-size:2.5rem}.hero-subtitle{font-size:1.125rem}.search-input{padding:1rem 4rem 1rem 1.25rem;font-size:.9375rem}.search-btn{padding:.75rem 1.5rem;font-size:.875rem}.actions-grid{grid-template-columns:1fr}.venue-grid{grid-template-columns:1fr}.filter-tabs{justify-content:center}.section-title{font-size:1.5rem}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.venue-card:nth-child(1){animation-delay:.05s}.venue-card:nth-child(2){animation-delay:.1s}.venue-card:nth-child(3){animation-delay:.15s}.venue-card:nth-child(4){animation-delay:.2s}.venue-card:nth-child(5){animation-delay:.25s}.venue-card:nth-child(6){animation-delay:.3s}
.empty-state{grid-column:1 / -1;text-align:center;padding:5rem 2rem;background:#fff;border-radius:20px;border:3px dashed var(--gray-200)}
.empty-state-icon{font-size:5rem;margin-bottom:1.5rem;opacity:.5}
.empty-state-title{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:800;color:var(--gray-900);margin-bottom:.5rem}
.empty-state-text{font-size:1.125rem;color:var(--gray-700);font-weight:500}
CSS
); ?>
</style>
</head>
<body>
<!-- TOP BAR / HEADER / PROMO / HERO / QUICK ACTIONS เหมือนเดิมทั้งหมด (คงไว้) -->
<!-- … (ย่อเพื่อความสั้น — ใช้ของเดิมจากไฟล์ที่ให้มา) … -->

<?php /* === จากไฟล์เดิมของคุณ ตัดมาจนถึงก่อนส่วน <section class="venues-section" id="venues"> แรก === */ ?>

<?php if ($isStaffUIHide): ?>
  <!-- ===== STAFF VIEW: แสดงเฉพาะ “สนามที่ฉันสร้าง” ===== -->
  <section class="venues-section" id="venues">
    <div class="section-header">
      <h2 class="section-title">โหมดผู้ดูแล — สนามของฉัน</h2>
    </div>

    <?php if (empty($myVenues)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">🛡️</div>
        <div class="empty-state-title">ยังไม่มีสนามที่คุณสร้าง</div>
        <div class="empty-state-text" style="margin-bottom:1.25rem">
          ไปที่ <strong>จัดการสนามของฉัน</strong> เพื่อเพิ่มสนามใหม่ได้เลย
        </div>
        <div style="display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap;">
          <a href="admin_venues.php" class="btn btn-secondary" style="min-width:220px">🏟️ จัดการสนามของฉัน</a>
          <a href="manage_bookings.php" class="btn btn-primary" style="min-width:220px">🗂️ จัดการการจอง</a>
        </div>
      </div>
    <?php else: ?>
      <div class="venue-grid" id="venueGrid">
        <?php foreach ($myVenues as $v):
          $img = $v['ImageURL'] ?: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=400&h=300&fit=crop';
          $statusClass = match($v['Status']) {
            'available'    => 'available',
            'upcoming'     => 'upcoming',
            'unavailable'  => 'unavailable',
            'maintenance'  => 'maintenance',
            'closed'       => 'closed',
            default        => 'unavailable'
          };
          $statusText = match($v['Status']) {
            'available'    => '🟢 ว่าง',
            'upcoming'     => '🟡 มีจอง',
            'unavailable'  => '🔴 ไม่ว่าง',
            'maintenance'  => '🛠️ ปรับปรุง',
            'closed'       => '🚫 ปิด',
            default        => 'สถานะไม่ทราบ'
          };
        ?>
          <div class="venue-card" data-type="<?= htmlspecialchars($v['TypeName']); ?>">
            <div class="venue-image-wrapper">
              <a href="venue_detail.php?venue_id=<?= (int)$v['VenueID']; ?>">
                <img src="<?= htmlspecialchars($img); ?>" alt="" class="venue-image">
              </a>
              <span class="venue-badge <?= $statusClass; ?>"><?= $statusText; ?></span>
              <span class="venue-type-badge"><?= htmlspecialchars($v['TypeName']); ?></span>
            </div>
            <div class="venue-content">
              <a href="venue_detail.php?venue_id=<?= (int)$v['VenueID']; ?>" class="venue-name">
                <?= htmlspecialchars($v['VenueName']); ?>
              </a>
              <div class="venue-info">
                <div class="info-row"><span class="info-icon">🕐</span>
                  <span>เวลาทำการ: <?= htmlspecialchars(substr($v['TimeOpen'] ?? '--:--', 0, 5)) ?>
                    - <?= htmlspecialchars(substr($v['TimeClose'] ?? '--:--', 0, 5)) ?> น.</span>
                </div>
                <div class="info-row"><span class="info-icon">📍</span>
                  <?php
                    $addr = trim($v['Address'] ?? '—');
                    $addrShort = function_exists('mb_strimwidth')
                      ? mb_strimwidth($addr, 0, 50, '…', 'UTF-8')
                      : (function_exists('mb_substr') ? mb_substr($addr, 0, 50, 'UTF-8') : $addr);
                  ?>
                  <span title="<?= htmlspecialchars($addr) ?>"><?= htmlspecialchars($addrShort) ?></span>
                </div>
              </div>
              <div class="venue-price">
                <div class="price-label">ราคาเริ่มต้น</div>
                <div class="price-value">฿<?= number_format((float)$v['PricePerHour'], 0); ?> <span style="font-size:1rem;font-weight:600;">/ชม.</span></div>
              </div>
              <div class="venue-actions">
                <a href="admin_venues.php" class="btn btn-secondary">✏️ แก้ไขสนาม</a>
                <a href="booking.php?venue_id=<?= (int)$v['VenueID']; ?>" class="btn btn-primary">🎯 จองให้ลูกค้า</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <!-- ===== VIEW เดิม สำหรับ customer / super_admin (คงไว้เหมือนเดิม) ===== -->
  <!-- FILTERS -->
  <section class="filters-section" id="venues">
    <div class="section-header">
      <h2 class="section-title">เลือกประเภทสนาม</h2>
    </div>
    <div class="filter-tabs">
      <button class="filter-btn active" data-type="all">🏆 ทั้งหมด</button>
      <button class="filter-btn" data-type="ฟุตบอล">⚽ ฟุตบอล</button>
      <button class="filter-btn" data-type="ฟุตซอล">🥅 ฟุตซอล</button>
      <button class="filter-btn" data-type="บาสเกตบอล">🏀 บาสเกตบอล</button>
      <button class="filter-btn" data-type="แบดมินตัน">🏸 แบดมินตัน</button>
      <button class="filter-btn" data-type="เทนนิส">🎾 เทนนิส</button>
      <button class="filter-btn" data-type="ปิงปอง">🏓 ปิงปอง</button>
      <button class="filter-btn" data-type="วอลเลย์บอล">🏐 วอลเลย์บอล</button>
      <button class="filter-btn" data-type="เบสบอล">⚾ เบสบอล</button>
      <button class="filter-btn" data-type="ยิงธนู">🏹 ยิงธนู</button>
      <button class="filter-btn" data-type="รักบี้">🏈 รักบี้</button>
      <button class="filter-btn" data-type="ปีนผา">🧗 ปีนผา</button>
      <button class="filter-btn" data-type="ฮอกกี้พื้นสนาม">🏑 ฮอกกี้พื้นสนาม</button>
    </div>
  </section>

  <!-- VENUES -->
  <section class="venues-section">
    <div class="section-header"><h2 class="section-title">สนามแนะนำ</h2></div>
    <div class="venue-grid" id="venueGrid">
      <?php if (empty($venues)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">🏟️</div>
          <div class="empty-state-title">ไม่พบสนามกีฬา</div>
          <div class="empty-state-text">ขออภัย ไม่มีสนามกีฬาในระบบในขณะนี้</div>
        </div>
      <?php else: foreach ($venues as $venue):
        $st = $venue['StatusNow'] ?? 'available';
        $disableBooking = in_array($st, ['unavailable','maintenance','closed']);
        $statusMap = [
          'available' => ['label' => '🟢 ว่าง', 'class' => 'available'],
          'upcoming'  => ['label' => '🟡 มีจอง', 'class' => 'upcoming'],
          'unavailable'=>['label' => '🔴 ไม่ว่าง', 'class' => 'unavailable'],
          'maintenance'=>['label' => '🛠️ ปรับปรุง', 'class' => 'maintenance'],
          'closed'     => ['label' => '🚫 ปิด', 'class' => 'closed']
        ];
        $statusInfo = $statusMap[$st] ?? ['label' => 'ไม่ทราบ', 'class' => 'unavailable'];
      ?>
        <div class="venue-card" data-type="<?= htmlspecialchars($venue['TypeName']); ?>">
          <div class="venue-image-wrapper">
            <a href="venue_detail.php?venue_id=<?= $venue['VenueID']; ?>">
              <img src="<?= htmlspecialchars($venue['ImageURL'] ?: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=400&h=300&fit=crop'); ?>"
                   alt="<?= htmlspecialchars($venue['VenueName']); ?>" class="venue-image">
            </a>
            <span class="venue-badge <?= $statusInfo['class']; ?>"><?= $statusInfo['label']; ?></span>
            <span class="venue-type-badge"><?= htmlspecialchars($venue['TypeName']); ?></span>
          </div>
          <div class="venue-content">
            <a href="venue_detail.php?venue_id=<?= $venue['VenueID']; ?>" class="venue-name">
              <?= htmlspecialchars($venue['VenueName']); ?>
            </a>
            <div class="venue-info">
              <div class="info-row"><span class="info-icon">🕐</span>
                <span>เวลาทำการ: <?= htmlspecialchars(substr($venue['TimeOpen'] ?? '--:--', 0, 5)) ?> - <?= htmlspecialchars(substr($venue['TimeClose'] ?? '--:--', 0, 5)) ?> น.</span>
              </div>
              <div class="info-row"><span class="info-icon">📍</span>
                <?php
                  $addr = trim($venue['Address'] ?? 'กรุงเทพมหานคร');
                  $addrShort = function_exists('mb_strimwidth')
                    ? mb_strimwidth($addr, 0, 50, '…', 'UTF-8')
                    : (function_exists('mb_substr') ? mb_substr($addr, 0, 50, 'UTF-8') : $addr);
                ?>
                <span title="<?= htmlspecialchars($addr) ?>"><?= htmlspecialchars($addrShort) ?></span>
              </div>
            </div>
            <div class="venue-price">
              <div class="price-label">ราคาเริ่มต้น</div>
              <div class="price-value">฿<?= number_format($venue['PricePerHour'], 0); ?> <span style="font-size:1rem;font-weight:600;">/ชม.</span></div>
            </div>
            <div class="venue-rating">
              <span class="stars">
                <?php $rating = (int)$venue['AvgRating']; echo str_repeat("⭐", min(5, $rating)); if ($rating < 5) echo str_repeat("☆", 5 - $rating); ?>
              </span>
              <span class="rating-text">
                <?= $venue['AvgRating'] > 0 ? "{$venue['AvgRating']}/5 ({$venue['ReviewCount']} รีวิว)" : "ยังไม่มีรีวิว"; ?>
              </span>
            </div>
            <div class="venue-actions">
              <a href="venue_detail.php?venue_id=<?= $venue['VenueID']; ?>" class="btn btn-secondary">📋 ดูรายละเอียด</a>
              <a href="<?= $disableBooking ? '#' : 'booking.php?venue_id='.$venue['VenueID']; ?>" class="btn btn-primary<?= $disableBooking ? ' disabled' : ''; ?>">🎯 จองทันที</a>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>
<?php endif; ?>

<!-- FOOTER + SCRIPT คงเดิมตามไฟล์ของคุณ -->
<!-- … ใช้สคริปต์เดิมสำหรับ search/filter ได้เลย … -->

</body>
</html>
