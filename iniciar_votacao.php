<?php
session_start();
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo']!=='admin') exit('Acesso negado');
$conn = new mysqli("localhost","root","","votacao");
$dep_id = intval($_GET['id'] ?? 0);
$isDesempate = isset($_GET['desempate']);

if ($isDesempate) {
    // Modo Desempate (status_votacao=3)
    $status = 3;
    // Redireciona direto para a votação de líder/desempate
    $redirect_url = "votacao_lider.php?id=$dep_id"; 
} else {
    // Modo Votação Inicial (status_votacao=1)
    $status = 1;
    // Votação inicial começa pela indicação
    $redirect_url = "votacao.php?id=$dep_id"; 
}

// Atualiza o status de votação no banco de dados
$stmt = $conn->prepare("UPDATE departamentos SET status_votacao=? WHERE id=?");
$stmt->bind_param("ii", $status, $dep_id);
$stmt->execute();
header("Location: $redirect_url");
exit;
?>