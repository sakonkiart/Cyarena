<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $PromoName = $_POST['PromoName'];
    $PromoCode = $_POST['PromoCode'];
    $Description = $_POST['Description'];
    $DiscountType = $_POST['DiscountType'];
    $DiscountValue = $_POST['DiscountValue'];
    $StartDate = $_POST['StartDate'];
    $EndDate = $_POST['EndDate'];
    $Conditions = $_POST['Conditions'];

    $sql = "INSERT INTO Tbl_Promotion 
            (PromoCode, PromoName, Description, DiscountType, DiscountValue, StartDate, EndDate, Conditions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $PromoCode, $PromoName, $Description, $DiscountType, $DiscountValue, $StartDate, $EndDate, $Conditions);
    $stmt->execute();

    header("Location: promotion_manage.php");
    exit;
}
?>
