<?php
session_start();
include "conexao.php";

// 1. VERIFICAÇÃO DE SEGURANÇA
// Garante que apenas usuários com nível de ADMIN (3) possam acessar esta página
if (!isset($_SESSION['usuario']) || $_SESSION['nivel'] != 3) {
    // Redireciona para o login ou homePage se não for Admin
    header("Location: login.php");
    exit;
}

// 2. VERIFICAÇÃO DO ID
// Verifica se o ID do usuário a ser excluído foi fornecido na URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redireciona de volta ao painel com uma mensagem de erro, se necessário
    header("Location: painel.php");
    exit;
}

$id_excluir = $_GET['id'];
$id_logado = $_SESSION['usuario_id'];

// 3. SEGURANÇA EXTRA: IMPEDE QUE O ADMIN EXCLUA A SI MESMO
if ($id_excluir == $id_logado) {
    // Você pode adicionar uma mensagem de erro aqui
    header("Location: painel.php?erro=autoexclusao");
    exit;
}


// 4. EXECUTAR EXCLUSÃO SEGURA
// Usamos prepared statements para evitar SQL Injection
$sql_delete = "DELETE FROM usuarios WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_delete);

if ($stmt) {
    // 'i' indica que o parâmetro é um inteiro (ID)
    mysqli_stmt_bind_param($stmt, "i", $id_excluir);
    
    if (mysqli_stmt_execute($stmt)) {
        // Exclusão bem-sucedida
        mysqli_stmt_close($stmt);
        // 5. REDIRECIONAMENTO FINAL
        header("Location: painel.php?sucesso=excluido");
        exit;
    } else {
        // Erro na execução
        mysqli_stmt_close($stmt);
        header("Location: painel.php?erro=falha_exclusao");
        exit;
    }
} else {
    // Erro na preparação da query
    header("Location: painel.php?erro=falha_query");
    exit;
}
?>