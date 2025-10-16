<?php
session_start();
if(!isset($_SESSION['usuario_id'])) exit;
$conn = new mysqli("localhost","root","","votacao");
$res = $conn->query("SELECT id FROM departamentos WHERE status_votacao=1 LIMIT 1");
$dep = $res->fetch_assoc();
echo json_encode(['departamento_id'=>$dep['id']??0]);
