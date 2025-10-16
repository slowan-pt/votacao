<?php
session_start();

$conn = new mysqli("localhost","root","", "votacao");
if($conn->connect_error) die("Erro de conexão: ".$conn->connect_error);

$erro = '';

if(isset($_POST['entrar'])){
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    // Consulta direta (senha texto puro)
    $stmt = $conn->prepare("SELECT id,nome,senha,tipo FROM usuarios WHERE email=? AND senha=? LIMIT 1");
    $stmt->bind_param("ss", $email, $senha);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if($usuario){
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        $conn->query("UPDATE usuarios SET online=1 WHERE id=".$usuario['id']);
        header("Location: departamentos.php");
        exit;
    } else {
        $erro = "E-mail ou senha inválidos";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body{font-family:Arial; max-width:400px;margin:50px auto;}
input{display:block;margin:10px 0;padding:8px;width:100%;}
button{padding:8px 12px;}
.erro{color:red;}
</style>
</head>
<body>
<h2>Login</h2>
<form method="POST">
    <input type="email" name="email" placeholder="E-mail" required>
    <input type="password" name="senha" placeholder="Senha" required>
    <button type="submit" name="entrar">Entrar</button>
</form>
<?php if($erro) echo "<p class='erro'>$erro</p>"; ?>
</body>
</html>
