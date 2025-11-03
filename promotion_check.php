<?php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

$code = trim($_GET['code'] ?? '');
$booking_date = $_GET['booking_date'] ?? date('Y-m-d');
$start_time = $_GET['start_time'] ?? '';

$response = ['valid' => false, 'message' => 'ไม่พบรหัสโปรโมชั่น'];

if (!$code) {
    echo json_encode($response);
    exit;
}

// ✅ ดึงข้อมูลโปรโมชั่นพร้อม ConditionType
$sql = "SELECT * FROM Tbl_Promotion 
        WHERE PromoCode = ? 
          AND NOW() BETWEEN StartDate AND EndDate";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $code);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();

if (!$promo) {
    $response['message'] = "รหัสโปรโมชั่นหมดอายุหรือไม่ถูกต้อง";
    echo json_encode($response);
    exit;
}

// ✅ ตรวจสอบเงื่อนไขพิเศษ
$user_id = $_SESSION['user_id'] ?? 0;
$condition_type = $promo['ConditionType'] ?? 'general';

// 🔹 เงื่อนไขที่ 1: จองครั้งแรก
if ($condition_type === 'first_booking') {
    // ตรวจสอบว่าลูกค้าเคยจองมาก่อนหรือไม่
    $check_sql = "SELECT COUNT(*) as booking_count 
                  FROM Tbl_Booking 
                  WHERE CustomerID = ? AND BookingStatusID != 3";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    
    if ($check_result['booking_count'] > 0) {
        $response['message'] = "⚠️ โค้ดนี้ใช้ได้เฉพาะการจองครั้งแรกเท่านั้น คุณเคยจองมาแล้ว " . $check_result['booking_count'] . " ครั้ง";
        echo json_encode($response);
        exit;
    }
}

// 🔹 เงื่อนไขที่ 2: จองก่อน 18:00 น.
if ($condition_type === 'before_18') {
    if (!empty($start_time)) {
        // แปลงเวลาจาก 24hr format
        list($hour, $minute) = explode(':', $start_time);
        $hour = (int)$hour;
        
        if ($hour >= 18) {
            $response['message'] = "⚠️ โค้ดนี้ใช้ได้เฉพาะการจองก่อน 18:00 น. เท่านั้น (คุณเลือกเวลา " . sprintf("%02d:%02d", $hour, $minute) . " น.)";
            echo json_encode($response);
            exit;
        }
    } else {
        $response['message'] = "⚠️ กรุณาเลือกเวลาเริ่มต้นเพื่อตรวจสอบเงื่อนไข";
        echo json_encode($response);
        exit;
    }
}

// 🔹 เงื่อนไขที่ 3: ส่วนลดพิเศษ (ไม่มีเงื่อนไขเพิ่มเติม)
// ผ่านทุกเงื่อนไข - อนุมัติโปรโมชั่น

$text = $promo['DiscountType'] === 'percent'
    ? "{$promo['DiscountValue']}%"
    : number_format($promo['DiscountValue'], 2) . " บาท";

$response = [
    'valid' => true,
    'promotion_id' => $promo['PromotionID'],
    'discount_type' => $promo['DiscountType'],
    'discount_value' => (float)$promo['DiscountValue'],
    'discount_text' => $text,
    'condition_type' => $condition_type
];

echo json_encode($response);
?>
