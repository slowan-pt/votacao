<?php
session_start();
if(!isset($_SESSION['usuario_id'])){ header("Location: login.php"); exit; }

$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) die("Erro: ".$conn->connect_error);

$dep_id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM departamentos WHERE id=?");
$stmt->bind_param("i",$dep_id);
$stmt->execute();
$departamento = $stmt->get_result()->fetch_assoc();
if(!$departamento) die("Departamento não encontrado.");

$isAdmin = ($_SESSION['usuario_tipo'] ?? '') === 'admin';

// REDIRECIONAMENTO AUTOMÁTICO PARA USUÁRIOS COMUNS APÓS AÇÃO DO ADMIN
if ($departamento['status_votacao'] == 0 && empty($departamento['lider_escolhido_2026'])) {
    if (!$isAdmin) {
        header("Location: departamentos.php"); exit;
    }
}

// =========================================================================
// CRÍTICO: DETERMINA O CAMPO DE VOTOS E CANDIDATOS CORRETO
// =========================================================================
$campo_votos_db = 'votos_lider_json'; 
$campo_candidatos_db = 'indicados';
$titulo_votacao = "Votação de Líder";

if ($departamento['status_votacao'] == 3) {
    // Modo Desempate: Usa a nova coluna de votos limpa e a lista de candidatos para desempate
    $campo_votos_db = 'votos_desempate_json'; 
    $campo_candidatos_db = 'candidatos_desempate_json';
    $titulo_votacao = "Desempate de Líder";
}
// =========================================================================

$candidatos_ids = json_decode($departamento[$campo_candidatos_db] ?? '[]', true) ?? []; 
$votos_atuais = json_decode($departamento[$campo_votos_db] ?? '[]', true) ?? [];
$user_id = $_SESSION['usuario_id'];

// =========================================================================
// CRÍTICO: VERIFICAÇÃO DE VOTO NA RODADA ATUAL
// Se o campo $campo_votos_db (que é 'votos_desempate_json' no desempate) estiver vazio, $ja_votou será false.
// =========================================================================
$ja_votou = false;
foreach($votos_atuais as $v){
    if($v['usuario_id'] == $user_id){
        $ja_votou = true;
        break;
    }
}
if($ja_votou) {
    // Redireciona APENAS se o usuário já votou NESTA rodada (o que não deve acontecer no desempate recém-iniciado)
    header("Location: resultado_lider.php?id=$dep_id"); exit;
}
// =========================================================================


if(isset($_POST['votar'])){
    $escolha = intval($_POST['lider'] ?? 0);
    if($escolha>0){
        $votos_contagem = json_decode($departamento['votos_contagem'] ?? '{}', true);
        
        $votos_atuais[] = ["usuario_id"=>$user_id,"votou_em"=>$escolha];
        
        $votos_contagem[$escolha] = ($votos_contagem[$escolha] ?? 0)+1;
        
        // CRÍTICO: Salva no campo correto ($campo_votos_db)
        $stmt_sql = "UPDATE departamentos SET {$campo_votos_db}=?, votos_contagem=? WHERE id=?";
        $stmt = $conn->prepare($stmt_sql);
        
        $stmt->bind_param("ssi", json_encode($votos_atuais), json_encode($votos_contagem), $dep_id);
        $stmt->execute();
    }
    header("Location: resultado_lider.php?id=$dep_id"); exit; // Redireciona para ver o resultado após votar
}

$indicados = [];
if(count($candidatos_ids)>0){
    $ids_str = implode(',',$candidatos_ids);
    // Usa uma query mais segura e eficiente
    $res = $conn->query("SELECT id,nome FROM usuarios WHERE id IN ($ids_str) ORDER BY nome");
    while($row = $res->fetch_assoc()) $indicados[$row['id']]=$row['nome'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_votacao; ?></title>
    <style>
        /* Estilos para Layout sx*/
        body{font-family:Arial; max-width:600px;margin:30px auto;text-align:center;}
        label{display:inline-block; margin:5px 15px;text-align:left;}
        button{margin:15px;padding:8px 12px;}
        h2{margin-bottom: 20px;}
        p{margin-bottom: 30px;}
    </style>
</head>
<body>
    <h2><?php echo $titulo_votacao; ?> - <?php echo htmlspecialchars($departamento['nome']); ?></h2>
    <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> | <a href="departamentos.php">Voltar</a></p>
    
    <?php if(count($indicados) === 0): ?>
        <p>Não há candidatos para esta rodada de votação.</p>
    <?php else: ?>
        <form method="POST">
            <?php foreach($indicados as $id=>$nome): ?>
            <label>
                <input type="radio" name="lider" value="<?php echo intval($id); ?>" required>
                <?php echo htmlspecialchars($nome); ?>
            </label>
            <?php endforeach; ?>
            <button type="submit" name="votar">Votar</button>
        </form>
    <?php endif; ?>
</body>
</html>