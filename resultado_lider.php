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


$candidatos_ids = json_decode($departamento['indicados'],true)??[];
// Se for desempate, usa a lista de candidatos de desempate
if ($departamento['status_votacao'] == 3) {
    $candidatos_ids = json_decode($departamento['candidatos_desempate_json'], true) ?? [];
}

$votos_lider = json_decode($departamento['votos_lider_json']??'[]',true)??[];
$votos_contagem = json_decode($departamento['votos_contagem']??'{}',true)??[];

$indicados = [];
if(count($candidatos_ids)>0){
    $ids_str = implode(',',$candidatos_ids);
    // ATENÇÃO: Risco de SQL Injection potencial na query $res. Prepared statement seria melhor.
    $res = $conn->query("SELECT id,nome FROM usuarios WHERE id IN ($ids_str) ORDER BY nome");
    while($row = $res->fetch_assoc()){
        $id = $row['id'];
        $quem_votou = array_map(function($v) use ($id){ return $v['votou_em']==$id?$v['usuario_id']:null; }, $votos_lider);
        $indicados[$id] = [
            "nome"=>htmlspecialchars($row['nome']),
            "votos"=>$votos_contagem[$id] ?? 0,
            "quem_votou"=>array_filter($quem_votou)
        ];
    }
}

// --- Lógica de Detecção de Empate ---
$lider_id = null; 
$max_votos = -1;
$empatados = [];

// Encontra o máximo de votos
foreach ($indicados as $id => $info) {
    if ($info['votos'] > $max_votos) {
        $max_votos = $info['votos'];
        $lider_id = $id;
    }
}

// Identifica todos os empatados
foreach ($indicados as $id => $info) {
    if ($info['votos'] == $max_votos && $max_votos > 0) {
        $empatados[] = $id;
    }
}
$isEmpate = count($empatados) > 1;

// --- Lógica de Ação do Admin ---
if ($isAdmin) {
    if (isset($_POST['finalizar'])) {
        if($isEmpate) die("Erro: Não é possível finalizar com empate.");
        
        // Ação: Finalizar Votação (Sem Empate)
        $stmt = $conn->prepare("UPDATE departamentos SET lider_escolhido_2026=?, status_votacao=0, candidatos_desempate_json=NULL WHERE id=?");
        $stmt->bind_param("ii", $lider_id, $dep_id);
        $stmt->execute();
        header("Location: departamentos.php"); exit;
    } elseif (isset($_POST['desempate_agora']) && $isEmpate) {
        // Ação: Iniciar Votação de Desempate Imediata
        $empatados_json = json_encode($empatados);
        
        // Limpa votos anteriores, armazena candidatos do desempate e define status_votacao=3
        $stmt = $conn->prepare("UPDATE departamentos SET candidatos_desempate_json=?, votos_lider_json='[]', votos_contagem='{}', status_votacao=3 WHERE id=?");
        $stmt->bind_param("si", $empatados_json, $dep_id);
        $stmt->execute();

        header("Location: votacao_lider.php?id=$dep_id"); exit;
    } elseif (isset($_POST['desempate_depois']) && $isEmpate) {
        // Ação: Salvar Empate para depois
        $empatados_json = json_encode($empatados);
        
        // Armazena candidatos do desempate e DESATIVA a votação (status_votacao=0)
        $stmt = $conn->prepare("UPDATE departamentos SET candidatos_desempate_json=?, status_votacao=0 WHERE id=?");
        $stmt->bind_param("si", $empatados_json, $dep_id);
        $stmt->execute();

        // REDIRECIONA ADMIN PARA DEPARTAMENTOS
        header("Location: departamentos.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Resultado da Votação</title>
<style>
body{font-family:Arial; max-width:700px; margin:30px auto; text-align:center;}
h2{margin-bottom: 20px;}
p{margin-bottom: 30px;}
table{width:100%; border-collapse:collapse; margin-top:20px; text-align:left;}
th,td{border:1px solid #ccc; padding:10px; text-align:left;}
th{background:#f0f0f0; font-weight:bold;}
button{padding:8px 12px; margin:10px; cursor:pointer;}
</style>
</head>
<body>
<h2>Resultado da votação - <?php echo htmlspecialchars($departamento['nome']); ?></h2>
<p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> | <a href="departamentos.php">Voltar</a></p>

<?php 
if(count($indicados)==0){ 
    echo "<p>Nenhum candidato.</p>"; 
    exit; 
} 
?>

<table>
<tr><th>Candidato</th><th>Votos Recebidos</th><th>Quem votou</th></tr>
<?php 
foreach($indicados as $id=>$info): 
    $is_leader = ($id == $lider_id && !$isEmpate) ? 'style="background-color: #e6ffe6; font-weight: bold;"' : '';
    $is_tied = (in_array($id, $empatados) && $isEmpate) ? 'style="background-color: #fffacd; font-weight: bold;"' : $is_leader;
?>
<tr <?php echo $is_tied; ?>>
<td><?php echo $info['nome']; ?></td>
<td><?php echo $info['votos']; ?></td>
<td>
<?php 
$nomes_votantes = [];
foreach($info['quem_votou'] as $uid){
    // ATENÇÃO: Risco de SQL Injection aqui, o ID não está sendo sanitizado/preparado antes da query
    $res = $conn->query("SELECT nome FROM usuarios WHERE id=$uid");
    // Garante que o nome seja sanitizado ao exibir
    if($row = $res->fetch_assoc()) $nomes_votantes[] = htmlspecialchars($row['nome']);
}
echo implode(", ",$nomes_votantes);
?>
</td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($isAdmin): ?>
    <form method="POST">
        <?php if ($isEmpate): ?>
            <h3>Resultado: Empate!</h3>
            <button type="submit" name="desempate_agora">Votação de Desempate</button>
            <button type="submit" name="desempate_depois">Fazer desempate depois</button>
        <?php else: ?>
            <button type="submit" name="finalizar">Finalizar Votação</button>
        <?php endif; ?>
    </form>
<?php endif; ?>

</body>
</html>