<?php
session_start();
if(!isset($_SESSION['usuario_id'])) exit;

$conn = new mysqli("localhost","root","","votacao");
$dep_id = intval($_GET['id'] ?? 0);

// Logados
$usuarios_online = [];
$ids_online = [];
$res = $conn->query("SELECT id,nome FROM usuarios WHERE online=1 ORDER BY nome");
while($u=$res->fetch_assoc()){
    $usuarios_online[] = ['id'=>$u['id'],'nome'=>$u['nome']];
    $ids_online[]=$u['id'];
}

// Departamento
$stmt = $conn->prepare("SELECT * FROM departamentos WHERE id=?");
$stmt->bind_param("i",$dep_id);
$stmt->execute();
$dep = $stmt->get_result()->fetch_assoc();
$votos = json_decode($dep['votos_json']??'[]',true)??[];

$usuarios=[];
foreach($usuarios_online as $u){
    $usuarios[]=['id'=>$u['id'],'nome'=>$u['nome'],'votou'=>in_array($u['id'],$votos)];
}

$total = count($ids_online);
$votaram = count(array_intersect($votos,$ids_online));
$faltam = $total-$votaram;

echo json_encode([
    'total'=>$total,
    'votaram'=>$votaram,
    'faltam'=>$faltam,
    'online'=>$total,
    'usuarios'=>$usuarios
]);
