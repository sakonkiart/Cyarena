<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

// ✅ เริ่มใช้งานโปรโมชั่นทันที
if (isset($_GET['start'])) {
    $id = (int)$_GET['start'];
    $sql = "UPDATE Tbl_Promotion 
            SET StartDate = CURRENT_TIMESTAMP,
                EndDate = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 YEAR)
            WHERE PromotionID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: promotion_manage.php");
    exit;
}

// ✅ หยุดใช้งานโปรโมชั่นทันที
if (isset($_GET['stop'])) {
    $id = (int)$_GET['stop'];
    $sql = "UPDATE Tbl_Promotion 
            SET EndDate = CURRENT_TIMESTAMP
            WHERE PromotionID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: promotion_manage.php");
    exit;
}

// ✅ ลบโปรโมชั่น
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Tbl_Promotion WHERE PromotionID = $id");
    header("Location: promotion_manage.php");
    exit;
}

// ✅ ดึงข้อมูลโปรโมชั่นทั้งหมด พร้อมคำนวณสถานะแบบเรียลไทม์
$sql = "SELECT *, 
        CASE 
            WHEN CURRENT_TIMESTAMP BETWEEN StartDate AND EndDate THEN 'active'
            WHEN CURRENT_TIMESTAMP < StartDate THEN 'upcoming'
            ELSE 'expired'
        END AS StatusPromo
        FROM Tbl_Promotion
        ORDER BY StartDate DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการโปรโมชั่น</title>
<style>
body {
  font-family: "Prompt", sans-serif;
  margin: 0;
  background: #f8fafc;
  color: #1e293b;
}
.container {
  max-width: 1200px;
  margin: 40px auto;
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
h1 {
  text-align: center;
  color: #0f172a;
  margin-bottom: 25px;
}
.btn {
  display: inline-block;
  background: #3b82f6;
  color: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: 0.2s;
  font-size: 13px;
  margin: 2px;
  white-space: nowrap;
}
.btn:hover { background: #2563eb; }
.btn-danger {
  background: #ef4444;
}
.btn-danger:hover { background: #dc2626; }
.btn-success {
  background: #16a34a;
}
.btn-success:hover { background: #15803d; }
.btn-warning {
  background: #f59e0b;
}
.btn-warning:hover { background: #d97706; }

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  font-size: 14px;
}
th, td {
  padding: 12px 8px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}
th {
  background: #f1f5f9;
  font-size: 13px;
}
.status-active {
  color: #16a34a;
  font-weight: bold;
}
.status-upcoming {
  color: #0ea5e9;
  font-weight: bold;
}
.status-expired {
  color: #dc2626;
  font-weight: bold;
}
.form-section {
  margin-bottom: 35px;
  border: 1px solid #e2e8f0;
  padding: 20px;
  border-radius: 10px;
  background: #f9fafb;
}
input, select, textarea {
  width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-family: "Prompt", sans-serif;
  box-sizing: border-box;
}
label {
  font-weight: 600;
  margin-bottom: 6px;
  display: block;
  margin-top: 10px;
}
button {
  background: #16a34a;
  color: #fff;
  border: none;
  padding: 10px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
button:hover {
  background: #15803d;
}
.action-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
</head>
<body>

<div class="container">
  <h1>🎁 จัดการโปรโมชั่น</h1>

  <!-- ✅ ฟอร์มเพิ่มโปรโมชั่น -->
  <div class="form-section">
    <h2>➕ เพิ่มโปรโมชั่นใหม่</h2>
    <form method="POST" action="promotion_save.php">
      <label>ชื่อโปรโมชั่น</label>
      <input type="text" name="PromoName" required>

      <label>รหัสโปรโมชั่น</label>
      <input type="text" name="PromoCode" required>

      <label>คำอธิบาย</label>
      <textarea name="Description" rows="3"></textarea>

      <label>ประเภทส่วนลด</label>
      <select name="DiscountType" required>
        <option value="percent">เปอร์เซ็นต์ (%)</option>
        <option value="fixed">จำนวนเงิน (฿)</option>
      </select>

      <label>มูลค่าส่วนลด</label>
      <input type="number" name="DiscountValue" step="0.01" required>

      <label>วันเริ่มต้น</label>
      <input type="datetime-local" name="StartDate" required>

      <label>วันสิ้นสุด</label>
      <input type="datetime-local" name="EndDate" required>

      <label>เงื่อนไขการใช้งาน</label>
      <textarea name="Conditions" rows="3"></textarea>
     
      <button type="submit" style="margin-top:16px; display:inline-block;">💾 บันทึกโปรโมชั่น</button>

    </form>
  </div>

  <!-- ✅ ตารางแสดงโปรโมชั่น -->
  <h2>📋 รายการโปรโมชั่นทั้งหมด</h2>
  <table>
    <tr>
      <th style="width: 50px;">#</th>
      <th>ชื่อโปรโมชั่น</th>
      <th>รหัส</th>
      <th style="width: 80px;">ประเภท</th>
      <th style="width: 80px;">ส่วนลด</th>
      <th style="width: 120px;">สถานะ</th>
      <th style="width: 300px;">จัดการ</th>
    </tr>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['PromotionID']; ?></td>
          <td><?php echo htmlspecialchars($row['PromoName']); ?></td>
          <td><?php echo htmlspecialchars($row['PromoCode']); ?></td>
          <td><?php echo htmlspecialchars($row['DiscountType']); ?></td>
          <td><?php echo htmlspecialchars($row['DiscountValue']); ?></td>
          <td class="status-<?php echo $row['StatusPromo']; ?>">
            <?php
              if ($row['StatusPromo'] == 'active') echo "🟢 กำลังใช้งาน";
              elseif ($row['StatusPromo'] == 'upcoming') echo "🔵 รอเริ่ม";
              else echo "🔴 หมดอายุ";
            ?>
          </td>
          <td>
            <div class="action-btns">
              <?php if ($row['StatusPromo'] == 'upcoming' || $row['StatusPromo'] == 'expired'): ?>
                <a href="?start=<?php echo $row['PromotionID']; ?>" 
                   class="btn btn-success" 
                   onclick="return confirm('เริ่มใช้งานโปรโมชั่นนี้ทันทีหรือไม่?')">
                   🟢 เริ่มใช้งาน
                </a>
              <?php endif; ?>
              
              <?php if ($row['StatusPromo'] == 'active'): ?>
                <a href="?stop=<?php echo $row['PromotionID']; ?>" 
                   class="btn btn-warning" 
                   onclick="return confirm('หยุดใช้งานโปรโมชั่นนี้ทันทีหรือไม่?')">
                   🔴 หยุดใช้งาน
                </a>
              <?php endif; ?>
              
              <a href="promotion_edit.php?id=<?php echo $row['PromotionID']; ?>" class="btn">✏️ แก้ไข</a>
              <a href="?delete=<?php echo $row['PromotionID']; ?>" 
                 class="btn btn-danger" 
                 onclick="return confirm('แน่ใจหรือไม่ว่าต้องการลบโปรโมชั่นนี้?')">
                 🗑️ ลบ
              </a>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="7" style="text-align:center;">ไม่มีโปรโมชั่น</td></tr>
    <?php endif; ?>
  </table>

  <br><a href="dashboard.php" class="btn">⬅ กลับหน้าหลัก</a>
</div>

</body>
</html>
