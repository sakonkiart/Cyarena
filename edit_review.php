<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

$review_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// ✅ ตรวจสอบว่ารีวิวนี้เป็นของผู้ใช้คนนี้จริง
$sql = "SELECT r.*, v.VenueName 
        FROM Tbl_Review r
        JOIN Tbl_Venue v ON r.VenueID = v.VenueID
        WHERE r.ReviewID = ? AND r.CustomerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("<h2 style='font-family:Prompt;text-align:center;color:#e11d48;margin-top:50px;'>❌ ไม่มีสิทธิ์แก้ไขรีวิวนี้</h2>");
}
$review = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $sql = "UPDATE Tbl_Review SET Rating=?, Comment=? WHERE ReviewID=? AND CustomerID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $rating, $comment, $review_id, $user_id);
    $stmt->execute();

    echo "<script>alert('อัปเดตรีวิวเรียบร้อย!');window.location='my_reviews.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แก้ไขรีวิว | CY Arena</title>
<style>
body { font-family:"Prompt",sans-serif; background:#f8fafc; margin:0; }
.container {
  max-width:600px; margin:60px auto; background:#fff; padding:30px;
  border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,0.08);
}
h2 { text-align:center; color:#0f172a; margin-bottom:25px; }
label { display:block; margin-top:15px; font-weight:600; color:#334155; }
input[type=number], textarea {
  width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;
  font-size:15px; margin-top:6px;
}
button {
  margin-top:25px; width:100%; padding:12px;
  background:#3b82f6; color:#fff; border:none; border-radius:8px;
  font-weight:600; font-size:16px; cursor:pointer;
}
button:hover { background:#2563eb; }
.back-link {
  display:block; text-align:center; margin-top:15px; text-decoration:none;
  color:#475569; font-weight:500;
}
.back-link:hover { color:#1e40af; }
</style>
</head>
<body>

<div class="container">
  <h2>✏️ แก้ไขรีวิว: <?php echo htmlspecialchars($review['VenueName']); ?></h2>
  <form method="POST">
      <label>คะแนน (1–5):</label>
      <input type="number" name="rating" min="1" max="5" required 
             value="<?php echo htmlspecialchars($review['Rating']); ?>">

      <label>ความคิดเห็น:</label>
      <textarea name="comment" rows="5" required><?php echo htmlspecialchars($review['Comment']); ?></textarea>

      <button type="submit">บันทึกการแก้ไข</button>
  </form>
  <a href="my_reviews.php" class="back-link">⬅ กลับไปหน้ารีวิวของฉัน</a>
</div>

</body>
</html>
