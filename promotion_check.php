<?php
include 'db_connect.php';
header('Content-Type: application/json');

$code = trim($_GET['code'] ?? '');
$response = ['valid' => false, 'message' => 'ไม่พบรหัสโปรโมชั่น'];

if ($code) {
    $sql = "SELECT * FROM Tbl_Promotion 
            WHERE PromoCode = ? 
              AND NOW() BETWEEN StartDate AND EndDate";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();

    if ($promo) {
        $text = $promo['DiscountType'] === 'percent'
            ? "{$promo['DiscountValue']}%"
            : number_format($promo['DiscountValue'], 2) . " บาท";

        $response = [
            'valid' => true,
            'discount_type' => $promo['DiscountType'],
            'discount_value' => $promo['DiscountValue'],
            'discount_text' => $text
        ];
    } else {
        $response['message'] = "รหัสโปรโมชั่นหมดอายุหรือไม่ถูกต้อง";
    }
}

echo json_encode($response);
?>
