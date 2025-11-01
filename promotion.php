<?php
session_start();
include 'db_connect.php';

// ดึงข้อมูลโปรโมชั่นทั้งหมด
$sql = "SELECT * FROM Tbl_Promotion ORDER BY StartDate DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🎁 โปรโมชั่นทั้งหมด - CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
  --primary: #2563eb;
  --primary-dark: #1e40af;
  --primary-light: #3b82f6;
  --secondary: #eab308;
  --accent: #f97316;
  --success: #16a34a;
  --danger: #dc2626;
  --gray-50: #fafaf9;
  --gray-100: #f5f5f4;
  --gray-200: #e7e5e4;
  --gray-700: #44403c;
  --gray-900: #1c1917;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Sarabun', 'Kanit', sans-serif;
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
  min-height: 100vh;
  padding: 2rem 1rem 4rem;
  position: relative;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" x="0" y="0" width="50" height="50" patternUnits="userSpaceOnUse"><path d="M0 0h50v50H0z" fill="none"/><path d="M0 0h50M0 0v50" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  pointer-events: none;
  z-index: 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

/* Header */
.header {
  text-align: center;
  margin-bottom: 3rem;
  animation: fadeInDown 0.8s ease-out;
}

@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.header-icon {
  width: 100px;
  height: 100px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
  backdrop-filter: blur(10px);
  border-radius: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
  margin: 0 auto 1.5rem;
  border: 3px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

.header h1 {
  font-family: 'Kanit', sans-serif;
  font-size: 3rem;
  font-weight: 900;
  color: white;
  margin-bottom: 0.5rem;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  letter-spacing: -1px;
}

.header-subtitle {
  font-size: 1.1rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
}

/* Stats Bar */
.stats-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  animation: fadeIn 1s ease-out 0.3s both;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.stat-card {
  flex: 1;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  padding: 1.25rem;
  text-align: center;
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-5px);
  background: rgba(255, 255, 255, 0.2);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.stat-number {
  font-size: 2rem;
  font-weight: 900;
  color: white;
  font-family: 'Kanit', sans-serif;
}

.stat-label {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.9);
  margin-top: 0.25rem;
  font-weight: 600;
}

/* Promo Grid */
.promo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 2rem;
  animation: fadeInUp 0.8s ease-out 0.5s both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.promo-card {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  transition: all 0.4s;
  position: relative;
  animation: scaleIn 0.5s ease-out both;
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.promo-card:nth-child(1) { animation-delay: 0.1s; }
.promo-card:nth-child(2) { animation-delay: 0.2s; }
.promo-card:nth-child(3) { animation-delay: 0.3s; }
.promo-card:nth-child(4) { animation-delay: 0.4s; }
.promo-card:nth-child(5) { animation-delay: 0.5s; }
.promo-card:nth-child(6) { animation-delay: 0.6s; }

.promo-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.promo-header {
  background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
  padding: 2rem 1.75rem;
  position: relative;
  overflow: hidden;
}

.promo-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
  animation: pulse 4s ease-in-out infinite;
}

.promo-badge {
  display: inline-block;
  background: rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(10px);
  padding: 0.4rem 1rem;
  border-radius: 50px;
  font-size: 0.8rem;
  font-weight: 800;
  color: white;
  border: 2px solid rgba(255, 255, 255, 0.3);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 0.75rem;
  position: relative;
  z-index: 1;
}

.promo-code {
  font-family: 'Kanit', sans-serif;
  font-size: 2rem;
  font-weight: 900;
  color: white;
  margin-bottom: 0.5rem;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
  letter-spacing: 2px;
}

.promo-name {
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.95);
  font-weight: 600;
  position: relative;
  z-index: 1;
}

.promo-body {
  padding: 1.75rem;
}

.promo-description {
  color: var(--gray-700);
  line-height: 1.6;
  margin-bottom: 1.5rem;
  font-size: 0.95rem;
}

.promo-discount {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border: 2px solid var(--primary-light);
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
  text-align: center;
}

.discount-label {
  font-size: 0.85rem;
  color: var(--primary-dark);
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.discount-value {
  font-family: 'Kanit', sans-serif;
  font-size: 2.5rem;
  font-weight: 900;
  color: var(--primary);
  line-height: 1;
}

.discount-unit {
  font-size: 1.25rem;
  font-weight: 700;
  margin-left: 0.25rem;
}

.promo-dates {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--gray-50);
  padding: 1rem;
  border-radius: 10px;
  margin-bottom: 1rem;
}

.date-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: var(--gray-700);
  font-weight: 600;
}

.date-icon {
  font-size: 1.1rem;
}

.promo-status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-active {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  color: #15803d;
  border: 2px solid #86efac;
}

.status-upcoming {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #92400e;
  border: 2px solid #fbbf24;
}

.status-expired {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #991b1b;
  border: 2px solid #f87171;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border: 3px dashed rgba(255, 255, 255, 0.3);
  border-radius: 24px;
  animation: fadeIn 0.8s ease-out;
}

.empty-icon {
  font-size: 5rem;
  margin-bottom: 1.5rem;
  opacity: 0.8;
}

.empty-title {
  font-family: 'Kanit', sans-serif;
  font-size: 2rem;
  color: white;
  font-weight: 800;
  margin-bottom: 0.75rem;
}

.empty-text {
  font-size: 1.1rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
}

/* Back Button */
.back-button {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  background: white;
  color: var(--primary);
  padding: 1rem 1.75rem;
  border-radius: 50px;
  text-decoration: none;
  font-weight: 800;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  z-index: 100;
  border: 3px solid var(--primary);
}

.back-button:hover {
  transform: translateY(-5px) scale(1.05);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
  background: var(--primary);
  color: white;
}

/* Conditions */
.promo-conditions {
  background: #fef3c7;
  border: 2px solid #fbbf24;
  border-radius: 10px;
  padding: 1rem;
  margin-top: 1rem;
  font-size: 0.85rem;
  color: #92400e;
  line-height: 1.6;
}

.conditions-title {
  font-weight: 800;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
  body {
    padding: 1.5rem 1rem 6rem;
  }
  
  .header h1 {
    font-size: 2rem;
  }
  
  .header-icon {
    width: 80px;
    height: 80px;
    font-size: 2.5rem;
  }
  
  .promo-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  
  .stats-bar {
    flex-direction: column;
  }
  
  .promo-dates {
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
  }
  
  .back-button {
    bottom: 1rem;
    right: 1rem;
    padding: 0.875rem 1.5rem;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-icon">🎁</div>
    <h1>โปรโมชั่นทั้งหมด</h1>
    <div class="header-subtitle">รวมส่วนลดและโปรโมชั่นพิเศษสำหรับคุณ</div>
  </div>

  <!-- Stats Bar -->
  <?php
  $totalPromos = $result->num_rows;
  $activePromos = 0;
  $upcomingPromos = 0;
  
  // Count status
  $result->data_seek(0);
  while ($row = $result->fetch_assoc()) {
    $now = new DateTime();
    $start = new DateTime($row['StartDate']);
    $end = new DateTime($row['EndDate']);
    
    if ($now >= $start && $now <= $end) {
      $activePromos++;
    } elseif ($now < $start) {
      $upcomingPromos++;
    }
  }
  $result->data_seek(0);
  ?>
  
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-number"><?php echo $totalPromos; ?></div>
      <div class="stat-label">โปรโมชั่นทั้งหมด</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $activePromos; ?></div>
      <div class="stat-label">ใช้งานได้ตอนนี้</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $upcomingPromos; ?></div>
      <div class="stat-label">เร็วๆ นี้</div>
    </div>
  </div>

  <!-- Promo Grid -->
  <div class="promo-grid">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($promo = $result->fetch_assoc()): ?>
        <?php
        $now = new DateTime();
        $start = new DateTime($promo['StartDate']);
        $end = new DateTime($promo['EndDate']);
        
        if ($now >= $start && $now <= $end) {
          $status = 'active';
          $statusText = '✅ ใช้งานได้';
          $statusClass = 'status-active';
        } elseif ($now < $start) {
          $status = 'upcoming';
          $statusText = '🕐 เร็วๆ นี้';
          $statusClass = 'status-upcoming';
        } else {
          $status = 'expired';
          $statusText = '❌ หมดอายุ';
          $statusClass = 'status-expired';
        }
        ?>
        
        <div class="promo-card">
          <div class="promo-header">
            <div class="promo-badge"><?php echo $promo['PromoCode']; ?></div>
            <div class="promo-code"><?php echo htmlspecialchars($promo['PromoName']); ?></div>
            <div class="promo-name"><?php echo htmlspecialchars($promo['Description']); ?></div>
          </div>
          
          <div class="promo-body">
            <div class="promo-discount">
              <div class="discount-label">💸 ส่วนลด</div>
              <div class="discount-value">
                <?php
                if ($promo['DiscountType'] == 'percent') {
                  echo $promo['DiscountValue'];
                  echo '<span class="discount-unit">%</span>';
                } else {
                  echo number_format($promo['DiscountValue'], 0);
                  echo '<span class="discount-unit">฿</span>';
                }
                ?>
              </div>
            </div>
            
            <div class="promo-dates">
              <div class="date-item">
                <span class="date-icon">📅</span>
                <?php echo date("d/m/Y", strtotime($promo['StartDate'])); ?>
              </div>
              <span style="color: var(--gray-400);">→</span>
              <div class="date-item">
                <span class="date-icon">⏰</span>
                <?php echo date("d/m/Y", strtotime($promo['EndDate'])); ?>
              </div>
            </div>
            
            <div style="text-align: center;">
              <span class="promo-status <?php echo $statusClass; ?>">
                <?php echo $statusText; ?>
              </span>
            </div>
            
            <?php if (!empty($promo['Conditions'])): ?>
              <div class="promo-conditions">
                <div class="conditions-title">📋 เงื่อนไข</div>
                <?php echo nl2br(htmlspecialchars($promo['Conditions'])); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <div class="empty-title">ยังไม่มีโปรโมชั่น</div>
        <div class="empty-text">ขออภัย ยังไม่มีโปรโมชั่นในขณะนี้<br>กรุณาตรวจสอบอีกครั้งในภายหลัง</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Back Button -->
<a href="dashboard.php" class="back-button">
  ← กลับหน้าหลัก
</a>

</body>
</html>
