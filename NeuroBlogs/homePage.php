<?php
// PHP - Arquivo: homePage.php (Layout de Duas Colunas Restaurado com Funcionalidades)
session_start();

// Define o fuso horário para o de São Paulo (UTC-3)
date_default_timezone_set('America/Sao_Paulo');

include "conexao.php"; 
include "menu_navegacao.php";


if (!isset($conn) || $conn->connect_error) {
    die("Erro fatal: A conexão com o banco de dados não pôde ser estabelecida. Verifique o arquivo 'conexao.php' e as credenciais. Erro: " . (isset($conn) ? $conn->connect_error : 'Variável $conn não definida.'));
}

// Verifica se a extensão GD está instalada e ativada
if (!extension_loaded('gd') || !function_exists('gd_info')) {
    // Manter como comentário para evitar parada no TCC, mas deve ser ativado se o upload for essencial.
    // die("Erro fatal: A biblioteca GD para processamento de imagens não está instalada ou ativada no seu servidor PHP.");
}

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

$userName = $_SESSION["usuario"];
$userId = $_SESSION['usuario_id'];



// --- 1. FUNÇÃO PARA REDIMENSIONAR E OTIMIZAR IMAGENS ---
if (!function_exists('resizeImage')) {
    function resizeImage($file, $maxWidth = 800, $maxHeight = 600) {
        $info = getimagesize($file);
        if ($info === false) {
            return false;
        }
        list($originalWidth, $originalHeight) = $info;
        $mime = $info['mime'];

        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return $file;
        }

        $scale = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = ceil($scale * $originalWidth);
        $newHeight = ceil($scale * $originalHeight);
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($file);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($file);
                } else {
                    return false; 
                }
                break;
            default:
                return false;
        }

        if ($image === false) {
            return false;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime == 'image/png') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        $temp_file_path = tempnam(sys_get_temp_dir(), 'resized_');

        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagepng($newImage, $temp_file_path, 9);
        } else {
            imagejpeg($newImage, $temp_file_path, 85); 
        }

        imagedestroy($image);
        imagedestroy($newImage);

        return $temp_file_path;
    }
}

// --- 2. BUSCA DE PREFERÊNCIAS DE ACESSIBILIDADE ---
// Mantendo a tabela 'perfil_usuario' conforme o arquivo original e o pedido.
$sql_perfil = "SELECT cor_fundo_pref, cor_texto_pref, tamanho_fonte_pref, fonte_preferida FROM perfil_usuario WHERE id = ?";
$stmt_perfil = mysqli_prepare($conn, $sql_perfil);
$user_prefs = [];

if ($stmt_perfil) {
    mysqli_stmt_bind_param($stmt_perfil, "i", $userId);
    mysqli_stmt_execute($stmt_perfil);
    $res_perfil = mysqli_stmt_get_result($stmt_perfil);

    if ($res_perfil && $row = mysqli_fetch_assoc($res_perfil)) {
        $user_prefs = $row;
    }
    mysqli_stmt_close($stmt_perfil);
}

// Valores padrão se não houver preferências salvas
$default_prefs = [
    'cor_fundo_pref' => '#f5f5f5',
    'cor_texto_pref' => '#2c3e50',
    'tamanho_fonte_pref' => '16px',
    'fonte_preferida' => 'sans-serif'
];
$prefs = array_merge($default_prefs, $user_prefs);

// --- 3. LÓGICA DE AÇÃO (Post, Like, Comment) ---
$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['success' => false];
$postMessage = ''; // Inicializa a variável para exibir mensagens de erro no formulário


// ------------------------------------------------------------------------------------------------
// LÓGICA DE CRIAÇÃO DE POST (Adaptada da versão de comunidade.php)
// ------------------------------------------------------------------------------------------------
if ($action == 'post' && isset($_POST['conteudo'])) {
    $communityIdPost = intval($_POST['id_comunidade']);
    $conteudo = trim($_POST['conteudo']);
    $imagem_path = null;

    if ($communityIdPost <= 0) {
        $postMessage = "Você deve selecionar uma comunidade para postar.";
    } 
    
    if (empty($postMessage)) {
        // 1. Verifica se o usuário é membro ou criador da comunidade (Importado de comunidade.php)
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

        if ($can_post) {
            if (!empty($conteudo) || (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0)) {
                
                // 2. Processamento da imagem (utilizando a função resizeImage já presente em homePage.php)
                if (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0) {
                    $file = $_FILES['imagem_post'];
                    $uploadDir = 'uploads/posts_comunidade/'; 
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $temp_file = $file['tmp_name'];
                    $original_name = $file['name'];
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $new_filename = uniqid('post_') . '.' . $ext;
                    $target_file = $uploadDir . $new_filename;

                    // Tenta redimensionar/otimizar usando a função de homePage.php
                    $optimized_temp_path = resizeImage($temp_file); 
                    
                    if ($optimized_temp_path && $optimized_temp_path != $temp_file) {
                        // Se a otimização produziu um novo arquivo (otimizado/redimensionado)
                        if (rename($optimized_temp_path, $target_file) || copy($optimized_temp_path, $target_file)) {
                            $imagem_path = $target_file;
                            if (file_exists($optimized_temp_path)) unlink($optimized_temp_path);
                        } else {
                            $postMessage = "Erro ao mover arquivo otimizado.";
                        }
                    } else if ($optimized_temp_path == $temp_file) {
                        // Se o arquivo original já estava no tamanho (move_uploaded_file é necessário)
                        if (move_uploaded_file($temp_file, $target_file)) {
                            $imagem_path = $target_file;
                        } else {
                            $postMessage = "Erro ao mover arquivo original.";
                        }
                    } else {
                         // Se falhou a otimização
                        if (move_uploaded_file($temp_file, $target_file)) {
                            $imagem_path = $target_file;
                        } else {
                            $postMessage = "Erro ao fazer upload da imagem.";
                        }
                    }
                }

                // 3. Insere a postagem SE não houve erro no upload da imagem
                if (empty($postMessage)) {
                    $sql_insert = "INSERT INTO posts_comunidade (id_comunidade, usuario_id, conteudo, imagem) VALUES (?, ?, ?, ?)";
                    $stmt_insert = mysqli_prepare($conn, $sql_insert);
                    
                    if ($stmt_insert) {
                        mysqli_stmt_bind_param($stmt_insert, "iiss", $communityIdPost, $userId, $conteudo, $imagem_path);
                        
                        if (mysqli_stmt_execute($stmt_insert)) {
                            // PADRÃO PRG: Redireciona para evitar duplicação
                            header("Location: homePage.php"); 
                            exit;
                        } else {
                            $postMessage = "Erro ao criar a publicação: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt_insert);
                    } else {
                         $postMessage = "Erro na preparação da query de inserção.";
                    }
                }

            } else {
                 $postMessage = "Conteúdo e/ou imagem são necessários para postar.";
            }
        } else {
             $postMessage = "Você não é membro ou criador desta comunidade e não pode postar nela.";
        }
    }
    
    // Se a requisição for AJAX e falhou, retorna JSON (compatibilidade mantida)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $response['message'] = $postMessage;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
// ------------------------------------------------------------------------------------------------
// FIM LÓGICA DE CRIAÇÃO DE POST ADAPTADA
// ------------------------------------------------------------------------------------------------


// Lógica de Curtir Post (AJAX) - CÓDIGO DE COMUNIDADE.PHP
if ($action == 'like_post' && isset($_POST['post_id'])) {
    $postId = intval($_POST['post_id']);
    $response = ['success' => false, 'new_count' => 0, 'status' => '']; // Estrutura de resposta de comunidade.php

    // 1. Verifica se já curtiu
    $sql_check = "SELECT id FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "ii", $postId, $userId);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        $alreadyLiked = mysqli_stmt_num_rows($stmt_check) > 0;
        mysqli_stmt_close($stmt_check);

        if ($alreadyLiked) {
            // Descurtir
            $sql_action = "DELETE FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
            $status = 'unliked';
        } else {
            // Curtir
            $sql_action = "INSERT INTO curtidas_comunidade (id_postagem, id_usuario) VALUES (?, ?)";
            $status = 'liked';
        }
        
        $stmt_action = mysqli_prepare($conn, $sql_action);
        if ($stmt_action) {
            mysqli_stmt_bind_param($stmt_action, "ii", $postId, $userId);
            if (mysqli_stmt_execute($stmt_action)) {
                $response['success'] = true;
                $response['status'] = $status;
                
                // Recalcula a contagem
                $sql_count = "SELECT COUNT(*) FROM curtidas_comunidade WHERE id_postagem = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count);
                if ($stmt_count) {
                    mysqli_stmt_bind_param($stmt_count, "i", $postId);
                    mysqli_stmt_execute($stmt_count);
                    mysqli_stmt_bind_result($stmt_count, $likeCount);
                    mysqli_stmt_fetch($stmt_count);
                    $response['new_count'] = $likeCount;
                    mysqli_stmt_close($stmt_count);
                }
            } else {
                 $response['message'] = "Erro ao executar ação: " . mysqli_error($conn);
            }
        } else {
             $response['message'] = "Erro na preparação da query de ação.";
        }
    } else {
         $response['message'] = "Erro na preparação da query de checagem.";
    }
    
    // CORREÇÃO: Responde com JSON e encerra o script imediatamente
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


// Lógica de Comentar Post (AJAX) - CÓDIGO DE COMUNIDADE.PHP
if ($action == 'comment_post' && isset($_POST['post_id']) && isset($_POST['comment_text'])) {
    $postId = intval($_POST['post_id']);
    $commentText = trim($_POST['comment_text']);

    if (!empty($commentText)) {
        // Tabela de comentários utilizada é 'comentarios_comunidade'
        $sql = "INSERT INTO comentarios_comunidade (id_postagem, id_usuario, conteudo) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $postId, $userId, $commentText);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $commentId = mysqli_insert_id($conn);
                $commentTime = date("Y-m-d H:i:s"); 

                // --- CORREÇÃO APLICADA AQUI (MANTIDA) ---
                // Busca dados do usuário para o HTML do comentário (apenas apelido, foto_perfil causa erro)
                $sql_user_info = "SELECT apelido FROM usuarios WHERE id = ?"; // REMOVIDO: foto_perfil
                $stmt_user_info = mysqli_prepare($conn, $sql_user_info);
                // Inicializa com valor padrão para foto_perfil
                $new_comment = ['apelido' => $userName, 'foto_perfil' => 'uploads/perfil/default.png', 'data_criacao' => $commentTime, 'conteudo' => $commentText];
                
                if ($stmt_user_info) {
                    mysqli_stmt_bind_param($stmt_user_info, "i", $userId);
                    mysqli_stmt_execute($stmt_user_info);
                    $result_user_info = mysqli_stmt_get_result($stmt_user_info);
                    if ($user_info_row = mysqli_fetch_assoc($result_user_info)) {
                        $new_comment['apelido'] = $user_info_row['apelido'];
                        // A foto de perfil permanecerá o default.
                    }
                    mysqli_stmt_close($stmt_user_info);
                }
                // --- FIM DA CORREÇÃO ---


                // Recalcula a contagem de comentários 
                $sql_count = "SELECT COUNT(*) FROM comentarios_comunidade WHERE id_postagem = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count);
                if ($stmt_count) {
                    mysqli_stmt_bind_param($stmt_count, "i", $postId);
                    mysqli_stmt_execute($stmt_count);
                    mysqli_stmt_bind_result($stmt_count, $commentCount);
                    mysqli_stmt_fetch($stmt_count);
                    $response['new_count'] = $commentCount; // Envia a nova contagem
                    mysqli_stmt_close($stmt_count);
                }
                
                // Formata o novo comentário para inserção imediata no DOM (HTML de comunidade.php)
                 $response['new_comment_html'] = '
                    <div class="comment-item border-bottom pb-2 mb-2">
                        <div class="d-flex align-items-center mb-1">
                            <img src="' . htmlspecialchars($new_comment['foto_perfil']) . '" alt="Foto" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                            <strong class="me-2">' . htmlspecialchars($new_comment['apelido']) . '</strong>
                            <small class="text-muted ms-auto">agora mesmo</small>
                        </div>
                        <p class="mb-0 ms-4">' . nl2br(htmlspecialchars($new_comment['conteudo'])) . '</p>
                    </div>';

            } else {
                $response['message'] = "Erro ao inserir comentário: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
             $response['message'] = "Erro na preparação da query de inserção.";
        }
    } else {
         $response['message'] = "O texto do comentário não pode ser vazio.";
    }
    
    // CORREÇÃO: Responde com JSON e encerra o script imediatamente
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// O bloco de verificação AJAX genérica abaixo foi removido para garantir o comportamento JSON consistente.

// --- FIM DA LÓGICA DE AÇÃO ---

// --- 3.5. BUSCA DE COMUNIDADES DO USUÁRIO PARA O FORMULÁRIO E BARRA LATERAL (AJUSTADO) ---
$user_communities = [];
$sql_fetch_user_communities = "
    SELECT c.id, c.nome_comunidade, c.imagem 
    FROM comunidades c
    JOIN membros_comunidade mc ON c.id = mc.id_comunidade
    WHERE mc.id_usuario = ?
    ORDER BY c.nome_comunidade ASC";

$stmt_comm_form = mysqli_prepare($conn, $sql_fetch_user_communities);

if ($stmt_comm_form) {
    mysqli_stmt_bind_param($stmt_comm_form, "i", $userId);
    mysqli_stmt_execute($stmt_comm_form);
    $result_comm_form = mysqli_stmt_get_result($stmt_comm_form);

    while ($row = mysqli_fetch_assoc($result_comm_form)) {
        $user_communities[] = $row;
    }
    mysqli_stmt_close($stmt_comm_form);
}
// --- FIM DA BUSCA DE COMUNIDADES ---


// --- 4. PAGINAÇÃO E VARIÁVEIS DE EXIBIÇÃO ---
$posts_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $posts_per_page;


// --- 5. LÓGICA DO FEED: Consulta Principal (Apenas Comunidades do Usuário) ---

$current_page_url = "homePage.php"; // URL base para a paginação

// Lista de IDs de comunidades que o usuário é membro
$comunidades_usuario = [];
// Reutiliza a lista $user_communities para preencher a lista de IDs
foreach ($user_communities as $comm) {
    $comunidades_usuario[] = $comm['id'];
}

// Prepara as variáveis para a consulta
$where_clause = " WHERE p.id IS NOT NULL "; // Cláusula base
$bind_types = "";
$bind_params = [];

// Filtro: APENAS POSTS DE COMUNIDADES QUE O USUÁRIO É MEMBRO (Filtro único e definitivo)
$where_clause .= " AND p.id_comunidade IS NOT NULL ";

if (!empty($comunidades_usuario)) {
    $ids_placeholder = implode(',', array_fill(0, count($comunidades_usuario), '?'));
    $where_clause .= " AND p.id_comunidade IN ({$ids_placeholder})";
    
    // Adiciona os IDs das comunidades aos parâmetros de binding
    $bind_types .= str_repeat('i', count(array_filter($comunidades_usuario, function($v) { return is_int($v); })));
    $bind_params = array_merge($bind_params, $comunidades_usuario);
} else {
    // Se não for membro de nenhuma comunidade, o feed deve vir vazio
    $where_clause .= " AND 1=0 "; 
}


// CORREÇÕES MANTIDAS: Tabelas de posts e interações corrigidas para '_comunidade'
$sql_select_posts = "SELECT p.id, p.usuario_id, u.apelido, p.conteudo, p.imagem, p.data_criacao,
                            (SELECT COUNT(*) FROM curtidas_comunidade lc WHERE lc.id_postagem = p.id) AS likes_count,
                            (SELECT COUNT(*) FROM comentarios_comunidade cc WHERE cc.id_postagem = p.id) AS comments_count,
                            c.nome_comunidade, c.id AS comunidade_id
                     FROM posts_comunidade p 
                     JOIN usuarios u ON p.usuario_id = u.id
                     LEFT JOIN comunidades c ON p.id_comunidade = c.id
                     {$where_clause}
                     GROUP BY p.id
                     ORDER BY p.data_criacao DESC
                     LIMIT ? OFFSET ?";
                     
$bind_types .= 'ii';
$bind_params[] = $posts_per_page;
$bind_params[] = $offset;


$stmt_posts = mysqli_prepare($conn, $sql_select_posts);
$result_posts = false;

if ($stmt_posts) {
    $bind_refs = array($bind_types);
    for ($i = 0; $i < count($bind_params); $i++) {
        $bind_refs[] = &$bind_params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt_posts), $bind_refs));

    mysqli_stmt_execute($stmt_posts);
    $result_posts = mysqli_stmt_get_result($stmt_posts);
    
    mysqli_stmt_close($stmt_posts);
}


// Lógica de Contagem Total de Posts para a Paginação (também ajustada para posts_comunidade)
$sql_count_posts = "SELECT COUNT(p.id) AS total_posts 
                    FROM posts_comunidade p
                    LEFT JOIN comunidades c ON p.id_comunidade = c.id
                    {$where_clause}"; 
                    
// Retira os últimos 2 'i's e os 2 últimos parâmetros (LIMIT e OFFSET)
$count_bind_types = substr($bind_types, 0, -2); 
$count_bind_params = array_slice($bind_params, 0, -2); 


$stmt_count = mysqli_prepare($conn, $sql_count_posts);
$total_posts = 0;

if ($stmt_count) {
    if (!empty($count_bind_types)) {
        $count_bind_refs = array($count_bind_types);
        for ($i = 0; $i < count($count_bind_params); $i++) {
            $count_bind_refs[] = &$count_bind_params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt_count), $count_bind_refs));
    }
    
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_posts);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_posts / $posts_per_page);


// --- 6. FUNÇÕES AUXILIARES ---

function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;

    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);

    if ($seconds <= 60) {
        return "agora mesmo";
    } elseif ($minutes <= 60) {
        return $minutes == 1 ? "há 1 minuto" : "há {$minutes} minutos";
    } elseif ($hours <= 24) {
        return $hours == 1 ? "há 1 hora" : "há {$hours} horas";
    } elseif ($days <= 7) {
        return $days == 1 ? "ontem" : "há {$days} dias";
    } elseif ($weeks <= 4.3) {
        return $weeks == 1 ? "há 1 semana" : "há {$weeks} semanas";
    } elseif ($months <= 12) {
        return $months == 1 ? "há 1 mês" : "há {$months} meses";
    } else {
        return $years == 1 ? "há 1 ano" : "há {$years} anos";
    }
}

function check_if_user_liked($conn, $postId, $userId) {
    // CORREÇÃO: Tabela de curtidas utilizada é 'curtidas_comunidade'
    $sql = "SELECT COUNT(*) FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $postId, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $count > 0;
    }
    return false;
}

function display_post_card($post) {
    global $conn, $userId;

    $is_liked = check_if_user_liked($conn, $post['id'], $userId);
    $post_id = $post['id'];
    $comunidade_html = '';

    if (!empty($post['nome_comunidade'])) {
        $comunidade_html = "<span class='post-community-link' data-comunidade-id='{$post['comunidade_id']}'>em <a href='comunidade.php?id={$post['comunidade_id']}'>{$post['nome_comunidade']}</a></span>";
    }

    // ADICIONADO: shadow-sm, mb-4, bg-white para estilo de cartão consistente
    $card_html = "<div class='card post-card shadow-sm mb-4 bg-white' data-post-id='{$post_id}'>
        <div class='post-header'>
            <img src='imagens/default.png' alt='Avatar' class='post-avatar'>
            <div class='post-info'>
                <span class='post-author'>{$post['apelido']}</span>
                {$comunidade_html}
                <span class='post-time'>" . time_ago($post['data_criacao']) . "</span>
            </div>
        </div>
        <div class='post-content'>
            <p>{$post['conteudo']}</p>";

    if ($post['imagem']) {
        // ADICIONADO: img-fluid e rounded para estilo de imagem consistente
        $card_html .= "<img src='{$post['imagem']}' alt='Imagem do post' class='post-image img-fluid rounded mt-2'>";
    }

    $card_html .= "</div>
        <div class='post-actions border-top pt-2 mt-2'>
            <button class='btn-action btn-like-post' data-post-id='{$post_id}' data-liked='". ($is_liked ? 'true' : 'false') ."'>
                <i class='fa-regular fa-heart " . ($is_liked ? 'fa-solid' : 'fa-regular') . "' style='color: " . ($is_liked ? 'red' : '#34495e') . ";'></i> 
                Curtidas (<span class='like-count'>{$post['likes_count']}</span>)
            </button>
            <button class='btn-action btn-comment' data-post-id='{$post_id}'>
                <i class='fa-solid fa-comment'></i> Comentários (<span class='comment-count'>{$post['comments_count']}</span>)
            </button>
        </div>
        <div class='comments-section' id='comments-{$post_id}'>
            <div class='comments-list' id='comments-list-{$post_id}'>";

    // --- CORREÇÃO APLICADA AQUI (MANTIDA) ---
    // Buscar Comentários para o Post - REMOVIDO: u.foto_perfil
    $sql_comments = "SELECT c.conteudo, c.data_criacao, u.apelido
                     FROM comentarios_comunidade c 
                     JOIN usuarios u ON c.id_usuario = u.id 
                     WHERE c.id_postagem = ? 
                     ORDER BY c.data_criacao ASC";
    $stmt_comments = mysqli_prepare($conn, $sql_comments);

    $comment_count = 0;
    if ($stmt_comments) {
        mysqli_stmt_bind_param($stmt_comments, "i", $post_id);
        mysqli_stmt_execute($stmt_comments);
        $result_comments = mysqli_stmt_get_result($stmt_comments);
        
        while ($comment = mysqli_fetch_assoc($result_comments)) {
            $comment_count++;
            // Força a imagem de perfil padrão, pois a coluna não foi encontrada na query
            $comment_profile_pic = 'uploads/perfil/default.png'; 
            $comment_time_ago = time_ago($comment['data_criacao']);

            // HTML de comentário com a estrutura de comunidade.php
            $card_html .= "
                <div class='comment-item border-bottom pb-2 mb-2'>
                    <div class='d-flex align-items-center mb-1'>
                        <img src='{$comment_profile_pic}' alt='Foto' class='rounded-circle me-2' style='width: 30px; height: 30px;'>
                        <strong class='me-2'>" . htmlspecialchars($comment['apelido']) . "</strong>
                        <small class='text-muted ms-auto'>{$comment_time_ago}</small>
                    </div>
                    <p class='mb-0 ms-4'>" . nl2br(htmlspecialchars($comment['conteudo'])) . "</p>
                </div>";
        }
        mysqli_stmt_close($stmt_comments);
    }
    // --- FIM DA CORREÇÃO ---

    if ($comment_count === 0) {
        $card_html .= "<div class='no-comments-message text-center p-3' id='no-comments-message-{$post_id}'>Nenhum comentário ainda. Seja o primeiro!</div>";
    }


    $card_html .= "</div>
            <div class='new-comment-form p-3'>
                <form class='comment-form-ajax' data-post-id='{$post_id}'>
                    <input type='hidden' name='action' value='comment_post'>
                    <input type='hidden' name='post_id' value='{$post_id}'>
                    <div class='input-group'>
                        <textarea name='comment_text' class='form-control' rows='1' placeholder='Escreva um comentário...' required></textarea>
                        <button type='submit' class='btn btn-primary'>Comentar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>";

    return $card_html;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeuroBlogs - Feed</title>
    <link rel="stylesheet" href="homePage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* Estilos de Acessibilidade */
        body {
            background-color: <?php echo htmlspecialchars($prefs['cor_fundo_pref']); ?>;
            color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>;
            font-size: <?php echo htmlspecialchars($prefs['tamanho_fonte_pref']); ?>;
            font-family: <?php echo htmlspecialchars($prefs['fonte_preferida']); ?>;
        }
        .card, .navigation, .member-list {
             /* Define fundo branco para os cartões e barra lateral, mantendo contraste */
             background-color: #ffffff !important;
        }
        .post-author, .post-community-link a {
            color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>;
        }
        .post-content p, .comments-list .comment-content {
            color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>;
        }
        /* Estilos do Formulário */
        .post-form-card {
            border: 1px solid #ddd;
        }
        .post-text-area {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 10px;
            resize: vertical;
        }
        .post-image-preview-wrapper {
            margin-top: 10px;
            margin-bottom: 10px;
            max-width: 100%;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .post-image-preview {
            width: 100%;
            height: auto;
            display: block;
        }
        .text-danger {
            color: red;
            font-size: 0.9em;
        }
        /* Botões */
        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-primary-2 {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary-2:hover {
            background-color: #2980b9;
        }
        .btn-action {
            background: none;
            border: none;
            color: #34495e;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.9em;
            transition: color 0.2s;
        }
        .btn-action:hover {
            color: #555;
        }
        .btn-action i {
            margin-right: 5px;
        }

        /* Estrutura de Comentários (Ajuste para Bootstrap) */
        .comments-section {
            border-top: 1px solid #eee;
            margin-top: 10px;
            padding-top: 10px;
            display: none; /* Inicia oculto */
        }
        .comments-section.active {
            display: block;
        }
        .comment-item {
            padding: 8px 0;
        }
        .comment-form-ajax .input-group { display: flex; }
        .comment-form-ajax .form-control { flex: 1 1 auto; margin-right: 5px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; }
        .comment-form-ajax .btn-primary { 
            background-color: #3498db; 
            color: white; 
            border: none; 
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
        }

        /* Estilos do Post Card */
        .post-card {
            border: none; /* A sombra já define o limite visual */
        }
        .post-header {
            display: flex;
            align-items: center;
            padding: 15px;
            padding-bottom: 10px;
        }
        .post-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        .post-info {
            display: flex;
            flex-direction: column;
        }
        .post-author {
            font-weight: bold;
        }
        .post-time, .post-community-link {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        .post-content {
            padding: 0 15px 15px 15px;
        }
        .post-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        .post-actions {
            padding: 0 15px 10px 15px;
        }

        /* Barra Lateral (Sidebar) */
        .member-list {
            border: 1px solid #eee;
        }
        .member-list h5 {
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
            color: #34495e;
            font-weight: bold;
        }
        .member-list li a:hover strong {
            color: #3498db;
        }
        
        /* Botão de Refresh */
        .btn-refresh {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #3498db; 
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1000; 
            font-size: 1em;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s;
        }
        .btn-refresh:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>

    <main class="main-content-area container mt-4">
        <div class="row"> 
            
            <div class="col-md-8"> 
                
                <div class="post-form-card mb-4 p-3 shadow-sm rounded bg-white">
                    <h5 class="mb-3" style="color: #3498db;"><i class="fas fa-bullhorn"></i> Criar Publicação</h5>
                    <form action="homePage.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="post">
                        
                        <div class="form-group mb-3">
                            <label for="id_comunidade" class="form-label visually-hidden">Postar em:</label>
                            <select name="id_comunidade" id="id_comunidade" class="form-control" required <?php echo empty($user_communities) ? 'disabled' : ''; ?>>
                                <option value="">Selecione uma comunidade para Postar *</option>
                                <?php foreach ($user_communities as $comm): ?>
                                    <option value="<?php echo $comm['id']; ?>">
                                        <?php echo htmlspecialchars($comm['nome_comunidade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($user_communities)): ?>
                                 <small class="text-muted">Você deve ser membro de uma comunidade para postar aqui.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-3">
                            <textarea name="conteudo" class="form-control post-text-area" rows="3" placeholder="O que você quer compartilhar com suas comunidades? (Máx. 500 caracteres)" maxlength="500"></textarea>
                        </div>
                        
                        <div class="post-image-preview-wrapper mb-3">
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <label for="imagem_post" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-image"></i> Imagem
                                <input type="file" name="imagem_post" id="imagem_post" accept="image/*" style="display: none;">
                            </label>
                            <button type="submit" class="btn-primary" <?php echo empty($user_communities) ? 'disabled' : ''; ?>>Publicar</button>
                        </div>

                        <?php if (!empty($postMessage)): // Exibe mensagem de erro de postagem ?>
                            <small class="text-danger mt-2 d-block"><?php echo htmlspecialchars($postMessage); ?></small>
                        <?php endif; ?>
                    </form>
                </div>

                <section class="feed-container">
                    <h2 class="feed-section-title mb-4" style="font-size: 1.25em;"><i class="fas fa-list-ul"></i> Publicações das Minhas Comunidades </h2>
                    <?php 
                    $post_count = 0;
                    if ($result_posts && mysqli_num_rows($result_posts) > 0) {
                        while ($post = mysqli_fetch_assoc($result_posts)) {
                            echo display_post_card($post);
                            $post_count++;
                        }
                    }
                    
                    if ($post_count == 0): ?>
                        <div class='alert alert-info text-center shadow-sm rounded' role='alert'>
                            Nenhuma publicação encontrada no seu feed. Junte-se ou crie uma comunidade para começar a ver postagens!
                        </div>
                    <?php endif; ?>
                    
                    <div class="pagination d-flex justify-content-center my-4">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars($current_page_url . "?page=" . ($page - 1)); ?>" class="btn btn-outline-secondary me-2">Anterior</a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo htmlspecialchars($current_page_url . "?page=" . ($page + 1)); ?>" class="btn btn-outline-secondary">Próxima</a>
                        <?php endif; ?>
                    </div>
                    
                </section>

            </div> 
            
            <div class="col-md-4">
                <div class="member-list shadow-sm p-3 rounded bg-white sticky-top" style="top: 20px;">
                    <h5 class="text-primary"> <i class="fas fa-users"></i> Minhas Comunidades (<?php echo count($user_communities); ?>)</h5>
                    
                    <?php if (!empty($user_communities)): ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($user_communities as $comm): ?>
                                <li class="p-2 border-bottom d-flex align-items-center">
                                    <a href="comunidade.php?id=<?php echo $comm['id']; ?>" class="d-flex align-items-center text-decoration-none w-100">
                                        <img src="<?php echo htmlspecialchars($comm['foto_comunidade'] ?? 'uploads/comunidades/default.png'); ?>" 
                                             alt="Logo" 
                                             class="rounded-circle me-3" 
                                             style="width: 40px; height: 40px; object-fit: cover; border: 1px solid #eee;">
                                        <div class="member-info">
                                            <strong class="text-dark"><?php echo htmlspecialchars($comm['nome_comunidade']); ?></strong>
                                        </div>
                                        <span class="badge bg-primary ms-auto">Ir</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="comunidades.php" class="btn btn-sm btn-outline-primary w-100 mt-3">Ver Todas</a>
                    <?php else: ?>
                        <div class="alert alert-warning mb-3">Você não é membro de nenhuma comunidade.</div>
                        <br>
                        <a href="comunidades.php" class="btn btn-sm btn-primary-2 w-100">Encontrar Comunidades</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>


<button id="refreshButton" class="btn-refresh" title="Atualizar Feed">
    <i class="fa-solid fa-arrows-rotate"></i> Atualizar
</button>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- LÓGICA DE PERSISTÊNCIA DE SCROLL ---

            function saveScrollPosition() {
                localStorage.setItem('scrollPosition', window.scrollY);
            }

            function restoreScrollPosition() {
                const scrollPosition = localStorage.getItem('scrollPosition');
                if (scrollPosition) {
                    setTimeout(() => {
                        window.scrollTo(0, parseInt(scrollPosition));
                        localStorage.removeItem('scrollPosition'); 
                    }, 50); 
                }
            }

            restoreScrollPosition();

            window.addEventListener('beforeunload', saveScrollPosition);

            const refreshButton = document.getElementById('refreshButton');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    localStorage.setItem('scrollPosition', window.scrollY);
                    window.location.reload(); 
                });
            }
            
            // --- FIM DA LÓGICA DE PERSISTÊNCIA DE SCROLL ---


            // 1. Lógica de Curtir Post (AJAX) 
            document.querySelectorAll('.btn-like-post').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const postId = this.getAttribute('data-post-id');
                    const likeCountSpan = this.querySelector('.like-count');
                    const heartIcon = this.querySelector('.fa-heart');

                    const formData = new FormData();
                    formData.append('action', 'like_post'); 
                    formData.append('post_id', postId);

                    fetch('homePage.php', { 
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de Rede: Status ' + response.status);
                        }
                        return response.json(); 
                    })
                    .then(data => {
                        if (data.success) {
                            likeCountSpan.textContent = data.new_count;
                            
                            if (data.status === 'liked') {
                                button.setAttribute('data-liked', 'true');
                                heartIcon.classList.remove('fa-regular');
                                heartIcon.classList.add('fa-solid');
                                heartIcon.style.color = 'red';
                            } else if (data.status === 'unliked') {
                                button.setAttribute('data-liked', 'false');
                                heartIcon.classList.remove('fa-solid');
                                heartIcon.classList.add('fa-regular');
                                heartIcon.style.color = '#34495e'; 
                            }
                        } else {
                            console.error('Erro ao curtir:', data.message);
                            alert('Erro ao curtir a publicação. Tente novamente.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição de Curtir:', error);
                        // Você pode adicionar um alerta para o usuário aqui se desejar
                    });
                });
            });

            // 2. Lógica de Comentar Post (AJAX) 
            document.querySelectorAll('.comment-form-ajax').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const postId = this.getAttribute('data-post-id');
                    const formData = new FormData(this);
                    
                    // Removido: formData.set('action', 'comment_post'); pois já está no HTML
                    // O campo hidden <input type='hidden' name='action' value='comment_post'> já cuida disso.

                    fetch('homePage.php', { 
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de Rede: Status ' + response.status);
                        }
                        return response.json(); 
                    })
                    .then(data => {
                        if (data.success) {
                            const commentsList = document.getElementById(`comments-list-${postId}`);
                            const noCommentsMessage = document.getElementById(`no-comments-message-${postId}`);
                            const commentButton = document.querySelector(`.btn-comment[data-post-id='${postId}']`);
                            const commentCountElement = commentButton.querySelector('.comment-count');
                            
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }
                            
                            // Insere o novo comentário no final da lista
                            commentsList.insertAdjacentHTML('beforeend', data.new_comment_html);
                            
                            // Limpa o campo de texto
                            this.querySelector('textarea[name="comment_text"]').value = ''; 

                            if (data.new_count !== undefined) {
                                commentCountElement.textContent = data.new_count;
                            }
                        } else {
                            console.error('Erro ao comentar:', data.message);
                            alert('Erro ao publicar o comentário. Tente novamente.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição de Comentar:', error);
                        // Você pode adicionar um alerta para o usuário aqui se desejar
                    });
                });
            });

            // 3. Lógica para mostrar/esconder a seção de comentários
            document.querySelectorAll('.btn-comment').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    const commentsSection = document.getElementById(`comments-${postId}`);
                    commentsSection.classList.toggle('active'); 
                });
            });
            
            // 4. Lógica para previsualizar a imagem antes do upload (ADAPTADO)
            document.getElementById('imagem_post').addEventListener('change', function(e) {
                // Seleciona o wrapper dentro do formulário
                let previewContainerWrapper = document.querySelector('.post-form-card .post-image-preview-wrapper');
                
                // Limpa a pré-visualização anterior
                previewContainerWrapper.innerHTML = '';
                
                const file = e.target.files[0];
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewImage = document.createElement('img');
                        previewImage.src = e.target.result;
                        previewImage.alt = 'Pré-visualização da imagem';
                        // Adiciona classes Bootstrap para melhor estilo
                        previewImage.className = 'post-image-preview img-fluid rounded'; 
                        
                        previewContainerWrapper.appendChild(previewImage);
                    };
                    reader.readAsDataURL(file);
                }
            });

        });
    </script>
</body>
</html>