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
$avatarUrlBase = 'uploads/avatars/';

if (!is_dir($avatarDir)) {
  @mkdir($avatarDir, 0777, true);
}

// โหลดข้อมูลปัจจุบัน
$sql = "SELECT CustomerID, FirstName, LastName, Phone, Email, Username, AvatarPath
        FROM Tbl_Customer
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
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if ($first === '') $errors[] = 'กรุณากรอกชื่อ';
    if ($last  === '') $errors[] = 'กรุณากรอกนามสกุล';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
      $errors[] = 'เบอร์โทรไม่ถูกต้อง';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'กรุณากรอกอีเมลที่ถูกต้อง';
    }
    if ($username === '') {
      $errors[] = 'กรุณากรอก Username';
    }

    // ตรวจสอบ Username ซ้ำ
    if ($username !== $profile['Username']) {
      $checkUser = $conn->prepare("SELECT CustomerID FROM Tbl_Customer WHERE Username = ? AND CustomerID != ?");
      $checkUser->bind_param('si', $username, $uid);
      $checkUser->execute();
      if ($checkUser->get_result()->num_rows > 0) {
        $errors[] = 'Username นี้ถูกใช้งานแล้ว';
      }
      $checkUser->close();
    }

    // ตรวจสอบ Email ซ้ำ
    if ($email !== $profile['Email']) {
      $checkEmail = $conn->prepare("SELECT CustomerID FROM Tbl_Customer WHERE Email = ? AND CustomerID != ?");
      $checkEmail->bind_param('si', $email, $uid);
      $checkEmail->execute();
      if ($checkEmail->get_result()->num_rows > 0) {
        $errors[] = 'อีเมลนี้ถูกใช้งานแล้ว';
      }
      $checkEmail->close();
    }

    // ตรวจสอบการเปลี่ยนรหัสผ่าน
    $updatePassword = false;
    if (!empty($newPassword)) {
      if (empty($currentPassword)) {
        $errors[] = 'กรุณากรอกรหัสผ่านเดิมเพื่อยืนยัน';
      } else {
        $sqlPass = "SELECT Password FROM Tbl_Customer WHERE CustomerID = ?";
        $stmtPass = $conn->prepare($sqlPass);
        $stmtPass->bind_param('i', $uid);
        $stmtPass->execute();
        $resultPass = $stmtPass->get_result()->fetch_assoc();
        $stmtPass->close();

        if (!password_verify($currentPassword, $resultPass['Password'])) {
          $errors[] = 'รหัสผ่านเดิมไม่ถูกต้อง';
        } else {
          if (strlen($newPassword) < 6) {
            $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
          } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';
          } else {
            $updatePassword = true;
          }
        }
      }
    }

    // จัดการอัปโหลดรูป
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
            $safeBase = preg_replace('/[^a-z0-9]+/i', '-', $first . '-' . $last);
            $newName  = $safeBase . '-' . $uid . '-' . time() . '.' . $ext;
            $destAbs  = $avatarDir . $newName;
            if (!move_uploaded_file($f['tmp_name'], $destAbs)) {
              $errors[] = 'อัปโหลดรูปไม่สำเร็จ';
            } else {
              $newAvatarRel = $avatarUrlBase . $newName;
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
      $fields = ['FirstName=?', 'LastName=?', 'Phone=?', 'Email=?', 'Username=?'];
      $params = [$first, $last, $phone, $email, $username];
      $types = 'sssss';

      if ($newAvatarRel) {
        $fields[] = 'AvatarPath=?';
        $params[] = $newAvatarRel;
        $types .= 's';
      }

      if ($updatePassword) {
        $fields[] = 'Password=?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        $types .= 's';
      }

      $params[] = $uid;
      $types .= 'i';

      $sql = "UPDATE Tbl_Customer SET " . implode(', ', $fields) . " WHERE CustomerID=?";
      $upd = $conn->prepare($sql);
      $upd->bind_param($types, ...$params);

      if ($upd->execute()) {
        $success = 'บันทึกโปรไฟล์เรียบร้อยแล้ว';
        $profile['FirstName']  = $first;
        $profile['LastName']   = $last;
        $profile['Phone']      = $phone;
        $profile['Email']      = $email;
        $profile['Username']   = $username;
        if ($newAvatarRel) $profile['AvatarPath'] = $newAvatarRel;

        $_SESSION['user_name'] = trim($first . ' ' . $last);
        if ($newAvatarRel) $_SESSION['avatar_path'] = $newAvatarRel;
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

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  :root{
    --brand-1:#0d6efd;
    --brand-2:#00b4ff;
    --brand-3:#4facfe;
    --radius:1.5rem;
  }
  body{
    background: linear-gradient(180deg, #f6faff 0%, #eef5ff 100%);
    font-family:"Prompt", system-ui, sans-serif;
    background-repeat: no-repeat;
    background-attachment: fixed;
    min-height: 100vh;
  }

  .container-sm{max-width:800px}
  label{font-weight:600;margin-bottom:0.5rem}
  .help{color:#64748b;font-size:.9rem;margin-top:0.5rem}
  
  .avatar-wrap{display:flex;gap:18px;align-items:center;margin-bottom:1.5rem}
  .avatar-wrap img{
    width:88px;height:88px;border-radius:50%;
    object-fit:cover;border:3px solid #3b82f6;background:#fff;
    box-shadow:0 6px 16px rgba(2,6,23,.12);
  }
  
  .btn-brand{
    background:#2563eb;color:#fff;font-weight:700;
    padding:0.75rem 1.5rem;border:none;border-radius:0.5rem;
  }
  .btn-brand:hover{background:#1d4ed8;color:#fff;transform:translateY(-1px)}

  .btn-back{
    color:#fff!important;font-weight:600;border:none;
    background:linear-gradient(135deg, var(--brand-1), var(--brand-3));
    box-shadow:0 6px 16px rgba(13,110,253,.25);
    padding:0.625rem 1.25rem;border-radius:0.5rem;
    display:inline-block;text-decoration:none;
  }
  .btn-back:hover{
    filter:brightness(.95);
    box-shadow:0 10px 22px rgba(13,110,253,.3);
  }

  .card-beauty{
    position:relative;
    border: 6px solid transparent;
    border-radius: var(--radius);
    background:
      linear-gradient(#ffffff,#ffffff) padding-box,
      linear-gradient(135deg, var(--brand-1), var(--brand-2), var(--brand-3)) border-box;
    box-shadow: 0 10px 28px rgba(13,110,253,0.12), 0 0 16px rgba(13,110,253,0.18);
  }

  .logo-wrap{
    position:absolute; top:10px; right:14px;
    width:92px; height:auto; pointer-events:none;
    filter: drop-shadow(0 3px 10px rgba(0,0,0,.18));
  }

  .card-logo-img{
    width:100%; height:auto; display:block;
    animation: bob 4.2s ease-in-out infinite;
  }

  @keyframes bob{
    0%,100%{ transform: translateY(0); }
    50%    { transform: translateY(-4px); }
  }

  @media (max-width: 576px){
    .logo-wrap{ width:74px; top:8px; right:10px; }
  }

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

  .section-divider {
    border-top: 2px solid #e5e7eb;
    margin: 2rem 0 1.5rem 0;
    padding-top: 1.5rem;
  }
  .section-title {
    font-weight: 700;
    color: var(--brand-1);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .password-strength {
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    margin-top: 8px;
  }
  .password-strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s;
    border-radius: 2px;
  }
  .strength-weak { background: #ef4444; width: 33%; }
  .strength-medium { background: #f59e0b; width: 66%; }
  .strength-strong { background: #10b981; width: 100%; }

  .form-control:focus {
    border-color: var(--brand-1);
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
  }

  /* Password Toggle Button */
  .password-toggle-wrapper {
    position: relative;
  }
  .password-toggle-wrapper input {
    padding-right: 45px;
  }
  .password-toggle-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 5px 10px;
    font-size: 1.1rem;
    transition: color 0.2s;
    z-index: 10;
  }
  .password-toggle-btn:hover {
    color: var(--brand-1);
  }
</style>
</head>
<body class="py-4">
  <div class="container-sm">
    <a href="dashboard.php" class="btn btn-back mb-3">
      ← กลับหน้า Dashboard
    </a>

    <div class="card p-4 card-beauty">
      <div class="logo-wrap" aria-hidden="true">
        <img src="images/cy.png" alt="CY Arena" class="card-logo-img">
      </div>

      <h3 class="mb-4 text-primary fw-semibold">แก้ไขโปรไฟล์</h3>

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

        <!-- รูปโปรไฟล์ -->
        <div class="mb-4">
          <label class="form-label">รูปโปรไฟล์</label>
          <div class="avatar-wrap">
            <img id="avatarPreview" src="<?= $avatarSrc ?>" alt="avatar">
            <div class="flex-grow-1">
              <input class="form-control" type="file" name="avatar" id="avatar" accept=".jpg,.jpeg,.png,.webp">
              <div class="help">รองรับ JPG/PNG/WebP ขนาดไม่เกิน 2MB</div>
            </div>
          </div>
        </div>

        <!-- ข้อมูลส่วนตัว -->
        <div class="section-title">
          <i class="bi bi-person-circle"></i> ข้อมูลส่วนตัว
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" required
                   value="<?= htmlspecialchars($profile['FirstName'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" required
                   value="<?= htmlspecialchars($profile['LastName'] ?? '') ?>">
          </div>
        </div>

        <!-- บัญชีและความปลอดภัย -->
        <div class="section-divider">
          <div class="section-title">
            <i class="bi bi-shield-lock"></i> บัญชีและความปลอดภัย
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required
                   value="<?= htmlspecialchars($profile['Username'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">อีเมล <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required
                   value="<?= htmlspecialchars($profile['Email'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">เบอร์โทร</label>
            <input type="text" name="phone" class="form-control"
                   placeholder="08x-xxx-xxxx"
                   value="<?= htmlspecialchars($profile['Phone'] ?? '') ?>">
          </div>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="section-divider">
          <div class="section-title">
            <i class="bi bi-key"></i> เปลี่ยนรหัสผ่าน
          </div>
          <button type="button" class="btn btn-outline-primary mb-3" id="togglePasswordChange">
            <i class="bi bi-unlock me-2"></i> คลิกเพื่อเปลี่ยนรหัสผ่าน
          </button>
        </div>

        <div class="row g-3 mb-4" id="passwordChangeSection" style="display: none;">
          <div class="col-md-12">
            <label class="form-label">รหัสผ่านเดิม <span class="text-danger">*</span></label>
            <div class="password-toggle-wrapper">
              <input type="password" name="current_password" id="current_password" class="form-control"
                     placeholder="กรอกรหัสผ่านเดิมเพื่อยืนยัน">
              <button type="button" class="password-toggle-btn" data-target="current_password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="help">กดปุ่มตาเพื่อแสดง/ซ่อนรหัสผ่านที่กำลังพิมพ์</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
            <div class="password-toggle-wrapper">
              <input type="password" name="new_password" id="new_password" class="form-control"
                     placeholder="อย่างน้อย 6 ตัวอักษร" minlength="6">
              <button type="button" class="password-toggle-btn" data-target="new_password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="password-strength">
              <div class="password-strength-bar" id="strengthBar"></div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
            <div class="password-toggle-wrapper">
              <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                     placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
              <button type="button" class="password-toggle-btn" data-target="confirm_password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="col-12">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelPasswordChange">
              <i class="bi bi-x-circle me-1"></i> ยกเลิกการเปลี่ยนรหัสผ่าน
            </button>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-brand" type="submit">
            <i class="bi bi-check-circle me-1"></i> บันทึกโปรไฟล์
          </button>
        </div>
      </form>
    </div>
  </div>

<script>
  // Preview รูปโปรไฟล์
  document.getElementById('avatar').addEventListener('change', function(e){
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const ok = ['image/jpeg','image/png','image/webp'].includes(f.type);
    if (!ok) { 
      alert('รองรับ JPG/PNG/WebP เท่านั้น'); 
      e.target.value=''; 
      return; 
    }
    if (f.size > <?= (int)$maxSize ?>) { 
      alert('ไฟล์ใหญ่เกิน 2MB'); 
      e.target.value=''; 
      return; 
    }
    const url = URL.createObjectURL(f);
    document.getElementById('avatarPreview').src = url;
  });

  // Password Strength Indicator
  const newPasswordInput = document.getElementById('new_password');
  const strengthBar = document.getElementById('strengthBar');

  newPasswordInput.addEventListener('input', function() {
    const password = this.value;
    
    if (password.length === 0) {
      strengthBar.className = 'password-strength-bar';
      return;
    }

    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    strengthBar.className = 'password-strength-bar';
    if (strength <= 2) {
      strengthBar.classList.add('strength-weak');
    } else if (strength <= 4) {
      strengthBar.classList.add('strength-medium');
    } else {
      strengthBar.classList.add('strength-strong');
    }
  });

  // ตรวจสอบรหัสผ่านตรงกัน
  const confirmPasswordInput = document.getElementById('confirm_password');
  
  function checkPasswordMatch() {
    if (confirmPasswordInput.value && confirmPasswordInput.value !== newPasswordInput.value) {
      confirmPasswordInput.setCustomValidity('รหัสผ่านไม่ตรงกัน');
    } else {
      confirmPasswordInput.setCustomValidity('');
    }
  }

  confirmPasswordInput.addEventListener('input', checkPasswordMatch);
  newPasswordInput.addEventListener('input', checkPasswordMatch);

  // Toggle Password Visibility
  document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      const icon = this.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  });

  // ป้องกันการส่งฟอร์ม
  document.querySelector('form').addEventListener('submit', function(e) {
    const newPass = newPasswordInput.value;
    const confirmPass = confirmPasswordInput.value;
    const currentPass = document.getElementById('current_password').value;
    
    if (newPass && newPass !== confirmPass) {
      e.preventDefault();
      alert('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
      confirmPasswordInput.focus();
      return false;
    }
    
    if (newPass && !currentPass) {
      e.preventDefault();
      alert('กรุณากรอกรหัสผ่านเดิมเพื่อยืนยันการเปลี่ยนรหัสผ่าน');
      document.getElementById('current_password').focus();
      return false;
    }
  });
</script>

</body>
</html>
