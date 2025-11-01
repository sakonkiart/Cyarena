<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
require_once 'db_connect.php';

$uid = (int)$_SESSION['user_id'];
$errors = [];
$success = '';
$maxSize = 2 * 1024 * 1024; // 2MB
$avatarDir = __DIR__ . '/uploads/avatars/';
$avatarUrlBase = 'uploads/avatars/'; // สำหรับ src ใน <img>

// สร้างโฟลเดอร์หากไม่มี
if (!is_dir($avatarDir)) {
  @mkdir($avatarDir, 0777, true);
}

// โหลดข้อมูลปัจจุบัน
$sql = "SELECT CustomerID, FirstName, LastName, Phone, AvatarPath
        FROM Tbl_customer
        WHERE CustomerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
  die("ไม่พบบัญชีผู้ใช้ของคุณ");
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $errors[] = 'ไม่สามารถยืนยันคำขอได้ (CSRF)';
  } else {
    // รับค่า
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($first === '') $errors[] = 'กรุณากรอกชื่อ';
    if ($last  === '') $errors[] = 'กรุณากรอกนามสกุล';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
      $errors[] = 'เบอร์โทรไม่ถูกต้อง';
    }

    // จัดการอัปโหลดรูป (ถ้ามี)
    $newAvatarRel = null;
    if (!empty($_FILES['avatar']['name'])) {
      $f = $_FILES['avatar'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        if ($f['size'] > $maxSize) {
          $errors[] = 'ไฟล์รูปใหญ่เกิน 2MB';
        } else {
          $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
          $ok = in_array($ext, ['jpg','jpeg','png','webp']);
          if (!$ok) {
            $errors[] = 'อนุญาตเฉพาะไฟล์ JPG, PNG, WEBP';
          } else {
            // ตั้งชื่อไฟล์ใหม่กันชนกัน
            $safeBase = preg_replace('/[^a-z0-9]+/i', '-', $first . '-' . $last);
            $newName  = $safeBase . '-' . $uid . '-' . time() . '.' . $ext;
            $destAbs  = $avatarDir . $newName;
            if (!move_uploaded_file($f['tmp_name'], $destAbs)) {
              $errors[] = 'อัปโหลดรูปไม่สำเร็จ';
            } else {
              // เส้นทางแบบที่เว็บมองเห็น
              $newAvatarRel = $avatarUrlBase . $newName;
              // ลบไฟล์เก่า (ถ้าเป็นไฟล์ใน folder นี้จริง)
              if (!empty($profile['AvatarPath'])) {
                $old = $profile['AvatarPath'];
                $oldAbs = __DIR__ . '/' . ltrim($old, '/');
                if (strpos(realpath($oldAbs) ?: '', realpath($avatarDir)) === 0 && file_exists($oldAbs)) {
                  @unlink($oldAbs);
                }
              }
            }
          }
        }
      } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูป (code ' . $f['error'] . ')';
      }
    }

    // บันทึก
    if (!$errors) {
      if ($newAvatarRel) {
        $upd = $conn->prepare("UPDATE Tbl_customer
                               SET FirstName=?, LastName=?, Phone=?, AvatarPath=?
                               WHERE CustomerID=?");
        $upd->bind_param('ssssi', $first, $last, $phone, $newAvatarRel, $uid);
        $_SESSION['avatar_path'] = $newAvatarRel;
      } else {
        $upd = $conn->prepare("UPDATE Tbl_customer
                               SET FirstName=?, LastName=?, Phone=?
                               WHERE CustomerID=?");
        $upd->bind_param('sssi', $first, $last, $phone, $uid);
      }
      if ($upd->execute()) {
        $success = 'บันทึกโปรไฟล์เรียบร้อยแล้ว';
        $profile['FirstName']  = $first;
        $profile['LastName']   = $last;
        $profile['Phone']      = $phone;
        if ($newAvatarRel) $profile['AvatarPath'] = $newAvatarRel;

        // อัปเดตชื่อที่โชว์บนแอป
        $_SESSION['user_name'] = trim($first . ' ' . $last);
      } else {
        $errors[] = 'บันทึกไม่สำเร็จ: ' . $conn->error;
      }
      $upd->close();
    }
  }
}

$displayName = htmlspecialchars($_SESSION['user_name'] ?? ($profile['FirstName'] . ' ' . $profile['LastName']));
$avatarPath  = $profile['AvatarPath'];
$avatarSrc   = $avatarPath && file_exists(__DIR__ . '/' . $avatarPath)
  ? htmlspecialchars($avatarPath)
  : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($displayName);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>แก้ไขโปรไฟล์ - CY Arena</title>

<!-- Google Font (ไทยอ่านง่าย) -->
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons (สำหรับไอคอนใน alert) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  :root{
    --brand-1:#0d6efd;
    --brand-2:#00b4ff;
    --brand-3:#4facfe;
    --radius:1.5rem;
  }
  body{
  /* โทนเดียว ไล่เฉดเนียนจากฟ้าอ่อน -> ขาวฟ้า */
  background: linear-gradient(180deg, #f6faff 0%, #eef5ff 100%);
  font-family:"Prompt", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans Thai", sans-serif;
  /* ปิด layer gradient ซ้อนที่ทำให้ดูเหมือนแบ่งครึ่ง */
  background-repeat: no-repeat;
  background-attachment: fixed;
}

  .container-sm{max-width:760px}
  label{font-weight:600}
  .help{color:#64748b;font-size:.9rem}
  .avatar-wrap{display:flex;gap:18px;align-items:center}
  .avatar-wrap img{
    width:88px;height:88px;border-radius:50%;
    object-fit:cover;border:3px solid #3b82f6;background:#fff;
    box-shadow:0 6px 16px rgba(2,6,23,.12);
  }
  .btn-brand{
    background:#2563eb;color:#fff;font-weight:700
  }
  .btn-brand:hover{background:#1d4ed8;color:#fff}

  /* ปุ่มกลับแบบ gradient */
  .btn-back{
    color:#fff!important;font-weight:600;border:none;
    background:linear-gradient(135deg, var(--brand-1), var(--brand-3));
    box-shadow:0 6px 16px rgba(13,110,253,.25);
  }
  .btn-back:hover{
    filter:brightness(.95);
    box-shadow:0 10px 22px rgba(13,110,253,.3);
  }

  /* การ์ดกรอบหนาแบบ gradient + glow */
  .card-beauty{
    position:relative;
    border: 6px solid transparent;
    border-radius: var(--radius);
    background:
      linear-gradient(#ffffff,#ffffff) padding-box,
      linear-gradient(135deg, var(--brand-1), var(--brand-2), var(--brand-3)) border-box;
    box-shadow: 0 10px 28px rgba(13,110,253,0.12), 0 0 16px rgba(13,110,253,0.18);
    transition: box-shadow .3s ease;
  }
  .card-beauty:hover{
    box-shadow: 0 14px 38px rgba(13,110,253,0.18), 0 0 22px rgba(13,110,253,0.28);
  }

  /* โลโก้มุมขวาบน (อยู่ในกรอบ ไม่ล้น) */
  .card-logo{
    position:absolute; top:10px; right:14px; width:88px; height:auto;
    filter: drop-shadow(0 3px 8px rgba(0,0,0,.15));
  }
  @media (max-width: 576px){
    .card-logo{ width:68px; top:8px; right:10px; }
  }

  /* กล่องแจ้งเตือนแบบ soft */
  .alert-soft-danger{
    background: rgba(255, 99, 99, 0.12);
    border-left:6px solid #ff3b3b;
    color:#b02a37;
    box-shadow: inset 0 0 10px rgba(255,0,0,0.08);
  }
  .alert-soft-success{
    background: rgba(16, 185, 129, .12);
    border-left:6px solid #10b981;
    color:#0f5132;
    box-shadow: inset 0 0 10px rgba(16,185,129,.08);
  }
  /* ตำแหน่งโลโก้ให้อยู่ "ภายในกรอบ" มุมขวาบน */
.logo-wrap{
  position:absolute; top:10px; right:14px;
  width:92px; height:auto; pointer-events:none;
  /* เงาใต้โลโก้ */
  filter: drop-shadow(0 3px 10px rgba(0,0,0,.18));
}

/* ตัวรูปโลโก้ + แอนิเมชันลอยและเรืองแสง */
.card-logo-img{
  width:100%; height:auto; display:block;
  transform-origin:center;
  animation:
    bob 4.2s ease-in-out infinite,
    glow 3s ease-in-out infinite alternate;
}

/* เส้น “ไฮไลต์” กวาดผ่านโลโก้ */
.logo-wrap::after{
  content:"";
  position:absolute; inset:0;
  background: linear-gradient(120deg, transparent 0%,
             rgba(255,255,255,.65) 48%, transparent 52%);
  transform: translateX(-160%);
  mix-blend-mode: screen; opacity:.6;
  animation: shine 3.8s linear infinite;
  border-radius: 8px;
}

/* Hover: หยุดการแกว่งนิด ๆ และขยายขึ้นเล็กน้อย (บนจอพอยน์เตอร์) */
@media (hover:hover){
  .logo-wrap:hover .card-logo-img{
    animation-play-state: paused;
    transform: scale(1.04) rotate(-1deg);
  }
}

/* ลดการเคลื่อนไหวตามระบบผู้ใช้ */
@media (prefers-reduced-motion: reduce) {
  .card-logo-img, .logo-wrap::after { animation: none !important; }
}

/* Keyframes */
@keyframes bob{
  0%,100%{ transform: translateY(0); }
  50%    { transform: translateY(-4px); }
}
@keyframes glow{
  from { filter: drop-shadow(0 0 6px rgba(13,110,253,.35)); }
  to   { filter: drop-shadow(0 0 14px rgba(13,110,253,.65)); }
}
@keyframes shine{
  0%   { transform: translateX(-160%) rotate(0.001deg); }
  100% { transform: translateX(160%)  rotate(0.001deg); }
}

/* ปรับขนาดโลโก้บนมือถือ */
@media (max-width: 576px){
  .logo-wrap{ width:74px; top:8px; right:10px; }
}

</style>
</head>
<body class="py-4">
  <div class="container-sm">
    <!-- ปุ่มกลับ -->
    <a href="dashboard.php" class="btn btn-back mb-3">
      ← กลับหน้า Dashboard
    </a>

    <!-- การ์ดโปรไฟล์ -->
    <div class="card p-4 card-beauty">

      <!-- โลโก้ในกรอบ -->
      <!-- โลโก้แบบมีลูกเล่น -->
<div class="logo-wrap" aria-hidden="true">
  <img src="images/cy.png" alt="CY Arena" class="card-logo-img">
</div>


      <h3 class="mb-3 text-primary fw-semibold">แก้ไขโปรไฟล์</h3>

      <?php if ($errors): ?>
        <div class="alert border-0 rounded-3 shadow-sm alert-soft-danger mb-3">
          <div class="d-flex align-items-center mb-2">
            <i class="bi bi-exclamation-circle-fill me-2 text-danger fs-5"></i>
            <strong>พบข้อผิดพลาด</strong>
          </div>
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert border-0 rounded-3 shadow-sm alert-soft-success mb-3">
          <i class="bi bi-check2-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-4">
          <label class="form-label">รูปโปรไฟล์</label>
          <div class="avatar-wrap">
            <img id="avatarPreview" src="<?= $avatarSrc ?>" alt="avatar">
            <div>
              <input class="form-control" type="file" name="avatar" id="avatar" accept=".jpg,.jpeg,.png,.webp">
              <div class="help mt-2">รองรับ JPG/PNG/WebP ขนาดไม่เกิน 2MB</div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อ</label>
            <input type="text" name="first_name" class="form-control" required
                   value="<?= htmlspecialchars($profile['FirstName'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">นามสกุล</label>
            <input type="text" name="last_name" class="form-control" required
                   value="<?= htmlspecialchars($profile['LastName'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" class="form-control"
                   placeholder="08x-xxx-xxxx"
                   value="<?= htmlspecialchars($profile['Phone'] ?? '') ?>">
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button class="btn btn-brand" type="submit">บันทึกโปรไฟล์</button>
          
        </div>
      </form>
    </div>
  </div>

<script>
  // Preview + validate รูปโปรไฟล์
  document.getElementById('avatar').addEventListener('change', function(e){
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const ok = ['image/jpeg','image/png','image/webp'].includes(f.type);
    if (!ok) { alert('รองรับ JPG/PNG/WebP เท่านั้น'); e.target.value=''; return; }
    if (f.size > <?= (int)$maxSize ?>) { alert('ไฟล์ใหญ่เกิน 2MB'); e.target.value=''; return; }
    const url = URL.createObjectURL(f);
    document.getElementById('avatarPreview').src = url;
  });


</body>
</html>
