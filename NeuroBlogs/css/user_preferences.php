<?php
// PHP - Arquivo: css/user_preferences.php
// Este arquivo deve ser chamado via <link rel="stylesheet" href="css/user_preferences.php?user_id=...">
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/css');

// Inclui sua conexão MySQLi
// O caminho deve ser ajustado, pois este arquivo está dentro da pasta 'css'
include "../conexao.php"; 

// 🎯 CORREÇÃO CRÍTICA: DEFINIR $userId PRIMEIRO
// O $userId DEVE ser definido antes de qualquer checagem que o utilize, incluindo os testes de debug.
$userId = $_GET['user_id'] ?? 0;

// Código de Teste (APENAS PARA DEBBUG)
// Agora o teste checa o valor CORRETO de $userId.

if (!$conn) {
    // Se a conexão falhar, o CSS deve mostrar um erro visual
    echo 'body { border: 5px solid red !important; }';
    exit;
}
if ($userId == 0) {
    // Se o ID do usuário for 0 ou não estiver na URL (o que deveria acontecer se a sessão não estiver funcionando)
    echo 'body { border: 5px solid orange !important; }';
    exit;
}
// Fim do Código de Teste


if ($userId == 0 || !isset($conn)) {
    // Se o ID for inválido ou a conexão falhar, retorna estilos padrão para evitar erro.
    echo '/* Estilos padrão aplicados */';
    exit;
}

// 1. Busca as preferências no DB
$sql = "SELECT cor_fundo_pref, cor_texto_pref, tamanho_fonte_pref, fonte_preferida FROM perfil_usuario WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prefs = mysqli_fetch_assoc($result);

// 2. Define as variáveis com valores seguros ou defaults
$fundo = $prefs['cor_fundo_pref'] ?? '#f5f5f5';
$texto = $prefs['cor_texto_pref'] ?? '#2c3e50';
$tamanho = $prefs['tamanho_fonte_pref'] ?? 'medium';
$fonte = $prefs['fonte_preferida'] ?? 'sans-serif';

// Converte o tamanho da fonte (nome) para um valor em pixels ou em/rem
$tamanho_map = [
    'small' => '14px',
    'medium' => '16px',
    'large' => '18px',
    'xlarge' => '20px'
];
$tamanho_base_fonte = $tamanho_map[$tamanho] ?? '16px';

// Sanitiza o nome da fonte para uso seguro no CSS
$fonte_preferida_sanitizada = htmlspecialchars($fonte, ENT_QUOTES, 'UTF-8');

?>
:root {
    /* Variáveis de Acessibilidade */
    --fundo-preferido: <?= $fundo ?>;
    --texto-preferido: <?= $texto ?>;
    --tamanho-base-fonte: <?= $tamanho_base_fonte ?>;
    --fonte-preferida: '<?= $fonte_preferida_sanitizada ?>', sans-serif;
    
    /* Variáveis Semânticas para o layout */
    --color-primary: #2879e4;
    --color-secondary: #1e3c72;
    --color-card-background: #ffffff;
    --color-border: #ddd;
}

/* Aplicação Global das Preferências */
body {
    background-color: var(--fundo-preferido) !important;
    color: var(--texto-preferido) !important;
    font-size: var(--tamanho-base-fonte) !important;
    font-family: var(--fonte-preferida) !important;
}

/* Ajustes de Contraste e Fonte em Elementos Comuns */

/* Textos do Post */
.post-card, .new-post-form {
    background-color: var(--color-card-background) !important;
    color: var(--texto-preferido) !important;
    border: 1px solid var(--color-border, #ddd);
}

.post-text {
    /* Garante que o texto principal respeite a fonte escolhida */
    font-family: var(--fonte-preferida) !important;
    line-height: 1.6; /* Aumenta o espaçamento entre linhas para melhor leitura */
}

/* Formulário e Inputs */
textarea, input[type="text"], .community-select {
    font-family: var(--fonte-preferida) !important;
    font-size: var(--tamanho-base-fonte) !important;
    color: var(--texto-preferido) !important;
    background-color: var(--fundo-preferido) !important;
    border-color: var(--color-border) !important;
}

textarea::placeholder {
    color: var(--texto-preferido) !important;
    opacity: 0.7;
}

/* Correção do Anel de Foco do Bootstrap (NOVO CÓDIGO) */
textarea:focus, 
input[type="text"]:focus, 
.community-select:focus {
    border-color: var(--color-primary) !important; 
    box-shadow: 0 0 0 0.25rem rgba(40, 121, 228, 0.25) !important;
}

/* Botões (ajustados para manter a legibilidade) */
button, .btn-full {
    font-size: calc(var(--tamanho-base-fonte) * 0.95);
    font-weight: 600;
}

/* Elementos de Navegação */
.navigation a {
    color: var(--texto-preferido) !important; /* Para garantir contraste */
}

/* Sidebar */
.right-sidebar .widget {
    background-color: var(--color-card-background) !important;
    border: 1px solid var(--color-border, #ddd);
    color: var(--texto-preferido) !important;
}

.username {
    color: var(--color-secondary); /* Mantém uma cor de link, mas garante contraste */
}

/* Garante que o ícone de acesso não suma no modo escuro (NOVO CÓDIGO) */
.navigation a i, .navigation a svg {
    color: var(--texto-preferido) !important;
}