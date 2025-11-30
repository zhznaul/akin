<?php
// PHP - Arquivo: comunidades.php (Página de Listagem e Gerenciamento de Comunidades)
session_start();
include "conexao.php"; 

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['usuario_id'];

// ------------------------------------------------------------------------------------------------
// 1. LÓGICA DE AÇÃO (ENTRAR/SAIR - AJAX)
// ------------------------------------------------------------------------------------------------
if (isset($_POST['action']) && isset($_POST['community_id'])) {
    $action = $_POST['action'];
    $communityId = intval($_POST['community_id']);
    $response = ['success' => false, 'error' => null]; // Adicionado 'error' para feedback

    if ($action == 'join') {
        // --- NOVO: Verificação do Limite de Membros (50) ---
        $maxMembers = 50;
        $sql_check_count = "SELECT COUNT(*) FROM membros_comunidade WHERE id_comunidade = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check_count);
        mysqli_stmt_bind_param($stmt_check, "i", $communityId);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $currentCount);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($currentCount >= $maxMembers) {
            $response['success'] = false;
            $response['error'] = 'A comunidade atingiu o limite máximo de ' . $maxMembers . ' membros.';
        } else {
            // Insere o usuário na tabela membros_comunidade
            $sql = "INSERT IGNORE INTO membros_comunidade (id_comunidade, id_usuario) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $communityId, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['status'] = 'joined';
                }
                mysqli_stmt_close($stmt);
            }
        }
        // -------------------------------------------------

    } elseif ($action == 'leave') {
        // Remove o usuário da tabela membros_comunidade
        $sql = "DELETE FROM membros_comunidade WHERE id_comunidade = ? AND id_usuario = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $communityId, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['status'] = 'left';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Recalcula a contagem de membros para a resposta AJAX (somente se a ação foi bem-sucedida ou a tentativa foi bloqueada)
    if ($response['success'] || $response['error']) { 
         $sql_count = "SELECT COUNT(*) FROM membros_comunidade WHERE id_comunidade = ?";
         $stmt_count = mysqli_prepare($conn, $sql_count);
         mysqli_stmt_bind_param($stmt_count, "i", $communityId);
         mysqli_stmt_execute($stmt_count);
         mysqli_stmt_bind_result($stmt_count, $newCount);
         mysqli_stmt_fetch($stmt_count);
         $response['new_count'] = $newCount;
         mysqli_stmt_close($stmt_count);
    }


    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ------------------------------------------------------------------------------------------------
// 2. BUSCA DE COMUNIDADES (TODAS)
// ------------------------------------------------------------------------------------------------

// Query para buscar todas as comunidades, a contagem de membros e se o usuário logado é membro
$sql = "
    SELECT 
        c.id, 
        c.nome_comunidade, 
        c.descricao,
        (SELECT COUNT(*) FROM membros_comunidade m WHERE m.id_comunidade = c.id) AS total_membros,
        EXISTS(SELECT 1 FROM membros_comunidade m2 WHERE m2.id_comunidade = c.id AND m2.id_usuario = ?) AS is_member
    FROM 
        comunidades c
    ORDER BY 
        c.nome_comunidade ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Erro ao buscar comunidades: " . mysqli_error($conn));
}

$comunidades = [];
while ($row = mysqli_fetch_assoc($result)) {
    $comunidades[] = $row;
}
mysqli_stmt_close($stmt);

// Busca as preferências de acessibilidade
$sql_prefs = "SELECT cor_fundo_pref, cor_texto_pref, tamanho_fonte_pref, fonte_preferida FROM perfil_usuario WHERE id = ?";
$stmt_prefs = mysqli_prepare($conn, $sql_prefs);
mysqli_stmt_bind_param($stmt_prefs, "i", $userId);
mysqli_stmt_execute($stmt_prefs);
$result_prefs = mysqli_stmt_get_result($stmt_prefs);
$prefs = mysqli_fetch_assoc($result_prefs) ?? [];
mysqli_stmt_close($stmt_prefs);

$prefs = [
    'cor_fundo_pref' => $prefs['cor_fundo_pref'] ?? '#f5f5f5',
    'cor_texto_pref' => $prefs['cor_texto_pref'] ?? '#2c3e50',
    'tamanho_fonte_pref' => $prefs['tamanho_fonte_pref'] ?? '16px',
    'fonte_preferida' => $prefs['fonte_preferida'] ?? 'sans-serif'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunidades | NeuroBlogs</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="homePage.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* Aplica as preferências de acessibilidade */
        body {
            background-color: <?php echo htmlspecialchars($prefs['cor_fundo_pref']); ?>;
            color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>;
            font-size: <?php echo htmlspecialchars($prefs['tamanho_fonte_pref']); ?>;
            font-family: <?php echo htmlspecialchars($prefs['fonte_preferida']); ?>;
        }
        .main-content-communities {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 15px;
        }
        .header-section h1 {
            color: #1e3c72;
            font-size: 2.5rem;
            /* 📌 Estilo minimalista para centralizar o título entre os dois links/botões */
            flex-grow: 1;
            text-align: center;
        }
        /* 📌 NOVO ESTILO: Link de Voltar (Não como botão verde) */
        .btn-back-link {
            /* Remove o estilo de botão para que pareça um link */
            background-color: transparent !important;
            color: #2879e4; /* Cor para links */
            padding: 10px 0;
            border: none;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        .btn-back-link:hover {
            opacity: 0.8;
            background-color: transparent !important;
        }
        .btn-create-community {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        .btn-create-community:hover {
            background-color: #388E3C;
        }
        .community-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .community-card {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .community-title {
            color: #2879e4;
            margin-top: 0;
            font-size: 1.5rem;
        }
        .community-description {
            color: #666;
            margin: 10px 0 20px 0;
            flex-grow: 1;
        }
        .community-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .member-count {
            color: #1e3c72;
            font-weight: 500;
        }
        .btn-action-community {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .btn-join {
            background-color: #28a745; 
            color: white;
        }
        .btn-join:hover {
            background-color: #1e7e34;
        }
        .btn-leave {
            background-color: #dc3545; 
            color: white;
        }
        .btn-leave:hover {
            background-color: #c82333;
        }
        .btn-view {
            background-color: #007bff;
            color: white;
        }
        .btn-view:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

    <div class="main-content-communities">
        <div class="header-section">
            
            <a href="homePage.php" class="btn-back-link" title="Voltar para o Feed Principal">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            
            <h1>Descubra Comunidades</h1>
            
            <a href="criar_comunidade.php" class="btn-create-community">
                <i class="fas fa-plus-circle"></i> Criar Nova Comunidade
            </a>
        </div>

        <?php if (count($comunidades) > 0): ?>
            <div class="community-grid">
                <?php foreach ($comunidades as $comunidade): 
                    $btnClass = $comunidade['is_member'] ? 'btn-leave' : 'btn-join';
                    $btnText = $comunidade['is_member'] ? 'Sair' : 'Entrar';
                    $btnAction = $comunidade['is_member'] ? 'leave' : 'join';
                ?>
                    <div class="community-card" data-id="<?= $comunidade['id'] ?>">
                        <div>
                            <h3 class="community-title"><?= htmlspecialchars($comunidade['nome_comunidade']) ?></h3>
                            <p class="community-description">
                                <?= empty($comunidade['descricao']) ? "Nenhuma descrição fornecida." : htmlspecialchars(substr($comunidade['descricao'], 0, 100)) . (strlen($comunidade['descricao']) > 100 ? '...' : '') ?>
                            </p>
                        </div>
                        
                        <div class="community-meta">
                            <span class="member-count">
                                <i class="fas fa-users"></i> 
                                <span class="member-count-value"><?= $comunidade['total_membros'] ?></span> / 50 membros
                            </span>
                            <div>
                                <a href="comunidade.php?id=<?= $comunidade['id'] ?>" class="btn-action-community btn-view">Ver</a>
                                <button class="btn-action-community <?= $btnClass ?>" 
                                        data-community-id="<?= $comunidade['id'] ?>" 
                                        data-action="<?= $btnAction ?>">
                                    <?= $btnText ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-communities">
                <p>Nenhuma comunidade foi encontrada no momento.</p>
                <p>Que tal ser o primeiro a <a href="criar_comunidade.php">criar uma</a>?</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-action-community').forEach(button => {
                // Filtra apenas os botões de Entrar/Sair
                if (!button.classList.contains('btn-view')) {
                    button.addEventListener('click', function() {
                        const communityId = this.getAttribute('data-community-id');
                        let action = this.getAttribute('data-action');
                        const buttonElement = this;
                        const card = buttonElement.closest('.community-card');
                        const countSpan = card.querySelector('.member-count-value');

                        const formData = new FormData();
                        formData.append('action', action);
                        formData.append('community_id', communityId);

                        fetch('comunidades.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Certifica-se de que a contagem é atualizada mesmo se houve um erro de limite
                            if (data.new_count !== undefined) {
                                countSpan.textContent = data.new_count;
                            }
                            
                            if (data.success) {
                                
                                // Alterna a ação e o estilo do botão
                                if (data.status === 'joined') {
                                    buttonElement.textContent = 'Sair';
                                    buttonElement.classList.remove('btn-join');
                                    buttonElement.classList.add('btn-leave');
                                    buttonElement.setAttribute('data-action', 'leave');
                                } else if (data.status === 'left') {
                                    buttonElement.textContent = 'Entrar';
                                    buttonElement.classList.remove('btn-leave');
                                    buttonElement.classList.add('btn-join');
                                    buttonElement.setAttribute('data-action', 'join');
                                }
                            } else {
                                // Exibe a mensagem de erro específica do limite, se houver
                                if (data.error) {
                                    alert(data.error);
                                } else {
                                    alert('Erro ao processar a ação. Tente novamente.');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Erro de rede/AJAX:', error);
                            alert('Erro de conexão ao processar a ação.');
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>