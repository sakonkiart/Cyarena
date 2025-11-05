<?php
// includes/auth.php

// หมายเหตุ:
// - คงฟังก์ชันเดิม: is_super_admin(), is_admin(), current_employee_id(), has_admin_access_to_venue()
// - เพิ่มเฉพาะสิ่งจำเป็นสำหรับโมเดลสิทธิ์แบบบริษัท: company_id(), require_company_scope()
// - ปรับตรรกะ has_admin_access_to_venue() ให้ตรวจสิทธิ์ตาม CompanyID ของผู้ใช้แทนโครง assignment เดิม
// - ไม่เรียก session_start() ที่นี่ (สมมติถูกเรียกจากหน้าเรียกใช้อยู่แล้ว)

function is_super_admin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function is_admin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * คืนค่า EmployeeID จาก session (คงชื่อ/พฤติกรรมเดิมไว้)
 */
function current_employee_id(): int {
  return (int)($_SESSION['employee_id'] ?? 0);
}

/**
 * >>> NEW: ดึง CompanyID ของผู้ใช้ปัจจุบันจาก session
 * ใช้ได้กับทั้ง admin และ employee ที่อยู่ใต้บริษัทหนึ่ง ๆ
 * ถ้าไม่มีให้คืน 0
 */
function company_id(): int {
  return (int)($_SESSION['company_id'] ?? 0);
}

/**
 * >>> NEW: บังคับ scope บริษัทสำหรับ admin/employee
 * - super_admin ผ่านได้โดยไม่ต้องมี company_id
 * - admin/employee ต้องมี company_id มิฉะนั้น 403
 * เรียกใช้ในหน้าแอดมินหลัง require_login() ตามต้องการ
 */
function require_company_scope(): void {
  if (is_super_admin()) return; // super_admin ไม่บังคับบริษัท
  $role = $_SESSION['role'] ?? null;
  if ($role === 'admin' || $role === 'employee') {
    if (company_id() <= 0) {
      http_response_code(403);
      echo "403 Forbidden – ยังไม่ได้กำหนดบริษัทให้ผู้ใช้งานนี้";
      exit;
    }
  }
}

/**
 * ตรวจสิทธิ์การเข้าถึงสนามแบบใหม่ (ตามบริษัท)
 * ------------------------------------------------
 * เดิม: ตรวจผ่านตาราง assignment/allowed_venue และ match กับประเภทสนาม
 * ใหม่: 
 *   - ถ้าเป็น super_admin -> อนุญาตเสมอ
 *   - ถ้าเป็น admin/employee -> อนุญาตก็ต่อเมื่อ สนาม (Tbl_Venue.VenueID) อยู่ในบริษัทเดียวกับผู้ใช้ (CompanyID ใน session)
 * 
 * หมายเหตุ:
 * - คงลายเซ็นเดิมของฟังก์ชัน (ยังรับ $employeeId) เพื่อความเข้ากันได้กับโค้ดที่เรียกใช้เดิม
 * - ในโมเดลสิทธิ์แบบบริษัท เราไม่ต้องใช้ $employeeId ในการตัดสินอีกต่อไป
 */
function has_admin_access_to_venue(mysqli $conn, int $employeeId, int $venueId): bool {
  // super_admin เห็นได้ทุกสนาม
  if (is_super_admin()) {
    return true;
  }

  // admin/employee ต้องมี CompanyID
  $cid = company_id();
  if ($cid <= 0) {
    return false;
  }

  // อนุญาตเมื่อสนามเป็นของบริษัทเดียวกัน
  $sql = "
    SELECT 1
    FROM Tbl_Venue
    WHERE VenueID = ? AND CompanyID = ?
    LIMIT 1
  ";
  if (!$stmt = $conn->prepare($sql)) {
    // ถ้าเตรียมคำสั่งไม่สำเร็จ ให้ปฏิเสธอย่างปลอดภัย
    return false;
  }
  $stmt->bind_param('ii', $venueId, $cid);
  $stmt->execute();
  $ok = ($stmt->get_result()->num_rows > 0);
  $stmt->close();
  return $ok;
}
