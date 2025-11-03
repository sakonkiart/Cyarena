<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db_connect.php';

$booking_id = $_GET['booking_id'] ?? 0;
$customer_id = $_SESSION['user_id'];

// ตรวจสอบว่าการจองนี้เป็นของลูกค้าคนนี้จริง และยังไม่ได้รีวิว
$sql = "SELECT b.BookingID, v.VenueName 
        FROM Tbl_Booking b
        JOIN Tbl_Venue v ON b.VenueID = v.VenueID
        LEFT JOIN Tbl_Review r ON b.BookingID = r.BookingID
        WHERE b.BookingID = ? AND b.CustomerID = ? AND r.ReviewID IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // ❌ ถ้ารีวิวไม่ได้ แสดงหน้าแจ้งเตือนแบบสวยงาม
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไม่สามารถรีวิวได้ | CY Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: "Prompt", sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .error-box {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 50px 40px;
        border-radius: 24px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        text-align: center;
        max-width: 500px;
        width: 100%;
        animation: slideUp 0.5s ease-out;
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .error-icon {
        font-size: 80px;
        margin-bottom: 20px;
        animation: bounce 1s ease infinite;
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    h2 {
        color: #1e293b;
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 15px;
    }
    p {
        color: #64748b;
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 30px;
    }
    .btn {
        display: inline-block;
        padding: 14px 32px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
    .btn:active {
        transform: translateY(0);
    }
    </style>
    </head>
    <body>
        <div class="error-box">
            <div class="error-icon">⚠️</div>
            <h2>ไม่สามารถรีวิวได้</h2>
            <p>อาจรีวิวไปแล้ว หรือข้อมูลการจองไม่ถูกต้อง<br>กรุณาตรวจสอบข้อมูลอีกครั้ง</p>
            <a href="my_bookings.php" class="btn">← กลับไปหน้าการจองของฉัน</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$booking = $result->fetch_assoc();

// ✅ เมื่อเปิดหน้ารีวิวได้
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $sql = "INSERT INTO Tbl_Review (CustomerID, VenueID, BookingID, Rating, Comment)
            VALUES (?, (SELECT VenueID FROM Tbl_Booking WHERE BookingID=?), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $customer_id, $booking_id, $booking_id, $rating, $comment);
    $stmt->execute();

    echo "<script>alert('ขอบคุณสำหรับการรีวิว!');window.location='my_bookings.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวสนาม | CY Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: "Prompt", sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #1328E9FF 100%);
    min-height: 100vh;
    padding: 40px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.container {
    max-width: 650px;
    width: 100%;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 45px 40px;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: fadeIn 0.5s ease-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.header {
    text-align: center;
    margin-bottom: 35px;
}
.icon {
    font-size: 50px;
    margin-bottom: 15px;
}
h2 {
    color: #1e293b;
    font-size: 26px;
    font-weight: 600;
    margin-bottom: 8px;
}
.venue-name {
    color: #3A37EBFF;
    font-size: 20px;
    font-weight: 600;
}
.form-group {
    margin-bottom: 25px;
}
label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #334155;
    font-size: 15px;
}
.rating-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.rating-btn {
    flex: 1;
    min-width: 60px;
    padding: 12px 8px;
    border: 2px solid #e2e8f0;
    background: #fff;
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    font-size: 16px;
    line-height: 1.4;
    transition: all 0.3s ease;
    font-family: "Prompt", sans-serif;
    font-weight: 500;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.rating-btn:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}
.rating-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #1310DAFF 100%);
    border-color: #667eea;
    color: #fff;
    transform: scale(1.05);
}
input[type=number] {
    display: none;
}
textarea {
    width: 100%;
    padding: 15px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    font-size: 15px;
    font-family: "Prompt", sans-serif;
    resize: vertical;
    min-height: 120px;
    transition: all 0.3s ease;
}
textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
textarea::placeholder {
    color: #94a3b8;
}
button[type=submit] {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 17px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: "Prompt", sans-serif;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    margin-top: 10px;
}
button[type=submit]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}
button[type=submit]:active {
    transform: translateY(0);
}
.back-btn {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 15px;
    transition: all 0.3s ease;
}
.back-btn:hover {
    color: #764ba2;
    transform: translateX(-5px);
}
@media (max-width: 600px) {
    .container {
        padding: 35px 25px;
    }
    h2 {
        font-size: 22px;
    }
    .venue-name {
        font-size: 18px;
    }
    .rating-btn {
        min-width: 50px;
        font-size: 14px;
        padding: 10px 6px;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="icon">⭐</div>
        <h2>รีวิวสนาม</h2>
        <div class="venue-name"><?php echo htmlspecialchars($booking['VenueName']); ?></div>
    </div>
    
    <form method="POST" id="reviewForm">
        <div class="form-group">
            <label>ให้คะแนนสนาม (1–5 ดาว):</label>
            <input type="number" name="rating" id="ratingInput" min="1" max="5" required>
            <div class="rating-container">
                <div class="rating-btn" data-rating="1">
                    <div>⭐</div>
                    <div>1</div>
                </div>
                <div class="rating-btn" data-rating="2">
                    <div>⭐⭐</div>
                    <div>2</div>
                </div>
                <div class="rating-btn" data-rating="3">
                    <div>⭐⭐⭐</div>
                    <div>3</div>
                </div>
                <div class="rating-btn" data-rating="4">
                    <div>⭐⭐⭐⭐</div>
                    <div>4</div>
                </div>
                <div class="rating-btn" data-rating="5">
                    <div>⭐⭐⭐⭐⭐</div>
                    <div>5</div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>ความคิดเห็นเพิ่มเติม:</label>
            <textarea name="comment" rows="5" placeholder="บอกความคิดเห็นของคุณเกี่ยวกับสนามแห่งนี้..." required></textarea>
        </div>

        <button type="submit">ส่งรีวิว ✨</button>
    </form>
    
    <a href="my_bookings.php" class="back-btn">← กลับไปหน้าการจองของฉัน</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingBtns = document.querySelectorAll('.rating-btn');
    const ratingInput = document.getElementById('ratingInput');
    
    ratingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            ratingBtns.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Set the rating value
            const rating = this.getAttribute('data-rating');
            ratingInput.value = rating;
        });
    });
    
    // Form validation
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        if (!ratingInput.value) {
            e.preventDefault();
            alert('กรุณาเลือกคะแนนก่อนส่งรีวิว');
        }
    });
});
</script>
</body>
</html>