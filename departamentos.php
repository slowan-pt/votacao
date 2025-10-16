<?php
session_start();
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php"); exit;
}

$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) die("Erro de conexão: ".$conn->connect_error);

$isAdmin = ($_SESSION['usuario_tipo'] ?? '') === 'admin';

// Logout
if(isset($_GET['sair'])){
    $user_id = $_SESSION['usuario_id'];
    $conn->query("UPDATE usuarios SET online=0 WHERE id=$user_id");
    session_destroy();
    header("Location: login.php");
    exit;
}

// Buscar departamentos com líder (se houver)
$deps = $conn->query("
    SELECT d.*, u.nome AS lider_nome 
    FROM departamentos d 
    LEFT JOIN usuarios u ON d.lider_escolhido_2026=u.id 
    ORDER BY d.nome
");

// Buscar usuários logados
$online = $conn->query("SELECT nome FROM usuarios WHERE online=1 ORDER BY nome");
$usuarios_online = [];
while($u = $online->fetch_assoc()){
    $usuarios_online[]=$u['nome'];
}
$online_count = count($usuarios_online);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Departamentos</title>
<style>
body{font-family:Arial; max-width:700px; margin:30px auto;}
table{width:100%; border-collapse:collapse;}
th,td{border:1px solid #ccc; padding:8px; text-align:left;}
th{background:#f0f0f0;}
button{padding:5px 10px;}
.online{margin-bottom:15px; font-weight:bold;}
.logout{float:right;}
.loading-banner{
    display:flex;
    align-items:center;
    background:#f0f8ff;
    border:1px solid #ccc;
    padding:10px;
    margin-bottom:10px;
    font-weight:bold;
}
.spinner{
    border:4px solid #f3f3f3;
    border-top:4px solid #3498db;
    border-radius:50%;
    width:20px;
    height:20px;
    animation: spin 1s linear infinite;
    margin-right:10px;
}
@keyframes spin{
    0%{transform:rotate(0deg);}
    100%{transform:rotate(360deg);}
}
</style>
<script>
// Função para verificar se admin iniciou a votação
function checarVotacao(){
    fetch('verifica_votacao.php')
        .then(resp => resp.json())
        .then(data => {
            if(data.votacao_id){ 
                // Redireciona para votacao.php (indicação) ou votacao_lider.php (desempate)
                if (data.status === 3) {
                    window.location.href='votacao_lider.php?id=' + data.votacao_id;
                } else {
                    window.location.href='votacao.php?id=' + data.votacao_id;
                }
            }
        });
}
<?php if(!$isAdmin): ?>
// Usuários normais checam a cada 2s
setInterval(checarVotacao,2000);
<?php endif; ?>
</script>
</head>
<body>

<h2>Departamentos 
    <a href="?sair=1"><button class="logout">Sair</button></a>
</h2>
<p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></p>
<p class="online">Usuários logados (<?php echo $online_count; ?>): <?php echo htmlspecialchars(implode(', ',$usuarios_online)); ?></p>

<?php if(!$isAdmin): ?>
<div class="loading-banner">
    <div class="spinner"></div>
    Aguardando o admin iniciar a votação...
</div>
<?php endif; ?>

<table>
<tr>
    <th>Departamento</th>
    <th>Líder escolhido</th>
    <th>Ação</th>
</tr>

<?php while($d = $deps->fetch_assoc()): ?>
<tr>
    <td><?php echo htmlspecialchars($d['nome']); ?></td>
    <td>
        <?php 
        if(!empty($d['lider_escolhido_2026'])){
            echo htmlspecialchars($d['lider_nome']);
        } else {
            echo "<em>Aguardando votação</em>";
        }
        ?>
    </td>
    <td>
        <?php 
        $candidatos_desempate_ids = json_decode($d['candidatos_desempate_json']??'[]', true)??[];
        $aguardando_desempate = empty($d['lider_escolhido_2026']) && $isAdmin && !empty($candidatos_desempate_ids) && $d['status_votacao'] == 0;
        
        if($aguardando_desempate): // Opção 1: Continuar desempate salvo
        ?>
            <a href="iniciar_votacao.php?id=<?php echo $d['id']; ?>&desempate=1">
                <button>Continuar Desempate</button>
            </a>
        <?php elseif(empty($d['lider_escolhido_2026']) && $isAdmin): // Opção 2: Iniciar votação normal ?>
            <a href="iniciar_votacao.php?id=<?php echo $d['id']; ?>">
                <button>Iniciar votação</button>
            </a>
        <?php else: // Opção 3: Desabilitado ou já finalizado ?>
            <button disabled>Iniciar votação</button>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>