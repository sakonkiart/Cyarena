<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

$review_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// ✅ ตรวจสอบสิทธิ์ก่อนลบ
$sql = "SELECT ReviewID FROM Tbl_Review WHERE ReviewID=? AND CustomerID=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<h2 style='font-family:Prompt;text-align:center;color:#e11d48;margin-top:50px;'>❌ ไม่มีสิทธิ์ลบรีวิวนี้</h2>");
}

// ✅ ดำเนินการลบ
$sql = "DELETE FROM Tbl_Review WHERE ReviewID=? AND CustomerID=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();

echo "<script>alert('ลบรีวิวเรียบร้อยแล้ว!');window.location='my_reviews.php';</script>";
exit;
?>
