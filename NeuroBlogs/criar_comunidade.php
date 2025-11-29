<?php
// PHP - Arquivo: criar_comunidade.php
session_start();
include "conexao.php"; 

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['usuario_id'];
$success_message = "";
$error_message = "";

// Lógica de Processamento (Mantida igual ao seu original)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_comunidade = trim($_POST['nome_comunidade'] ?? ''); 
    $descricao = trim($_POST['descricao'] ?? '');

    if (empty($nome_comunidade)) {
        $error_message = "O nome da comunidade não pode estar vazio.";
    } else {
        // Verifica se já existe
        $sql_verificar = "SELECT id FROM comunidades WHERE nome_comunidade = ?";
        $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
        
        if ($stmt_verificar) {
            mysqli_stmt_bind_param($stmt_verificar, "s", $nome_comunidade);
            mysqli_stmt_execute($stmt_verificar);
            mysqli_stmt_store_result($stmt_verificar);

            if (mysqli_stmt_num_rows($stmt_verificar) > 0) {
                $error_message = "Já existe uma comunidade com o nome '{$nome_comunidade}'. Por favor, escolha outro nome.";
                mysqli_stmt_close($stmt_verificar);
            } else {
                mysqli_stmt_close($stmt_verificar);
                
                // Insere nova comunidade
                $sql_insert = "INSERT INTO comunidades (nome_comunidade, descricao, id_criador) VALUES (?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);
                
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ssi", $nome_comunidade, $descricao, $userId);

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $new_community_id = mysqli_insert_id($conn);
                        
                        // Adiciona criador como admin
                        $sql_membro = "INSERT INTO membros_comunidade (id_comunidade, id_usuario, is_admin) VALUES (?, ?, 1)";
                        $stmt_membro = mysqli_prepare($conn, $sql_membro);
                        mysqli_stmt_bind_param($stmt_membro, "ii", $new_community_id, $userId);
                        
                        if (mysqli_stmt_execute($stmt_membro)) {
                            mysqli_stmt_close($stmt_membro);
                            header("Location: criar_comunidade.php?success=1&name=" . urlencode($nome_comunidade));
                            exit;
                        }
                    } else {
                        $error_message = "Erro ao criar a comunidade: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    $error_message = "Erro ao preparar consulta: " . mysqli_error($conn);
                }
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['name'])) {
    $nome_comunidade_sucesso = htmlspecialchars(urldecode($_GET['name']));
    $success_message = "Comunidade '{$nome_comunidade_sucesso}' criada com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Comunidade | NeuroBlogs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="homePage.css">

    <style>
        /* Ajustes específicos para centralizar e embelezar o formulário */
        body {
            background-color: #f5f5f5; /* Cor de fundo suave */
            min-height: 100vh;
        }
        
        .creation-container {
            max-width: 600px; /* Largura ideal para formulários de criação */
            margin: 50px auto;
            padding: 20px;
        }

        .creation-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Sombra suave */
            padding: 40px;
            border: 1px solid #e0e0e0;
        }

        .creation-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .creation-header h2 {
            color: #1e3c72; /* Cor primária do seu tema */
            font-weight: 700;
            margin-bottom: 10px;
        }

        .creation-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ced4da;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: #2879e4;
            box-shadow: 0 0 0 0.2rem rgba(40, 121, 228, 0.25);
        }

        .btn-create {
            background-color: #2879e4;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            border: none;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        .btn-create:hover {
            background-color: #1e3c72;
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            color: #2879e4;
            text-decoration: underline;
        }
    </style>
</head>
<body>


    <?php // include "menu_navegacao.php"; ?> 

    <div class="creation-container">
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= $success_message ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <div class="creation-card">
            <div class="creation-header">
                <div class="mb-3">
                    <i class="fas fa-users fa-3x" style="color: #2879e4;"></i>
                </div>
                <h2>Criar Nova Comunidade</h2>
                <p>Crie um espaço para compartilhar ideias e conectar pessoas.</p>
            </div>

            <form method="POST" action="criar_comunidade.php">
                <div class="mb-3">
                    <label for="nome_comunidade" class="form-label">Nome da Comunidade</label>
                    <input type="text" class="form-control" id="nome_comunidade" name="nome_comunidade" 
                           placeholder="Ex: Tecnologia" 
                           required 
                           value="<?= htmlspecialchars($nome_comunidade ?? '') ?>"> 
                </div>
                
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição <span class="text-muted fw-light">(Opcional)</span></label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="5" 
                              placeholder="Descreva o propósito e as regras da sua comunidade..."><?= htmlspecialchars($descricao ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-create">
                    <i class="fas fa-plus-circle me-2"></i> Criar Comunidade
                </button>
            </form>

            <a href="comunidades.php" class="btn-back">
                <i class="fas fa-arrow-left me-1"></i> Voltar para Comunidades
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>