<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$customerName = $_SESSION['user_name'];

include 'db_connect.php';

if (!isset($_GET['venue_id']) || !is_numeric($_GET['venue_id'])) {
    die("Error: ไม่พบรหัสสนามกีฬา");
}
$venue_id = (int)$_GET['venue_id'];

$sql = "SELECT v.*, vt.TypeName
        FROM Tbl_Venue AS v
        JOIN Tbl_Venue_Type AS vt ON v.VenueTypeID = vt.VenueTypeID
        WHERE v.VenueID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $venue = $result->fetch_assoc();
} else {
    die("Error: ไม่พบสนามกีฬานี้ หรือสนามไม่พร้อมให้บริการ");
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองสนาม - <?php echo htmlspecialchars($venue['VenueName']); ?></title>
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
  color: #1e293b;
  padding-bottom: 40px;
}

.header {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  padding: 20px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo {
  font-size: 1.8rem;
  font-weight: 700;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.user-name {
  color: #475569;
  font-weight: 500;
}

.logout-btn {
  background: #ef4444;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.logout-btn:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.container {
  max-width: 800px;
  margin: 40px auto;
  padding: 0 20px;
}

.venue-card {
  background: white;
  border-radius: 20px;
  padding: 35px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  margin-bottom: 30px;
  animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.venue-header {
  border-bottom: 3px solid #3b82f6;
  padding-bottom: 20px;
  margin-bottom: 25px;
}

.venue-title {
  font-size: 2rem;
  color: #0f172a;
  margin-bottom: 10px;
  font-weight: 700;
}

.venue-type {
  display: inline-block;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  color: #1e40af;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
  border: 2px solid #bfdbfe;
}

.venue-details {
  display: grid;
  gap: 15px;
  margin-top: 20px;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 10px;
  border-left: 4px solid #3b82f6;
}

.detail-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: white;
  border-radius: 10px;
  font-size: 1.1rem;
}

.detail-content {
  flex: 1;
}

.detail-label {
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 3px;
}

.detail-value {
  font-size: 1.1rem;
  font-weight: 600;
  color: #0f172a;
}

.price-highlight {
  color: #10b981;
  font-size: 1.5rem;
}

.booking-card {
  background: white;
  border-radius: 20px;
  padding: 35px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  animation: slideUp 0.6s ease-out;
}

.section-title {
  font-size: 1.5rem;
  color: #0f172a;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e2e8f0;
}

.form-group {
  margin-bottom: 25px;
}

label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  margin-bottom: 10px;
  color: #1e40af;
  font-size: 1rem;
}

label i {
  font-size: 1.1rem;
}

input[type="date"],
input[type="number"],
input[type="text"],
select {
  width: 100%;
  padding: 14px 16px;
  border-radius: 10px;
  border: 2px solid #e2e8f0;
  font-size: 1rem;
  transition: all 0.3s;
  background: #f8fafc;
  font-family: "Prompt", sans-serif;
}

input:focus,
select:focus {
  border-color: #3b82f6;
  background: white;
  outline: none;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.time-row {
  display: flex;
  gap: 10px;
  align-items: center;
}

.time-row select {
  min-width: 80px;
}

.time-separator {
  font-size: 1.5rem;
  color: #64748b;
  font-weight: 600;
}

.readonly-field {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #475569;
  cursor: not-allowed;
}

.help-text {
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.help-text.error {
  color: #dc2626;
  font-weight: 600;
}

.promo-group {
  display: flex;
  gap: 12px;
}

.promo-group input {
  flex: 1;
}

.check-promo-btn {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
  border: none;
  padding: 14px 24px;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.check-promo-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

#promoResult {
  margin-top: 12px;
  padding: 12px;
  border-radius: 8px;
  font-weight: 500;
  display: none;
}

#promoResult.show {
  display: block;
}

#promoResult.success {
  background: #d1fae5;
  color: #065f46;
  border: 2px solid #10b981;
}

#promoResult.error {
  background: #fee2e2;
  color: #991b1b;
  border: 2px solid #ef4444;
}

.price-summary {
  background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
  border: 2px solid #0ea5e9;
  border-radius: 12px;
  padding: 20px;
  margin-top: 25px;
}

.price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 1rem;
  color: #0f172a;
}

.price-row.total {
  border-top: 2px solid #0ea5e9;
  padding-top: 12px;
  margin-top: 12px;
  font-size: 1.3rem;
  font-weight: 700;
  color: #0369a1;
}

.price-row .label {
  font-weight: 500;
}

.price-row .value {
  font-weight: 700;
}

.discount-value {
  color: #dc2626;
}

.submit-btn {
  width: 100%;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: white;
  padding: 16px;
  border-radius: 12px;
  border: none;
  font-weight: 700;
  font-size: 1.1rem;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 25px;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(30, 64, 175, 0.4);
  background: linear-gradient(135deg, #1e3a8a, #2563eb);
}

.submit-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
  box-shadow: none;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 25px;
  text-decoration: none;
  color: white;
  font-weight: 600;
  padding: 12px 20px;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 10px;
  transition: all 0.3s;
}

.back-link:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-5px);
}

@media (max-width: 768px) {
  .header {
    padding: 15px 20px;
    flex-direction: column;
    gap: 15px;
  }

  .logo {
    font-size: 1.5rem;
  }

  .venue-card,
  .booking-card {
    padding: 25px 20px;
  }

  .venue-title {
    font-size: 1.5rem;
  }

  .promo-group {
    flex-direction: column;
  }

  .check-promo-btn {
    width: 100%;
  }

  .time-row {
    flex-wrap: wrap;
  }
}
<script>
// 🎯 ตัวแปรสำหรับเก็บข้อมูลโปรโมชั่น
let currentPromoData = null;
const pricePerHour = <?php echo (float)$venue['PricePerHour']; ?>;

// 🔗 ตัวแปรสำหรับเก็บ DOM Elements และค่าคงที่ (Global Scope)
// ประกาศตัวแปรเหล่านี้ไว้ภายนอก IIFE เพื่อให้ฟังก์ชันที่ถูกย้ายออกมา (computeEnd) สามารถเข้าถึงได้
let dateEl, hh12El, mmEl, apEl, startHidden, hoursEl, endDisp, endHidden, startHelp, endHelp, submitBtn;
let open24, close24, totalPriceEl;
let basePriceEl, discountRowEl, discountEl, netPriceEl;


/* Time & Booking Logic (Utility Functions - คงเดิม) */
function pad2(n){ return String(n).padStart(2,'0'); }
function to12(hhmm){
  let [h,m]=hhmm.split(':').map(Number);
  const ampm = h>=12 ? 'PM':'AM';
  h = h%12; if(h===0) h=12;
  return `${pad2(h)}:${pad2(m)} ${ampm}`;
}
function to24_from_parts(h12, m, ap){
  let h = parseInt(h12||'0',10);
  if (isNaN(h)) return '';
  if (ap === 'PM' && h !== 12) h += 12;
  if (ap === 'AM' && h === 12) h = 0;
  return `${pad2(h)}:${pad2(parseInt(m||'0',10))}`;
}
function cmpTime(a,b){ return a===b?0:(a>b?1:-1); }
function addMinutes(hhmm, mins){
  let [h,m]=hhmm.split(':').map(Number);
  let t=h*60+m+mins;
  if (t<0) t=0;
  return `${pad2(Math.floor(t/60) % 24)}:${pad2(t%60)}`; // ปรับแก้ให้รองรับการข้ามวัน
}
function roundUpTo30(hhmm){
  let [h,m]=hhmm.split(':'.map(Number);
  const mins=h*60+m;
  const add=(30-(mins%30))%30;
  const next=mins+add;
  return `${pad2(Math.floor(next/60))}:${pad2(next%60)}`;
}
function nowHHMM(){
  const d=new Date();
  return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

// ----------------------------------------------------------------
// ⭐ computeEnd() ถูกย้ายมา Global Scope
// ----------------------------------------------------------------
function computeEnd(){
  // ตรวจสอบว่ามีการโหลด DOM Elements แล้วหรือยัง
  if (!startHidden) return; 

  endHelp.textContent=''; endHelp.classList.remove('error'); submitBtn.disabled=false;
  const st = startHidden.value;
  const hrs = parseFloat(hoursEl.value||'0');
  
  if (!st || !hrs || hrs<=0){ 
    endDisp.value=''; 
    endHidden.value=''; 
    totalPriceEl.value='';
    basePriceEl.textContent = '฿0.00';
    discountRowEl.style.display = 'none';
    netPriceEl.textContent = '฿0.00';
    return; 
  }
  
  const end24 = addMinutes(st, Math.round(hrs*60));
  endHidden.value = end24;
  endDisp.value = to12(end24);
  
  // 💰 คำนวณราคาต้นทุน
  let basePrice = hrs * pricePerHour;
  let finalPrice = basePrice;
  let discountAmount = 0;
  
  // 🎁 คำนวณส่วนลดถ้ามีโปรโมชั่น
  if (currentPromoData) {
    if (currentPromoData.discount_type === 'percent') {
      discountAmount = basePrice * (currentPromoData.discount_value / 100);
      finalPrice = basePrice - discountAmount;
    } else if (currentPromoData.discount_type === 'fixed') {
      discountAmount = currentPromoData.discount_value;
      finalPrice = basePrice - discountAmount;
    }
    finalPrice = Math.max(0, finalPrice); // ไม่ให้ติดลบ
    discountAmount = basePrice - finalPrice; // คำนวณส่วนลดที่แท้จริง
  }
  
  // ✅ อัปเดตการแสดงผล
  totalPriceEl.value = finalPrice.toFixed(2);
  basePriceEl.textContent = '฿' + basePrice.toFixed(2);
  netPriceEl.textContent = '฿' + finalPrice.toFixed(2);
  
  if (discountAmount > 0) {
    discountRowEl.style.display = 'flex';
    discountEl.textContent = '-฿' + discountAmount.toFixed(2);
  } else {
    discountRowEl.style.display = 'none';
  }

  // ตรวจสอบเวลาเกินปิดสนาม
  if (cmpTime(end24, close24) > 0){
    endHelp.innerHTML='<i class="fas fa-exclamation-circle"></i> เวลาเสร็จสิ้นเกินเวลาปิดสนาม โปรดปรับเวลาเริ่มหรือจำนวนชั่วโมง';
    endHelp.classList.add('error');
    submitBtn.disabled = true;
  }
}

// ----------------------------------------------------------------
// ฟังก์ชันตรวจสอบโปรโมชั่น (คงเดิม แต่ตอนนี้เรียก computeEnd ได้แล้ว)
// ----------------------------------------------------------------
function checkPromotion() {
  const code = document.getElementById('promoCode').value.trim();
  const resultEl = document.getElementById('promoResult');
  
  if (!code) {
    resultEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> กรุณากรอกรหัสโปรโมชั่น';
    resultEl.className = 'show error';
    currentPromoData = null;
    document.getElementById('promotion_id').value = '';
    computeEnd();
    return;
  }
  
  fetch('promotion_check.php?code=' + encodeURIComponent(code))
    .then(res => res.json())
    .then(data => {
      if (data.valid) {
        currentPromoData = data;
        resultEl.innerHTML = `<i class="fas fa-check-circle"></i> ใช้ได้: ส่วนลด <strong>${data.discount_text}</strong>`;
        resultEl.className = 'show success';
        if (data.promotion_id) {
          document.getElementById('promotion_id').value = data.promotion_id;
        }
        computeEnd();
      } else {
        currentPromoData = null;
        resultEl.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
        resultEl.className = 'show error';
        document.getElementById('promotion_id').value = '';
        computeEnd();
      }
    })
    .catch(() => {
      currentPromoData = null;
      resultEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
      resultEl.className = 'show error';
      computeEnd();
    });
}


// ----------------------------------------------------------------
// IIFE (สำหรับ Initialization และ Event Handlers)
// ----------------------------------------------------------------
(function(){
  // 1. กำหนดค่าให้ตัวแปร Global
  dateEl = document.getElementById('booking_date');
  hh12El = document.getElementById('hh12');
  mmEl = document.getElementById('mm');
  apEl = document.getElementById('ampm');
  startHidden = document.getElementById('start_time');
  hoursEl = document.getElementById('hours');
  endDisp = document.getElementById('end_time_display');
  endHidden = document.getElementById('end_time');
  startHelp = document.getElementById('startHelp');
  endHelp = document.getElementById('endHelp');
  submitBtn = document.getElementById('submitBtn');
  open24 = document.getElementById('open_24').value;
  close24= document.getElementById('close_24').value;
  totalPriceEl = document.getElementById('total_price');
  
  basePriceEl = document.getElementById('base_price_display');
  discountRowEl = document.getElementById('discount_row');
  discountEl = document.getElementById('discount_display');
  netPriceEl = document.getElementById('net_price_display');

  function buildStaticLists(){
    hh12El.innerHTML = '<option value="">--</option>';
    for (let i=1;i<=12;i++) {
      const v = pad2(i);
      const opt = document.createElement('option');
      opt.value=v; opt.textContent=v;
      hh12El.appendChild(opt);
    }
    mmEl.innerHTML = '<option value="">--</option>';
    ['00','30'].forEach(v=>{
      const opt=document.createElement('option');
      opt.value=v; opt.textContent=v;
      mmEl.appendChild(opt);
    });
  }

  function applyMinForToday(){
    const d = new Date();
    const todayStr = `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    startHelp.textContent=''; startHelp.classList.remove('error');
    if (dateEl.value === todayStr){
      let minStart = roundUpTo30(nowHHMM());
      if (cmpTime(minStart, open24) < 0) minStart = open24;
      if (cmpTime(minStart, close24) >= 0) {
        startHelp.innerHTML = '<i class="fas fa-exclamation-circle"></i> วันนี้จองไม่ได้แล้ว (เลยเวลาปิด) โปรดเลือกวันถัดไป';
        startHelp.classList.add('error');
        submitBtn.disabled = true;
        return;
      }
      startHelp.innerHTML = `<i class="fas fa-clock"></i> เวลาที่เร็วสุดวันนี้: ${to12(minStart)}`;
    }
  }

  function autoClampToAllowed(){
    if (!hh12El.value || !mmEl.value || !apEl.value) {
      startHidden.value=''; endDisp.value=''; endHidden.value=''; 
      computeEnd(); // เรียก computeEnd ที่ Global
      return;
    }
    let st24 = to24_from_parts(hh12El.value, mmEl.value, apEl.value);
    if (!st24){ return; }

    const todayStr = new Date().toISOString().slice(0,10);
    if (dateEl.value === todayStr){
      let minStart = roundUpTo30(nowHHMM());
      if (cmpTime(minStart, open24) < 0) minStart = open24;
      if (cmpTime(st24, minStart) < 0) {
        const twelve = to12(minStart);
        const [t,ap] = twelve.split(' '); const [H,M]=t.split(':');
        hh12El.value = H; mmEl.value = M; apEl.value = ap;
        st24 = minStart;
      }
    } else {
      if (cmpTime(st24, open24) < 0) st24 = open24;
    }

    if (cmpTime(st24, close24) > 0) {
      const twelve = to12(close24);
      const [t,ap]=twelve.split(' '); const [H,M]=t.split(':');
      hh12El.value=H; mmEl.value=M; apEl.value=ap;
      st24 = close24;
    }

    startHidden.value = st24;
    computeEnd(); // เรียก computeEnd ที่ Global
  }

  // ⭐⭐ ลบฟังก์ชัน computeEnd() ที่เคยอยู่ตรงนี้ออกไปแล้ว ⭐⭐
  
  buildStaticLists();
  applyMinForToday();

  // ตั้งค่าเริ่มต้น
  (function setDefaultEarliest(){
    const d = new Date();
    const todayLocal = `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
    let earliest = open24;
    if (dateEl.value === todayLocal){
      let n = roundUpTo30(nowHHMM());
      if (cmpTime(n, earliest) > 0) earliest = n;
    }
    const twelve = to12(earliest);
    const [t,ap] = twelve.split(' '); const [H,M]=t.split(':');
    hh12El.value = H;
    mmEl.value = M;
    apEl.value = ap;
    startHidden.value = earliest;
    computeEnd();
  })();

  // Event Listeners
  hh12El.addEventListener('change', autoClampToAllowed);
  mmEl.addEventListener('change', autoClampToAllowed);
  apEl.addEventListener('change', autoClampToAllowed);
  hoursEl.addEventListener('input', computeEnd);
  dateEl.addEventListener('change', ()=>{ applyMinForToday(); autoClampToAllowed(); });

  // Validate before submit
  document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (submitBtn.disabled) {
      e.preventDefault();
      return;
    }
    if (!startHidden.value) {
      e.preventDefault();
      startHelp.innerHTML = '<i class="fas fa-exclamation-circle"></i> กรุณาเลือกเวลาเริ่มให้ครบ';
      startHelp.classList.add('error');
    }
  });
})();
</script>
</body>
</html>
