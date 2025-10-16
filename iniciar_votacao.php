<?php
session_start();
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo']!=='admin') exit('Acesso negado');
$conn = new mysqli("localhost","root","","votacao");
$dep_id = intval($_GET['id'] ?? 0);
$isDesempate = isset($_GET['desempate']);

if ($isDesempate) {
    // MODO DESEMPATE (acionado por "Continuar Desempate")
    
    // CORREÇÃO: Define status_votacao=3 E ZERA os votos e contagens (votos_lider_json e votos_contagem)
    $stmt = $conn->prepare("UPDATE departamentos SET status_votacao=3, votos_lider_json='[]', votos_contagem='{}' WHERE id=?");
    $stmt->bind_param("i", $dep_id);
    $stmt->execute();

    // Redireciona para a tela de votação do líder/desempate
    header("Location: votacao_lider.php?id=$dep_id");
    
} else {
    // MODO VOTAÇÃO INICIAL (acionado por "Iniciar votação")
    
    // Define status_votacao=1 (Indicação)
    $stmt = $conn->prepare("UPDATE departamentos SET status_votacao=1 WHERE id=?");
    $stmt->bind_param("i", $dep_id);
    $stmt->execute();

    // Redireciona para a tela de indicação
    header("Location: votacao.php?id=$dep_id"); 
}

exit;
?>