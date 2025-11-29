<?php
// Credenciais do banco de dados
$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "neuroblogs";

// Cria a conexão com o banco de dados
// A variável foi renomeada para $conn para ser compatível com homepage.php
$conn = mysqli_connect($servidor, $usuario, $senha, $banco);

// Verifica se a conexão falhou
if(!$conn){
    die("Falha na conexao: ".mysqli_connect_error());
}
?>