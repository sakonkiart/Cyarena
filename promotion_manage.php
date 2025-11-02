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

// ✅ ดึงข้อมูลโปรโมชั่นทั้งหมด
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
<title>🎁 จัดการโปรโมชั่น - CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --primary: #2563eb;
  --primary-dark: #1e40af;
  --primary-light: #3b82f6;
  --secondary: #eab308;
  --success: #16a34a;
  --danger: #ef4444;
  --warning: #f59e0b;
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-700: #374151;
  --gray-900: #111827;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Kanit', sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  padding: 40px 20px;
  color: var(--gray-900);
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  text-align: center;
  margin-bottom: 40px;
  animation: fadeInDown 0.6s ease-out;
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

.header-title {
  font-size: 3rem;
  font-weight: 800;
  color: white;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
}

.header-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 1.1rem;
  font-weight: 500;
}

/* Form Section */
.form-card {
  background: white;
  border-radius: 24px;
  padding: 40px;
  margin-bottom: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.form-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 30px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 20px;
  border-bottom: 3px solid var(--primary);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 8px;
  font-size: 0.95rem;
}

label i {
  color: var(--primary);
  font-size: 1.1rem;
}

label .required {
  color: var(--danger);
  font-weight: 800;
  margin-left: 4px;
}

input, select, textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--gray-200);
  border-radius: 12px;
  font-family: 'Kanit', sans-serif;
  font-size: 1rem;
  transition: all 0.3s;
  background: white;
}

input:focus, select:focus, textarea:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

textarea {
  resize: vertical;
  min-height: 100px;
}

.btn-submit {
  background: linear-gradient(135deg, var(--success) 0%, #22c55e 100%);
  color: white;
  border: none;
  padding: 14px 32px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1.05rem;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4);
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
}

.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(22, 163, 74, 0.5);
}

.btn-submit:disabled {
  background: var(--gray-200);
  cursor: not-allowed;
  box-shadow: none;
}

.error-message {
  background: #fee2e2;
  border: 2px solid var(--danger);
  border-radius: 12px;
  padding: 12px 16px;
  color: #991b1b;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  display: none;
}

.error-message.show {
  display: flex;
}

/* Table Section */
.table-card {
  background: white;
  border-radius: 24px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.8s ease-out;
  overflow-x: auto;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 25px;
  padding-bottom: 20px;
  border-bottom: 3px solid var(--primary);
}

.table-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 12px;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.95rem;
}

thead {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  color: white;
}

thead th {
  padding: 16px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

thead th:first-child {
  border-radius: 12px 0 0 0;
}

thead th:last-child {
  border-radius: 0 12px 0 0;
}

tbody tr {
  border-bottom: 1px solid var(--gray-100);
  transition: all 0.3s;
}

tbody tr:hover {
  background: var(--gray-50);
  transform: scale(1.01);
}

tbody td {
  padding: 16px 12px;
  color: var(--gray-700);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
}

.status-active {
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  color: #065f46;
  border: 2px solid #10b981;
}

.status-upcoming {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: #1e40af;
  border: 2px solid #3b82f6;
}

.status-expired {
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  color: #991b1b;
  border: 2px solid #ef4444;
}

.action-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.3s;
  border: none;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-success {
  background: linear-gradient(135deg, var(--success), #22c55e);
  color: white;
  box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
}

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
}

.btn-warning {
  background: linear-gradient(135deg, var(--warning), #fbbf24);
  color: white;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, var(--danger), #dc2626);
  color: white;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-back {
  background: white;
  color: var(--primary);
  border: 2px solid var(--primary);
  padding: 12px 24px;
  font-size: 1rem;
  margin-top: 30px;
}

.btn-back:hover {
  background: var(--primary);
  color: white;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--gray-700);
}

.empty-state i {
  font-size: 4rem;
  color: var(--gray-200);
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .header-title {
    font-size: 2rem;
  }
  
  .form-card, .table-card {
    padding: 24px;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  table {
    font-size: 0.85rem;
  }
  
  .action-btns {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Header -->
  <div class="header">
    <h1 class="header-title">
      <i class="fas fa-gift"></i>
      จัดการโปรโมชั่น
    </h1>
    <p class="header-subtitle">สร้างและจัดการโปรโมชั่นส่วนลดสำหรับลูกค้า</p>
  </div>

  <!-- Form Section -->
  <div class="form-card">
    <h2 class="form-title">
      <i class="fas fa-plus-circle"></i>
      เพิ่มโปรโมชั่นใหม่
    </h2>
    
    <form method="POST" action="promotion_save.php" id="promoForm">
      <div class="form-grid">
        <div class="form-group">
          <label>
            <i class="fas fa-tag"></i>
            ชื่อโปรโมชั่น
            <span class="required">*</span>
          </label>
          <input type="text" name="PromoName" placeholder="เช่น ส่วนลดฤดูร้อน" required>
        </div>

        <div class="form-group">
          <label>
            <i class="fas fa-ticket-alt"></i>
            รหัสโปรโมชั่น
            <span class="required">*</span>
          </label>
          <input type="text" name="PromoCode" placeholder="เช่น SUMMER2025" required>
        </div>

        <div class="form-group">
          <label>
            <i class="fas fa-percentage"></i>
            ประเภทส่วนลด
            <span class="required">*</span>
          </label>
          <select name="DiscountType" required>
            <option value="percent">เปอร์เซ็นต์ (%)</option>
            <option value="fixed">จำนวนเงิน (฿)</option>
          </select>
        </div>

        <div class="form-group">
          <label>
            <i class="fas fa-dollar-sign"></i>
            มูลค่าส่วนลด
            <span class="required">*</span>
          </label>
          <input type="number" name="DiscountValue" step="0.01" placeholder="เช่น 20 หรือ 100" required>
        </div>

        <div class="form-group full-width">
          <label>
            <i class="fas fa-align-left"></i>
            คำอธิบาย
            <span class="required">*</span>
          </label>
          <textarea name="Description" rows="3" placeholder="อธิบายรายละเอียดโปรโมชั่นให้ลูกค้าเข้าใจง่าย" required></textarea>
        </div>

        <div class="form-group">
          <label>
            <i class="fas fa-calendar-alt"></i>
            วันเริ่มต้น
            <span class="required">*</span>
          </label>
          <input type="datetime-local" name="StartDate" required>
        </div>

        <div class="form-group">
          <label>
            <i class="fas fa-calendar-check"></i>
            วันสิ้นสุด
            <span class="required">*</span>
          </label>
          <input type="datetime-local" name="EndDate" required>
        </div>

        <div class="form-group full-width">
          <label>
            <i class="fas fa-info-circle"></i>
            เงื่อนไขการใช้งาน
            <span class="required">*</span>
          </label>
          <textarea name="Conditions" rows="3" placeholder="กรอกเงื่อนไขตามประเภทที่ต้องการ..." required></textarea>
          
          <div style="margin-top: 12px; padding: 16px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 2px solid #3b82f6; border-radius: 12px;">
            <div style="font-weight: 700; color: #1e40af; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-lightbulb" style="font-size: 1.2rem;"></i>
              💡 เงื่อนไขพิเศษที่รองรับ (ต้องกรอกตามตัวอย่าง)
            </div>
            
            <div style="display: grid; gap: 12px;">
              <div style="background: white; padding: 12px; border-radius: 8px; border-left: 4px solid #16a34a;">
                <strong style="color: #16a34a;">🎯 จองครั้งแรก:</strong> 
                <code style="background: #f0fdf4; padding: 2px 8px; border-radius: 4px; color: #15803d; font-weight: 600;">จองครั้งแรกลดเลยทันที</code>
              </div>
              
              <div style="background: white; padding: 12px; border-radius: 8px; border-left: 4px solid #2563eb;">
                <strong style="color: #2563eb;">⏰ จองก่อน 18:00:</strong> 
                <code style="background: #eff6ff; padding: 2px 8px; border-radius: 4px; color: #1e40af; font-weight: 600;">จองก่อน 18:00 ลดเลยทันที</code>
              </div>
              
              <div style="background: white; padding: 12px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <strong style="color: #f59e0b;">⭐ ส่วนลดพิเศษ:</strong> 
                <code style="background: #fffbeb; padding: 2px 8px; border-radius: 4px; color: #92400e; font-weight: 600;">โค้ดส่วนลดพิเศษมาแล้ว</code>
              </div>
            </div>
          </div>
          
          <div class="error-message" id="conditionError">
            <i class="fas fa-exclamation-triangle"></i>
            <span>กรุณากรอกเงื่อนไขการใช้งาน</span>
          </div>
        </div>

      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <i class="fas fa-save"></i>
        บันทึกโปรโมชั่น
      </button>
    </form>
  </div>

  <!-- Table Section -->
  <div class="table-card">
    <div class="table-header">
      <h2 class="table-title">
        <i class="fas fa-list"></i>
        รายการโปรโมชั่นทั้งหมด
      </h2>
    </div>

    <?php if ($result->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th>ชื่อโปรโมชั่น</th>
            <th>รหัส</th>
            <th style="width: 100px;">ประเภท</th>
            <th style="width: 100px;">ส่วนลด</th>
            <th style="width: 140px;">สถานะ</th>
            <th style="width: 300px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><strong>#<?php echo $row['PromotionID']; ?></strong></td>
              <td><strong><?php echo htmlspecialchars($row['PromoName']); ?></strong></td>
              <td><code style="background: #f3f4f6; padding: 4px 8px; border-radius: 6px; font-weight: 600;"><?php echo htmlspecialchars($row['PromoCode']); ?></code></td>
              <td><?php echo $row['DiscountType'] == 'percent' ? '📊 เปอร์เซ็นต์' : '💵 จำนวนเงิน'; ?></td>
              <td><strong><?php echo $row['DiscountType'] == 'percent' ? $row['DiscountValue'].'%' : '฿'.$row['DiscountValue']; ?></strong></td>
              <td>
                <span class="status-badge status-<?php echo $row['StatusPromo']; ?>">
                  <?php
                    if ($row['StatusPromo'] == 'active') echo "🟢 กำลังใช้งาน";
                    elseif ($row['StatusPromo'] == 'upcoming') echo "🔵 รอเริ่ม";
                    else echo "🔴 หมดอายุ";
                  ?>
                </span>
              </td>
              <td>
                <div class="action-btns">
                  <?php if ($row['StatusPromo'] == 'upcoming' || $row['StatusPromo'] == 'expired'): ?>
                    <a href="?start=<?php echo $row['PromotionID']; ?>" 
                       class="btn btn-success" 
                       onclick="return confirm('เริ่มใช้งานโปรโมชั่นนี้ทันทีหรือไม่?')">
                       <i class="fas fa-play"></i> เริ่มใช้งาน
                    </a>
                  <?php endif; ?>
                  
                  <?php if ($row['StatusPromo'] == 'active'): ?>
                    <a href="?stop=<?php echo $row['PromotionID']; ?>" 
                       class="btn btn-warning" 
                       onclick="return confirm('หยุดใช้งานโปรโมชั่นนี้ทันทีหรือไม่?')">
                       <i class="fas fa-stop"></i> หยุดใช้งาน
                    </a>
                  <?php endif; ?>
                  
                  <a href="promotion_edit.php?id=<?php echo $row['PromotionID']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> แก้ไข
                  </a>
                  
                  <a href="?delete=<?php echo $row['PromotionID']; ?>" 
                     class="btn btn-danger" 
                     onclick="return confirm('แน่ใจหรือไม่ว่าต้องการลบโปรโมชั่นนี้?')">
                     <i class="fas fa-trash"></i> ลบ
                  </a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p style="font-size: 1.2rem; margin-top: 10px; font-weight: 600;">ยังไม่มีโปรโมชั่น</p>
        <p style="color: #9ca3af;">เพิ่มโปรโมชั่นใหม่จากฟอร์มด้านบน</p>
      </div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-back">
      <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
    </a>
  </div>
</div>

<script>
// ✅ Form Validation - บังคับกรอกเงื่อนไข
document.getElementById('promoForm').addEventListener('submit', function(e) {
  const conditions = document.querySelector('textarea[name="Conditions"]').value.trim();
  const errorMsg = document.getElementById('conditionError');
  
  if (conditions === '') {
    e.preventDefault();
    errorMsg.classList.add('show');
    document.querySelector('textarea[name="Conditions"]').focus();
    document.querySelector('textarea[name="Conditions"]').style.borderColor = 'var(--danger)';
    return false;
  } else {
    errorMsg.classList.remove('show');
    document.querySelector('textarea[name="Conditions"]').style.borderColor = '';
  }
  
  // Validate dates
  const startDate = new Date(document.querySelector('input[name="StartDate"]').value);
  const endDate = new Date(document.querySelector('input[name="EndDate"]').value);
  
  if (endDate <= startDate) {
    e.preventDefault();
    alert('⚠️ วันสิ้นสุดต้องมาหลังวันเริ่มต้น');
    return false;
  }
});

// Clear error on input
document.querySelector('textarea[name="Conditions"]').addEventListener('input', function() {
  if (this.value.trim() !== '') {
    document.getElementById('conditionError').classList.remove('show');
    this.style.borderColor = '';
  }
});
</script>

</body>
</html>
