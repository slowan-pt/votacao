<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php"); exit;
}

$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) die("Erro: ".$conn->connect_error);

$dep_id = intval($_GET['id'] ?? 0);

// Buscar departamento
$stmt = $conn->prepare("SELECT * FROM departamentos WHERE id=?");
$stmt->bind_param("i",$dep_id);
$stmt->execute();
$departamento = $stmt->get_result()->fetch_assoc();
if(!$departamento) die("Departamento não encontrado.");
if(!empty($departamento['lider_escolhido_2026'])) header("Location: departamentos.php");

// Todos os membros (checkboxes)
$usuarios_all = $conn->query("SELECT id,nome FROM usuarios ORDER BY nome");
$listaUsuariosAll = []; while($u=$usuarios_all->fetch_assoc()) $listaUsuariosAll[]=$u;

// Usuários logados
$usuarios_online = $conn->query("SELECT id,nome FROM usuarios WHERE online=1 ORDER BY nome");
$listaUsuariosOnline = []; $usuarios_online_ids = [];
while($u=$usuarios_online->fetch_assoc()){
    $listaUsuariosOnline[]=$u;
    $usuarios_online_ids[] = intval($u['id']);
}

$online_count = count($usuarios_online_ids);

// --- Garantir valores defaults para campos JSON (evita NULL/undefined) ---
$indicados_json = $departamento['indicados'] ?? '[]';
if($indicados_json === null || $indicados_json === '') $indicados_json = '[]';
$indicados_ids = json_decode($indicados_json, true);
if(!is_array($indicados_ids)) $indicados_ids = [];

$votos_json = $departamento['votos_json'] ?? '[]';
if($votos_json === null || $votos_json === '') $votos_json = '[]';
$votos = json_decode($votos_json, true);
if(!is_array($votos)) $votos = [];

// Forçar todos os valores de $votos para int (evita comparação falha)
$votos = array_map('intval', $votos);

// usuário atual
$user_id = intval($_SESSION['usuario_id']);

// Processar envio do formulário
$bloquear = false;
if(isset($_POST['indicar'])){
    $novos = $_POST['indicados'] ?? [];
    // garantir que sejam inteiros e únicos
    $novos_sanitizados = array_values(array_unique(array_map('intval', $novos)));
    $json = json_encode($novos_sanitizados);

    // registrar voto do usuário atual caso ainda não registrado
    if(!in_array($user_id, $votos, true)) $votos[] = $user_id;
    $votos_json_to_save = json_encode(array_values(array_unique($votos)));

    $stmt = $conn->prepare("UPDATE departamentos SET indicados=?, votos_json=? WHERE id=?");
    $stmt->bind_param("ssi",$json,$votos_json_to_save,$dep_id);
    $stmt->execute();

    // atualizar variáveis em execução
    $indicados_ids = $novos_sanitizados;
    $votos = array_map('intval', json_decode($votos_json_to_save, true));
    $bloquear = true; // bloquear inputs para este usuário (frontend)
}

if(isset($_POST['nenhum'])){
    if(!in_array($user_id, $votos, true)) $votos[] = $user_id;
    $votos_json_to_save = json_encode(array_values(array_unique($votos)));
    $stmt = $conn->prepare("UPDATE departamentos SET votos_json=? WHERE id=?");
    $stmt->bind_param("si",$votos_json_to_save,$dep_id);
    $stmt->execute();

    $votos = array_map('intval', json_decode($votos_json_to_save, true));
    $bloquear = true; // bloquear inputs
}

// Contagem votos
$total_votantes = count($usuarios_online_ids);

// garantir que $votos só contenha IDs de usuários que estão online (opcional)
// $votos = array_values(array_intersect($votos, $usuarios_online_ids));

// Quantos votaram: intersect entre $votos e $usuarios_online_ids
$quantos_votaram = 0;
if(is_array($votos) && is_array($usuarios_online_ids)){
    $quantos_votaram = count(array_intersect($votos, $usuarios_online_ids));
}
$faltam = $total_votantes - $quantos_votaram;

// Redireciona se todos votaram
if($quantos_votaram === $total_votantes && $total_votantes > 0){
    header("Location: votacao_lider.php?id=$dep_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Votação - <?php echo htmlspecialchars($departamento['nome']); ?></title>
<style>
body{font-family:Arial; max-width:600px;margin:30px auto;text-align:center;}
label{display:block;margin:5px 0;text-align:left;}
button{margin:5px;padding:8px 12px;}
.colunas{display:flex; justify-content:space-between; margin-bottom:20px;}
.coluna{width:48%; border:1px solid #ccc; padding:10px; border-radius:5px; min-height:150px;}
.coluna h4{margin-top:0;}
#busca{margin-bottom:10px; padding:6px; width:100%; box-sizing:border-box;}
.modal{
  display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
  background:rgba(0,0,0,0.6); justify-content:center; align-items:center;
}
.modal-content{
  background:#fff; padding:20px; border-radius:8px; width:80%; max-width:400px; text-align:left;
}
.spinner{
  border:4px solid #f3f3f3; border-top:4px solid #3498db; border-radius:50%; width:30px; height:30px;
  animation: spin 1s linear infinite; margin:10px auto;
}
@keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
input:disabled, button:disabled{background:#ccc;}
</style>
<script>
// Bloqueia inputs
function bloquearInputs(){
    document.querySelectorAll('input[type=checkbox]').forEach(i=>i.disabled=true);
    document.querySelectorAll('button').forEach(b=>b.disabled=true);
}

// Atualiza modal com já votaram / faltam
function atualizarModal(){
    fetch('contador_votos.php?id=<?php echo $dep_id;?>')
    .then(res=>res.json())
    .then(data=>{
        let ja=document.getElementById('jaVotaramModal');
        let faltam=document.getElementById('faltamVotarModal');
        ja.innerHTML=''; faltam.innerHTML='';
        data.usuarios.forEach(u=>{
            let div=document.createElement('div'); div.innerText=u.nome;
            if(u.votou) ja.appendChild(div); else faltam.appendChild(div);
        });
        if(data.votaram==data.total && data.total>0){
            window.location='votacao_lider.php?id=<?php echo $dep_id;?>';
        }
    })
    .catch(err=>{
        // falha silenciosa; não trava a página
        console.error('Erro ao atualizar modal:', err);
    });
}

// Mostrar modal
function mostrarModal(){
    document.getElementById('modal').style.display='flex';
}

// Filtrar membros
function filtrarMembros(){
    let filtro = document.getElementById('busca').value.toLowerCase();
    document.querySelectorAll('#listaCheck label').forEach(label=>{
        label.style.display = label.innerText.toLowerCase().includes(filtro)?'':'none';
    });
}

window.onload = function(){
    <?php if($bloquear): ?>
    bloquearInputs();
    mostrarModal();
    setInterval(atualizarModal,1000);
    <?php endif; ?>
};
</script>
</head>
<body>

<h2><?php echo htmlspecialchars($departamento['nome']); ?> - Indicar Candidatos</h2>
<p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> | <a href="departamentos.php">Voltar</a></p>

<!-- Colunas topo -->
<div class="colunas">
    <div class="coluna">
        <h4>Já votaram (<?php echo $quantos_votaram; ?>)</h4>
        <div id="jaVotaram">
            <?php foreach($listaUsuariosOnline as $u): if(in_array($u['id'],$votos)) echo "<div>".htmlspecialchars($u['nome'])."</div>"; endforeach; ?>
        </div>
    </div>
    <div class="coluna">
        <h4>Faltam votar (<?php echo $faltam; ?>)</h4>
        <div id="faltamVotar">
            <?php foreach($listaUsuariosOnline as $u): if(!in_array($u['id'],$votos)) echo "<div>".htmlspecialchars($u['nome'])."</div>"; endforeach; ?>
        </div>
    </div>
</div>

<!-- Busca -->
<input type="text" id="busca" placeholder="Buscar membro..." onkeyup="filtrarMembros()">

<!-- Lista de checkboxes -->
<form method="POST" id="listaCheck">
    <?php foreach($listaUsuariosAll as $u):
        $checked = in_array($u['id'],$indicados_ids) ? 'checked' : '';
    ?>
    <label>
        <input type="checkbox" name="indicados[]" value="<?php echo intval($u['id']); ?>" <?php echo $checked; ?>>
        <?php echo htmlspecialchars($u['nome']); ?>
    </label>
    <?php endforeach; ?>
    <button type="submit" name="indicar">Salvar Indicados</button>
</form>

<form method="POST">
    <button type="submit" name="nenhum">Prefiro não indicar ninguém</button>
</form>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <h3>Votação em andamento</h3>
        <div class="spinner"></div>
        <div class="colunas">
            <div class="coluna">
                <h4>Já votaram</h4>
                <div id="jaVotaramModal"></div>
            </div>
            <div class="coluna">
                <h4>Faltam votar</h4>
                <div id="faltamVotarModal"></div>
            </div>
        </div>
        <p>Aguarde todos os usuários terminarem de votar...</p>
    </div>
</div>

</body>
</html>
