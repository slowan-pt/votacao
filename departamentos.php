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
    body{font-family:Arial; max-width:800px;margin:30px auto;}
    table{width:100%; border-collapse:collapse;margin-top:20px;text-align:left;}
    th,td{border:1px solid #ccc;padding:10px;text-align:left;}
    th{background:#f0f0f0;font-weight:bold;}
    button{padding:8px 12px;margin:5px 0;cursor:pointer;}
    .logout{float:right;margin-top:-30px;}
    .online{font-size:0.9em;color:#555;margin-bottom:20px;}
    .loading-banner{background:#fff3cd;color:#856404;padding:15px;text-align:center;border-radius:5px;margin-top:20px;display:flex;align-items:center;justify-content:center;}
    .spinner{border:4px solid rgba(0,0,0,.1);border-left-color:#856404;border-radius:50%;width:20px;height:20px;animation:spin 1s linear infinite;margin-right:10px;}
    @keyframes spin{to{transform:rotate(360deg);}}
</style>

<script>
function checarVotacao(){
    fetch('verifica_votacao.php')
        .then(response => response.json())
        .then(data => {
            // Se houver uma votação ativa (votacao_id não é nulo)
            if(data.votacao_id){
                let pagina = '';
                
                // Status 1: Indicação. Redireciona para votacao.php
                if(data.status == 1){ 
                    pagina = 'votacao.php'; 
                } 
                // Status 3: Votação de Líder/Desempate. Redireciona para votacao_lider.php
                else if (data.status == 3){ 
                    pagina = 'votacao_lider.php'; 
                }

                if(pagina){
                    window.location.href = pagina + '?id=' + data.votacao_id;
                }
            }
            // Se votacao_id for null, o usuário permanece na tela de departamentos.
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
        // Lógica para o botão "Continuar Desempate"
        $candidatos_desempate_ids = json_decode($d['candidatos_desempate_json']??'[]', true)??[];
        $aguardando_desempate = empty($d['lider_escolhido_2026']) && $isAdmin && !empty($candidatos_desempate_ids) && $d['status_votacao'] == 0;
        
        if($aguardando_desempate): // Opção 1: Continuar desempate salvo
        ?>
            <a href="iniciar_votacao.php?id=<?php echo $d['id']; ?>&desempate=1">
                <button>Continuar Desempate</button>
            </a>
        <?php elseif(empty($d['lider_escolhido_2026']) && $isAdmin): // Opção 2: Iniciar Indicação/Votação
        ?>
            <a href="iniciar_votacao.php?id=<?php echo $d['id']; ?>">
                <button>Iniciar votação</button>
            </a>
        <?php elseif($d['status_votacao'] == 1 || $d['status_votacao'] == 3): // Opção 3: Ver resultado (enquanto votação está ativa)
        ?>
            <a href="resultado_lider.php?id=<?php echo $d['id']; ?>">
                <button>Ver Resultados</button>
            </a>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>