<?php
session_start();
if(!isset($_SESSION['usuario_id'])){ header("Location: login.php"); exit; }

$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) die("Erro: ".$conn->connect_error);

$dep_id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM departamentos WHERE id=? ");
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

// Apenas o Admin pode enviar POST para finalizar/desempate
if ($isAdmin) {
    // --- Lógica de Ação do Admin ---
    
    // 1. Finalizar Votação (Vencedor único)
    if (isset($_POST['finalizar'])) {
        $lider_id = intval($_POST['lider_id_vencedor'] ?? 0);
        if($lider_id > 0){
            // Finaliza o departamento, define o líder e zera o status
            $stmt = $conn->prepare("UPDATE departamentos SET lider_escolhido_2026=?, status_votacao=0 WHERE id=?");
            $stmt->bind_param("ii",$lider_id,$dep_id);
            $stmt->execute();
        }
        header("Location: departamentos.php"); exit;
    } 
    
    // 2. Votação de Desempate (Iniciar Desempate Imediato)
    elseif (isset($_POST['desempate_agora'])) {
        $empatados_json = $_POST['empatados_json'] ?? '[]';
        
        // Define os candidatos para o desempate e inicia a votação (status=3), limpando os votos ATUAIS
        $stmt = $conn->prepare("UPDATE departamentos 
                                SET candidatos_desempate_json=?, 
                                status_votacao=3, 
                                votos_desempate_json='[]',
                                votos_contagem='{}'
                                WHERE id=?");
        $stmt->bind_param("si", $empatados_json, $dep_id);
        $stmt->execute();

        header("Location: votacao_lider.php?id=$dep_id"); exit;
    }
    
    // 3. Fazer Desempate Depois (Pausar)
    elseif (isset($_POST['desempate_depois'])) {
        $empatados_json = $_POST['empatados_json'] ?? '[]';
        
        // Salva os candidatos para o desempate e define status=0 (Pausa)
        $stmt = $conn->prepare("UPDATE departamentos 
                                SET candidatos_desempate_json=?, 
                                status_votacao=0 
                                WHERE id=?");
        $stmt->bind_param("si", $empatados_json, $dep_id);
        $stmt->execute();
        
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
    body{font-family:Arial; max-width:800px; margin:30px auto; text-align:center;}
    table{width:100%; border-collapse:collapse; margin-top:20px;}
    th, td{border:1px solid #ccc; padding:10px; text-align:left;}
    th{background-color:#f2f2f2;}
    .spinner{border:4px solid #f3f3f3; border-top:4px solid #3498db; border-radius:50%; width:30px; height:30px; animation: spin 1s linear infinite; margin:10px auto;}
    @keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
    #acoesAdmin button{margin:5px; padding:10px 15px;}
</style>
</head>
<body>
<h2>Resultado da Votação - <?php echo htmlspecialchars($departamento['nome']); ?></h2>
<p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> | <a href="departamentos.php">Voltar</a></p>

<table>
    <thead>
        <tr>
            <th>Candidato</th>
            <th>Votos Recebidos</th>
            <th>Quem votou</th>
        </tr>
    </thead>
    <tbody id="tabelaResultado">
        <tr><td colspan="3"><div class="spinner"></div> Carregando resultados...</td></tr>
    </tbody>
</table>

<div id="acoesAdmin">
    <?php if(!$isAdmin): ?>
        <p>Aguardando o administrador finalizar a rodada.</p>
    <?php endif; ?>
</div>

<script>
    const depId = <?php echo $dep_id; ?>;
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    const tabelaBody = document.getElementById('tabelaResultado');
    const acoesAdminDiv = document.getElementById('acoesAdmin');

    function buscarResultados() {
        fetch(`resultado_lider_json.php?id=${depId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.erro) {
                    tabelaBody.innerHTML = `<tr><td colspan="3">Erro: ${data.erro}</td></tr>`;
                    return;
                }
                
                // 1. Limpar tabela e popular
                tabelaBody.innerHTML = '';
                data.indicados.forEach(info => {
                    const row = tabelaBody.insertRow();
                    row.innerHTML = `
                        <td>${info.nome}</td>
                        <td>${info.votos}</td>
                        <td>${info.quem_votou_nomes}</td>
                    `;
                });

                // 2. Gerar título e botões de ação (Apenas para Admin)
                if (isAdmin) {
                    let htmlBotoes = '<form method="POST">';

                    if (data.isEmpate) {
                        htmlBotoes += '<h3>Resultado: Empate!</h3>';
                        // Adicionar candidatos empatados para envio no POST
                        htmlBotoes += `<input type="hidden" name="empatados_json" value='${JSON.stringify(data.empatados)}'>`;
                        htmlBotoes += '<button type="submit" name="desempate_agora">Votação de Desempate</button>';
                        htmlBotoes += '<button type="submit" name="desempate_depois">Fazer desempate depois</button>';
                    } else if (data.lider_id) {
                        htmlBotoes += `<h3>Líder Vencedor: ${data.indicados.find(i => i.id === data.lider_id).nome}</h3>`;
                        htmlBotoes += `<input type="hidden" name="lider_id_vencedor" value="${data.lider_id}">`;
                        htmlBotoes += '<button type="submit" name="finalizar">Finalizar Votação</button>';
                    }

                    htmlBotoes += '</form>';
                    acoesAdminDiv.innerHTML = htmlBotoes;
                } else if (!isAdmin && data.isEmpate) {
                    // Mensagem para o usuário comum em caso de empate
                    acoesAdminDiv.innerHTML = '<p>Houve um empate. Aguarde o administrador iniciar a votação de desempate.</p>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar resultados:', error);
                tabelaBody.innerHTML = `<tr><td colspan="3">Erro ao carregar resultados. Tentando novamente...</td></tr>`;
            });
    }

    // Inicia a atualização e configura para repetir a cada 2s para acompanhar a votação/ação do admin
    buscarResultados();
    setInterval(buscarResultados, 2000);
</script>
</body>
</html>