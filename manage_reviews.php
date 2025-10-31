<?php
session_start();

// ✅ ตรวจสอบสิทธิ์พนักงาน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';

// ✅ ถ้ามีการลบรีวิว
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Tbl_Review WHERE ReviewID = $delete_id");
    $message = "🗑️ ลบรีวิวเรียบร้อยแล้ว!";
}

// ✅ ดึงข้อมูลรีวิวทั้งหมด
$sql = "SELECT r.ReviewID, c.FirstName, v.VenueName, r.Rating, r.Comment, r.ReviewDate
        FROM Tbl_Review r
        JOIN Tbl_Customer c ON r.CustomerID = c.CustomerID
        JOIN Tbl_Venue v ON r.VenueID = v.VenueID
        ORDER BY r.ReviewDate DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการรีวิวลูกค้า | CY Arena</title>
<style>
body { font-family: "Prompt", sans-serif; background: #f4f7f6; margin: 0; }
.header { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
.container { max-width: 1000px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

.top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.top-bar h2 {
  margin: 0;
  color: #1e293b;
}

.back-btn {
  background: #3b82f6;
  color: white;
  text-decoration: none;
  padding: 10px 16px;
  border-radius: 8px;
  font-weight: 600;
  box-shadow: 0 3px 6px rgba(59,130,246,0.3);
  transition: 0.25s;
}
.back-btn:hover {
  background: #2563eb;
  transform: translateY(-2px);
}

table { width: 100%; border-collapse: collapse; border-radius: 10px; overflow: hidden; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #3498db; color: #fff; }
td { color: #1e293b; }

.action-btn { padding: 6px 10px; border-radius: 5px; text-decoration: none; color: white; font-weight: 600; }
.delete { background: #e74c3c; }
.delete:hover { background: #c0392b; }

.message { text-align: center; font-weight: bold; color: #27ae60; margin-bottom: 15px; }
</style>
</head>
<body>

<header class="header">
  <div><strong>CY Arena Admin</strong></div>
  <div>
    <span>ผู้ดูแล: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    <a href="logout.php" style="color:#fff;background:#e74c3c;padding:6px 12px;border-radius:5px;text-decoration:none;">ออกจากระบบ</a>
  </div>
</header>

<div class="container">

  <div class="top-bar">
    <h2>จัดการรีวิวลูกค้า</h2>
    <a href="dashboard.php" class="back-btn">⬅ กลับหน้า Dashboard</a>
  </div>

  <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>

  <table>
    <tr>
      <th>รหัส</th>
      <th>ลูกค้า</th>
      <th>สนาม</th>
      <th>คะแนน</th>
      <th>ความคิดเห็น</th>
      <th>วันที่รีวิว</th>
      <th>จัดการ</th>
    </tr>

    <?php if ($result->num_rows > 0): ?>
      <?php while($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['ReviewID']; ?></td>
          <td><?php echo htmlspecialchars($row['FirstName']); ?></td>
          <td><?php echo htmlspecialchars($row['VenueName']); ?></td>
          <td><?php echo str_repeat("⭐", $row['Rating']); ?> (<?php echo $row['Rating']; ?>/5)</td>
          <td><?php echo htmlspecialchars($row['Comment']); ?></td>
          <td><?php echo date("d/m/Y", strtotime($row['ReviewDate'])); ?></td>
          <td>
            <a href="?delete=<?php echo $row['ReviewID']; ?>" class="action-btn delete" onclick="return confirm('แน่ใจหรือไม่ว่าจะลบรีวิวนี้?');">ลบ</a>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="7" style="text-align:center;">ยังไม่มีรีวิว</td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>
