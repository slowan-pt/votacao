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
// Se o status for 0 e ainda não tiver líder, significa que a votação foi pausada (desempate depois) ou não iniciada.
if ($departamento['status_votacao'] == 0 && empty($departamento['lider_escolhido_2026'])) {
    if (!$isAdmin) {
        header("Location: departamentos.php"); exit;
    }
}


// Determina qual lista de candidatos usar (indicados iniciais ou empatados)
if ($departamento['status_votacao'] == 3) {
    // Votação de Desempate: usa candidatos_desempate_json
    $candidatos_ids = json_decode($departamento['candidatos_desempate_json'], true) ?? [];
    $titulo_votacao = "Desempate de Líder";
} else {
    // Votação de Líder Inicial (Status 2, ou padrão): usa indicados
    $candidatos_ids = json_decode($departamento['indicados'], true) ?? [];
    $titulo_votacao = "Votação de Líder";
}

$votos_lider = json_decode($departamento['votos_lider_json']??'[]',true)??[];
$user_id = $_SESSION['usuario_id'];

// Verifica se o usuário já votou
$ja_votou = false;
foreach($votos_lider as $v){
    if($v['usuario_id'] == $user_id){
        $ja_votou = true;
        break;
    }
}
if($ja_votou) {
    header("Location: resultado_lider.php?id=$dep_id"); exit;
}


if(isset($_POST['votar'])){
    $escolha = intval($_POST['lider'] ?? 0);
    if($escolha>0){
        $votos_contagem = json_decode($departamento['votos_contagem'] ?? '{}', true);
        $votos_lider[] = ["usuario_id"=>$user_id,"votou_em"=>$escolha];
        $votos_contagem[$escolha] = ($votos_contagem[$escolha] ?? 0)+1;
        $stmt = $conn->prepare("UPDATE departamentos SET votos_lider_json=?, votos_contagem=? WHERE id=? ");
        $stmt->bind_param("ssi", json_encode($votos_lider), json_encode($votos_contagem), $dep_id);
        $stmt->execute();
    }
    header("Location: resultado_lider.php?id=$dep_id"); exit;
}

$indicados = [];
if(count($candidatos_ids)>0){
    // ATENÇÃO: Risco de SQL Injection aqui. Uso de prepared statements seria melhor.
    $ids_str = implode(',',$candidatos_ids);
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