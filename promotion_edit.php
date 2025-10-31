<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';

// ✅ ตรวจสอบว่ามี id ที่จะใช้แก้ไขไหม
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ ไม่พบรหัสโปรโมชั่น");
}

$id = (int)$_GET['id'];

// ✅ ดึงข้อมูลโปรโมชั่นจากฐานข้อมูล
$sql = "SELECT * FROM Tbl_Promotion WHERE PromotionID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("❌ ไม่พบโปรโมชั่นนี้");
}
$promo = $result->fetch_assoc();

// ✅ เมื่อกดอัปเดต
if (isset($_POST['update_promo'])) {
    $code = trim($_POST['PromoCode']);
    $name = trim($_POST['PromoName']);
    $desc = trim($_POST['Description']);
    $type = $_POST['DiscountType'];
    $value = floatval($_POST['DiscountValue']);
    $start = $_POST['StartDate'];
    $end = $_POST['EndDate'];
    $conditions = trim($_POST['Conditions']);

    $sql_update = "UPDATE Tbl_Promotion 
                   SET PromoCode=?, PromoName=?, Description=?, DiscountType=?, DiscountValue=?, StartDate=?, EndDate=?, Conditions=? 
                   WHERE PromotionID=?";
    $stmt_upd = $conn->prepare($sql_update);
    $stmt_upd->bind_param("ssssssssi", $code, $name, $desc, $type, $value, $start, $end, $conditions, $id);
    $stmt_upd->execute();

    echo "<script>alert('✅ อัปเดตโปรโมชั่นเรียบร้อยแล้ว'); window.location='promotion_manage.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แก้ไขโปรโมชั่น - CY Arena</title>
<style>
body {
  font-family: "Prompt", sans-serif;
  background: #f1f5f9;
  color: #1e293b;
  margin: 0;
}
.container {
  max-width: 700px;
  margin: 50px auto;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
  padding: 30px 40px;
}
h1 {
  text-align: center;
  color: #0f172a;
  margin-bottom: 25px;
}
label {
  font-weight: 600;
  display: block;
  margin-top: 12px;
}
input, textarea, select {
  width: 100%;
  padding: 10px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  margin-top: 6px;
}
button {
  margin-top: 20px;
  padding: 12px 18px;
  border: none;
  background: #3b82f6;
  color: white;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
}
button:hover {
  background: #2563eb;
}
.back-btn {
  display: inline-block;
  text-decoration: none;
  background: #94a3b8;
  color: white;
  padding: 10px 16px;
  border-radius: 8px;
  margin-top: 10px;
}
.back-btn:hover {
  background: #64748b;
}
</style>
</head>
<body>

<div class="container">
  <h1>✏️ แก้ไขโปรโมชั่น</h1>

  <form method="POST">
    <label>ชื่อโปรโมชั่น</label>
    <input type="text" name="PromoName" value="<?php echo htmlspecialchars($promo['PromoName']); ?>" required>

    <label>รหัสโปรโมชั่น</label>
    <input type="text" name="PromoCode" value="<?php echo htmlspecialchars($promo['PromoCode']); ?>" required>

    <label>คำอธิบาย</label>
    <textarea name="Description" rows="3"><?php echo htmlspecialchars($promo['Description']); ?></textarea>

    <label>ประเภทส่วนลด</label>
    <select name="DiscountType" required>
      <option value="percent" <?php if($promo['DiscountType']=='percent') echo 'selected'; ?>>เปอร์เซ็นต์ (%)</option>
      <option value="fixed" <?php if($promo['DiscountType']=='fixed') echo 'selected'; ?>>จำนวนเงิน (บาท)</option>
    </select>

    <label>มูลค่าส่วนลด</label>
    <input type="number" step="0.01" name="DiscountValue" value="<?php echo $promo['DiscountValue']; ?>" required>

    <label>วันเริ่มต้น</label>
    <input type="datetime-local" name="StartDate" value="<?php echo date('Y-m-d\TH:i', strtotime($promo['StartDate'])); ?>" required>

    <label>วันสิ้นสุด</label>
    <input type="datetime-local" name="EndDate" value="<?php echo date('Y-m-d\TH:i', strtotime($promo['EndDate'])); ?>" required>

    <label>เงื่อนไข (ถ้ามี)</label>
    <textarea name="Conditions" rows="2"><?php echo htmlspecialchars($promo['Conditions']); ?></textarea>

    <button type="submit" name="update_promo">💾 บันทึกการแก้ไข</button>
    <a href="promotion_manage.php" class="back-btn">⬅ กลับ</a>
  </form>
</div>

</body>
</html>
