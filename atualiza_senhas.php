<?php
$conn = new mysqli("localhost","root","","votacao");
if($conn->connect_error) die("Erro: ".$conn->connect_error);

// Seleciona todos os usuários
$usuarios = $conn->query("SELECT id, senha FROM usuarios");
while($u = $usuarios->fetch_assoc()){
    // Se a senha ainda não estiver criptografada (menos de 60 chars)
    if(strlen($u['senha']) < 60){
        $hash = password_hash($u['senha'], PASSWORD_DEFAULT);
        $conn->query("UPDATE usuarios SET senha='$hash' WHERE id=".$u['id']);
        echo "Usuário ID ".$u['id']." atualizado.<br>";
    }
}
echo "Atualização concluída!";
?>
