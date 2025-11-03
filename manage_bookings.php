<?php
session_start();

/* >>> ADD: ป้องกัน cache ให้โหลดข้อมูลสดหลัง redirect เสมอ */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* =========================
   >>> ADD: รองรับสิทธิ์ type_admin (ลูกค้าที่ถูกแต่งตั้งให้จัดการการจองได้เฉพาะ 1 ประเภทสนาม)
   ========================= */
$IS_TYPE_ADMIN   = false;
$TYPE_ADMIN_VTID = 0;
$TYPE_ADMIN_NAME = '';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'type_admin') {
    $IS_TYPE_ADMIN   = true;
    $TYPE_ADMIN_VTID = (int)($_SESSION['type_admin_venue_type_id'] ?? 0);
    $TYPE_ADMIN_NAME = (string)($_SESSION['type_admin_type_name'] ?? '');

    // สวมบท employee ชั่วคราวเพื่อให้ผ่านการตรวจสิทธิ์เดิม (ด้านล่าง)
    $_SESSION['role_backup_for_type_admin'] = 'type_admin';
    $_SESSION['role'] = 'employee';
}

// ✅ ตรวจสอบสิทธิ์พนักงาน (type_admin ที่สวมบทเป็น employee ก็จะผ่านได้)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    /* >>> ADD: คืน role ให้ถูกต้องก่อน redirect */
    if (isset($_SESSION['role_backup_for_type_admin']) && $_SESSION['role_backup_for_type_admin'] === 'type_admin') {
        $_SESSION['role'] = 'type_admin';
        unset($_SESSION['role_backup_for_type_admin']);
    }
    header("Location: login.php");
    exit;
}

// สมมติว่าไฟล์นี้เชื่อมต่อฐานข้อมูลและตั้งค่า $conn (MySQLi)
include 'db_connect.php'; 

$employee_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'พนักงาน';

// Avatar logic
$avatarPath = $_SESSION['avatar_path'] ?? '';
$avatarLocal = 'assets/avatar-default.png';

function _exists_rel($rel) {
    return is_file(__DIR__ . '/' . ltrim($rel, '/'));
}

if ($avatarPath && _exists_rel($avatarPath)) {
    $avatarSrc = $avatarPath;
} elseif (_exists_rel($avatarLocal)) {
    $avatarSrc = $avatarLocal;
} else {
    $avatarSrc = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#2563eb"/><text x="50%" y="54%" text-anchor="middle" font-size="48" font-family="Arial" fill="#fff">👤</text></svg>'
    );
}

/* >>> ADD: util คืนค่า role type_admin ถ้าเคยสวมบท employee (เรียกก่อนทุก redirect) */
function _restore_type_admin_role_before_redirect(): void {
    if (isset($_SESSION['role_backup_for_type_admin']) && $_SESSION['role_backup_for_type_admin'] === 'type_admin') {
        $_SESSION['role'] = 'type_admin';
        unset($_SESSION['role_backup_for_type_admin']);
    }
}

/* >>> ADD: ฟังก์ชันตรวจสอบสิทธิ์ของ type_admin ว่าสามารถจัดการ booking นี้ได้หรือไม่ */
function _type_admin_can_manage(mysqli $conn, int $booking_id, int $vtid): bool {
    if ($vtid <= 0) return false;
    // ใช้ prepared statement เพื่อความปลอดภัย
    $q = "SELECT 1
          FROM Tbl_Booking b
          JOIN Tbl_Venue v ON v.VenueID = b.VenueID
          WHERE b.BookingID = ? AND v.VenueTypeID = ?";
    if (!$st = $conn->prepare($q)) return false;
    $st->bind_param("ii", $booking_id, $vtid);
    $st->execute();
    $rs = $st->get_result();
    $ok = $rs && $rs->num_rows === 1;
    $st->close();
    return $ok;
}

// ===================================================================================
// >>> [ส่วนที่ 1] ADD: ฟังก์ชันสำหรับส่งอีเมลยืนยันการจอง
// ***********************************************************************************
function sendConfirmationEmail($recipient_email, $booking_code, $venue_name, $booking_date, $booking_time, $admin_name) {
    // === คำแนะนำ: ในการใช้งานจริง ควรใช้ไลบรารีที่แข็งแกร่งกว่านี้ เช่น PHPMailer ===
    // *** ต้องมีการตั้งค่าเซิร์ฟเวอร์ SMTP บน PHP หรือใช้ไลบรารีสำหรับส่งอีเมล ***
    
    // ตรวจสอบอีเมลก่อนส่ง
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Attempted to send email to invalid address: " . $recipient_email);
        return;
    }

    $subject = "✅ ยืนยันการจองสนามเรียบร้อยแล้ว - รหัส " . $booking_code;
    
    $message = "
        <html>
        <head>
            <title>ยืนยันการจองสนาม</title>
            <style>
                body { font-family: Tahoma, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .detail-box { background-color: #f4f4f4; border-left: 5px solid #4CAF50; padding: 15px; margin-top: 15px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎉 การจองของคุณได้รับการยืนยันแล้ว</h2>
                </div>
                <div class='content'>
                    <p>เรียนลูกค้า,</p>
                    <p>การจองสนามของคุณ <b>รหัส #" . htmlspecialchars($booking_code) . "</b> ได้รับการยืนยันโดยผู้ดูแลระบบ (" . htmlspecialchars($admin_name) . ") เรียบร้อยแล้ว โปรดตรวจสอบรายละเอียด:</p>
                    
                    <div class='detail-box'>
                        <p><b>📍 สถานที่:</b> " . htmlspecialchars($venue_name) . "</p>
                        <p><b>📅 วันที่:</b> " . htmlspecialchars($booking_date) . "</p>
                        <p><b>⏰ เวลา:</b> " . htmlspecialchars($booking_time) . "</p>
                        <p><b>รหัสการจอง:</b> #" . htmlspecialchars($booking_code) . "</p>
                    </div>
                    
                    <p>โปรดเตรียมหลักฐานการจองเพื่อแสดงต่อเจ้าหน้าที่ในวันใช้งาน</p>
                    <p>ขอบคุณที่ใช้บริการของเรา</p>
                </div>
                <div class='footer'>
                    ระบบจัดการการจอง | หากมีข้อสงสัย โปรดติดต่อผู้ดูแล
                </div>
            </div>
        </body>
        </html>
    ";
    
    // ตั้งค่า Header สำหรับการส่งอีเมลแบบ HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    // *** แก้ไขอีเมลผู้ส่งจริง (Important) ***
    $headers .= "From: noreply@yourdomain.com" . "\r\n"; 
    
    // ส่งอีเมล (ใช้ฟังก์ชัน mail() ของ PHP)
    // การส่งอาจล้มเหลวหากเซิร์ฟเวอร์ไม่ได้ตั้งค่า SMTP
    $mail_success = mail($recipient_email, $subject, $message, $headers);
    
    // บันทึก log การส่งอีเมล (ไม่จำเป็นต้องแสดงให้ลูกค้าเห็น)
    if (!$mail_success) {
        error_log("Failed to send confirmation email to: " . $recipient_email . " for booking " . $booking_code);
    }
}
// ***********************************************************************************
// >>> END: ฟังก์ชันสำหรับส่งอีเมล
// ===================================================================================


/* >>> ADD: รองรับปุ่มลัดเปลี่ยนสถานะ/ชำระเงิน (เช็คสิทธิ์ type_admin ก่อนเสมอ)
   ใช้ได้กับพารามิเตอร์:
   - ?quick=confirm|complete|cancel|paid&id=BOOKING_ID
*/
if (
    (isset($_GET['quick']) || isset($_GET['action']) || isset($_GET['pay'])) &&
    isset($_GET['id']) && ctype_digit((string)$_GET['id'])
) {
    $op  = $_GET['quick'] ?? ($_GET['action'] ?? (($_GET['pay'] ?? '')));
    $bid = (int)$_GET['id'];
    $booking_data = null; // สำหรับเก็บข้อมูลการจองเดิม

    // ถ้าเป็น type_admin ต้องยืนยันสิทธิ์ตามประเภทสนามก่อน
    if ($IS_TYPE_ADMIN && !_type_admin_can_manage($conn, $bid, $TYPE_ADMIN_VTID)) {
        $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์จัดการการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
        _restore_type_admin_role_before_redirect(); /* >>> ADD */
        header("Location: manage_bookings.php");
        exit;
    }

    // Map คำสั่งเป็น SQL
    $sql = null; $msg = null;
    
    // ***********************************************************************
    // <<< ADD: ดึงข้อมูลการจองก่อนอัปเดต ถ้าเป็นการยืนยัน
    // ***********************************************************************
    if ($op === 'confirm') {
        $q_fetch = "
            SELECT 
                b.BookingStatusID, b.BookingCode, b.StartTime, b.EndTime, 
                c.Email AS CustomerEmail, v.VenueName
            FROM Tbl_Booking b
            JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
            JOIN Tbl_Venue v ON b.VenueID = v.VenueID
            WHERE b.BookingID = ?";
        if ($st_fetch = $conn->prepare($q_fetch)) {
            $st_fetch->bind_param("i", $bid);
            $st_fetch->execute();
            $res_fetch = $st_fetch->get_result();
            $booking_data = $res_fetch->fetch_assoc();
            $st_fetch->close();
        }
    }
    
    if ($op === 'confirm') {
        // StatusID=2: ยืนยันแล้ว
        $sql = "UPDATE Tbl_Booking SET BookingStatusID = 2 WHERE BookingID = ?";
        $msg = "✅ ยืนยันการจองแล้ว";
    } elseif ($op === 'complete') {
        // StatusID=4: เสร็จสิ้น
        $sql = "UPDATE Tbl_Booking SET BookingStatusID = 4 WHERE BookingID = ?";
        $msg = "✅ ปิดงาน/เสร็จสิ้นแล้ว";
    } elseif ($op === 'cancel') {
        // StatusID=3: ยกเลิกแล้ว
        $sql = "UPDATE Tbl_Booking SET BookingStatusID = 3 WHERE BookingID = ?";
        $msg = "✅ ยกเลิกการจองแล้ว";
    } elseif ($op === 'paid' || $op === 'pay') {
        // PaymentStatusID=2: ชำระแล้ว
        $sql = "UPDATE Tbl_Booking SET PaymentStatusID = 2 WHERE BookingID = ?";
        $msg = "✅ บันทึกชำระเงินแล้ว";
    }

    if ($sql) {
        if ($st = $conn->prepare($sql)) {
            $st->bind_param("i", $bid);
            
            if ($st->execute()) {
                
                // ***********************************************************************
                // <<< ADD: ตรวจสอบและส่งอีเมลยืนยันสำหรับ Quick Confirm
                // ***********************************************************************
                $CONFIRMED_STATUS_ID = 2; // สถานะ 'ยืนยันแล้ว'

                if ($op === 'confirm' && $booking_data) {
                    $current_status_id = (int)$booking_data['BookingStatusID'];
                    
                    // ตรวจสอบว่าสถานะเดิมไม่ใช่ 'ยืนยันแล้ว' ก่อนจึงส่ง
                    if ($current_status_id != $CONFIRMED_STATUS_ID) {
                        $admin_name = $userName; // ใช้ชื่อพนักงาน/ผู้ดูแลที่ล็อกอิน
                        try {
                            // ต้องใช้ DateTime เพราะเป็นข้อมูลจากฐานข้อมูล
                            $start_time     = new DateTime($booking_data['StartTime']);
                            $end_time       = new DateTime($booking_data['EndTime']);

                            // จัดรูปแบบสำหรับอีเมล
                            $booking_date = $start_time->format('d/m/Y');
                            $booking_time = $start_time->format('H:i') . ' - ' . $end_time->format('H:i') . ' น.';

                            sendConfirmationEmail(
                                $booking_data['CustomerEmail'],
                                $booking_data['BookingCode'],
                                $booking_data['VenueName'],
                                $booking_date,
                                $booking_time,
                                $admin_name
                            );
                        } catch (Exception $e) {
                            error_log("Email sending error (Quick Confirm #$bid): " . $e->getMessage());
                        }
                    }
                }
                // ***********************************************************************
                
                $_SESSION['success_message'] = "$msg (#{$bid})";
            } else {
                $_SESSION['error_message'] = "❌ ไม่สามารถดำเนินการได้: " . $st->error;
            }
            $st->close();
        } else {
            $_SESSION['error_message'] = "❌ ไม่สามารถเตรียมคำสั่งได้";
        }
        _restore_type_admin_role_before_redirect(); /* >>> ADD */
        header("Location: manage_bookings.php");
        exit;
    }
}
/* <<< END ADD */

// ✅ จัดการการอัปเดตสถานะ (จาก Modal/Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $booking_status = intval($_POST['booking_status']);
    $payment_status = intval($_POST['payment_status']);
    $booking_data = null; // สำหรับเก็บข้อมูลการจองเดิม

    // ***********************************************************************
    // <<< ADD: ดึงข้อมูลการจองก่อนอัปเดต เพื่อเช็คสถานะเดิมและข้อมูลอีเมล
    // ***********************************************************************
    $q_fetch = "
        SELECT 
            b.BookingStatusID, b.BookingCode, b.StartTime, b.EndTime, 
            c.Email AS CustomerEmail, v.VenueName
        FROM Tbl_Booking b
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID
        WHERE b.BookingID = ?";
    if ($st_fetch = $conn->prepare($q_fetch)) {
        $st_fetch->bind_param("i", $booking_id);
        $st_fetch->execute();
        $res_fetch = $st_fetch->get_result();
        $booking_data = $res_fetch->fetch_assoc();
        $st_fetch->close();
    }
    
    // ตรวจสอบว่าดึงข้อมูลได้หรือไม่
    if (!$booking_data) {
        $_SESSION['error_message'] = "❌ ไม่พบข้อมูลการจอง ID #$booking_id";
        _restore_type_admin_role_before_redirect();
        header("Location: manage_bookings.php");
        exit;
    }
    $current_status_id = (int)$booking_data['BookingStatusID'];
    // ***********************************************************************

    /* >>> ADD: ถ้าเป็น type_admin ต้องเช็คสิทธิ์ และอัปเดตโดย "ไม่แตะต้อง EmployeeID" */
    if ($IS_TYPE_ADMIN) {
        if (!_type_admin_can_manage($conn, $booking_id, $TYPE_ADMIN_VTID)) {
            $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์แก้ไขการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
            _restore_type_admin_role_before_redirect(); /* >>> ADD */
            header("Location: manage_bookings.php");
            exit;
        }
        $sql_ta = "UPDATE Tbl_Booking 
                   SET BookingStatusID = ?, PaymentStatusID = ?
                   WHERE BookingID = ?";
        if ($stmt = $conn->prepare($sql_ta)) {
            $stmt->bind_param("iii", $booking_status, $payment_status, $booking_id);
            if ($stmt->execute()) {
                
                // ***********************************************************************
                // <<< ADD: ตรวจสอบและส่งอีเมลยืนยันสำหรับ POST Update (Admin/Type_Admin)
                // ***********************************************************************
                $CONFIRMED_STATUS_ID = 2; // สถานะ 'ยืนยันแล้ว'

                if ($booking_status == $CONFIRMED_STATUS_ID && $current_status_id != $CONFIRMED_STATUS_ID) {
                    $admin_name = $userName;
                    try {
                        $start_time     = new DateTime($booking_data['StartTime']);
                        $end_time       = new DateTime($booking_data['EndTime']);

                        // จัดรูปแบบสำหรับอีเมล
                        $booking_date = $start_time->format('d/m/Y');
                        $booking_time = $start_time->format('H:i') . ' - ' . $end_time->format('H:i') . ' น.';

                        sendConfirmationEmail(
                            $booking_data['CustomerEmail'],
                            $booking_data['BookingCode'],
                            $booking_data['VenueName'],
                            $booking_date,
                            $booking_time,
                            $admin_name
                        );
                    } catch (Exception $e) {
                        error_log("Email sending error (POST Update TA #$booking_id): " . $e->getMessage());
                    }
                }
                // ***********************************************************************

                $_SESSION['success_message'] = "✅ อัปเดตสถานะเรียบร้อยแล้ว! (Booking #$booking_id)";
            } else {
                $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "❌ ไม่สามารถเตรียมคำสั่งอัปเดตได้";
        }
        _restore_type_admin_role_before_redirect(); /* >>> ADD */
        header("Location: manage_bookings.php");
        exit; // กันไม่ให้ไหลไปใช้โค้ดเดิมของ employee ด้านล่าง
    }

    // ----- โค้ดเดิมของพนักงาน (คงไว้: มีการบันทึก EmployeeID) -----
    $update_sql = "UPDATE Tbl_Booking 
                   SET BookingStatusID = ?, PaymentStatusID = ?, EmployeeID = ?
                   WHERE BookingID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiii", $booking_status, $payment_status, $employee_id, $booking_id);
    
    if ($stmt->execute()) {

        // ***********************************************************************
        // <<< ADD: ตรวจสอบและส่งอีเมลยืนยันสำหรับ POST Update (Employee)
        // ***********************************************************************
        $CONFIRMED_STATUS_ID = 2; // สถานะ 'ยืนยันแล้ว'
        
        if ($booking_status == $CONFIRMED_STATUS_ID && $current_status_id != $CONFIRMED_STATUS_ID) {
            $admin_name = $userName;
            try {
                $start_time     = new DateTime($booking_data['StartTime']);
                $end_time       = new DateTime($booking_data['EndTime']);

                // จัดรูปแบบสำหรับอีเมล
                $booking_date = $start_time->format('d/m/Y');
                $booking_time = $start_time->format('H:i') . ' - ' . $end_time->format('H:i') . ' น.';

                sendConfirmationEmail(
                    $booking_data['CustomerEmail'],
                    $booking_data['BookingCode'],
                    $booking_data['VenueName'],
                    $booking_date,
                    $booking_time,
                    $admin_name
                );
            } catch (Exception $e) {
                error_log("Email sending error (POST Update Emp #$booking_id): " . $e->getMessage());
            }
        }
        // ***********************************************************************

        $_SESSION['success_message'] = "✅ อัปเดตสถานะเรียบร้อยแล้ว! (Booking #$booking_id)";
    } else {
        $_SESSION['error_message'] = "❌ เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
    }
    $stmt->close();
    _restore_type_admin_role_before_redirect(); /* >>> ADD */
    header("Location: manage_bookings.php");
    exit;
}

// ✅ จัดการการยกเลิกการจอง
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);

    /* >>> ADD: ป้องกัน type_admin ยกเลิกข้ามประเภทสนาม */
    if ($IS_TYPE_ADMIN && !_type_admin_can_manage($conn, $cancel_id, $TYPE_ADMIN_VTID)) {
        $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์ยกเลิกการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
        _restore_type_admin_role_before_redirect(); /* >>> ADD */
        header("Location: manage_bookings.php");
        exit;
    }

    $conn->query("UPDATE Tbl_Booking SET BookingStatusID = 3 WHERE BookingID = $cancel_id");
    $_SESSION['success_message'] = "✅ ยกเลิกการจองเรียบร้อยแล้ว! (Booking #$cancel_id)";
    _restore_type_admin_role_before_redirect(); /* >>> ADD */
    header("Location: manage_bookings.php");
    exit;
}

// ✅ จัดการการลบการจอง
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    /* >>> ADD: ป้องกัน type_admin ลบข้ามประเภทสนาม */
    if ($IS_TYPE_ADMIN && !_type_admin_can_manage($conn, $delete_id, $TYPE_ADMIN_VTID)) {
        $_SESSION['error_message'] = "❌ คุณไม่มีสิทธิ์ลบการจองนี้ (อนุญาตเฉพาะประเภทสนาม: {$TYPE_ADMIN_NAME})";
        _restore_type_admin_role_before_redirect(); /* >>> ADD */
        header("Location: manage_bookings.php");
        exit;
    }
    
    // ลบข้อมูลจากฐานข้อมูล
    $delete_sql = "DELETE FROM Tbl_Booking WHERE BookingID = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "🗑️ ลบการจองเรียบร้อยแล้ว! (Booking #$delete_id)";
    } else {
        $_SESSION['error_message'] = "❌ ไม่สามารถลบการจองได้: " . $stmt->error;
    }
    $stmt->close();
    
    _restore_type_admin_role_before_redirect(); /* >>> ADD */
    header("Location: manage_bookings.php");
    exit;
}

// Get messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

/* >>> FIX: ต้องคืน role ก่อนเริ่มแสดงผล HTML หากไม่มีการ redirect */
_restore_type_admin_role_before_redirect();

// ✅ ฟิลเตอร์การค้นหา
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_payment = $_GET['payment'] ?? '';
$filter_date = $_GET['date'] ?? '';

$sql = "SELECT 
            b.BookingID, b.BookingCode, b.VenueID, v.VenueName, v.VenueTypeID, c.FirstName, c.LastName, c.Phone, c.Email,
            b.StartTime, b.EndTime, b.HoursBooked, b.TotalPrice,
            bs.StatusName AS BookingStatus, b.BookingStatusID,
            ps.StatusName AS PaymentStatus, b.PaymentStatusID,
            b.PaymentSlipPath
        FROM Tbl_Booking b
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID
        JOIN Tbl_Customer c ON b.CustomerID = c.CustomerID
        JOIN Tbl_Booking_Status bs ON b.BookingStatusID = bs.BookingStatusID
        JOIN Tbl_Payment_Status ps ON b.PaymentStatusID = ps.PaymentStatusID
        WHERE 1=1";

/* >>> ADD: จำกัดรายการเฉพาะประเภทสนามของ type_admin */
if ($IS_TYPE_ADMIN && $TYPE_ADMIN_VTID > 0) {
    // ต้องปลอดภัย
    $sql .= " AND v.VenueTypeID = " . (int)$TYPE_ADMIN_VTID;
}

// ป้องกัน SQL Injection สำหรับเงื่อนไข WHERE
if (!empty($search)) {
    $search_safe = "%" . $conn->real_escape_string($search) . "%";
    $sql .= " AND (c.FirstName LIKE '$search_safe' OR c.LastName LIKE '$search_safe' OR v.VenueName LIKE '$search_safe' OR b.BookingCode LIKE '$search_safe' OR b.BookingID LIKE '$search_safe')";
}

if (!empty($filter_status)) {
    $sql .= " AND b.BookingStatusID = " . intval($filter_status);
}

if (!empty($filter_payment)) {
    $sql .= " AND b.PaymentStatusID = " . intval($filter_payment);
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(b.StartTime) = '" . $conn->real_escape_string($filter_date) . "'";
}

$sql .= " ORDER BY b.BookingID DESC";

$result = $conn->query($sql);

$bookings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// ดึงข้อมูลสถานะทั้งหมด (ใช้สำหรับ dropdown ใน Modal และ Filter)
$booking_statuses = $conn->query("SELECT * FROM Tbl_Booking_Status ORDER BY BookingStatusID")->fetch_all(MYSQLI_ASSOC);
$payment_statuses = $conn->query("SELECT * FROM Tbl_Payment_Status ORDER BY PaymentStatusID")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง - <?php echo $userName; ?></title>
    <!-- ใช้ Tailwind CSS CDN สำหรับความง่ายและความเร็วในการพัฒนา -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Icon library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap');
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f4f7f9;
        }
        .container {
            max-width: 1400px;
        }
        .modal {
            display: none; /* ซ่อนไว้ก่อน */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            padding-top: 50px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            from {opacity: 0;}
            to {opacity: 1;}
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        /* Custom status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-1 { background-color: #fef9c3; color: #a16207; } /* รอดำเนินการ */
        .status-2 { background-color: #d1fae5; color: #047857; } /* ยืนยันแล้ว */
        .status-3 { background-color: #fee2e2; color: #b91c1c; } /* ยกเลิกแล้ว */
        .status-4 { background-color: #c7d2fe; color: #4338ca; } /* เสร็จสิ้น */

        .payment-1 { background-color: #f3e8ff; color: #6b21a8; } /* ยังไม่ชำระ */
        .payment-2 { background-color: #dbeafe; color: #1e40af; } /* ชำระแล้ว */

        /* Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
            .table-responsive table {
                width: 100%;
                min-width: 800px; 
            }
        }

    </style>
</head>
<body>

<div class="container mx-auto p-4 md:p-8">
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 bg-white p-4 rounded-lg shadow-md">
        <h1 class="text-3xl font-semibold text-gray-800 flex items-center mb-4 md:mb-0">
            <i class="fas fa-calendar-check text-blue-600 mr-3"></i>
            จัดการการจอง
        </h1>
        <div class="flex items-center space-x-3">
            <span class="text-gray-600">
                <?php echo htmlspecialchars($userName); ?> 
                <?php if ($IS_TYPE_ADMIN) : ?>
                    <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded-full">
                        Admin (<?php echo htmlspecialchars($TYPE_ADMIN_NAME); ?>)
                    </span>
                <?php else : ?>
                    <span class="text-xs font-bold text-blue-700 bg-blue-100 px-2 py-1 rounded-full">
                        พนักงาน
                    </span>
                <?php endif; ?>
            </span>
            <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover border-2 border-blue-400">
            <a href="logout.php" class="text-red-500 hover:text-red-700 transition duration-150">
                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
            </a>
        </div>
    </header>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div id="success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg shadow-sm" role="alert">
            <p class="font-bold">สำเร็จ!</p>
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div id="error-alert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg shadow-sm" role="alert">
            <p class="font-bold">ผิดพลาด!</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filter/Search Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ค้นหา (ชื่อ/สนาม/รหัส)</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ชื่อลูกค้า, สนาม, ID"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">สถานะการจอง</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($booking_statuses as $status): ?>
                        <option value="<?php echo $status['BookingStatusID']; ?>"
                            <?php echo $filter_status == $status['BookingStatusID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['StatusName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="payment" class="block text-sm font-medium text-gray-700 mb-1">สถานะชำระเงิน</label>
                <select name="payment" id="payment" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($payment_statuses as $p_status): ?>
                        <option value="<?php echo $p_status['PaymentStatusID']; ?>"
                            <?php echo $filter_payment == $p_status['PaymentStatusID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p_status['StatusName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1">วันที่จอง</label>
                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-150">
                    <i class="fas fa-filter"></i> กรอง
                </button>
                <a href="manage_bookings.php" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg shadow-md text-center transition duration-150">
                    <i class="fas fa-undo"></i> ล้าง
                </a>
            </div>
        </form>
    </div>

    <!-- Booking Table -->
    <div class="bg-white p-4 rounded-lg shadow-md overflow-hidden">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">รายการการจองทั้งหมด (<?php echo count($bookings); ?> รายการ)</h2>
        <?php if (empty($bookings)): ?>
            <p class="text-center py-10 text-gray-500">
                <i class="fas fa-info-circle mr-2"></i> ไม่พบรายการการจองตามเงื่อนไขที่กำหนด
                <?php if ($IS_TYPE_ADMIN): ?>
                    <span class="block mt-2 text-sm text-red-500">
                        (แสดงเฉพาะประเภทสนาม: <?php echo htmlspecialchars($TYPE_ADMIN_NAME); ?>)
                    </span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสจอง</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลูกค้า/สนาม</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ช่วงเวลา</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รวม (ชม./บาท)</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะจอง</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชำระเงิน</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สลิป</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        <?php foreach ($bookings as $booking): ?>
                        <tr data-id="<?php echo $booking['BookingID']; ?>"
                            data-status-id="<?php echo $booking['BookingStatusID']; ?>"
                            data-payment-id="<?php echo $booking['PaymentStatusID']; ?>"
                            data-slip-path="<?php echo htmlspecialchars($booking['PaymentSlipPath'] ?? ''); ?>">
                            <td class="px-3 py-4 whitespace-nowrap text-gray-500 font-mono text-xs">#<?php echo htmlspecialchars($booking['BookingID']); ?></td>
                            <td class="px-3 py-4 whitespace-nowrap text-blue-600 font-bold"><?php echo htmlspecialchars($booking['BookingCode'] ?? 'N/A'); ?></td>
                            <td class="px-3 py-4 whitespace-pre-wrap">
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['FirstName'] . ' ' . $booking['LastName']); ?></p>
                                <p class="text-xs text-gray-500">📞 <?php echo htmlspecialchars($booking['Phone']); ?></p>
                                <p class="text-xs text-blue-500 font-medium mt-1">🏟️ <?php echo htmlspecialchars($booking['VenueName']); ?></p>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-gray-700">
                                <?php 
                                    $start = new DateTime($booking['StartTime']);
                                    $end = new DateTime($booking['EndTime']);
                                    echo $start->format('d/m/Y') . '<br>';
                                    echo '<span class="text-xs text-gray-500">' . $start->format('H:i') . ' - ' . $end->format('H:i') . ' น.</span>';
                                ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-right">
                                <p class="font-semibold text-gray-700"><?php echo number_format($booking['TotalPrice'], 0); ?> ฿</p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['HoursBooked']); ?> ชม.</p>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap">
                                <span class="status-badge status-<?php echo $booking['BookingStatusID']; ?>">
                                    <?php echo htmlspecialchars($booking['BookingStatus']); ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap">
                                <span class="status-badge payment-<?php echo $booking['PaymentStatusID']; ?>">
                                    <?php echo htmlspecialchars($booking['PaymentStatus']); ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-center">
                                <?php if ($booking['PaymentSlipPath']): ?>
                                    <button onclick="openSlipModal('<?php echo htmlspecialchars($booking['PaymentSlipPath']); ?>', '<?php echo $booking['BookingID']; ?>')"
                                            class="text-blue-600 hover:text-blue-800 transition duration-150 p-1 rounded-md bg-blue-50">
                                        <i class="fas fa-file-image"></i> ดูสลิป
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">ไม่มีสลิป</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-center">
                                <div class="flex flex-col space-y-1">
                                    <button onclick="prepareEditForm(<?php echo $booking['BookingID']; ?>, '<?php echo htmlspecialchars($booking['BookingStatusID']); ?>', '<?php echo htmlspecialchars($booking['PaymentStatusID']); ?>')" 
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-bold py-1 px-2 rounded-full transition duration-150">
                                        <i class="fas fa-edit"></i> แก้ไขสถานะ
                                    </button>
                                    
                                    <!-- Quick Actions -->
                                    <?php if ($booking['BookingStatusID'] == 1): // รอดำเนินการ ?>
                                        <a href="?quick=confirm&id=<?php echo $booking['BookingID']; ?>" onclick="return confirm('ยืนยันการจอง #<?php echo $booking['BookingID']; ?> นี้? (ระบบจะส่งอีเมลยืนยัน)');"
                                            class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-2 rounded-full transition duration-150">
                                            <i class="fas fa-check"></i> ยืนยัน
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['PaymentStatusID'] == 1): // ยังไม่ชำระ ?>
                                        <a href="?quick=paid&id=<?php echo $booking['BookingID']; ?>" onclick="return confirm('บันทึกชำระเงินแล้วสำหรับการจอง #<?php echo $booking['BookingID']; ?>?');"
                                            class="bg-purple-500 hover:bg-purple-600 text-white text-xs font-bold py-1 px-2 rounded-full transition duration-150">
                                            <i class="fas fa-money-bill-wave"></i> ชำระแล้ว
                                        </a>
                                    <?php endif; ?>

                                    <button onclick="confirmDelete(<?php echo $booking['BookingID']; ?>)" 
                                            class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-1 px-2 rounded-full transition duration-150">
                                        <i class="fas fa-trash"></i> ลบ (ถาวร)
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Editing Status -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeEditModal()">&times;</span>
        <h3 class="text-xl font-bold mb-4 text-gray-800">แก้ไขสถานะการจอง <span id="modal-booking-id" class="text-blue-600"></span></h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="booking_id" id="edit-booking-id">
            
            <div class="mb-4">
                <label for="booking-status" class="block text-sm font-medium text-gray-700 mb-1">สถานะการจอง</label>
                <select name="booking_status" id="booking-status" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($booking_statuses as $status): ?>
                        <option value="<?php echo $status['BookingStatusID']; ?>">
                            <?php echo htmlspecialchars($status['StatusName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-6">
                <label for="payment-status" class="block text-sm font-medium text-gray-700 mb-1">สถานะชำระเงิน</label>
                <select name="payment_status" id="payment-status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($payment_statuses as $p_status): ?>
                        <option value="<?php echo $p_status['PaymentStatusID']; ?>">
                            <?php echo htmlspecialchars($p_status['StatusName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-150">
                    ยกเลิก
                </button>
                <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">
                    บันทึกการเปลี่ยนแปลง
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Viewing Slip -->
<div id="slipModal" class="modal">
    <div class="modal-content max-w-xl">
        <span class="close-btn" onclick="closeSlipModal()">&times;</span>
        <h3 class="text-xl font-bold mb-4 text-gray-800">สลิปการชำระเงิน <span id="modal-slip-id" class="text-blue-600"></span></h3>
        <div id="slip-image-container" class="bg-gray-100 p-2 rounded-lg text-center">
            <!-- Slip image will be loaded here -->
            <img id="slip-image" src="" alt="สลิปการชำระเงิน" class="max-w-full h-auto mx-auto rounded-md shadow-lg" 
                 onerror="this.onerror=null; this.src='https://placehold.co/400x300/CCCCCC/333333?text=ไม่พบสลิป'; this.classList.add('p-8');" 
                 onload="this.classList.remove('p-8');">
        </div>
        <p class="text-xs text-red-500 mt-4">
            <i class="fas fa-exclamation-triangle"></i> หากภาพไม่แสดงหรือสลิปไม่ถูกต้อง โปรดติดต่อลูกค้าเพื่อตรวจสอบ
        </p>
    </div>
</div>

<script>
// --- Modal Functions ---

function openEditModal() {
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openSlipModal(slipPath, bookingId) {
    document.getElementById('modal-slip-id').textContent = 'ID #' + bookingId;
    const slipImage = document.getElementById('slip-image');
    
    // ตรวจสอบและตั้งค่าเส้นทางสลิป
    if (slipPath) {
        // Assume the path is relative to the root or current directory
        slipImage.src = slipPath;
        slipImage.classList.remove('p-8');
    } else {
        // Fallback placeholder if no path exists
        slipImage.src = 'https://placehold.co/400x300/CCCCCC/333333?text=ไม่พบสลิป';
        slipImage.classList.add('p-8');
    }
    document.getElementById('slipModal').style.display = 'block';
}

function closeSlipModal() {
    document.getElementById('slipModal').style.display = 'none';
}

function prepareEditForm(bookingId, currentStatus, currentPayment) {
    document.getElementById('modal-booking-id').textContent = 'ID #' + bookingId;
    document.getElementById('edit-booking-id').value = bookingId;
    document.getElementById('booking-status').value = currentStatus;
    document.getElementById('payment-status').value = currentPayment;
    openEditModal();
}


// ฟังก์ชันยืนยันการลบ (ใหม่)
function confirmDelete(bookingId) {
    // ใช้ console.log แทน alert/confirm เพื่อให้สามารถ debug ใน Canvas ได้
    console.log("Attempting to delete Booking ID: #", bookingId);
    
    const message = `🗑️ ต้องการลบการจองนี้ออกจากระบบหรือไม่?\n\n` +
                    `📌 Booking ID: #${bookingId}\n\n` +
                    `⚠️ คำเตือน:\n` +
                    `• ข้อมูลจะถูกลบออกจากระบบถาวร\n` +
                    `• ไม่สามารถกู้คืนข้อมูลได้\n` +
                    `• หากต้องการเก็บประวัติ ให้ใช้ "ยกเลิก" แทน\n\n` +
                    `❓ ยืนยันการลบ?`;
    
    // ใช้ window.confirm() เพื่อยืนยันการลบ
    if (window.confirm(message)) {
        window.location.href = `?delete=${bookingId}`;
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const slipModal = document.getElementById('slipModal');
    if (event.target == editModal) closeEditModal();
    if (event.target == slipModal) closeSlipModal();
}
</script>

</body>
</html>
<?php
// ในกรณีที่ไม่มีการ redirect เกิดขึ้นเลย (เช่น เข้าหน้าครั้งแรก) โค้ด PHP ได้ถูกปิดไปแล้ว
// และมีการเรียก _restore_type_admin_role_before_redirect() ก่อนเริ่ม HTML แล้ว
// ดังนั้นโค้ดส่วนนี้จึงเป็นแค่การปิดท้ายไฟล์เท่านั้น
?>
