<?php
session_start();
include 'db_connect.php';

// ดึงข้อมูลโปรโมชั่นทั้งหมด
$sql = "SELECT * FROM Tbl_promotion ORDER BY StartDate DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🎁 โปรโมชั่นทั้งหมด - CY Arena</title>
<style>
body {
  font-family: "Prompt", sans-serif;
  background: #f9fafb;
  margin: 0;
  color: #1e293b;
}
.container {
  max-width: 900px;
  margin: 40px auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  padding: 35px 40px;
}
h1 {
  color: #0f172a;
  margin-bottom: 20px;
}
.promo-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 18px 25px;
  margin-bottom: 20px;
  background: #f8fafc;
  transition: transform 0.2s;
}
.promo-card:hover {
  transform: translateY(-3px);
}
.promo-card h2 {
  color: #0f172a;
  margin: 0;
}
.promo-card p {
  margin: 8px 0;
  color: #475569;
}
.badge {
  display: inline-block;
  background: #f59e0b;
  color: white;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 8px;
}
.date {
  color: #94a3b8;
  font-size: 14px;
}
</style>
</head>
<body>

<div class="container">
  <h1>🎉 โปรโมชั่นทั้งหมด</h1>

  <?php if ($result->num_rows > 0): ?>
    <?php while ($promo = $result->fetch_assoc()): ?>
      <div class="promo-card">
        <span class="badge">โค้ด: <?php echo htmlspecialchars($promo['PromoCode']); ?></span>
        <h2><?php echo htmlspecialchars($promo['PromoName']); ?></h2>
        <p><?php echo nl2br(htmlspecialchars($promo['Description'])); ?></p>

        <p>
          💸 ส่วนลด:
          <strong>
            <?php
              echo $promo['DiscountType'] == 'percent'
                ? $promo['DiscountValue'] . '%'
                : number_format($promo['DiscountValue']) . ' บาท';
            ?>
          </strong>
        </p>

        <p class="date">
          📅 ใช้ได้ตั้งแต่ 
          <?php echo date("d/m/Y", strtotime($promo['StartDate'])); ?> 
          ถึง 
          <?php echo date("d/m/Y", strtotime($promo['EndDate'])); ?>
        </p>

        <?php if (!empty($promo['Conditions'])): ?>
          <p style="font-size:14px; color:#64748b;">📝 เงื่อนไข: <?php echo nl2br(htmlspecialchars($promo['Conditions'])); ?></p>
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>ยังไม่มีโปรโมชั่นในขณะนี้</p>
  <?php endif; ?>
</div>

</body>
</html>
<?php