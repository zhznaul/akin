<?php
// PHP - Arquivo: perfil.php (Perfil Pessoal e Feed de Posts Pessoais)
session_start();
// O conexao.php é necessário para a conexão e para as funções de post/like/comment
include "conexao.php"; 


// Inclui funções utilitárias como time_ago (se estiver em conexao.php ou outro arquivo)
date_default_timezone_set('America/Sao_Paulo'); // Garante o fuso horário

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
// 1. LÓGICA DE AÇÃO (Post, Like, Comment, Delete Comment, Delete Post) PARA POSTS PESSOAIS
// ------------------------------------------------------------------------------------------------
$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['success' => false];

// Lógica de Postar Novo Post Pessoal (Inalterada)
if ($action == 'post_pessoal' && $isCurrentUser && isset($_POST['conteudo'])) {
    // ... (lógica post_pessoal inalterada)
    $conteudo = trim($_POST['conteudo']);
    $imagem_post = NULL; // Inicializa como NULL
    $has_image_error = false;
    $upload_error_message = "";
    
    // NOVO: Obtém a visibilidade
    $visibilidade = $_POST['visibilidade'] ?? 'publico';
    // Garante que o valor é válido para evitar injeção e erro no ENUM
    if (!in_array($visibilidade, ['publico', 'privado'])) {
        $visibilidade = 'publico';
    }


    // --- Lógica de Upload da Imagem (Sem Redimensionamento - Apenas salvando o original) ---
    if (isset($_FILES['imagem'])) {
        $file = $_FILES['imagem'];
        
        // VERIFICAÇÃO DE ERROS DE UPLOAD (Inclui limite de tamanho do php.ini)
        if ($file['error'] === UPLOAD_ERR_OK) {
            
            $uploadDir = 'uploads/posts_pessoais/';
            
            // Cria o diretório se não existir
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    $has_image_error = true;
                    $upload_error_message = "Erro ao criar diretório de upload. Verifique as permissões da pasta.";
                }
            }

            if (!$has_image_error) {
                // Verifica o tipo de arquivo
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowed_types)) {
                    $has_image_error = true;
                    $upload_error_message = "Tipo de arquivo não permitido. Apenas JPG, PNG, WEBP ou GIF.";
                } else {
                    // Gera um nome único para o arquivo final, usando a extensão original
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $currentUserId . '_' . time() . '.' . $extension; 
                    $targetPath = $uploadDir . $filename;

                    // Move o arquivo original (sem redimensionamento) para o destino final
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $imagem_post = $targetPath;
                    } else {
                        // Falha ao mover/renomear
                        $has_image_error = true;
                        $upload_error_message = "Erro ao salvar a imagem no destino final.";
                    }
                }
            }
        
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            // Se houver um erro de upload que não seja "nenhum arquivo enviado"
            $has_image_error = true;
            $error_code = $file['error'];
            
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE:
                    $upload_error_message = "A imagem é muito grande (excede o limite 'upload_max_filesize' ou 'post_max_size' do servidor PHP).";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $upload_error_message = "A imagem excede o limite definido no formulário (MAX_FILE_SIZE).";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $upload_error_message = "O upload da imagem foi feito parcialmente.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $upload_error_message = "Faltando uma pasta temporária no servidor.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $upload_error_message = "Falha ao escrever a imagem no disco do servidor.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $upload_error_message = "Uma extensão PHP parou o upload da imagem.";
                    break;
                default:
                    $upload_error_message = "Erro desconhecido ($error_code) no upload do arquivo.";
            }
        }
    }
    // --- Fim da Lógica de Upload da Imagem ---

    // Permite posts apenas com imagem ou apenas com conteudo
    if (empty($conteudo) && $imagem_post === NULL) {
        $response['message'] = "O post não pode ser totalmente vazio. Adicione conteúdo ou uma imagem válida.";
    } else {
        // ATUALIZADO: Inclui o campo 'visibilidade' na inserção
        $sql = "INSERT INTO posts_pessoais (usuario_id, conteudo, imagem, visibilidade) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            // ATUALIZADO: Adiciona 's' e a variável $visibilidade
            mysqli_stmt_bind_param($stmt, "isss", $currentUserId, $conteudo, $imagem_post, $visibilidade); 
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                
                
            } else {
                $response['message'] = "Erro ao inserir post no banco: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = "Erro na preparação da query: " . mysqli_error($conn);
        }
    }
    
    // Responde
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Lógica de Curtir Post Pessoal (Inalterada)
if ($action == 'like_pessoal' && isset($_POST['post_id']) && $currentUserId > 0) {
    // ... (lógica de like inalterada)
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

// Lógica de Comentar Post Pessoal (Inalterada)
if ($action == 'comment_pessoal' && isset($_POST['post_id']) && isset($_POST['comment_text']) && $currentUserId > 0) {
    // ... (lógica de comentário inalterada)
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
                $new_comment_id = mysqli_insert_id($conn); // Pega o ID do novo comentário
                
                // Busca o apelido do usuário logado para montar o HTML do novo comentário
                $sql_user = "SELECT apelido FROM usuarios WHERE id = ?";
                $stmt_user = mysqli_prepare($conn, $sql_user);
                mysqli_stmt_bind_param($stmt_user, "i", $currentUserId);
                mysqli_stmt_execute($stmt_user);
                $result_user = mysqli_stmt_get_result($stmt_user);
                $user = mysqli_fetch_assoc($result_user);
                $user_apelido = htmlspecialchars($user['apelido'] ?? 'Usuário Desconhecido');
                mysqli_stmt_close($stmt_user);

                // Recria o HTML do novo comentário para inserção via AJAX, incluindo o ID do comentário e o botão de exclusão
                $response['new_comment_html'] = "
                    <div class='comment-item' id='comment-{$new_comment_id}'>
                        <div class='comment-header'>
                            <span class='comment-author'>{$user_apelido}</span>
                            <span class='comment-time'>agora</span>
                            <button class='btn-delete-comment' data-comment-id='{$new_comment_id}' title='Excluir Comentário'>
                                <i class='fas fa-times-circle'></i> 
                            </button>
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

// Lógica de Excluir Comentário Pessoal (Inalterada)
if ($action == 'delete_comment_pessoal' && isset($_POST['comment_id']) && $currentUserId > 0) {
    $commentId = intval($_POST['comment_id']);

    // 1. Verificar se o usuário atual é o autor do comentário OU o autor do post
    $sql_check = "
        SELECT 
            c.id_usuario AS commenter_id, 
            p.usuario_id AS post_owner_id,
            c.id_postagem AS post_id
        FROM 
            comentarios_pessoais c
        JOIN 
            posts_pessoais p ON c.id_postagem = p.id
        WHERE 
            c.id = ?
    ";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $commentId);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $commentData = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);

        if ($commentData) {
            // Permissão: Se é dono do comentário OU dono do post
            $isCommentOwner = ($currentUserId == $commentData['commenter_id']);
            $isPostOwner = ($currentUserId == $commentData['post_owner_id']);
            $postId = $commentData['post_id'];

            if ($isCommentOwner || $isPostOwner) {
                // Usuário tem permissão para excluir
                $sql_delete = "DELETE FROM comentarios_pessoais WHERE id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $commentId);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $response['success'] = true;
                        $response['message'] = "Comentário excluído com sucesso.";
                        $response['post_id'] = $postId;
                        
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
                        $response['message'] = "Erro ao excluir: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $response['message'] = "Erro na preparação da query de exclusão.";
                }
            } else {
                $response['message'] = "Você não tem permissão para excluir este comentário.";
            }
        } else {
            $response['message'] = "Comentário não encontrado.";
        }
    } else {
        $response['message'] = "Erro na verificação de permissão.";
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Lógica de Excluir Post Pessoal (Inalterada)
if ($action == 'delete_post_pessoal' && isset($_POST['post_id']) && $isCurrentUser) {
    $postId = intval($_POST['post_id']);

    // 1. Verificar se o usuário atual é o autor do post e buscar o caminho da imagem
    $sql_check = "SELECT usuario_id, imagem FROM posts_pessoais WHERE id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $postId);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $postData = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);

        if ($postData) {
            $postOwnerId = $postData['usuario_id'];
            $postImagePath = $postData['imagem'];

            // Permissão: Se é dono do post
            if ($currentUserId == $postOwnerId) {
                // 2. Excluir o registro do banco. (Os dados relacionados em curtidas_pessoais e comentarios_pessoais
                // serão excluídos automaticamente se suas chaves estrangeiras tiverem ON DELETE CASCADE).
                $sql_delete = "DELETE FROM posts_pessoais WHERE id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $postId);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        
                        // 3. Excluir o arquivo de imagem do servidor, se existir
                        if (!empty($postImagePath) && file_exists($postImagePath)) {
                            // Verifica se o caminho não é perigoso (embora o uploadDir já limite)
                            if (strpos($postImagePath, 'uploads/posts_pessoais/') === 0) {
                                unlink($postImagePath);
                            }
                        }
                        
                        $response['success'] = true;
                        $response['message'] = "Postagem excluída com sucesso.";
                        
                    } else {
                        $response['message'] = "Erro ao excluir post: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $response['message'] = "Erro na preparação da query de exclusão do post.";
                }
            } else {
                $response['message'] = "Você não tem permissão para excluir esta postagem.";
            }
        } else {
            $response['message'] = "Postagem não encontrada.";
        }
    } else {
        $response['message'] = "Erro na verificação de permissão (post).";
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


// ------------------------------------------------------------------------------------------------
// 2. BUSCAR DADOS DO PERFIL (Inalterada)
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
// 📌 NOVO: 3. LÓGICA DE PAGINAÇÃO
// ------------------------------------------------------------------------------------------------
$postsPerPage = 10; // Define o limite de posts por página
// Garante que o número da página é um inteiro positivo, ou 1 se for inválido
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
// Calcula o ponto de início (offset) na consulta SQL
$offset = ($currentPage - 1) * $postsPerPage;

// 1. Contar o total de posts (importante para calcular as páginas)
$sql_count_total = "SELECT COUNT(*) AS total FROM posts_pessoais WHERE usuario_id = ?";
// Adiciona a restrição de visibilidade para usuários não-donos
if (!$isCurrentUser) {
    $sql_count_total .= " AND visibilidade = 'publico'";
}

$stmt_count = mysqli_prepare($conn, $sql_count_total);
mysqli_stmt_bind_param($stmt_count, "i", $targetUserId);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$row_count = mysqli_fetch_assoc($result_count);
$totalPosts = $row_count['total'];
// Calcula o número total de páginas
$totalPages = ceil($totalPosts / $postsPerPage);
mysqli_stmt_close($stmt_count);
// ------------------------------------------------------------------------------------------------


// ------------------------------------------------------------------------------------------------
// 4. BUSCAR POSTS PESSOAIS DO FEED (AGORA COM LIMIT E OFFSET)
// ------------------------------------------------------------------------------------------------
$posts = [];
// Seleciona a visibilidade e contagens
$sql_select_posts = "
    SELECT 
        p.id, 
        p.conteudo, 
        p.imagem, 
        p.data_criacao,
        p.visibilidade, 
        (SELECT COUNT(*) FROM curtidas_pessoais lc WHERE lc.id_postagem = p.id) AS likes_count,
        (SELECT COUNT(*) FROM comentarios_pessoais cc WHERE cc.id_postagem = p.id) AS comments_count
    FROM 
        posts_pessoais p
    WHERE 
        p.usuario_id = ? ";

// Lógica de filtragem de visibilidade
if (!$isCurrentUser) {
    // Se não é o próprio usuário, mostra APENAS posts públicos
    $sql_select_posts .= " AND p.visibilidade = 'publico' ";
}

// 📌 NOVO: Adiciona a ordenação, LIMIT e OFFSET para a paginação
$sql_select_posts .= " ORDER BY p.data_criacao DESC LIMIT ? OFFSET ?";

$stmt_posts = mysqli_prepare($conn, $sql_select_posts);

// 'i i i' para $targetUserId, $postsPerPage (LIMIT) e $offset (OFFSET)
mysqli_stmt_bind_param($stmt_posts, "iii", $targetUserId, $postsPerPage, $offset); 
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
        /* ESTILOS PARA O BOTÃO VOLTAR */
        /* -------------------------------------- */
        .profile-top-bar {
            display: flex; 
            justify-content: flex-start;
            margin-bottom: 20px; 
            padding-top: 5px;
        }
        .btn-back-link {
            background-color: transparent !important;
            color: #2879e4;
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
        
        /* NOVO: Estilos para o controle da esquerda (Imagem + Visibilidade) */
        .left-controls {
            display: flex;
            gap: 15px; /* Espaço entre o botão de imagem e o toggle */
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

        /* NOVO: Estilos para o Toggle de Visibilidade */
        .visibility-toggle {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden; /* Garante que os botões se ajustem à borda */
        }
        .visibility-toggle input[type="radio"] {
            display: none;
        }
        .visibility-toggle .btn-visibility {
            background-color: #f0f0f0;
            color: #555;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s, color 0.3s;
            border: none; /* Remove a borda individual */
            border-radius: 0;
        }
        /* Estilo quando um botão está selecionado (ativo) */
        .visibility-toggle input[type="radio"]:checked + .btn-visibility {
            background-color: #2879e4;
            color: white;
        }
        .visibility-toggle input[type="radio"]:checked + .btn-visibility.privado {
            background-color: #cc0000;
        }
        .visibility-toggle .btn-visibility:first-of-type {
            border-right: 1px solid #ddd;
        }
        .visibility-toggle .btn-visibility:last-of-type {
            border-left: 1px solid #ddd;
        }
        /* FIM DOS ESTILOS DO TOGGLE */


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
            /* Flexbox para alinhamento horizontal (ajuda o botão de exclusão) */
            display: flex; 
            align-items: center; 
            flex-wrap: wrap; /* Permite quebrar linha em telas menores */
        }
        /* NOVO: Estilos para a tag de visibilidade no post */
        .post-card .post-tag-visibility {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 10px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1; /* Alinha o texto na mesma linha */
        }
        .post-tag-visibility.privado {
            background-color: #fce8e8;
            color: #cc0000;
        }
        .post-tag-visibility.publico {
            background-color: #e8f9e8;
            color: #008000;
        }
        /* FIM DOS ESTILOS DA TAG */


        .post-content {
            white-space: pre-wrap;
            margin-bottom: 15px;
            color: #333;
        }
        .post-image-preview-wrapper {
            margin-bottom: 15px;
            text-align: center; /* Centraliza a preview */
        }
        .post-image-preview, 
        .post-image { 
            max-width: 450px; 
            max-height: 400px; 
            width: auto;
            height: auto;
            display: inline-block; /* Altera para inline-block para centralizar */
            border-radius: 4px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        } 
        .post-image-wrapper { 
            margin-top: 15px; 
            margin-bottom: 15px; 
            text-align: center; /* Centraliza a imagem no feed */
        } 
        /* FIM DOS ESTILOS DE IMAGEM */

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
        /* Alinha a data e o botão de exclusão à direita */
        .comment-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; /* Alinha verticalmente */
            font-size: 0.85rem; 
            margin-bottom: 5px; 
        } 
        .comment-author { 
            font-weight: bold; 
            color: #2879e4; 
        } 
        .comment-time { 
            color: #999; 
            margin-left: 10px; /* Espaçamento entre autor e tempo */
        } 
        /* Estilo para o botão de excluir comentário */
        .btn-delete-comment {
            background: none;
            border: none;
            color: #cc0000;
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            margin-left: auto; /* Alinha à direita no comment-header */
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        .btn-delete-comment:hover {
            opacity: 1;
        }
        /* FIM DO ESTILO DE EXCLUSÃO */

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
        
        /* 📌 NOVO: Estilos da Paginação */
        .pagination-controls {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .page-link {
            text-decoration: none;
            color: #2879e4;
            padding: 8px 12px;
            margin: 0 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: inline-block;
            transition: background-color 0.2s, color 0.2s;
        }
        .page-link:hover {
            background-color: #e6f0ff;
        }
        .page-link.active {
            background-color: #2879e4;
            color: white;
            border-color: #2879e4;
            font-weight: bold;
        }
        .page-dots {
            padding: 8px 4px;
            color: #999;
        }
        /* FIM DA PAGINAÇÃO */

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
                <h2>Posts Pessoais (Página <?= $currentPage ?> de <?= $totalPages ?>)</h2>

                <?php if ($isCurrentUser): ?>
                <div class="new-post-form-wrapper">
                    <h3>O que você está pensando, <?= htmlspecialchars($displayApelido) ?>?</h3>
                    <form id="postFormPessoal" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="post_pessoal">
                        <textarea name="conteudo" id="conteudo_post_pessoal" class="post-text-area" placeholder="Escreva seu post aqui..."></textarea>
                        
                        <div class="post-image-preview-wrapper">
                            </div>

                        <div class="post-options">
                            <div class="left-controls">
                                <div>
                                    <input type="file" name="imagem" id="imagem_post_pessoal" accept="image/*">
                                    <label for="imagem_post_pessoal">
                                        <i class="fas fa-camera"></i> Adicionar Imagem
                                    </label>
                                </div>
                                
                                <div class="visibility-toggle">
                                    <input type="radio" id="visibility_publico" name="visibilidade" value="publico" checked>
                                    <label for="visibility_publico" class="btn-visibility publico" title="Público (Todos podem ver)">
                                        <i class="fas fa-globe"></i> Público
                                    </label>
                                    
                                    <input type="radio" id="visibility_privado" name="visibilidade" value="privado">
                                    <label for="visibility_privado" class="btn-visibility privado" title="Privado (Apenas você pode ver)">
                                        <i class="fas fa-lock"></i> Privado
                                    </label>
                                </div>
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
                                
                                <?php if ($isCurrentUser): ?>
                                    <span class="post-tag-visibility <?= htmlspecialchars($post['visibilidade']) ?>">
                                        <i class="fas fa-<?= ($post['visibilidade'] == 'privado' ? 'lock' : 'globe') ?>"></i> 
                                        <?= ($post['visibilidade'] == 'privado' ? 'Privado' : 'Público') ?>
                                    </span>
                                    
                                    <button class="action-btn delete-post-btn" data-post-id="<?= $post['id'] ?>" title="Excluir Postagem" style="color: #cc0000; margin-left: auto;">
                                        <i class="fas fa-trash-alt"></i> Excluir Post
                                    </button>
                                    
                                <?php endif; ?>
                                
                            </p>
                            
                            <p class="post-content"><?= nl2br(htmlspecialchars($post['conteudo'])) ?></p>
                            
                            <?php if (!empty($post['imagem'])): ?>
                                <div class="post-image-wrapper">
                                    <img src="<?= htmlspecialchars($post['imagem']) ?>" alt="Imagem do Post" class="post-image">
                                </div>
                            <?php endif; ?>

                            <div class="post-actions">
                                <?php if ($post['visibilidade'] == 'publico' || $isCurrentUser): ?>
                                    <button class="action-btn like-btn <?= $isLiked ? 'liked' : '' ?>" data-post-id="<?= $post['id'] ?>">
                                        <i class="fas fa-heart"></i> 
                                        <span id="likes-count-<?= $post['id'] ?>"><?= $post['likes_count'] ?></span> Curtidas
                                    </button>
                                    <button class="action-btn comment-toggle-btn" data-post-id="<?= $post['id'] ?>">
                                        <i class="fas fa-comment"></i> 
                                        <span id="comments-count-<?= $post['id'] ?>"><?= $post['comments_count'] ?></span> Comentários
                                    </button>
                                <?php else: ?>
                                    <span style="color: #999;">Conteúdo privado.</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="comments-section" id="comments-section-<?= $post['id'] ?>">
                                <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
                                    <?php 
                                    // Busca os comentários para esta postagem (ATUALIZADO para pegar o ID do comentário e do autor)
                                    $sql_comments = "
                                        SELECT 
                                            c.id,              /* ID do comentário */
                                            c.conteudo, 
                                            c.data_criacao, 
                                            u.apelido,
                                            u.id AS commenter_id /* ID do autor do comentário */
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
                                        // Variável de controle para quem pode excluir (dono do comentário ou dono do post)
                                        $canDelete = ($currentUserId > 0) && ($currentUserId == $comment['commenter_id'] || $isCurrentUser);
                                    ?>
                                        <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                                            <div class="comment-header">
                                                <span class="comment-author"><?= htmlspecialchars($comment['apelido']) ?></span>
                                                <span class="comment-time" title="<?= date('d/m/Y H:i', strtotime($comment['data_criacao'])) ?>"><?= time_ago($comment['data_criacao']) ?></span>
                                                <?php if ($canDelete): ?>
                                                    <button class="btn-delete-comment" data-comment-id="<?= $comment['id'] ?>" title="Excluir Comentário">
                                                        <i class="fas fa-times-circle"></i> 
                                                    </button>
                                                <?php endif; ?>
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

                <?php if ($totalPages > 1): ?>
                    <div class="pagination-controls">
                        <?php 
                        // Base da URL para os links de paginação
                        $baseUrl = "perfil.php?id=" . $targetUserId;
                        $pageRange = 2; // Número de páginas a mostrar antes e depois da atual
                        ?>
                        
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= $baseUrl . "&page=" . ($currentPage - 1) ?>" class="page-link">&laquo; Anterior</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php 
                            // Lógica para mostrar apenas um intervalo de páginas (Ex: 1... 5 6 [7] 8 9 ...15)
                            // Mostra a primeira, a última e as páginas adjacentes à atual
                            if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $pageRange && $i <= $currentPage + $pageRange)):
                            ?>
                                <a href="<?= $baseUrl . "&page=" . $i ?>" class="page-link <?= ($i == $currentPage ? 'active' : '') ?>">
                                    <?= $i ?>
                                </a>
                            <?php 
                            // Adiciona reticências se a próxima página não for adjacente
                            elseif ($i == $currentPage - $pageRange - 1 || $i == $currentPage + $pageRange + 1):
                            ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= $baseUrl . "&page=" . ($currentPage + 1) ?>" class="page-link">Próximo &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                </div>
        </div>
    </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // =================================================================
            // 4. Lógica de Envio do Post Pessoal (AJAX com File Upload)
            // =================================================================
            const postForm = document.getElementById('postFormPessoal');
            if (postForm) {
                // Adiciona o eventListener ao formulário
                postForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Impede o envio tradicional

                    const conteudo = document.getElementById('conteudo_post_pessoal').value.trim();
                    const imagemInput = document.getElementById('imagem_post_pessoal');
                    const imagemFile = imagemInput.files[0];
                    
                    if (conteudo === "" && !imagemFile) {
                        alert("O post não pode ser totalmente vazio. Adicione conteúdo ou uma imagem.");
                        return;
                    }

                    // Cria o objeto FormData a partir do próprio formulário.
                    const formData = new FormData(this);
                    
                    fetch('perfil.php', {
                        method: 'POST',
                        body: formData // O FormData envia a imagem e todos os outros campos (incluindo visibilidade)
                    })
                    .then(response => {
                        // Força a resposta para JSON
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Recarrega a página para exibir o novo post (que estará na primeira página)
                            window.location.href = window.location.pathname + window.location.search.split('&page=')[0]; 
                        } else {
                            // Se o post foi salvo (mas sem imagem) ou se deu erro total
                            alert(data.message || 'Erro desconhecido ao postar. Tente novamente.');
                            // Recarrega a página de qualquer forma para limpar o formulário
                            window.location.reload(); 
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX na postagem:', error);
                        alert('Erro de conexão ao postar. Tente novamente.');
                        window.location.reload(); 
                    });
                });
            }


            // =================================================================
            // 5. Lógica de Pré-visualização de Imagem
            // =================================================================
            const imagemPostInput = document.getElementById('imagem_post_pessoal');
            const previewWrapper = document.querySelector('.new-post-form-wrapper .post-image-preview-wrapper');

            if (imagemPostInput && previewWrapper) {
                imagemPostInput.addEventListener('change', function(e) {
                    previewWrapper.innerHTML = ''; // Limpa a pré-visualização anterior
                    
                    const file = e.target.files[0];
                    
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewImage = document.createElement('img');
                            previewImage.src = e.target.result;
                            previewImage.alt = 'Pré-visualização da imagem';
                            // Classe CSS que respeita o limite visual de 450x400
                            previewImage.className = 'post-image-preview'; 
                            
                            previewWrapper.appendChild(previewImage);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }


            // =================================================================
            // 1. Lógica de Curtir (Like)
            // =================================================================
            document.querySelectorAll('.like-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    
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
                            this.classList.toggle('liked', data.liked);
                            document.getElementById(`likes-count-${postId}`).textContent = data.likes_count;
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
                            
                            // Adiciona o novo comentário (no topo, para ser mais notável)
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
            // 3. Lógica de Excluir Comentário
            // =================================================================
            document.addEventListener('click', function(e) {
                // Verifica se o clique foi no botão de exclusão ou em um ícone dentro dele
                if (e.target.closest('.btn-delete-comment')) {
                    const deleteButton = e.target.closest('.btn-delete-comment');
                    const commentId = deleteButton.getAttribute('data-comment-id');
                    
                    if (!confirm('Tem certeza que deseja excluir este comentário?')) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'delete_comment_pessoal');
                    formData.append('comment_id', commentId);

                    fetch('perfil.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 1. Remove o comentário da lista (usa o ID para encontrar o div)
                            const commentElement = document.getElementById(`comment-${commentId}`);
                            if(commentElement) commentElement.remove();
                            
                            // 2. Atualiza a contagem de comentários no post
                            if (data.post_id && data.comments_count !== undefined) {
                                document.getElementById(`comments-count-${data.post_id}`).textContent = data.comments_count;
                            }

                            // 3. Verifica se a lista ficou vazia e adiciona a mensagem padrão
                            const commentsList = document.getElementById(`comments-list-${data.post_id}`);
                            if (commentsList && commentsList.children.length === 0) {
                                const noCommentsMessage = document.createElement('div');
                                noCommentsMessage.className = 'no-comments-message';
                                noCommentsMessage.id = `no-comments-message-${data.post_id}`;
                                noCommentsMessage.textContent = 'Nenhum comentário ainda.';
                                commentsList.appendChild(noCommentsMessage);
                            }
                            
                        } else {
                            alert(data.message || 'Erro ao excluir o comentário.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX na exclusão do comentário:', error);
                        alert('Erro de conexão ao excluir o comentário.');
                    });
                }
            });


            // =================================================================
            // 4. Lógica de Excluir Post Pessoal
            // =================================================================
            document.querySelectorAll('.delete-post-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    
                    if (!confirm('ATENÇÃO: Você tem certeza que deseja excluir esta postagem? Todos os comentários e curtidas serão perdidos permanentemente.')) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'delete_post_pessoal');
                    formData.append('post_id', postId);

                    fetch('perfil.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Remove o post inteiro da DOM
                            const postElement = document.getElementById(`post-${postId}`);
                            if(postElement) postElement.remove();
                            
                            // Recarrega a página após a exclusão para garantir que a paginação seja re-renderizada corretamente
                            window.location.reload(); 
                            
                        } else {
                            alert(data.message || 'Erro ao excluir a postagem.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede/AJAX na exclusão do post:', error);
                        alert('Erro de conexão ao excluir a postagem.');
                    });
                });
            });

        });
    </script>
</body>
</html>