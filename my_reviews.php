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

// Avatar logic (Copied from previous files for consistency)
$avatarPath  = $_SESSION['avatar_path'] ?? '';
$avatarLocal = 'assets/avatar-default.png';

function _exists_rel($rel){ return is_file(__DIR__ . '/' . ltrim($rel, '/')); }

if ($avatarPath && _exists_rel($avatarPath)) {
  $avatarSrc = $avatarPath;
} elseif (_exists_rel($avatarLocal)) {
  $avatarSrc = $avatarLocal;
} else {
  $avatarSrc = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#16a34a"/><text x="50%" y="54%" text-anchor="middle" font-size="48" font-family="Arial" fill="#fff">⚽</text></svg>'
  );
}

// ✅ ดึงข้อมูลรีวิวของลูกค้าคนนี้
$sql = "SELECT r.ReviewID, r.Rating, r.Comment, r.ReviewDate, v.VenueName
        FROM Tbl_Review r
        JOIN Tbl_Venue v ON r.VenueID = v.VenueID
        WHERE r.CustomerID = ?
        ORDER BY r.ReviewDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวของฉัน | CY Arena</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background-color: #f4f7f6; /* Consistent with other pages */
    color: #1f2937;
  }
  .container-main {
    max-width: 900px;
  }
  /* Custom class for star color */
  .star-color {
    color: #fbbf24; /* Amber-400 for a warm yellow star */
  }

  /* Review Card Hover Effect */
  .review-card-effect {
    transition: all 0.2s ease-in-out;
  }
  .review-card-effect:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* lg shadow */
    transform: translateY(-3px);
  }

  /* Hamburger Menu Toggler */
  @media (max-width: 768px) {
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
</style>
</head>
<body>

<!-- Header/Navbar (Consistent with dashboard.php and my_bookings.php) -->
<header class="bg-white shadow-md sticky top-0 z-20">
  <div class="container mx-auto flex justify-between items-center p-4">
    <div class="text-2xl font-bold text-teal-600">CY Arena</div>
    
    <!-- Desktop Navigation -->
    <nav class="_header_nav hidden md:flex space-x-2">
      <a href="dashboard.php" class="px-3 py-2 text-gray-700 hover:text-teal-600 hover:bg-teal-50 rounded-lg">หน้าหลัก</a>
      <a href="my_bookings.php" class="px-3 py-2 text-gray-700 hover:text-teal-600 hover:bg-teal-50 rounded-lg">การจองของฉัน</a>
    
      <a href="#my-reviews" class="px-3 py-2 text-teal-600 font-semibold bg-teal-50 rounded-lg">รีวิวของฉัน</a>
      <?php if ($role === 'admin'): ?>
      <a href="admin.php" class="px-3 py-2 text-gray-700 hover:text-teal-600 hover:bg-teal-50 rounded-lg">จัดการระบบ</a>
      <?php endif; ?>
    </nav>
    
    <!-- User/Logout Section -->
    <div class="flex items-center space-x-3">
      <span class="text-sm font-medium hidden sm:inline text-gray-800">สวัสดี, <?php echo htmlspecialchars($userName); ?></span>
      <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden border-2 border-teal-500">
        <img src="<?php echo $avatarSrc; ?>" alt="User Avatar" class="w-full h-full object-cover">
      </div>
      <a href="logout.php" class="text-red-500 hover:text-red-700 text-sm font-medium ml-4 hidden md:inline">
        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
      </a>
      <!-- Burger Menu Button for Mobile -->
      <button class="burger-menu md:hidden text-gray-600 hover:text-teal-600 text-xl" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<!-- Mobile Navigation Overlay -->
<nav id="mobile-menu" class="_header_nav md:hidden" style="display: none;">
  <a href="dashboard.php">หน้าหลัก</a>
  <a href="my_bookings.php">การจองของฉัน</a>
  <a href="profile.php">โปรไฟล์</a>
  <a href="#my-reviews">รีวิวของฉัน</a>
  <?php if ($role === 'admin'): ?>
  <a href="admin.php">จัดการระบบ</a>
  <?php endif; ?>
  <a href="logout.php" class="text-red-500">ออกจากระบบ</a>
</nav>

<!-- SECTION: My Reviews -->
<section id="my-reviews" class="py-8 md:py-16">
  <div class="container-main mx-auto px-4">
    <h2 class="text-3xl md:text-4xl font-bold text-center text-teal-700 mb-8 md:mb-12 flex items-center justify-center">
        <i class="fas fa-star text-amber-500 mr-3"></i> รีวิวของฉัน
    </h2>

    <?php if ($result->num_rows > 0): ?>
      <!-- Review List -->
      <div class="space-y-6">
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="review-card-effect bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-4 border-teal-400">
            
            <!-- Review Header (Venue Name & Date) -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start border-b pb-3 mb-3">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center mb-2 sm:mb-0">
                    <i class="fas fa-futbol text-teal-500 mr-2"></i>
                    <?php echo htmlspecialchars($row['VenueName']); ?>
                </h3>
                <div class="text-sm text-gray-500 flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-teal-400"></i>
                    รีวิวเมื่อ: <?php echo date("d M Y", strtotime($row['ReviewDate'])); ?>
                </div>
            </div>

            <!-- Rating Stars -->
            <div class="text-2xl mb-3">
              <?php 
                $rating = $row['Rating'];
                for ($i = 0; $i < 5; $i++) {
                  if ($i < $rating) {
                    echo '<i class="fas fa-star star-color"></i>'; // Filled star
                  } else {
                    echo '<i class="far fa-star star-color opacity-50"></i>'; // Empty star
                  }
                }
              ?>
            </div>

            <!-- Comment Content -->
            <div class="comment text-gray-700 leading-relaxed italic border-l-4 pl-4 ml-1 bg-gray-50 p-3 rounded-lg">
                <p><?php echo nl2br(htmlspecialchars($row['Comment'])); ?></p>
            </div>

            <!-- Action Buttons -->
            <div class="mt-5 flex space-x-3">
              <a href="edit_review.php?id=<?php echo $row['ReviewID']; ?>" 
                 class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-gray-900 font-semibold rounded-lg transition duration-200 flex items-center shadow-md">
                <i class="fas fa-edit mr-2"></i> แก้ไข
              </a>
              <!-- NOTE: The use of window.confirm() is kept for functional continuity but should ideally be replaced with a custom modal in a full production app. -->
              <a href="delete_review.php?id=<?php echo $row['ReviewID']; ?>"
                 class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg transition duration-200 flex items-center shadow-md"
                 onclick="return confirm('แน่ใจหรือไม่ว่าต้องการลบรีวิวนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้');">
                <i class="fas fa-trash-alt mr-2"></i> ลบ
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <!-- Empty State (Modernized) -->
      <div class="bg-white rounded-xl shadow-lg p-10 text-center border-t-4 border-teal-500/50">
          <i class="far fa-comment-dots text-teal-500 text-5xl mb-4"></i>
          <p class="text-xl font-semibold text-gray-700 mt-2">คุณยังไม่ได้ให้รีวิวสนามใดๆ เลย</p>
          <p class="text-gray-500 mt-2">เมื่อจองและใช้บริการสนามเสร็จสิ้นแล้ว อย่าลืมมาแบ่งปันประสบการณ์ของคุณนะครับ/คะ</p>
          <a href="my_bookings.php" class="mt-5 inline-block bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300 shadow-md">
              ดูรายการจองที่รอรีวิว <i class="fas fa-arrow-right ml-2"></i>
          </a>
      </div>
    <?php endif; ?>
  </div>
</section>
<!-- END SECTION: My Reviews -->

<!-- JavaScript for Interactivity -->
<script>
// Mobile Menu Toggle
function toggleMenu() {
  const menu = document.getElementById('mobile-menu');
  menu.style.display = menu.style.display === 'flex' ? 'none' : 'flex';
}
</script>

</body>
</html>
