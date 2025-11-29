<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
};
include "conexao.php";


$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ALTERADO: Troca $_POST['nome'] por $_POST['apelido']
    $apelido = $_POST['apelido'];
    $email = $_POST['email']; 
    $nivel = $_POST['nivel'];
    
    // ALTERADO: Troca 'nome' por 'apelido' na consulta UPDATE
    $sql = "UPDATE usuarios SET apelido='$apelido', email='$email', nivel='$nivel' WHERE id=$id";
    mysqli_query($conn, $sql); 
    header("Location: painel.php");
} else {
    // ALTERADO: Troca 'nome' por 'apelido' na consulta SELECT
    $sql = "SELECT id, apelido, email, nivel FROM usuarios WHERE id=$id";
    $resultado = mysqli_query($conn, $sql); 
    $usuario = mysqli_fetch_assoc($resultado);
} 

?>

<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <title>Editar Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="editar.css"> 
  </head>
  <body>
    <div class="editar-container">
        <div class="card">
            <h1 class="text-center mb-4" style="color:rgb(0, 0, 0);">Editar Usuário</h1>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Apelido:</label>
                    <input type="text" name="apelido" class="form-control" value="<?= $usuario['apelido']?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail:</label>
                    <input type="email" name="email" class="form-control" value="<?= $usuario['email']?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nível:</label>
                    <input type="number" name="nivel" class="form-control" value="<?= $usuario['nivel']?>" required>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-success">Salvar Alterações</button>
                    <a href="painel.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
  </body>
</html>