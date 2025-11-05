<?php
// includes/auth.php
function is_super_admin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}
function is_admin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function current_employee_id(): int {
  return (int)($_SESSION['employee_id'] ?? 0);
}
function has_admin_access_to_venue(mysqli $conn, int $employeeId, int $venueId): bool {
  $sql = "
    SELECT 1
    FROM Tbl_Admin_Allowed_Venue av
    JOIN Tbl_Admin_Assignment aa ON aa.EmployeeID = av.EmployeeID
    JOIN Tbl_Venue v ON v.VenueID = av.VenueID
    WHERE av.EmployeeID = ? AND av.VenueID = ? AND v.VenueTypeID = aa.VenueTypeID
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $employeeId, $venueId);
  $stmt->execute();
  $ok = ($stmt->get_result()->num_rows > 0);
  $stmt->close();
  return $ok;
}
