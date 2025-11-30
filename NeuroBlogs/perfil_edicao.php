<?php
// PHP - Arquivo: perfil_edicao.php (Perfil Privado/Edição)
// Esta é a página SOMENTE para o usuário logado editar seus dados e foto.
session_start();
include "conexao.php"; // Inclui o arquivo de conexão

// 1. Verificação de Autenticação
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['usuario_id'];
$userName = $_SESSION['usuario'];
$message = "";
$error = "";

// 2. Processamento do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newBio = $_POST['bio'] ?? '';
    $newBio = trim($newBio); 

    // Lógica para Upload de Imagem de Perfil (usando a imagem recortada do AJAX)
    $targetPath = $currentImagePath = null; 

    // Verifica se a imagem recortada foi enviada via AJAX (do Cropper.js)
    if (isset($_FILES['foto_perfil_cropped']) && $_FILES['foto_perfil_cropped']['error'] == 0) {
        
        $uploadDir = 'uploads/perfil/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = 'png'; 
        $uniqueName = $userId . '_' . time() . '.' . $fileExt;
        $targetPath = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($_FILES['foto_perfil_cropped']['tmp_name'], $targetPath)) {
            $currentImagePath = $targetPath;
            $message .= "Foto de perfil atualizada com sucesso. ";
        } else {
            $error .= "Erro ao mover o arquivo de imagem recortada. ";
        }
    } else if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
        $error .= "Por favor, utilize a ferramenta de recorte para ajustar a imagem. ";
    }

    // 3. Atualizar o Banco de Dados
    
    mysqli_begin_transaction($conn);

    // 3a. Garante que a linha na tabela perfil_usuario exista (INSERT IGNORE)
    $sql_check_profile = "INSERT IGNORE INTO perfil_usuario (id) VALUES (?)";
    $stmt_check = mysqli_prepare($conn, $sql_check_profile);
    mysqli_stmt_bind_param($stmt_check, "i", $userId);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_close($stmt_check);

    // 3b. Monta a query de UPDATE
    $sql_update = "UPDATE perfil_usuario SET bio = ?";
    $params = "s";
    $bindValues = [&$newBio]; 

    if ($currentImagePath) {
        $sql_update .= ", foto_perfil = ?";
        $params .= "s";
        $bindValues[] = $currentImagePath; 
    }

    $sql_update .= " WHERE id = ?";
    $params .= "i";
    $bindValues[] = $userId;
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    
    // --- Bind dinâmico ---
    $refs = [];
    foreach ($bindValues as $key => $value) {
        $refs[$key] = &$bindValues[$key];
    }
    $bind_params_array = array_merge([$stmt_update, $params], $refs);
    call_user_func_array('mysqli_stmt_bind_param', $bind_params_array);
    // ----------------------
    
    if (mysqli_stmt_execute($stmt_update)) {
        mysqli_commit($conn);
        if (!$error) {
             $message = "Perfil atualizado com sucesso!";
        } else {
            $message .= " Bio atualizada com sucesso.";
        }
    } else {
        mysqli_rollback($conn);
        $error = "Erro ao atualizar o perfil: " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt_update);
}


// 4. Buscar Dados Atuais do Perfil
$sql_fetch = "
    SELECT 
        u.apelido, 
        p.bio, 
        p.foto_perfil 
    FROM 
        usuarios u
    LEFT JOIN 
        perfil_usuario p ON u.id = p.id 
    WHERE 
        u.id = ?";
        
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $userId);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$perfil = mysqli_fetch_assoc($result_fetch);

// Define as variáveis de exibição com os dados atuais
$currentBio = $perfil['bio'] ?? '';
$currentPhoto = ($perfil['foto_perfil'] && file_exists($perfil['foto_perfil'])) ? $perfil['foto_perfil'] : 'uploads/perfil/default_profile.png';

mysqli_stmt_close($stmt_fetch);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil | NeuroBlogs</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="homePage.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    
    <style>
        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        /* -------------------------------------- */
        /* 📌 NOVOS ESTILOS PARA O BOTÃO VOLTAR */
        /* -------------------------------------- */
        .profile-top-bar {
            display: flex; 
            justify-content: flex-start;
            margin-bottom: 20px; 
            padding-top: 5px; /* Adiciona um pequeno padding superior */
        }
        .btn-back-link {
            /* Remove o estilo de botão para que pareça um link */
            background-color: transparent !important;
            color: #2879e4; /* Cor para links */
            padding: 0; 
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
        /* -------------------------------------- */
        
        .profile-photo-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%; 
            overflow: hidden;
            border: 4px solid #1e3c72;
            background-color: #f0f0f0;
        }
        .profile-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-header h1 {
            color: #1e3c72;
            font-size: 2rem;
            margin: 0;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        textarea#bio {
            min-height: 150px;
            resize: vertical;
        }
        .form-control, textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn-save {
            background-color: #2879e4;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background-color 0.3s;
        }
        .btn-save:hover {
            background-color: #1e3c72;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        /* ---------------------------------------------------------------------- */
        /* Estilos para o Recorte Circular no Cropper.js */
        /* ---------------------------------------------------------------------- */
        #image-to-crop {
            display: block;
            max-width: 100%;
        }
        .crop-modal {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
        }
        .modal-content-crop {
            margin: 5% auto;
            width: 90%;
            max-width: 600px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .modal-content-crop h3 {
             color: #1e3c72;
             margin-bottom: 15px;
        }
        
        .cropper-view-box {
            border-radius: 50%;
            outline: 0; 
            box-shadow: 0 0 0 1000px rgba(0, 0, 0, 0.5); 
        }
        .cropper-face {
            background-color: transparent !important;
        }
    </style>
</head>
<body>
    <main class="main-content-single">
        <div class="profile-container">
            
            <div class="profile-top-bar">
                <a href="perfil.php" class="btn-back-link" title="Voltar para o seu Perfil">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="profile-header">
                <h1>Editar Perfil de <?= htmlspecialchars($userName) ?></h1>
                <p style="color: #666;">Altere sua foto e biografia (apenas você vê esta tela).</p>
                <a href="perfil.php" class="btn-save" style="display: inline-block; padding: 8px 15px; font-size: 0.9rem; margin-top: 10px;">Ver Perfil Público <i class="fas fa-external-link-alt"></i></a>
            </div>

            <?php if ($message): ?>
                <div class="alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">Foto Atual:</label>
                    <div class="profile-photo-wrapper" style="margin: 0 auto;">
                        <img src="<?= htmlspecialchars($currentPhoto) ?>" alt="Foto Atual" class="profile-photo">
                    </div>
                </div>

                <div class="form-group">
                    <label for="foto_perfil"><i class="fas fa-image"></i> Alterar Foto de Perfil:</label>
                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" class="form-control" style="padding: 10px 0;">
                    <small class="form-text text-muted">A foto será recortada em um círculo. **Role o mouse para Zoom e arraste o círculo para redimensionar.**</small>
                </div>
            </form>

            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <div class="form-group">
                    <label for="bio"><i class="fas fa-file-alt"></i> Sua Biografia (Bio):</label>
                    <textarea id="bio" name="bio" class="form-control" placeholder="Fale um pouco sobre você..."><?= htmlspecialchars($currentBio) ?></textarea>
                </div>

                <div class="form-group" style="text-align: right;">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Salvar Bio
                    </button>
                </div>
            </form>
        </div>
    </main>

    <div id="cropModal" class="crop-modal">
        <div class="modal-content-crop">
            <h3>Ajustar Foto de Perfil</h3>
            <div style="max-height: 400px; overflow: hidden;">
                <img id="image-to-crop" src="" alt="Imagem para recorte">
            </div>
            
            <button type="button" id="cropAndSaveBtn" class="btn-save" style="width: 100%; margin-top: 20px;">
                <i class="fas fa-crop-alt"></i> Recortar e Salvar Foto
            </button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

    <script>
        const photoInput = document.getElementById('foto_perfil');
        const cropModal = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('image-to-crop');
        const cropAndSaveBtn = document.getElementById('cropAndSaveBtn');
        const profileForm = document.getElementById('profileForm');

        let cropper; 

        // 1. Configura o evento ao selecionar o arquivo
        photoInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    imageToCrop.src = event.target.result;
                    cropModal.style.display = 'block';

                    // Inicializa o Cropper (Configuração para Pan/Zoom/Resize)
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1, 
                        viewMode: 1,    
                        dragMode: 'move', // Mover a imagem com clique e arraste (Pan)
                        cropBoxResizable: true, // Redimensionar o círculo de recorte
                        zoomable: true, // Permite zoom com a roda do mouse/scroll
                        
                        ready: function () {
                            const cropBox = this.cropper.getContainerData();
                            this.cropper.setCropBoxData({ 
                                width: Math.min(cropBox.width, cropBox.height) * 0.8, 
                                height: Math.min(cropBox.width, cropBox.height) * 0.8 
                            });
                        }
                    });
                };
                reader.readAsDataURL(files[0]);
                
                // Reseta o valor do input para permitir selecionar o mesmo arquivo novamente
                e.target.value = ''; 
            }
        });

        // 2. Evento de clique no botão "Recortar e Salvar"
        cropAndSaveBtn.addEventListener('click', function() {
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({
                width: 250,  
                height: 250, 
            });

            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('foto_perfil_cropped', blob, 'cropped_image.png');
                formData.append('bio', document.getElementById('bio').value);

                cropModal.style.display = 'none';
                cropper.destroy();
                
                // Envia os dados via Fetch/AJAX
                fetch('perfil_edicao.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Força um recarregamento total da página para limpar o estado e mostrar a nova foto
                    if (response.ok) {
                        window.location.reload(); 
                    } else {
                        alert('Erro no servidor ao processar a foto. Tente novamente.');
                        console.error('Erro de resposta HTTP:', response.status);
                    }
                })
                .catch(error => {
                    alert('Erro de rede ao salvar a foto. Tente novamente.');
                    console.error('Erro de rede:', error);
                });

            }, 'image/png'); 
        });
        
        // 3. Impede a submissão do formulário principal se a foto estiver em processo de recorte
        profileForm.addEventListener('submit', function(e) {
            if (cropModal.style.display !== 'none') {
                 e.preventDefault(); 
                 alert('Por favor, clique em "Recortar e Salvar Foto" no pop-up para finalizar a imagem, ou feche o pop-up para submeter apenas a Bio.');
            }
        });
    </script>
</body>
</html>