<?php
// PHP - Arquivo: perfil.php (Perfil Pessoal e Feed de Posts Pessoais)
session_start();
// O conexao.php é necessário para a conexão e para as funções de post/like/comment
include "conexao.php"; 


// Inclui funções utilitárias como time_ago (se estiver em conexao.php ou outro arquivo)
// date_default_timezone_set('America/Sao_Paulo'); // Opcional, se já não estiver em conexao.php

if (!isset($conn) || $conn->connect_error) {
    die("Erro fatal: A conexão com o banco de dados não pôde ser estabelecida.");
}

// Padrão: Usa o ID da URL (?id=X). Se não houver ID na URL, usa o ID do usuário logado.
$targetUserId = $_GET['id'] ?? ($_SESSION['usuario_id'] ?? 0);
$currentUserId = $_SESSION['usuario_id'] ?? 0;

if ($targetUserId == 0) {
    // Se não houver ID na URL e o usuário não estiver logado
    header("Location: login.php");
    exit;
}

// Verifica se o perfil que está sendo visto pertence ao usuário logado
$isCurrentUser = ($currentUserId > 0) && ($currentUserId == $targetUserId);

// --- Função time_ago para exibir o tempo de forma amigável ---
// Se esta função não estiver em 'conexao.php', adicione-a aqui
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        date_default_timezone_set('America/Sao_Paulo'); // Garante o fuso horário
        $time_difference = time() - strtotime($timestamp);

        if ($time_difference < 1) { return 'agora'; }
        $condition = array( 
            12 * 30 * 24 * 60 * 60 => 'ano',
            30 * 24 * 60 * 60       => 'mês',
            24 * 60 * 60            => 'dia',
            60 * 60                 => 'hora',
            60                      => 'minuto',
            1                       => 'segundo'
        );

        foreach( $condition as $secs => $str ) {
            $d = $time_difference / $secs;

            if( $d >= 1 ) {
                $t = round( $d );
                if ($str == 'mês' && $t >= 12) {
                    $t = floor($t / 12);
                    $str = 'ano';
                }
                return 'há ' . $t . ' ' . $str . ( ($t > 1 && $str != 'mês' && $str != 'ano') ? 's' : '' ) . ( ($str == 'mês' && $t > 1) ? 'es' : '' ) . ( ($str == 'ano' && $t > 1) ? 's' : '' );
            }
        }
    }
}
// --- Fim da Função time_ago ---

// ------------------------------------------------------------------------------------------------
// 1. LÓGICA DE AÇÃO (Post, Like, Comment) PARA POSTS PESSOAIS
// ------------------------------------------------------------------------------------------------
$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['success' => false];

// Lógica de Postar Novo Post Pessoal (Apenas para o próprio usuário)
if ($action == 'post_pessoal' && $isCurrentUser && isset($_POST['conteudo'])) {
    $conteudo = trim($_POST['conteudo']);
    $imagem_post = NULL; // Supondo que a lógica de upload e otimização de imagem será adicionada aqui (como em homePage.php)

    if (empty($conteudo)) {
        $response['message'] = "O post não pode ser vazio.";
    } else {
        // A lógica de upload de imagem (usando a função resizeImage de homePage.php e upload) DEVE ser implementada aqui.
        // O código abaixo assume que a imagem já foi processada e o caminho está em $imagem_post, ou é NULL.
        
        // Tabela de posts PESSOAIS
        $sql = "INSERT INTO posts_pessoais (usuario_id, conteudo, imagem) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $currentUserId, $conteudo, $imagem_post);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                // Opcional: retornar o HTML do novo post para atualização em tempo real
            } else {
                $response['message'] = "Erro ao inserir post no banco: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = "Erro na preparação da query: " . mysqli_error($conn);
        }
    }
    
    // Responde apenas se for uma requisição AJAX
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
}

// Lógica de Curtir Post Pessoal (AJAX)
if ($action == 'like_pessoal' && isset($_POST['post_id']) && $currentUserId > 0) {
    $postId = intval($_POST['post_id']);
    
    // Tabela de curtidas PESSOAIS
    $sql_check = "SELECT id FROM curtidas_pessoais WHERE id_postagem = ? AND id_usuario = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "ii", $postId, $currentUserId);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        $alreadyLiked = mysqli_stmt_num_rows($stmt_check) > 0;
        mysqli_stmt_close($stmt_check); 

        if ($alreadyLiked) {
            // Descurtir
            $sql_action = "DELETE FROM curtidas_pessoais WHERE id_postagem = ? AND id_usuario = ?";
        } else {
            // Curtir
            $sql_action = "INSERT INTO curtidas_pessoais (id_postagem, id_usuario) VALUES (?, ?)";
        }
        
        $stmt_action = mysqli_prepare($conn, $sql_action);
        if ($stmt_action) {
            mysqli_stmt_bind_param($stmt_action, "ii", $postId, $currentUserId);
            if (mysqli_stmt_execute($stmt_action)) {
                $response['success'] = true;
                $response['liked'] = !$alreadyLiked; // Status atualizado
                
                // Recalcula a contagem de likes
                $sql_count = "SELECT COUNT(*) FROM curtidas_pessoais WHERE id_postagem = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count);
                mysqli_stmt_bind_param($stmt_count, "i", $postId);
                mysqli_stmt_execute($stmt_count);
                $result_count = mysqli_stmt_get_result($stmt_count);
                $row_count = mysqli_fetch_row($result_count);
                $response['likes_count'] = $row_count[0];
                mysqli_stmt_close($stmt_count);

            } else {
                $response['message'] = "Erro ao executar ação: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_action);
        } else {
            $response['message'] = "Erro na preparação da query de ação: " . mysqli_error($conn);
        }
    } else {
        $response['message'] = "Erro na preparação da query de checagem: " . mysqli_error($conn);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Lógica de Comentar Post Pessoal (AJAX)
if ($action == 'comment_pessoal' && isset($_POST['post_id']) && isset($_POST['comment_text']) && $currentUserId > 0) {
    $postId = intval($_POST['post_id']);
    $commentText = trim($_POST['comment_text']);

    if (empty($commentText)) {
        $response['message'] = "O comentário não pode ser vazio.";
    } else {
        // Tabela de comentários PESSOAIS
        $sql = "INSERT INTO comentarios_pessoais (id_postagem, id_usuario, conteudo) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $postId, $currentUserId, $commentText);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                
                // Busca o apelido do usuário logado para montar o HTML do novo comentário
                $sql_user = "SELECT apelido FROM usuarios WHERE id = ?";
                $stmt_user = mysqli_prepare($conn, $sql_user);
                mysqli_stmt_bind_param($stmt_user, "i", $currentUserId);
                mysqli_stmt_execute($stmt_user);
                $result_user = mysqli_stmt_get_result($stmt_user);
                $user = mysqli_fetch_assoc($result_user);
                $user_apelido = htmlspecialchars($user['apelido'] ?? 'Usuário Desconhecido');
                mysqli_stmt_close($stmt_user);

                // Recria o HTML do novo comentário para inserção via AJAX
                $response['new_comment_html'] = "
                    <div class='comment-item'>
                        <div class='comment-header'>
                            <span class='comment-author'>{$user_apelido}</span>
                            <span class='comment-time'>agora</span>
                        </div>
                        <p class='comment-content'>" . nl2br(htmlspecialchars($commentText)) . "</p>
                    </div>";

                // Recalcula a contagem de comentários
                $sql_count = "SELECT COUNT(*) FROM comentarios_pessoais WHERE id_postagem = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count);
                mysqli_stmt_bind_param($stmt_count, "i", $postId);
                mysqli_stmt_execute($stmt_count);
                $result_count = mysqli_stmt_get_result($stmt_count);
                $row_count = mysqli_fetch_row($result_count);
                $response['comments_count'] = $row_count[0];
                mysqli_stmt_close($stmt_count);

            } else {
                $response['message'] = "Erro ao inserir comentário: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = "Erro na preparação da query de comentário: " . mysqli_error($conn);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ------------------------------------------------------------------------------------------------
// 2. BUSCAR DADOS DO PERFIL
// ------------------------------------------------------------------------------------------------
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
mysqli_stmt_bind_param($stmt_fetch, "i", $targetUserId);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$profileData = mysqli_fetch_assoc($result_fetch);
mysqli_stmt_close($stmt_fetch);

if (!$profileData) {
    // Se o ID não existir no banco
    die("Perfil de usuário não encontrado.");
}

$displayApelido = $profileData['apelido'];
$displayBio = $profileData['bio'] ?: 'Nenhuma biografia definida.';
// Lógica para foto de perfil padrão
$defaultPhoto = 'caminho/para/foto_padrao.png'; // Defina o caminho para uma imagem padrão
$displayPhoto = $profileData['foto_perfil'] ?: $defaultPhoto;

// ------------------------------------------------------------------------------------------------
// 3. BUSCAR POSTS PESSOAIS DO FEED
// ------------------------------------------------------------------------------------------------

$posts = [];
$sql_select_posts = "
    SELECT 
        p.id, 
        p.conteudo, 
        p.imagem, 
        p.data_criacao,
        (SELECT COUNT(*) FROM curtidas_pessoais lc WHERE lc.id_postagem = p.id) AS likes_count,
        (SELECT COUNT(*) FROM comentarios_pessoais cc WHERE cc.id_postagem = p.id) AS comments_count
    FROM 
        posts_pessoais p
    WHERE 
        p.usuario_id = ? 
    ORDER BY 
        p.data_criacao DESC";

$stmt_posts = mysqli_prepare($conn, $sql_select_posts);
mysqli_stmt_bind_param($stmt_posts, "i", $targetUserId);
mysqli_stmt_execute($stmt_posts);
$result_posts = mysqli_stmt_get_result($stmt_posts);

while ($post = mysqli_fetch_assoc($result_posts)) {
    $posts[] = $post;
}
mysqli_stmt_close($stmt_posts);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?= htmlspecialchars($displayApelido) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos básicos para o perfil */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .main-content-single {
            display: flex;
            justify-content: center;
        }
        .profile-container {
            width: 100%;
            max-width: 800px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            text-align: center;
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
            margin-bottom: 15px;
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #ddd;
        }
        .profile-header h1 {
            color: #1e3c72;
            margin: 10px 0 5px;
            font-size: 2.5rem;
        }
        .btn-edit, .btn-follow {
            background-color: #2879e4;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 15px;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-edit:hover, .btn-follow:hover {
            background-color: #1e3c72;
        }
        .bio-section {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .bio-section h2 {
            color: #1e3c72;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .bio-text {
            line-height: 1.6;
        }
        .feed-container {
            padding-top: 20px;
        }
        .feed-container h2 {
            color: #1e3c72;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        /* Estilos dos Posts */
        .new-post-form-wrapper {
            background-color: #f0f4f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .new-post-form-wrapper h3 {
            color: #1e3c72;
            margin-top: 0;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        .post-text-area {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            margin-bottom: 10px;
            box-sizing: border-box;
            font-size: 1rem;
        }
        .post-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .post-options label {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .post-options label:hover {
            background-color: #0056b3;
        }
        .post-options input[type="file"] {
            display: none;
        }
        .post-options button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        .post-options button:hover {
            background-color: #388E3C;
        }

        .post-card {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .post-meta {
            color: #999;
            font-size: 0.85rem;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .post-content {
            white-space: pre-wrap;
            margin-bottom: 15px;
            color: #333;
        }
        .post-image-preview-wrapper {
            margin-bottom: 15px;
        }
        .post-image-preview {
            max-width: 100%;
            height: auto;
            display: block;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .post-image-wrapper { 
            margin-top: 15px; 
            margin-bottom: 15px; 
        } 
        .post-image { 
            max-width: 100%; 
            height: auto; 
            display: block; 
            border-radius: 4px; 
        } 
        .post-actions { 
            display: flex; 
            gap: 15px; 
            border-top: 1px solid #eee; 
            padding-top: 10px; 
        } 
        .action-btn { 
            background: none; 
            border: none; 
            color: #555; 
            cursor: pointer; 
            padding: 5px 10px; 
            transition: color 0.2s; 
            font-size: 0.95rem; 
        } 
        .action-btn:hover { 
            color: #2879e4; 
        } 
        .like-btn.liked { 
            color: #d9534f; /* Vermelho para curtido */ 
        } 
        .comments-section { 
            margin-top: 15px; 
            padding-top: 15px; 
            border-top: 1px solid #eee; 
            display: none; /* Inicia oculto */ 
        } 
        .comments-section.active { 
            display: block; /* Visível quando ativado */ 
        } 
        .comment-item { 
            padding: 10px; 
            background-color: #f9f9f9; 
            border-radius: 4px; 
            margin-bottom: 8px; 
        } 
        .comment-header { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.85rem; 
            margin-bottom: 5px; 
        } 
        .comment-author { 
            font-weight: bold; 
            color: #2879e4; 
        } 
        .comment-time { 
            color: #999; 
        } 
        .comment-content { 
            font-size: 0.9rem; 
            color: #333; 
        } 
        .comment-form { 
            display: flex; 
            margin-top: 10px; 
        } 
        .comment-form input[type="text"] { 
            flex-grow: 1; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px 0 0 4px; 
            outline: none; 
        } 
        .comment-form button { 
            background-color: #2879e4; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 0 4px 4px 0; 
            cursor: pointer; 
            transition: background-color 0.2s; 
        } 
        .comment-form button:hover {
            background-color: #1e3c72;
        }
        .no-posts-message {
            color: #666;
            text-align: center;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

    </style>
</head>
<body>
    <main>
    <div class="main-content-single">
        <div class="profile-container">
            
            <div class="profile-top-bar">
                <a href="homePage.php" class="btn-back-link" title="Voltar para o Feed Principal">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="profile-header">
                <div class="profile-photo-wrapper">
                    <img src="<?= htmlspecialchars($displayPhoto) ?>" alt="Foto de Perfil de <?= htmlspecialchars($displayApelido) ?>" class="profile-photo">
                </div>
                <h1><?= htmlspecialchars($displayApelido) ?></h1>
                <p style="color: #666;">Membro da NeuroBlogs</p>
                <?php if ($isCurrentUser): ?>
                <a href="perfil_edicao.php" class="btn-edit">
                    <i class="fas fa-user-edit"></i> Editar seu Perfil
                </a>
                <?php else: ?>
                <button class="btn-follow">
                    <i class="fas fa-user-plus"></i> Seguir
                </button>
                <?php endif; ?>
            </div>
            
            <div class="bio-section">
                <h2>Biografia</h2>
                <p class="bio-text"><?= htmlspecialchars($displayBio) ?></p>
            </div>
            
            <div class="feed-container">
                <h2>Posts Pessoais</h2>
                <?php if ($isCurrentUser): ?>
                <div class="new-post-form-wrapper">
                    <h3>O que você está pensando, <?= htmlspecialchars($displayApelido) ?>?</h3>
                    <form id="postFormPessoal" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="post_pessoal">
                        <textarea name="conteudo" id="conteudo_post_pessoal" class="post-text-area" placeholder="Escreva seu post aqui..." required></textarea>
                        
                        <div class="post-options">
                            <div>
                                <input type="file" name="imagem" id="imagem_post_pessoal" accept="image/*">
                                <label for="imagem_post_pessoal">
                                    <i class="fas fa-camera"></i> Adicionar Imagem
                                </label>
                            </div>
                            <button type="submit">Postar</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (count($posts) > 0): ?>
                    <?php foreach ($posts as $post): ?>
                        <?php 
                        // Verifica se o usuário logado curtiu o post
                        $isLiked = false;
                        if ($currentUserId > 0) {
                            $sql_check_like = "SELECT id FROM curtidas_pessoais WHERE id_postagem = ? AND id_usuario = ?";
                            $stmt_check_like = mysqli_prepare($conn, $sql_check_like);
                            mysqli_stmt_bind_param($stmt_check_like, "ii", $post['id'], $currentUserId);
                            mysqli_stmt_execute($stmt_check_like);
                            mysqli_stmt_store_result($stmt_check_like);
                            $isLiked = mysqli_stmt_num_rows($stmt_check_like) > 0;
                            mysqli_stmt_close($stmt_check_like);
                        }
                        ?>
                        <div class="post-card" id="post-<?= $post['id'] ?>">
                            <p class="post-meta">
                                Postado por <?= htmlspecialchars($displayApelido) ?> 
                                <span style="margin-left: 10px;">•</span> 
                                <span title="<?= date('d/m/Y H:i', strtotime($post['data_criacao'])) ?>"><?= time_ago($post['data_criacao']) ?></span>
                            </p>
                            
                            <p class="post-content"><?= nl2br(htmlspecialchars($post['conteudo'])) ?></p>
                            
                            <?php if (!empty($post['imagem'])): ?>
                                <div class="post-image-wrapper">
                                    <img src="<?= htmlspecialchars($post['imagem']) ?>" alt="Imagem do Post" class="post-image">
                                </div>
                            <?php endif; ?>

                            <div class="post-actions">
                                <button class="action-btn like-btn <?= $isLiked ? 'liked' : '' ?>" data-post-id="<?= $post['id'] ?>">
                                    <i class="fas fa-heart"></i> 
                                    <span id="likes-count-<?= $post['id'] ?>"><?= $post['likes_count'] ?></span> Curtidas
                                </button>
                                <button class="action-btn comment-toggle-btn" data-post-id="<?= $post['id'] ?>">
                                    <i class="fas fa-comment"></i> 
                                    <span id="comments-count-<?= $post['id'] ?>"><?= $post['comments_count'] ?></span> Comentários
                                </button>
                            </div>
                            
                            <div class="comments-section" id="comments-section-<?= $post['id'] ?>">
                                <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
                                    <?php 
                                    // Busca os comentários para esta postagem (apenas os 5 mais recentes)
                                    $sql_comments = "
                                        SELECT 
                                            c.conteudo, 
                                            c.data_criacao, 
                                            u.apelido 
                                        FROM 
                                            comentarios_pessoais c 
                                        JOIN 
                                            usuarios u ON c.id_usuario = u.id 
                                        WHERE 
                                            c.id_postagem = ? 
                                        ORDER BY 
                                            c.data_criacao DESC 
                                        LIMIT 5";

                                    $stmt_comments = mysqli_prepare($conn, $sql_comments);
                                    mysqli_stmt_bind_param($stmt_comments, "i", $post['id']);
                                    mysqli_stmt_execute($stmt_comments);
                                    $result_comments = mysqli_stmt_get_result($stmt_comments);
                                    $has_comments = mysqli_num_rows($result_comments) > 0;
                                    
                                    if ($has_comments):
                                        while($comment = mysqli_fetch_assoc($result_comments)):
                                    ?>
                                        <div class="comment-item">
                                            <div class="comment-header">
                                                <span class="comment-author"><?= htmlspecialchars($comment['apelido']) ?></span>
                                                <span class="comment-time" title="<?= date('d/m/Y H:i', strtotime($comment['data_criacao'])) ?>"><?= time_ago($comment['data_criacao']) ?></span>
                                            </div>
                                            <p class="comment-content"><?= nl2br(htmlspecialchars($comment['conteudo'])) ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="no-comments-message" id="no-comments-message-<?= $post['id'] ?>">Nenhum comentário ainda.</div>
                                    <?php endif; 
                                    mysqli_stmt_close($stmt_comments);
                                    ?>
                                </div>
                                
                                <?php if ($currentUserId > 0): ?>
                                    <form class="comment-form" data-post-id="<?= $post['id'] ?>">
                                        <input type="text" name="comment_text" placeholder="Escreva um comentário..." required>
                                        <button type="submit" data-action="comment_pessoal">Comentar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-posts-message">Este usuário ainda não publicou posts pessoais.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // =================================================================
            // 1. Lógica de Curtir (Like)
            // =================================================================
            document.querySelectorAll('.like-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    const isLiked = this.classList.contains('liked');
                    const likesCountSpan = document.getElementById(`likes-count-${postId}`);
                    
                    const formData = new FormData();
                    formData.append('action', 'like_pessoal');
                    formData.append('post_id', postId);

                    fetch('perfil.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Atualiza a classe/cor do botão
                            this.classList.toggle('liked', data.liked);
                            // Atualiza a contagem de likes
                            if (likesCountSpan) {
                                likesCountSpan.textContent = data.likes_count;
                            }
                        } else {
                            alert(data.message || 'Erro ao processar a curtida. Você está logado?');
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX na curtida:', error);
                        alert('Erro de conexão ao curtir o post.');
                    });
                });
            });

            // =================================================================
            // 2. Lógica de Comentar
            // =================================================================
            // 2.1. Toggle para mostrar/esconder a seção de comentários
            document.querySelectorAll('.comment-toggle-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    const commentsSection = document.getElementById(`comments-section-${postId}`);
                    commentsSection.classList.toggle('active');
                });
            });

            // 2.2. Submissão do formulário de comentário
            document.querySelectorAll('.comment-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const postId = this.getAttribute('data-post-id');
                    const commentInput = this.querySelector('input[name="comment_text"]');
                    const commentText = commentInput.value.trim();
                    const commentsList = document.getElementById(`comments-list-${postId}`);
                    const commentsCountSpan = document.getElementById(`comments-count-${postId}`);

                    if (commentText === "") {
                        alert("O comentário não pode ser vazio.");
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'comment_pessoal');
                    formData.append('post_id', postId);
                    formData.append('comment_text', commentText);

                    fetch('perfil.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove a mensagem 'Nenhum comentário ainda.' se ela existir
                            const noCommentsMessage = document.getElementById(`no-comments-message-${postId}`);
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }
                            
                            // Adiciona o novo comentário ao topo da lista (já que a lista é ordenada por DESC)
                            // Na verdade, como a busca inicial é DESC (mais novo primeiro), o novo deve ir no topo.
                            // Mas para o AJAX simples, vou adicionar no final por consistência, se quisermos no topo, 
                            // precisaríamos alterar a lógica de inserção para `prepend` ou recarregar a lista.
                            // Por enquanto, vou manter o `beforeend` e instruir sobre a ordem.
                            commentsList.insertAdjacentHTML('afterbegin', data.new_comment_html);
                            commentInput.value = ''; // Limpa o campo
                            
                            // Atualiza a contagem
                            if (commentsCountSpan) {
                                commentsCountSpan.textContent = data.comments_count;
                            }
                        } else {
                            alert(data.message || 'Erro ao publicar o comentário. Tente novamente.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX na publicação do comentário:', error);
                        alert('Erro de conexão ao publicar o comentário.');
                    });
                });
            });

            // =================================================================
            // 3. Lógica de Pré-visualização de Imagem para Novo Post Pessoal (Apenas para o próprio usuário)
            // =================================================================
            const imagemPostInput = document.getElementById('imagem_post_pessoal');
            if (imagemPostInput) {
                imagemPostInput.addEventListener('change', function(e) {
                    let previewContainer = document.querySelector('.post-image-preview-wrapper');
                    
                    // Remove a pré-visualização anterior
                    if(previewContainer) previewContainer.remove();
                    
                    const file = e.target.files[0];
                    
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewContainer = document.createElement('div');
                            previewContainer.className = 'post-image-preview-wrapper';
                            
                            const previewImage = document.createElement('img');
                            previewImage.src = e.target.result;
                            previewImage.alt = 'Pré-visualização da imagem';
                            previewImage.className = 'post-image-preview';
                            
                            previewContainer.appendChild(previewImage);
                            
                            // Insere a pré-visualização após a textarea
                            document.querySelector('.post-text-area').insertAdjacentElement('afterend', previewContainer);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }


        });
    </script>
</body>
</html>