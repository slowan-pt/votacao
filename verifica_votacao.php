<?php
session_start();
header('Content-Type: application/json');
$conn = new mysqli("localhost","root","","votacao");

// Buscar se algum departamento tem votação com status 1 (indicação) ou 3 (desempate)
$res = $conn->query("SELECT id, status_votacao FROM departamentos WHERE (status_votacao=1 OR status_votacao=3) AND lider_escolhido_2026 IS NULL LIMIT 1");

if($d = $res->fetch_assoc()){
    echo json_encode(['votacao_id'=>$d['id'], 'status'=>$d['status_votacao']]);
} else {
    echo json_encode(['votacao_id'=>null, 'status'=>null]);
}
?>