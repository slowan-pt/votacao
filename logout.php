<?php
session_start();
if(isset($_SESSION['usuario_id'])){
    $id = $_SESSION['usuario_id'];
    $conn = new mysqli("localhost","root","","votacao");
    $stmt = $conn->prepare("UPDATE usuarios SET online=0 WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
}

session_destroy();
header("Location: login.php");
exit;
