<?php
// PHP - Arquivo: comunidade.php (Página Individual da Comunidade e Feed)
session_start();
include "conexao.php"; 

// Define o fuso horário para o de São Paulo (UTC-3)
date_default_timezone_set('America/Sao_Paulo');

$userId = $_SESSION['usuario_id'] ?? 0; 
$userName = $_SESSION['usuario'] ?? '';

if ($userId === 0) {
    header("Location: login.php");
    exit;
}

$comunidadeId = $_GET['id'] ?? 0;

if ($comunidadeId == 0) {
    header("Location: comunidades.php");
    exit;
}

// ------------------------------------------------------------------------------------------------
// FUNÇÕES UTILITÁRIAS (Redimensionamento e Tempo)
// ------------------------------------------------------------------------------------------------
if (!function_exists('resizeImage')) {
    function resizeImage($file, $maxWidth = 800, $maxHeight = 600) {
        $info = getimagesize($file);
        if ($info === false) { return false; }
        list($originalWidth, $originalHeight) = $info;
        $mime = $info['mime'];

        $ratio = $originalWidth / $originalHeight;
        $targetWidth = $originalWidth > $maxWidth ? $maxWidth : $originalWidth;
        $targetHeight = $originalHeight > $maxHeight ? $maxHeight : $originalHeight;

        if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
            if ($maxWidth / $maxHeight > $ratio) {
                $targetWidth = $maxHeight * $ratio;
                $targetHeight = $maxHeight;
            } else {
                $targetWidth = $maxWidth;
                $targetHeight = $maxWidth / $ratio;
            }
        } 

        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($file); break;
            case 'image/png': $src = imagecreatefrompng($file); break;
            case 'image/gif': $src = imagecreatefromgif($file); break;
            default: return false;
        }

        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($mime == 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
        imagedestroy($src);
        
        $temp_file = tempnam(sys_get_temp_dir(), 'resized_');
        imagepng($dst, $temp_file);
        imagedestroy($dst);

        return $temp_file;
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->d * 7;

        $string = array(
            'y' => 'ano', 'm' => 'mês', 'w' => 'semana', 'd' => 'dia',
            'h' => 'hora', 'i' => 'minuto', 's' => 'segundo',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' atrás' : 'agora mesmo';
    }
}

// ------------------------------------------------------------------------------------------------
// LÓGICA DE AÇÃO (AJAX/POST) - EXPULSÃO DE MEMBRO
// ------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'expel_member') {
    $memberIdToExpel = intval($_POST['member_id']);
    $communityIdCheck = intval($_POST['community_id']);

    // ... (Lógica de expulsão mantida da correção anterior) ...
    $sql_check_creator = "SELECT id_criador FROM comunidades WHERE id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check_creator);
    mysqli_stmt_bind_param($stmt_check, "i", $communityIdCheck);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $creator_data = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($creator_data && $creator_data['id_criador'] == $userId) {
        if ($memberIdToExpel == $userId) {
            echo json_encode(['success' => false, 'message' => 'O criador não pode ser expulso.']);
            exit;
        }

        $sql_expel = "DELETE FROM membros_comunidade WHERE id_comunidade = ? AND id_usuario = ?";
        $stmt_expel = mysqli_prepare($conn, $sql_expel);
        if ($stmt_expel) {
            mysqli_stmt_bind_param($stmt_expel, "ii", $communityIdCheck, $memberIdToExpel);
            if (mysqli_stmt_execute($stmt_expel)) {
                echo json_encode(['success' => true, 'message' => 'Membro expulso com sucesso.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao expulsar membro do BD.']);
            }
            mysqli_stmt_close($stmt_expel);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro na preparação da query.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Permissão negada.']);
    }
    exit;
}

// ------------------------------------------------------------------------------------------------
// LÓGICA DE AÇÃO (POST) - CRIAÇÃO DE POST
// ------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    $communityIdPost = intval($_POST['community_id']);
    $conteudo = trim($_POST['conteudo']);
    $imagem_path = null;

    // 1. Verifica se o usuário é membro ou criador
    $sql_check_member_creator = "
        SELECT 
            (SELECT id_criador FROM comunidades WHERE id = ?) AS id_criador,
            (SELECT 1 FROM membros_comunidade WHERE id_comunidade = ? AND id_usuario = ?) AS is_member
    ";
    $stmt_check = mysqli_prepare($conn, $sql_check_member_creator);
    mysqli_stmt_bind_param($stmt_check, "iii", $communityIdPost, $communityIdPost, $userId);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $data_check = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    $can_post = ($data_check && ($data_check['id_criador'] == $userId || $data_check['is_member'] == 1));

    if ($can_post && (!empty($conteudo) || (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0))) {
        
        // 2. Processamento da imagem (se existir)
        if (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0) {
            $file = $_FILES['imagem_post'];
            $uploadDir = 'uploads/posts_comunidade/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $temp_file = resizeImage($file['tmp_name']);
            
            if ($temp_file) {
                $fileExt = 'png';
                $uniqueName = 'post_c_' . $userId . '_' . time() . '.' . $fileExt;
                $target_file = $uploadDir . $uniqueName;

                if (rename($temp_file, $target_file)) {
                    $imagem_path = $target_file;
                }
            }
        }

        // 3. Insere a postagem
        $sql_insert = "INSERT INTO posts_comunidade (id_comunidade, usuario_id, conteudo, imagem) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "iiss", $communityIdPost, $userId, $conteudo, $imagem_path);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            // Recarrega a página para exibir o novo post
            header("Location: comunidade.php?id=" . $communityIdPost);
            exit;
        } else {
            $postMessage = "Erro ao criar a publicação: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_insert);

    } else if ($can_post) {
         $postMessage = "Conteúdo e/ou imagem são necessários para postar.";
    }
}
// ------------------------------------------------------------------------------------------------
// FIM LÓGICA DE CRIAÇÃO DE POST
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
// LÓGICA DE AÇÃO (AJAX) - CURTIR/DESCURTIR POST
// ------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_post') {
    $postId = intval($_POST['post_id']);
    $response = ['success' => false, 'new_count' => 0, 'status' => ''];

    // 1. Verifica se já curtiu
    $sql_check = "SELECT id FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $postId, $userId);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $hasLiked = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);

    if ($hasLiked) {
        // Descurtir
        $sql_action = "DELETE FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
        $response['status'] = 'unliked';
    } else {
        // Curtir
        $sql_action = "INSERT INTO curtidas_comunidade (id_postagem, id_usuario) VALUES (?, ?)";
        $response['status'] = 'liked';
    }

    $stmt_action = mysqli_prepare($conn, $sql_action);
    if ($stmt_action) {
        mysqli_stmt_bind_param($stmt_action, "ii", $postId, $userId);
        if (mysqli_stmt_execute($stmt_action)) {
            $response['success'] = true;

            // Busca a nova contagem
            $sql_count = "SELECT COUNT(*) AS count FROM curtidas_comunidade WHERE id_postagem = ?";
            $stmt_count = mysqli_prepare($conn, $sql_count);
            mysqli_stmt_bind_param($stmt_count, "i", $postId);
            mysqli_stmt_execute($stmt_count);
            $result_count = mysqli_stmt_get_result($stmt_count);
            $count_data = mysqli_fetch_assoc($result_count);
            $response['new_count'] = $count_data['count'];
            mysqli_stmt_close($stmt_count);
        }
        mysqli_stmt_close($stmt_action);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// ------------------------------------------------------------------------------------------------
// FIM LÓGICA DE CURTIR
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
// LÓGICA DE AÇÃO (AJAX) - POSTAR COMENTÁRIO
// ------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_comment') {
    $postId = intval($_POST['post_id']);
    $commentText = trim($_POST['comment_text']);
    $response = ['success' => false];

    if (!empty($commentText)) {
        $sql_insert = "INSERT INTO comentarios_comunidade (id_postagem, id_usuario, conteudo) VALUES (?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        
        if ($stmt_insert) {
            mysqli_stmt_bind_param($stmt_insert, "iis", $postId, $userId, $commentText);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $response['success'] = true;
                $newCommentId = mysqli_insert_id($conn);
                
                // Busca o novo comentário para retorno (incluindo apelido e foto)
                $sql_comment = "
                    SELECT c.conteudo, c.data_criacao, u.apelido, pu.foto_perfil
                    FROM comentarios_comunidade c
                    JOIN usuarios u ON c.id_usuario = u.id
                    LEFT JOIN perfil_usuario pu ON u.id = pu.id
                    WHERE c.id = ?
                ";
                $stmt_comment = mysqli_prepare($conn, $sql_comment);
                mysqli_stmt_bind_param($stmt_comment, "i", $newCommentId);
                mysqli_stmt_execute($stmt_comment);
                $result_comment = mysqli_stmt_get_result($stmt_comment);
                $new_comment = mysqli_fetch_assoc($result_comment);
                mysqli_stmt_close($stmt_comment);

                if ($new_comment) {
                    // Adiciona o novo comentário ao HTML de resposta
                    $response['new_comment_html'] = '
                        <div class="comment-item border-bottom pb-2 mb-2">
                            <div class="d-flex align-items-center mb-1">
                                <img src="' . htmlspecialchars($new_comment['foto_perfil'] ?? 'uploads/perfil/default.png') . '" alt="Foto" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                                <strong class="me-2">' . htmlspecialchars($new_comment['apelido']) . '</strong>
                                <small class="text-muted ms-auto">' . time_ago($new_comment['data_criacao']) . '</small>
                            </div>
                            <p class="mb-0 ms-4">' . nl2br(htmlspecialchars($new_comment['conteudo'])) . '</p>
                        </div>
                    ';
                }
            }
            mysqli_stmt_close($stmt_insert);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// ------------------------------------------------------------------------------------------------
// FIM LÓGICA DE COMENTÁRIOS
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
// LÓGICA DE TROCA DE FOTO DA COMUNIDADE (POST) - (Mantida da correção anterior)
// ------------------------------------------------------------------------------------------------
$photoMessage = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_community_photo'])) {
    // ... (Lógica de Troca de Foto - Sem alterações, utiliza resizeImage) ...
    $error_photo = false;

    if (isset($_FILES['nova_foto_comunidade']) && $_FILES['nova_foto_comunidade']['error'] == 0) {
        $file = $_FILES['nova_foto_comunidade'];
        $uploadDir = 'uploads/comunidade/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $temp_file = resizeImage($file['tmp_name']);
        
        if ($temp_file) {
            $fileExt = 'png';
            $uniqueName = 'community_' . $comunidadeId . '_' . time() . '.' . $fileExt;
            $target_file = $uploadDir . $uniqueName;

            $sql_check_creator = "SELECT id_criador, imagem FROM comunidades WHERE id = ?";
            $stmt_check = mysqli_prepare($conn, $sql_check_creator);
            mysqli_stmt_bind_param($stmt_check, "i", $comunidadeId);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $community_pre_update = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);

            if ($community_pre_update && $community_pre_update['id_criador'] == $userId) {
                if (rename($temp_file, $target_file)) {
                    $sql_update = "UPDATE comunidades SET imagem = ? WHERE id = ?";
                    $stmt_update = mysqli_prepare($conn, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "si", $target_file, $comunidadeId);
                    
                    if (mysqli_stmt_execute($stmt_update)) {
                        $photoMessage = "Foto da comunidade atualizada com sucesso!";
                        $oldImage = $community_pre_update['imagem'];
                        if ($oldImage && $oldImage != 'uploads/comunidade/default.png' && file_exists($oldImage)) {
                            unlink($oldImage);
                        }
                    } else {
                        $photoMessage = "Erro ao atualizar caminho no banco de dados.";
                    }
                    mysqli_stmt_close($stmt_update);
                } else { $photoMessage = "Erro ao mover o arquivo redimensionado."; }
            } else { 
                $photoMessage = "Erro: Permissão negada para atualizar a foto."; 
                if(file_exists($temp_file)) { unlink($temp_file); }
            }
        } else { $photoMessage = "Erro no processamento da imagem ou arquivo inválido."; }
    } else if (!$error_photo) {
        $photoMessage = "Nenhuma imagem válida enviada.";
    }
}
// ------------------------------------------------------------------------------------------------
// FIM LÓGICA DE TROCA DE FOTO
// ------------------------------------------------------------------------------------------------


// ------------------------------------------------------------------------------------------------
// BUSCA DE DADOS DA COMUNIDADE (Atualizada)
// ------------------------------------------------------------------------------------------------
$sql_select_community = "
    SELECT c.nome_comunidade, c.descricao, c.id_criador, c.imagem, COUNT(m.id_usuario) AS total_membros
    FROM comunidades c
    LEFT JOIN membros_comunidade m ON c.id = m.id_comunidade
    WHERE c.id = ?
    GROUP BY c.id
";
$stmt_community = mysqli_prepare($conn, $sql_select_community);
mysqli_stmt_bind_param($stmt_community, "i", $comunidadeId);
mysqli_stmt_execute($stmt_community);
$result_community = mysqli_stmt_get_result($stmt_community);
$community = mysqli_fetch_assoc($result_community);
mysqli_stmt_close($stmt_community);

if (!$community) {
    header("Location: comunidades.php");
    exit;
}

// Variáveis de Hierarquia e Membro
$isCreator = ($userId == $community['id_criador']); 
$isMember = false;
$sql_check_member = "SELECT 1 FROM membros_comunidade WHERE id_comunidade = ? AND id_usuario = ?";
$stmt_check_member = mysqli_prepare($conn, $sql_check_member);
mysqli_stmt_bind_param($stmt_check_member, "ii", $comunidadeId, $userId);
mysqli_stmt_execute($stmt_check_member);
mysqli_stmt_store_result($stmt_check_member);
if (mysqli_stmt_num_rows($stmt_check_member) > 0) {
    $isMember = true;
}
mysqli_stmt_close($stmt_check_member);

// ------------------------------------------------------------------------------------------------
// FUNÇÕES PARA BUSCAR MEMBROS, POSTS E COMENTÁRIOS
// ------------------------------------------------------------------------------------------------

function fetch_members($conn, $communityId) {
    $sql_members = "
        SELECT u.id, u.apelido, pu.foto_perfil
        FROM membros_comunidade mc
        JOIN usuarios u ON mc.id_usuario = u.id
        LEFT JOIN perfil_usuario pu ON u.id = pu.id
        WHERE mc.id_comunidade = ?
    ";
    $stmt_members = mysqli_prepare($conn, $sql_members);
    mysqli_stmt_bind_param($stmt_members, "i", $communityId);
    mysqli_stmt_execute($stmt_members);
    $result_members = mysqli_stmt_get_result($stmt_members);
    return mysqli_fetch_all($result_members, MYSQLI_ASSOC);
}
$members = fetch_members($conn, $comunidadeId);


function fetch_comments_for_post($conn, $postId) {
    $comments = [];
    $sql = "
        SELECT 
            c.conteudo, c.data_criacao,
            u.apelido, u.id AS usuario_id, pu.foto_perfil
        FROM comentarios_comunidade c
        JOIN usuarios u ON c.id_usuario = u.id
        LEFT JOIN perfil_usuario pu ON u.id = pu.id
        WHERE c.id_postagem = ?
        ORDER BY c.data_criacao ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $postId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $comments[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $comments;
}


function fetch_community_posts($conn, $communityId, $userId) {
    $posts = [];
    $sql = "
        SELECT 
            pc.id, pc.conteudo, pc.imagem AS imagem_post, pc.data_criacao,
            u.apelido, u.id AS usuario_id, pu.foto_perfil,
            (SELECT COUNT(*) FROM curtidas_comunidade cc WHERE cc.id_postagem = pc.id) AS likes_count,
            (SELECT COUNT(*) FROM comentarios_comunidade ccom WHERE ccom.id_postagem = pc.id) AS comments_count,
            (SELECT 1 FROM curtidas_comunidade cc WHERE cc.id_postagem = pc.id AND cc.id_usuario = ?) AS liked_by_user
        FROM posts_comunidade pc
        JOIN usuarios u ON pc.usuario_id = u.id
        LEFT JOIN perfil_usuario pu ON u.id = pu.id
        WHERE pc.id_comunidade = ?
        ORDER BY pc.data_criacao DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $userId, $communityId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        // Adiciona os comentários de cada post
        $row['comments'] = fetch_comments_for_post($conn, $row['id']);
        $posts[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $posts;
}

$posts = fetch_community_posts($conn, $comunidadeId, $userId);


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['nome_comunidade']); ?> | Comunidade</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="homePage.css"> 
    <style>
        .community-container { max-width: 1200px; margin: 0 auto; padding-top: 20px; }
        .community-header { background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); display: flex; align-items: center; }
        .community-image-container { width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin-right: 20px; position: relative; }
        .community-image { width: 100%; height: 100%; object-fit: cover; }
        .member-list { background-color: #fff; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .member-item { display: flex; align-items: center; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .member-item:last-child { border-bottom: none; }
        .member-info { display: flex; align-items: center; }
        .member-info img { width: 30px; height: 30px; border-radius: 50%; margin-right: 10px; }
        .post-image-preview-wrapper { max-height: 400px; overflow: hidden; margin-top: 10px; }
        .post-image-preview { width: 100%; height: auto; display: block; }
        .comment-item img { object-fit: cover; }
    </style>
</head>
<body>

    <a href="comunidades.php" class="btn btn-secondary m-3"><i class="fas fa-arrow-left"></i> Voltar às Comunidades</a>

    <div class="container community-container">
        <?php if ($photoMessage): ?>
            <div class="alert alert-<?php echo strpos($photoMessage, 'Erro') !== false ? 'danger' : 'success'; ?> mt-3">
                <?php echo $photoMessage; ?>
            </div>
        <?php endif; ?>

        <div class="community-header">
            <div class="community-image-container">
                <img id="community-photo-img" 
                    src="<?php echo htmlspecialchars($community['imagem'] ?? 'uploads/comunidade/default.png'); ?>" 
                    alt="Imagem da Comunidade" class="community-image">
                
                <?php if ($isCreator): ?>
                <button id="btn-change-photo" class="btn btn-sm btn-light" title="Mudar foto" 
                        style="position: absolute; bottom: 5px; right: 5px; opacity: 0.9; border-radius: 50%; padding: 5px;">
                    <i class="fas fa-camera"></i>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="community-info">
                <h1><?php echo htmlspecialchars($community['nome_comunidade']); ?> 
                    <?php if ($isCreator): ?><small class="badge bg-primary">Dono</small><?php endif; ?>
                </h1>
                <p><?php echo nl2br(htmlspecialchars($community['descricao'])); ?></p>
                <p class="text-muted"><?php echo $community['total_membros']; ?> Membros</p>

                <?php if (!$isCreator): ?>
                    <button class="btn btn-sm <?php echo $isMember ? 'btn-danger' : 'btn-success'; ?>"
                            data-community-id="<?php echo $comunidadeId; ?>"
                            data-action="<?php echo $isMember ? 'leave' : 'join'; ?>">
                        <?php echo $isMember ? 'Sair da Comunidade' : 'Entrar na Comunidade'; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isCreator): ?>
        <form id="form-change-photo" method="POST" enctype="multipart/form-data" style="display: none;">
            <input type="hidden" name="update_community_photo" value="1">
            <input type="file" id="input-community-photo" name="nova_foto_comunidade" accept="image/*">
            <button type="submit" id="submit-community-photo" style="display: none;">Salvar</button>
        </form>
        <?php endif; ?>


        <div class="row">
            <div class="col-md-8">
                
                <?php if ($isMember || $isCreator): ?>
                <div class="card mb-4 post-form-card">
                    <div class="card-body">
                        <h5 class="card-title">Criar Nova Publicação</h5>
                        <form id="postCommunityForm" action="comunidade.php?id=<?php echo $comunidadeId; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="community_id" value="<?php echo $comunidadeId; ?>">
                            <input type="hidden" name="action" value="create_post">
                            
                            <div class="form-group mb-3">
                                <textarea name="conteudo" class="form-control post-text-area" rows="3" placeholder="O que você quer compartilhar na comunidade? (Máx. 500 caracteres)" maxlength="500"></textarea>
                            </div>
                            
                            <div id="image-preview-wrapper" class="post-image-preview-wrapper"></div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="imagem_post_comunidade" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-image"></i> Adicionar Imagem
                                    <input type="file" name="imagem_post" id="imagem_post_comunidade" style="display: none;" accept="image/*">
                                </label>
                                <button type="submit" class="btn btn-primary">Publicar</button>
                            </div>
                            <?php if (isset($postMessage)): ?>
                                <small class="text-danger mt-2 d-block"><?php echo $postMessage; ?></small>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="community-feed">
                    <?php if (empty($posts)): ?>
                        <p class="alert alert-info">Nenhuma publicação nesta comunidade ainda.</p>
                    <?php else: ?>
                        <?php 
                            foreach ($posts as $post): 
                            $postId = $post['id'];
                            $isPostLiked = $post['liked_by_user'];
                        ?>
                            <div class="card mb-4 post-card">
                                <div class="card-body">
                                    <div class="post-header d-flex align-items-center mb-3">
                                        <img src="<?php echo htmlspecialchars($post['foto_perfil'] ?? 'uploads/perfil/default.png'); ?>" alt="Foto de Perfil" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                        <a href="perfil.php?id=<?php echo $post['usuario_id']; ?>" class="fw-bold text-decoration-none text-dark"><?php echo htmlspecialchars($post['apelido']); ?></a>
                                        <small class="text-muted ms-auto" title="<?php echo htmlspecialchars($post['data_criacao']); ?>">
                                            <?php echo time_ago($post['data_criacao']); ?>
                                        </small>
                                    </div>
                                    <p class="card-text post-content"><?php echo nl2br(htmlspecialchars($post['conteudo'])); ?></p>
                                    <?php if ($post['imagem_post']): ?>
                                        <div class="post-image-wrapper mb-3">
                                            <img src="<?php echo htmlspecialchars($post['imagem_post']); ?>" class="img-fluid rounded" alt="Imagem da Postagem">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="post-actions border-top pt-2 d-flex">
                                        <button class="btn btn-sm like-btn me-3" data-post-id="<?php echo $postId; ?>" data-is-liked="<?php echo $isPostLiked ? 'true' : 'false'; ?>">
                                            <i class="fas fa-heart <?php echo $isPostLiked ? 'text-danger' : 'text-muted'; ?>"></i> 
                                            <span class="like-count text-muted"><?php echo $post['likes_count']; ?></span> Curtidas
                                        </button>
                                        
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $postId; ?>" aria-expanded="false" aria-controls="comments-<?php echo $postId; ?>">
                                            <i class="fas fa-comment"></i> 
                                            <span id="count-<?php echo $postId; ?>" class="comment-count"><?php echo $post['comments_count']; ?></span> Comentários
                                        </button>
                                    </div>
                                    
                                    <div class="collapse mt-3" id="comments-<?php echo $postId; ?>">
                                        <div class="card card-body p-3">
                                            <div class="comments-list mb-3" id="comments-list-<?php echo $postId; ?>">
                                                <?php if (empty($post['comments'])): ?>
                                                    <p class="text-muted text-center" id="no-comments-message-<?php echo $postId; ?>">Nenhum comentário ainda.</p>
                                                <?php else: ?>
                                                    <?php foreach ($post['comments'] as $comment): ?>
                                                        <div class="comment-item border-bottom pb-2 mb-2">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <img src="<?php echo htmlspecialchars($comment['foto_perfil'] ?? 'uploads/perfil/default.png'); ?>" alt="Foto" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                                                                <strong class="me-2"><?php echo htmlspecialchars($comment['apelido']); ?></strong>
                                                                <small class="text-muted ms-auto" title="<?php echo htmlspecialchars($comment['data_criacao']); ?>"><?php echo time_ago($comment['data_criacao']); ?></small>
                                                            </div>
                                                            <p class="mb-0 ms-4"><?php echo nl2br(htmlspecialchars($comment['conteudo'])); ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <form class="comment-form" data-post-id="<?php echo $postId; ?>">
                                                <div class="input-group">
                                                    <textarea class="form-control comment-input" rows="1" placeholder="Escreva um comentário..." required></textarea>
                                                    <button type="submit" class="btn btn-primary">Comentar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <div class="col-md-4">
                <div class="member-list">
                    <h5><i class="fas fa-users"></i> Membros da Comunidade (<?php echo count($members); ?>)</h5>
                    <ul class="list-unstyled" id="member-list-items">
                        <?php foreach ($members as $member): 
                            $isMemberCreator = ($member['id'] == $community['id_criador']);
                        ?>
                            <li class="member-item" data-member-id="<?php echo $member['id']; ?>">
                                <div class="member-info">
                                    <img src="<?php echo htmlspecialchars($member['foto_perfil'] ?? 'uploads/perfil/default.png'); ?>" alt="Foto de Perfil">
                                    <a href="perfil.php?id=<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['apelido']); ?></a>
                                    <?php if ($isMemberCreator): ?>
                                        <span class="badge bg-primary ms-2">Dono</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($isCreator && !$isMemberCreator): ?>
                                    <button class="btn btn-sm btn-danger btn-expel" 
                                            data-member-id="<?php echo $member['id']; ?>"
                                            data-community-id="<?php echo $comunidadeId; ?>"
                                            title="Expulsar Membro">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const comunidadeId = <?php echo $comunidadeId; ?>;
            const isCreator = <?php echo $isCreator ? 'true' : 'false'; ?>;

            // ------------------------------------------
            // 1. LÓGICA DE TROCA DE FOTO (Criador)
            // ------------------------------------------
            if (isCreator) {
                const btnChangePhoto = document.getElementById('btn-change-photo');
                const inputCommunityPhoto = document.getElementById('input-community-photo');
                const formChangePhoto = document.getElementById('form-change-photo');

                if (btnChangePhoto && inputCommunityPhoto && formChangePhoto) {
                    btnChangePhoto.addEventListener('click', function() {
                        inputCommunityPhoto.click(); 
                    });
                    inputCommunityPhoto.addEventListener('change', function() {
                        if (this.files.length > 0) {
                            const file = this.files[0];
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                document.getElementById('community-photo-img').src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                            formChangePhoto.submit(); 
                        }
                    });
                }
            }


            // ------------------------------------------
            // 2. LÓGICA DE EXPULSÃO (Criador)
            // ------------------------------------------
            if (isCreator) {
                document.querySelectorAll('.btn-expel').forEach(button => {
                    button.addEventListener('click', function() {
                        const memberId = this.getAttribute('data-member-id');
                        const memberItem = this.closest('.member-item');
                        const memberName = memberItem.querySelector('a').textContent;

                        if (confirm(`Tem certeza que deseja expulsar ${memberName} da comunidade?`)) {
                            const formData = new FormData();
                            formData.append('action', 'expel_member');
                            formData.append('member_id', memberId);
                            formData.append('community_id', comunidadeId);

                            fetch('comunidade.php?id=' + comunidadeId, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    memberItem.remove(); 
                                    // RECARREGA a página para atualizar o total de membros no header
                                    window.location.reload(); 
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Erro de rede/AJAX na expulsão:', error);
                                alert('Erro de conexão ao tentar expulsar o membro.');
                            });
                        }
                    });
                });
            }

            // ------------------------------------------
            // 3. LÓGICA DE CURTIR/DESCURTIR (AJAX)
            // ------------------------------------------
            document.querySelectorAll('.like-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    const isLiked = this.getAttribute('data-is-liked') === 'true';
                    const likeCountSpan = this.querySelector('.like-count');
                    const heartIcon = this.querySelector('.fa-heart');

                    const formData = new FormData();
                    formData.append('action', 'like_post');
                    formData.append('post_id', postId);

                    fetch('comunidade.php?id=' + comunidadeId, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            likeCountSpan.textContent = data.new_count;
                            
                            // Atualiza o estado do botão
                            if (data.status === 'liked') {
                                button.setAttribute('data-is-liked', 'true');
                                heartIcon.classList.remove('text-muted');
                                heartIcon.classList.add('text-danger');
                            } else if (data.status === 'unliked') {
                                button.setAttribute('data-is-liked', 'false');
                                heartIcon.classList.remove('text-danger');
                                heartIcon.classList.add('text-muted');
                            }
                        } else {
                            alert('Erro ao curtir a publicação. Tente novamente.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX no curtir:', error);
                        alert('Erro de conexão ao curtir a publicação.');
                    });
                });
            });


            // ------------------------------------------
            // 4. LÓGICA DE COMENTÁRIO (AJAX)
            // ------------------------------------------
            document.querySelectorAll('.comment-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const postId = this.getAttribute('data-post-id');
                    const commentInput = this.querySelector('.comment-input');
                    const commentText = commentInput.value.trim();
                    
                    if (commentText === '') return;

                    const formData = new FormData();
                    formData.append('action', 'post_comment');
                    formData.append('post_id', postId);
                    formData.append('comment_text', commentText);

                    fetch('comunidade.php?id=' + comunidadeId, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const commentsList = document.getElementById(`comments-list-${postId}`);
                            const noCommentsMessage = document.getElementById(`no-comments-message-${postId}`);
                            
                            // MUDANÇA 2: Usar o ID único para pegar o contador
                            const commentCountSpan = document.getElementById(`count-${postId}`);
                            
                            // Remove a mensagem 'Nenhum comentário ainda.' se ela existir
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }
                            
                            commentsList.insertAdjacentHTML('beforeend', data.new_comment_html);
                            commentInput.value = ''; // Limpa o campo
                            
                            // Atualiza a contagem de comentários no botão
                            const currentCount = parseInt(commentCountSpan.textContent);
                            commentCountSpan.textContent = currentCount + 1;

                        } else {
                            alert('Erro ao publicar o comentário. Tente novamente.');
                        }
                    })
                });
            });


            // ------------------------------------------
            // 5. LÓGICA DE PRÉ-VISUALIZAÇÃO DE IMAGEM DO POST
            // ------------------------------------------
            const imgInput = document.getElementById('imagem_post_comunidade');
            if(imgInput) {
                imgInput.addEventListener('change', function(e) {
                    const previewContainer = document.getElementById('image-preview-wrapper');
                    previewContainer.innerHTML = ''; // Limpa a pré-visualização anterior
                    
                    const file = e.target.files[0];
                    
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewImage = document.createElement('img');
                            previewImage.src = e.target.result;
                            previewImage.alt = 'Pré-visualização da imagem';
                            previewImage.className = 'post-image-preview img-fluid rounded';
                            
                            previewContainer.appendChild(previewImage);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // ------------------------------------------
            // 6. LÓGICA ENTRAR/SAIR DA COMUNIDADE (Se houver)
            // ------------------------------------------
            // Adapte este bloco se você usa AJAX para entrar/sair
            /*
            document.querySelectorAll('.btn[data-action]').forEach(button => {
                button.addEventListener('click', function() {
                    // Lógica AJAX para entrar/sair
                });
            });
            */
        });
    </script>
</body>
</html>