<?php
session_start();
include 'db_connect.php';

// ✅ ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ✅ ตรวจสอบการส่งข้อมูลจากฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    $reply_text = trim($_POST['reply_text']);
    $user_id = $_SESSION['user_id']; // ผู้ตอบกลับ

    // ✅ ตรวจสอบ role ของผู้ตอบ
    $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true;
    $is_owner = isset($_SESSION['is_owner']) && $_SESSION['is_owner'] == true;

    // ✅ กำหนดบทบาท
    $role = 'user';
    if ($is_admin) {
        $role = 'admin';
    } elseif ($is_owner) {
        $role = 'owner';
    }

    // ✅ ตรวจสอบความถูกต้องของข้อมูลก่อนบันทึก
    if ($review_id > 0 && $reply_text !== '') {
        // (แนะนำให้ตรวจสอบว่า ReviewID มีอยู่จริง)
        $check = $conn->prepare("SELECT ReviewID FROM Tbl_Review WHERE ReviewID = ?");
        $check->bind_param("i", $review_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // ✅ เพิ่มคำตอบลงในตาราง Tbl_Reply
            $sql = "INSERT INTO Tbl_Reply (ReviewID, OwnerID, ReplyText, Role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $review_id, $user_id, $reply_text, $role);
            $stmt->execute();
        }

        $check->close();
    }
}

// ✅ ป้องกัน header injection + redirect กลับหน้าเดิมอย่างปลอดภัย
$referer = $_SERVER['HTTP_REFERER'] ?? 'venue_reviews.php';
header("Location: " . htmlspecialchars($referer));
exit;
?>
