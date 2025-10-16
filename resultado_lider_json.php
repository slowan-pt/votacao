<?php
session_start();
// O usuário precisa estar logado para acessar os resultados
if(!isset($_SESSION['usuario_id'])) exit;

header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) {
    echo json_encode(['erro' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

$dep_id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM departamentos WHERE id=?");
$stmt->bind_param("i",$dep_id);
$stmt->execute();
$departamento = $stmt->get_result()->fetch_assoc();

if(!$departamento) {
    echo json_encode(['erro' => 'Departamento não encontrado.']);
    exit;
}

// --- DETERMINAÇÃO DAS VARIÁVEIS DE VOTAÇÃO ---
$campo_votos_db = 'votos_lider_json'; // Padrão: Votação Inicial
$campo_candidatos_db = 'indicados';

if ($departamento['status_votacao'] == 3) {
    // Desempate
    $campo_votos_db = 'votos_desempate_json'; 
    $campo_candidatos_db = 'candidatos_desempate_json';
}
// --------------------------------------------------

// Pega os IDs dos candidatos corretos (indicados ou desempate)
$candidatos_ids = json_decode($departamento[$campo_candidatos_db] ?? '[]', true) ?? [];

// Pega os votos da rodada atual
$votos_atuais = json_decode($departamento[$campo_votos_db] ?? '[]',true)??[];
$votos_contagem = json_decode($departamento['votos_contagem']??'{}',true)??[];

$indicados_data = [];
$nomes_votantes_cache = []; // Cache para evitar múltiplas consultas ao DB

if(count($candidatos_ids)>0){
    $ids_str = implode(',',$candidatos_ids);
    $res = $conn->query("SELECT id,nome FROM usuarios WHERE id IN ($ids_str) ORDER BY nome");
    
    while($row = $res->fetch_assoc()){
        $id = $row['id'];
        $nomes_votantes = [];
        
        // Coleta os IDs dos usuários que votaram neste candidato
        $quem_votou_ids = [];
        foreach ($votos_atuais as $v) {
            if ($v['votou_em'] == $id) {
                $quem_votou_ids[] = $v['usuario_id'];
            }
        }
        
        // Busca os nomes dos votantes
        foreach ($quem_votou_ids as $uid) {
            if (!isset($nomes_votantes_cache[$uid])) {
                $res_votante = $conn->query("SELECT nome FROM usuarios WHERE id=$uid");
                if($row_votante = $res_votante->fetch_assoc()) {
                    $nomes_votantes_cache[$uid] = htmlspecialchars($row_votante['nome']);
                }
            }
            if (isset($nomes_votantes_cache[$uid])) {
                $nomes_votantes[] = $nomes_votantes_cache[$uid];
            }
        }

        $indicados_data[] = [
            "id" => $id,
            "nome"=> htmlspecialchars($row['nome']),
            "votos"=> $votos_contagem[$id] ?? 0,
            "quem_votou_nomes"=> implode(", ", $nomes_votantes)
        ];
    }
}

// Lógica de Desempate/Vencedor
$lider_id = null; 
$max_votos = -1;
$empatados = [];
foreach ($indicados_data as $info) {
    if ($info['votos'] > $max_votos) {
        $max_votos = $info['votos'];
        $lider_id = $info['id'];
    }
}
foreach ($indicados_data as $info) {
    if ($info['votos'] == $max_votos && $max_votos > 0) {
        $empatados[] = $info['id'];
    }
}

$isEmpate = count($empatados) > 1;

// Retorno JSON
echo json_encode([
    'indicados' => $indicados_data,
    'lider_id' => $lider_id,
    'max_votos' => $max_votos,
    'empatados' => $empatados,
    'isEmpate' => $isEmpate
]);