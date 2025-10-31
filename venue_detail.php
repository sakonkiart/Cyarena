<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';

// ✅ รองรับทั้ง venue_id และ id
$venue_id = null;
if (isset($_GET['venue_id']) && is_numeric($_GET['venue_id'])) {
    $venue_id = (int)$_GET['venue_id'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $venue_id = (int)$_GET['id'];
}

if (!$venue_id) {
    die("❌ ไม่พบรหัสสนามที่ถูกต้อง");
}

// ✅ ดึงข้อมูลสนาม
$sql = "SELECT v.*, vt.TypeName
        FROM Tbl_Venue v
        JOIN Tbl_Venue_Type vt ON v.VenueTypeID = vt.VenueTypeID
        WHERE v.VenueID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("❌ ไม่พบข้อมูลสนามนี้");
}
$venue = $result->fetch_assoc();

// ✅ ตรวจสอบว่ามี CreatedAt หรือไม่
$check_col = $conn->query("SHOW COLUMNS FROM Tbl_Review LIKE 'CreatedAt'");
$has_created_at = ($check_col && $check_col->num_rows > 0);

// ✅ ดึงข้อมูลรีวิวของสนาม
if ($has_created_at) {
    $sql_reviews = "SELECT r.*, c.FirstName
                    FROM Tbl_Review r
                    JOIN Tbl_Customer c ON r.CustomerID = c.CustomerID
                    WHERE r.VenueID = ?
                    ORDER BY r.CreatedAt DESC
                    LIMIT 5";
} else {
    $sql_reviews = "SELECT r.*, c.FirstName
                    FROM Tbl_Review r
                    JOIN Tbl_Customer c ON r.CustomerID = c.CustomerID
                    WHERE r.VenueID = ?
                    ORDER BY r.ReviewID DESC
                    LIMIT 5";
}

$stmt = $conn->prepare($sql_reviews);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$reviews = $stmt->get_result();

// ✅ คำนวณคะแนนเฉลี่ย
$sql_avg = "SELECT AVG(Rating) as avg_rating, COUNT(*) as total_reviews FROM Tbl_Review WHERE VenueID = ?";
$stmt_avg = $conn->prepare($sql_avg);
$stmt_avg->bind_param("i", $venue_id);
$stmt_avg->execute();
$rating_data = $stmt_avg->get_result()->fetch_assoc();
$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_reviews = $rating_data['total_reviews'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($venue['VenueName']); ?> | CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Prompt", sans-serif;
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  min-height: 100vh;
  padding: 20px;
  color: #1e293b;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  overflow: hidden;
  animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Header Image */
.venue-image-wrapper {
  position: relative;
  height: 450px;
  overflow: hidden;
}

.venue-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
  padding: 30px;
  color: white;
}

.venue-type-badge {
  display: inline-block;
  background: rgba(59, 130, 246, 0.9);
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 10px;
}

.venue-title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 10px;
  text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

/* Rating Section */
.rating-section {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-top: 10px;
}

.rating-score {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.2rem;
  font-weight: 600;
}

.stars {
  color: #fbbf24;
  font-size: 1.3rem;
}

.review-count {
  color: rgba(255,255,255,0.8);
  font-size: 0.9rem;
}

/* Content */
.content {
  padding: 40px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.info-card {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  transition: all 0.3s;
}

.info-card:hover {
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.info-icon {
  font-size: 2rem;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border-radius: 12px;
}

.info-content h3 {
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 5px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-content p {
  font-size: 1.3rem;
  font-weight: 700;
  color: #0f172a;
}

/* Description */
.description-section {
  margin: 30px 0;
  padding: 25px;
  background: #f8fafc;
  border-left: 4px solid #3b82f6;
  border-radius: 8px;
}

.description-section h2 {
  color: #0f172a;
  margin-bottom: 15px;
  font-size: 1.5rem;
}

.description-section p {
  line-height: 1.8;
  color: #475569;
}

/* Buttons */
.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin: 30px 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 28px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.btn-primary {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #5a67d8, #6b3fa0);
}

.btn-secondary {
  background: #f59e0b;
  color: white;
}

.btn-secondary:hover {
  background: #d97706;
}

.btn-back {
  background: #64748b;
  color: white;
}

.btn-back:hover {
  background: #475569;
}

/* Address */
.address-section {
  background: #fff7ed;
  border: 2px solid #fed7aa;
  border-radius: 12px;
  padding: 25px;
  margin: 30px 0;
}

.address-section h3 {
  color: #0f172a;
  margin-bottom: 12px;
  font-size: 1.3rem;
  display: flex;
  align-items: center;
  gap: 10px;
}

.address-section p {
  color: #475569;
  line-height: 1.8;
}

/* Reviews */
.reviews-section {
  border-top: 2px solid #e2e8f0;
  padding-top: 40px;
  margin-top: 40px;
}

.reviews-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.reviews-header h2 {
  font-size: 1.8rem;
  color: #0f172a;
}

.review-item {
  background: #f8fafc;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 15px;
  border-left: 4px solid #3b82f6;
  transition: all 0.3s;
}

.review-item:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  transform: translateX(4px);
}

.review-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.reviewer-name {
  font-weight: 600;
  color: #0f172a;
  font-size: 1.1rem;
}

.review-rating {
  color: #fbbf24;
  font-weight: 600;
  font-size: 1rem;
}

.review-date {
  color: #94a3b8;
  font-size: 0.85rem;
  margin-top: 5px;
}

.review-comment {
  color: #475569;
  line-height: 1.7;
  margin-top: 10px;
}

.no-reviews {
  text-align: center;
  padding: 40px;
  color: #94a3b8;
  font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
  body {
    padding: 10px;
  }

  .venue-title {
    font-size: 1.8rem;
  }

  .content {
    padding: 20px;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .action-buttons {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Hero Image Section -->
  <div class="venue-image-wrapper">
    <img src="<?php echo htmlspecialchars($venue['ImageURL'] ?: 'https://via.placeholder.com/1200x450/667eea/ffffff?text=' . urlencode($venue['VenueName'])); ?>" 
         alt="<?php echo htmlspecialchars($venue['VenueName']); ?>" 
         class="venue-image">
    <div class="image-overlay">
      <span class="venue-type-badge">
        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($venue['TypeName']); ?>
      </span>
      <h1 class="venue-title"><?php echo htmlspecialchars($venue['VenueName']); ?></h1>
      
      <?php if ($total_reviews > 0): ?>
      <div class="rating-section">
        <div class="rating-score">
          <span class="stars">
            <?php 
            $full_stars = floor($avg_rating);
            $half_star = ($avg_rating - $full_stars) >= 0.5;
            for($i = 0; $i < $full_stars; $i++) echo '⭐';
            if($half_star) echo '⭐';
            ?>
          </span>
          <span><?php echo $avg_rating; ?>/5</span>
        </div>
        <span class="review-count">(<?php echo $total_reviews; ?> รีวิว)</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Content Section -->
  <div class="content">
    <!-- Info Cards -->
    <div class="info-grid">
      <div class="info-card">
        <div class="info-icon">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="info-content">
          <h3>ราคา / ชั่วโมง</h3>
          <p>฿<?php echo number_format($venue['PricePerHour'], 2); ?></p>
        </div>
      </div>

      <div class="info-card">
        <div class="info-icon">
          <i class="fas fa-star"></i>
        </div>
        <div class="info-content">
          <h3>คะแนนรีวิว</h3>
          <p><?php echo $avg_rating; ?> / 5</p>
        </div>
      </div>

      <div class="info-card">
        <div class="info-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="info-content">
          <h3>จำนวนรีวิว</h3>
          <p><?php echo $total_reviews; ?> รีวิว</p>
        </div>
      </div>
    </div>

    <!-- Description -->
    <?php if (!empty($venue['Description'])): ?>
    <div class="description-section">
      <h2><i class="fas fa-info-circle"></i> รายละเอียดสนาม</h2>
      <p><?php echo nl2br(htmlspecialchars($venue['Description'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Address -->
    <div class="address-section">
      <h3><i class="fas fa-map-marker-alt"></i> ที่อยู่สนาม</h3>
      <p><?php echo nl2br(htmlspecialchars($venue['Address'] ?: 'ยังไม่ระบุที่อยู่')); ?></p>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
      <a href="booking.php?venue_id=<?php echo $venue['VenueID']; ?>" class="btn btn-primary">
        <i class="fas fa-calendar-check"></i> จองสนามนี้
      </a>
      <a href="venue_reviews.php?venue_id=<?php echo $venue['VenueID']; ?>" class="btn btn-secondary">
        <i class="fas fa-star"></i> ดูรีวิวทั้งหมด
      </a>
      <a href="dashboard.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
      </a>
    </div>

    <!-- Reviews Section -->
    <div class="reviews-section">
      <div class="reviews-header">
        <h2><i class="fas fa-comments"></i> รีวิวล่าสุด</h2>
      </div>

      <?php if ($reviews->num_rows == 0): ?>
        <div class="no-reviews">
          <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
          <p>ยังไม่มีรีวิวสำหรับสนามนี้</p>
        </div>
      <?php else: ?>
        <?php while ($review = $reviews->fetch_assoc()): ?>
        <div class="review-item">
          <div class="review-header">
            <div>
              <div class="reviewer-name">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($review['FirstName']); ?>
              </div>
              <?php if (!empty($review['CreatedAt'])): ?>
                <div class="review-date">
                  <i class="far fa-clock"></i> <?php echo date("d/m/Y H:i", strtotime($review['CreatedAt'])); ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="review-rating">
              <?php for($i = 0; $i < $review['Rating']; $i++) echo '⭐'; ?>
              <span>(<?php echo $review['Rating']; ?>/5)</span>
            </div>
          </div>
          <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['Comment'])); ?></p>
        </div>
        <?php endwhile; ?>

        <?php if ($total_reviews > 5): ?>
        <div style="text-align: center; margin-top: 20px;">
          <a href="venue_reviews.php?venue_id=<?php echo $venue['VenueID']; ?>" class="btn btn-secondary">
            <i class="fas fa-eye"></i> ดูรีวิวทั้งหมด (<?php echo $total_reviews; ?>)
          </a>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>