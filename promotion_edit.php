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

// ✅ เมื่อกดอัปเดต (ไม่แก้ไขวันที่)
if (isset($_POST['update_promo'])) {
    $code = trim($_POST['PromoCode']);
    $name = trim($_POST['PromoName']);
    $desc = trim($_POST['Description']);
    $type = $_POST['DiscountType'];
    $value = floatval($_POST['DiscountValue']);
    $conditions = trim($_POST['Conditions']);

    $sql_update = "UPDATE Tbl_Promotion 
                   SET PromoCode=?, PromoName=?, Description=?, DiscountType=?, DiscountValue=?, Conditions=? 
                   WHERE PromotionID=?";
    $stmt_upd = $conn->prepare($sql_update);
    $stmt_upd->bind_param("sssdssi", $code, $name, $desc, $type, $value, $conditions, $id);
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
  padding: 2rem 1rem;
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
    radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
  pointer-events: none;
  z-index: 0;
}

.container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  border-radius: 24px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  position: relative;
  z-index: 1;
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

.header {
  background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
  padding: 2.5rem 2rem;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.header::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 0.5; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.header-content {
  position: relative;
  z-index: 1;
}

.header-icon {
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.5rem;
  margin: 0 auto 1rem;
  border: 3px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

h1 {
  font-family: 'Kanit', sans-serif;
  color: white;
  font-size: 2rem;
  font-weight: 800;
  margin: 0;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.95rem;
  margin-top: 0.5rem;
  font-weight: 500;
}

.form-container {
  padding: 2.5rem 2rem;
}

.form-grid {
  display: grid;
  gap: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

label {
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
}

.label-icon {
  font-size: 1.1rem;
}

.required {
  color: var(--danger);
  font-weight: 800;
}

input, textarea, select {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: 12px;
  font-size: 1rem;
  font-family: 'Sarabun', sans-serif;
  transition: all 0.3s;
  background: var(--gray-50);
}

input:focus, textarea:focus, select:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
  transform: translateY(-2px);
}

textarea {
  resize: vertical;
  min-height: 100px;
  line-height: 1.6;
}

select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 1.25rem;
  padding-right: 3rem;
}

.input-hint {
  font-size: 0.85rem;
  color: var(--gray-700);
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.discount-preview {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border: 2px solid var(--primary-light);
  border-radius: 12px;
  padding: 1rem;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.discount-preview-icon {
  font-size: 2rem;
}

.discount-preview-text {
  font-weight: 700;
  color: var(--primary-dark);
  font-size: 1.1rem;
}

.button-group {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 2px solid var(--gray-200);
}

.btn {
  flex: 1;
  padding: 1rem 1.5rem;
  border: none;
  border-radius: 12px;
  font-weight: 800;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  text-decoration: none;
  font-family: 'Sarabun', sans-serif;
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.5);
}

.btn-secondary {
  background: white;
  color: var(--gray-700);
  border: 2px solid var(--gray-300);
}

.btn-secondary:hover {
  background: var(--gray-50);
  border-color: var(--primary);
  color: var(--primary);
  transform: translateY(-2px);
}

.info-box {
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 2px solid #f59e0b;
  border-radius: 12px;
  padding: 1rem;
  margin-top: 0.5rem;
}

.info-box-title {
  font-weight: 700;
  color: #92400e;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.info-box-content {
  font-size: 0.9rem;
  color: #78350f;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .button-group {
    flex-direction: column-reverse;
  }
  
  .header {
    padding: 2rem 1.5rem;
  }
  
  h1 {
    font-size: 1.5rem;
  }
  
  .form-container {
    padding: 2rem 1.5rem;
  }
}
</style>
</head>
<body>

<div class="container">
  <div class="header">
    <div class="header-content">
      <div class="header-icon">✏️</div>
      <h1>แก้ไขโปรโมชั่น</h1>
      <div class="subtitle">อัปเดตข้อมูลโปรโมชั่นและส่วนลดสำหรับลูกค้า</div>
    </div>
  </div>

  <div class="form-container">
    <form method="POST" id="promoForm">
      <div class="form-grid">
        
        <!-- Row 1: ชื่อโปรโมชั่น และ รหัสโปรโมชั่น -->
        <div class="form-row">
          <div class="form-group">
            <label>
              <span class="label-icon">🎁</span>
              ชื่อโปรโมชั่น
              <span class="required">*</span>
            </label>
            <input type="text" name="PromoName" value="<?php echo htmlspecialchars($promo['PromoName']); ?>" required placeholder="เช่น โปรส่วนลดวันธรรมดา">
          </div>

          <div class="form-group">
            <label>
              <span class="label-icon">🏷️</span>
              รหัสโปรโมชั่น
              <span class="required">*</span>
            </label>
            <input type="text" name="PromoCode" value="<?php echo htmlspecialchars($promo['PromoCode']); ?>" required placeholder="เช่น WEEKDAY20" style="text-transform: uppercase;">
            <div class="input-hint">💡 ลูกค้าจะใช้รหัสนี้ในการรับส่วนลด</div>
          </div>
        </div>

        <!-- คำอธิบาย -->
        <div class="form-group full-width">
          <label>
            <span class="label-icon">📝</span>
            คำอธิบาย
          </label>
          <textarea name="Description" rows="3" placeholder="อธิบายรายละเอียดโปรโมชั่นให้ลูกค้าเข้าใจง่าย"><?php echo htmlspecialchars($promo['Description']); ?></textarea>
        </div>

        <!-- Row 2: ประเภทส่วนลด และ มูลค่า -->
        <div class="form-row">
          <div class="form-group">
            <label>
              <span class="label-icon">💰</span>
              ประเภทส่วนลด
              <span class="required">*</span>
            </label>
            <select name="DiscountType" id="discountType" required>
              <option value="percent" <?php if($promo['DiscountType']=='percent') echo 'selected'; ?>>เปอร์เซ็นต์ (%)</option>
              <option value="fixed" <?php if($promo['DiscountType']=='fixed') echo 'selected'; ?>>จำนวนเงิน (บาท)</option>
            </select>
          </div>

          <div class="form-group">
            <label>
              <span class="label-icon">💵</span>
              มูลค่าส่วนลด
              <span class="required">*</span>
            </label>
            <input type="number" step="0.01" name="DiscountValue" id="discountValue" value="<?php echo $promo['DiscountValue']; ?>" required placeholder="0.00">
            <div class="discount-preview" id="discountPreview">
              <span class="discount-preview-icon">🎉</span>
              <span class="discount-preview-text" id="previewText">ลด <?php echo $promo['DiscountType'] == 'percent' ? $promo['DiscountValue'].'%' : number_format($promo['DiscountValue'], 2).' บาท'; ?></span>
            </div>
          </div>
        </div>

        <!-- เงื่อนไขการใช้งาน -->
        <div class="form-group full-width">
          <label>
            <span class="label-icon">📋</span>
            เงื่อนไขการใช้งาน
          </label>
          <textarea name="Conditions" rows="4" placeholder="ระบุเงื่อนไขการใช้งาน เช่น ใช้ได้เฉพาะวันธรรมดา, จองขั้นต่ำ 2 ชั่วโมง"><?php echo htmlspecialchars($promo['Conditions']); ?></textarea>
          
          <div class="info-box">
            <div class="info-box-title">
              💡 เงื่อนไขพิเศษที่รองรับ
            </div>
            <div class="info-box-content">
              • <strong>จองครั้งแรกลดเลยทันที</strong> → ใช้ได้เฉพาะครั้งแรก<br>
              • <strong>จองก่อน 18:00 ลดเลยทันที</strong> → เช็คเวลาเริ่มต้น<br>
              • <strong>โค้ดส่วนลดพิเศษมาแล้ว</strong> → ใช้ได้ทุกคน<br>
              • <strong>หรือเว้นว่าง</strong> → ไม่มีเงื่อนไข
            </div>
          </div>
        </div>

        <!-- หมายเหตุ: ไม่มีการแก้ไขวันที่ -->
        <div class="info-box">
          <div class="info-box-title">
            ⚠️ หมายเหตุสำคัญ
          </div>
          <div class="info-box-content">
            การแก้ไขนี้จะ<strong>ไม่เปลี่ยนวันเริ่มต้น-สิ้นสุด</strong> ของโปรโมชั่น<br>
            หากต้องการเปลี่ยนสถานะ กรุณาใช้ปุ่ม "เริ่มใช้งาน" หรือ "หยุดใช้งาน" ในหน้าจัดการโปรโมชั่น
          </div>
        </div>

      </div>

      <div class="button-group">
        <a href="promotion_manage.php" class="btn btn-secondary">
          ← ยกเลิก
        </a>
        <button type="submit" name="update_promo" class="btn btn-primary">
          💾 บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Update Discount Preview
function updatePreview() {
  const type = document.getElementById('discountType').value;
  const value = parseFloat(document.getElementById('discountValue').value) || 0;
  const previewText = document.getElementById('previewText');
  
  if (type === 'percent') {
    previewText.textContent = `ลด ${value}%`;
  } else {
    previewText.textContent = `ลด ${value.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})} บาท`;
  }
}

document.getElementById('discountType').addEventListener('change', updatePreview);
document.getElementById('discountValue').addEventListener('input', updatePreview);
</script>

</body>
</html>
