// ==== ใช้ร่วมกัน ====
$PAID_OK = ["paid","paid_confirmed"];   // ชื่อใน Tbl_Payment_Status.StatusName

// --------------------------------------------------------
// โหมดที่ 1: ส่งอีเมล "ยืนยันการจอง" ทันทีเมื่อเปลี่ยนสถานะแล้ว (มี booking_id)
// เงื่อนไข: BookingStatusID = 2 และ PaymentStatus เป็น paid/paid_confirmed
// และยังไม่ได้ส่งอีเมลยืนยัน (ConfirmationEmailSent=0)
// --------------------------------------------------------
if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {

    $bookingID = (int)$_GET['booking_id'];

    $sql = "
        SELECT 
            b.BookingID,
            c.Email,
            CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
            b.StartTime, b.EndTime,
            v.VenueName,
            ps.StatusName,
            b.ConfirmationEmailSent
        FROM Tbl_Booking b
        JOIN Tbl_Customer c        ON b.CustomerID     = c.CustomerID
        JOIN Tbl_Venue v           ON b.VenueID        = v.VenueID
        JOIN Tbl_Payment_Status ps ON b.PaymentStatusID = ps.PaymentStatusID
        WHERE b.BookingID = ?
          AND b.BookingStatusID = 2
          AND ps.StatusName IN ('paid','paid_confirmed')
          AND b.ConfirmationEmailSent = 0
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { http_response_code(500); die("DB prepare failed."); }

    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($bk = $result->fetch_assoc()) {
        $ok = sendEmail(
            $conn,
            $bk['Email'],
            $bk['CustomerName'],
            $bk['StartTime'],
            $bk['EndTime'],
            $bk['BookingID'],
            $bk['VenueName'],
            true // confirmation
        );

        if ($ok) {
            // กันส่งซ้ำ
            $u = $conn->prepare("UPDATE Tbl_Booking SET ConfirmationEmailSent = 1 WHERE BookingID = ?");
            $u->bind_param("i", $bookingID);
            $u->execute(); $u->close();

            echo "✅ Confirmation email sent for #{$bookingID}";
        } else {
            echo "❌ Failed sending confirmation for #{$bookingID}";
        }
    } else {
        echo "ℹ️ Nothing to send (maybe already sent or not paid/confirmed).";
    }
    $stmt->close();
    $conn->close();
    exit;
}

// --------------------------------------------------------
// โหมดที่ 2: Cron “เตือนก่อนเวลา 1 ชั่วโมง” (ไม่มี booking_id)
// เงื่อนไข: BookingStatusID = 2, PaymentStatus paid/paid_confirmed,
//           ยังไม่เคยเตือน (Notification1hSent=0),
//           StartTime อยู่ในหน้าต่าง 60–61 นาทีข้างหน้า
// --------------------------------------------------------
$winStart = date('Y-m-d H:i:s', strtotime('+60 minutes'));
$winEnd   = date('Y-m-d H:i:s', strtotime('+61 minutes'));

$sql = "
    SELECT 
        b.BookingID, c.Email,
        CONCAT(c.FirstName,' ',c.LastName) AS CustomerName,
        b.StartTime, b.EndTime, v.VenueName
    FROM Tbl_Booking b
    JOIN Tbl_Customer c        ON b.CustomerID      = c.CustomerID
    JOIN Tbl_Venue v           ON b.VenueID         = v.VenueID
    JOIN Tbl_Payment_Status ps ON b.PaymentStatusID = ps.PaymentStatusID
    WHERE b.BookingStatusID = 2
      AND ps.StatusName IN ('paid','paid_confirmed')
      AND b.Notification1hSent = 0
      AND b.StartTime >= ?
      AND b.StartTime <  ?
    LIMIT 200
";
$stmt = $conn->prepare($sql);
if ($stmt === false) { http_response_code(500); die("DB prepare failed."); }
$stmt->bind_param("ss", $winStart, $winEnd);
$stmt->execute();
$rs = $stmt->get_result();

$sent = 0; $fail = 0;
while ($bk = $rs->fetch_assoc()) {
    $ok = sendEmail(
        $conn,
        $bk['Email'],
        $bk['CustomerName'],
        $bk['StartTime'],
        $bk['EndTime'],
        $bk['BookingID'],
        $bk['VenueName'],
        false // reminder
    );
    if ($ok) {
        $upd = $conn->prepare("UPDATE Tbl_Booking SET Notification1hSent = 1 WHERE BookingID = ?");
        $upd->bind_param("i", $bk['BookingID']);
        $upd->execute(); $upd->close();
        $sent++;
    } else { $fail++; }
}
$stmt->close();

echo "⏰ 1h reminder done. Sent {$sent}, failed {$fail}.";
$conn->close();
