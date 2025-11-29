<?php
// PHP - Arquivo: config_acessibilidade.php
session_start();
include "conexao.php"; 

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['usuario_id'];
$mensagem = ""; // Mensagem de feedback
$erro = false;

// --- DADOS PARA OS SELECTS (Opções de Acessibilidade) ---
$opcoes_cores_fundo = [
    '#f5f5f5' => 'Cinza Claro (Suave)',
    '#FFFFFF' => 'Branco Padrão',
    '#2c3e50' => 'Escuro (Alto Contraste)',
    '#f7f3e6' => 'Bege (Papel Antigo)',
    '#c8e6c9' => 'Verde Pastel'
];

$opcoes_cores_texto = [
    '#2c3e50' => 'Azul Escuro (Alto Contraste)',
    '#000000' => 'Preto Padrão',
    '#FFFFFF' => 'Branco (Para Fundos Escuros)',
];

$opcoes_tamanho_fonte = [
    'small' => 'Pequena (14px)',
    'medium' => 'Média (16px)',
    'large' => 'Grande (18px)',
    'xlarge' => 'Extra Grande (20px)'
];

$opcoes_fontes = [
    'sans-serif' => 'Padrão (Sem serifa)',
    'OpenDyslexic' => 'OpenDyslexic (Especial)', // Requer que a fonte esteja instalada/linkada
    'Arial' => 'Arial',
    'Verdana' => 'Verdana (Clássica)'
];

// --- 1. PROCESSAR FORMULÁRIO (SALVAR PREFERÊNCIAS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta os dados do POST (Sanitização e Validação)
    $fundo = $_POST['cor_fundo_pref'];
    $texto = $_POST['cor_texto_pref'];
    $tamanho = $_POST['tamanho_fonte_pref'];
    $fonte = $_POST['fonte_preferida'];

    // 2. ID do Usuário Logado (Variável CRÍTICA)
    // Se esta variável não estiver definida, o update falha.
    $userId = $_SESSION['usuario_id']; // Certifique-se que você está usando o ID correto da sessão

    // 3. Consulta SQL para ATUALIZAR as preferências
    $sql_update = "
        UPDATE perfil_usuario 
        SET 
            cor_fundo_pref = ?, 
            cor_texto_pref = ?, 
            tamanho_fonte_pref = ?, 
            fonte_preferida = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql_update);

    // BIND PARAMETERS: "ssssi" = string, string, string, string, integer
    mysqli_stmt_bind_param($stmt, "ssssi", 
        $fundo, 
        $texto, 
        $tamanho, 
        $fonte, 
        $userId
    );

    // 4. Executa a atualização
    if (mysqli_stmt_execute($stmt)) {
        // Sucesso: Redireciona o usuário (Opcional, mas recomendado)
        // header("Location: homePage.php?status=preferences_saved");
        // exit;
        $mensagem_sucesso = "Preferências salvas com sucesso!";
    } else {
        // Erro na execução
        $mensagem_erro = "Erro ao salvar preferências: " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt);

}


// --- 2. BUSCAR PREFERÊNCIAS ATUAIS (para pré-preencher o formulário) ---
$sql_select = "SELECT cor_fundo_pref, cor_texto_pref, tamanho_fonte_pref, fonte_preferida FROM perfil_usuario WHERE id = $userId";
$result = mysqli_query($conn, $sql_select);
$prefs_atuais = mysqli_fetch_assoc($result);

// Define as variáveis de controle (usando os valores atuais ou defaults)
$current_fundo = $prefs_atuais['cor_fundo_pref'] ?? '#f5f5f5';
$current_texto = $prefs_atuais['cor_texto_pref'] ?? '#2c3e50';
$current_tamanho = $prefs_atuais['tamanho_fonte_pref'] ?? 'medium';
$current_fonte = $prefs_atuais['fonte_preferida'] ?? 'sans-serif';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Acessibilidade</title>
    <link rel="stylesheet" href="homePage.css">
    <link rel="stylesheet" href="css/config_acessibilidade.css"> <link rel="stylesheet" href="css/user_preferences.php?user_id=<?= $userId ?>">
</head>
<body>
    <header class="main-header">
        <nav class="nav-bar">
            <h1 class="logo"><a href="homePage.php" style="color: inherit; text-decoration: none;">NeuroBlogs</a></h1>
        </nav>
    </header>

    <main class="config-container">
        
        <div class="card-config">
            <h2>⚙️ Configurações de Acessibilidade</h2>
            <p class="subtitle-config">Ajuste o contraste, cores e fontes para reduzir a sobrecarga sensorial e melhorar a leitura. Suas escolhas serão aplicadas em todo o site.</p>
            
            <?php if ($mensagem): ?>
                <div class="alert <?= $erro ? 'alert-error' : 'alert-success' ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="form-group">
                    <label for="cor_fundo_pref">Cor de Fundo da Página (Redução de Strain Visual):</label>
                    <select name="cor_fundo_pref" id="cor_fundo_pref" required>
                        <?php foreach ($opcoes_cores_fundo as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($current_fundo == $value) ? 'selected' : '' ?> style="background-color: <?= $value ?>; color: <?= (in_array($value, ['#2c3e50'])) ? '#FFFFFF' : '#000000' ?>;">
                                <?= $label ?> (Exemplo)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cor_texto_pref">Cor do Texto (Contraste):</label>
                    <select name="cor_texto_pref" id="cor_texto_pref" required>
                        <?php foreach ($opcoes_cores_texto as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($current_texto == $value) ? 'selected' : '' ?> style="color: <?= $value ?>; font-weight: bold;">
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tamanho_fonte_pref">Tamanho da Fonte:</label>
                    <select name="tamanho_fonte_pref" id="tamanho_fonte_pref" required>
                        <?php foreach ($opcoes_tamanho_fonte as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($current_tamanho == $value) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fonte_preferida">Tipo de Fonte (Foco Dislexia/Leitura):</label>
                    <select name="fonte_preferida" id="fonte_preferida" required>
                        <?php foreach ($opcoes_fontes as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($current_fonte == $value) ? 'selected' : '' ?> style="font-family: <?= $value ?>;">
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Se você escolher 'OpenDyslexic', certifique-se de que a fonte está instalada no seu projeto.</small>
                </div>
                
                <button type="submit" class="btn-salvar-config">Salvar Configurações</button>
            </form>
            
            <div class="back-link">
                <a href="homePage.php">← Voltar para o Feed</a>
            </div>
        </div>

    </main>
</body>
</html>