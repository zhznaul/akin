<?php
// PHP - Arquivo: homePage.php (Com Exclusão e Paginação Completa - CORRIGIDO)
session_start();

// Define o fuso horário para o de São Paulo (UTC-3)
date_default_timezone_set('America/Sao_Paulo');

include "conexao.php"; 

// 1. VERIFICAÇÕES DE SISTEMA E LOGIN
if (!isset($conn) || $conn->connect_error) {
    die("Erro fatal: A conexão com o banco de dados falhou.");
}

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

$userName = $_SESSION["usuario"];
$userId = $_SESSION['usuario_id'];

// ADIÇÃO MÍNIMA 1: Foto de perfil padrão para uso consistente
$default_photo = 'uploads/perfil/default.png'; 


// 2. FUNÇÕES AUXILIARES
if (!function_exists('time_ago')) {
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

        if ($seconds <= 60) return "agora mesmo";
        elseif ($minutes <= 60) return $minutes == 1 ? "há 1 minuto" : "há {$minutes} minutos";
        elseif ($hours <= 24) return $hours == 1 ? "há 1 hora" : "há {$hours} horas";
        elseif ($days <= 7) return $days == 1 ? "ontem" : "há {$days} dias";
        elseif ($weeks <= 4.3) return $weeks == 1 ? "há 1 semana" : "há {$weeks} semanas";
        elseif ($months <= 12) return $months == 1 ? "há 1 mês" : "há {$months} meses";
        else return $years == 1 ? "há 1 ano" : "há {$years} anos";
    }
}

if (!function_exists('resizeImage')) {
    function resizeImage($file, $maxWidth = 800, $maxHeight = 600) {
        // 0. Verifica se a biblioteca GD está ativa
        // Isso previne o "Fatal error: Call to undefined function"
        if (!extension_loaded('gd')) {
            // Opcional: Logar o erro para o desenvolvedor saber
            error_log("Aviso: A extensão GD do PHP não está habilitada. Retornando imagem original.");
            return $file;
        }

        // 1. Verifica se o arquivo existe fisicamente
        if (!file_exists($file)) {
            return false;
        }

        // 2. Obtém informações da imagem suprimindo erros (@) caso o arquivo não seja uma imagem válida
        $info = @getimagesize($file);
        
        // Se falhar ou se o MIME type não estiver definido, retorna falso
        if ($info === false || !isset($info['mime'])) {
            return false;
        }

        list($originalWidth, $originalHeight) = $info;
        $mime = $info['mime'];

        // Se a imagem for menor que o limite, retorna o arquivo original
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return $file;
        }

        // Calcula a escala
        $scale = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) ceil($scale * $originalWidth);
        $newHeight = (int) ceil($scale * $originalHeight);

        // 3. Tenta criar a imagem a partir do arquivo original
        $image = null;
        
        // Usamos o operador @ para suprimir warnings do PHP caso o arquivo esteja corrompido
        switch ($mime) {
            case 'image/jpeg': 
                $image = @imagecreatefromjpeg($file); 
                break;
            case 'image/png': 
                $image = @imagecreatefrompng($file); 
                break;
            case 'image/gif': 
                $image = @imagecreatefromgif($file); 
                break;
            case 'image/webp': 
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($file);
                }
                break;
            default: 
                return false; // Formato não suportado
        }

        // Se a criação da imagem falhou (ex: arquivo corrompido), retorna falso
        if (!$image) {
            return false;
        }

        // 4. Cria a nova imagem (canvas)
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // 5. Tratamento CORRETO de Transparência (PNG, GIF e WebP)
        if ($mime == 'image/png' || $mime == 'image/gif' || $mime == 'image/webp') {
            // Desativa a mistura alpha para poder sobrescrever o fundo preto padrão
            imagealphablending($newImage, false);
            // Salva a informação de transparência
            imagesavealpha($newImage, true);
            // Cria uma cor transparente
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            // Preenche o fundo com transparente
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Copia e redimensiona
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Cria arquivo temporário
        $temp_file_path = tempnam(sys_get_tempdir(), 'resized');

        // 6. Salva a imagem final
        // Nota: O código original convertia GIF para PNG. Mantive essa lógica pois o GD não lida bem com redimensionamento de GIF animado.
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagepng($newImage, $temp_file_path, 9);
        } elseif ($mime == 'image/webp' && function_exists('imagewebp')) {
            imagewebp($newImage, $temp_file_path, 80);
        } else {
            // Padrão para JPEG (incluindo fallbacks)
            // Se tiver transparência e cair aqui, coloca fundo branco para evitar fundo preto do JPEG
            if ($mime != 'image/jpeg') {
                $white = imagecolorallocate($newImage, 255, 255, 255);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);
                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            }
            imagejpeg($newImage, $temp_file_path, 85);
        }

        // Libera memória
        imagedestroy($image);
        imagedestroy($newImage);

        return $temp_file_path;
    }
}


// ------------------------------------------------------------------------------------------------
// 3. PROCESSAMENTO AJAX (CURTIDAS, COMENTÁRIOS E EXCLUSÕES)
// ------------------------------------------------------------------------------------------------
$action = isset($_POST['action']) ? $_POST['action'] : '';


// --- AJAX: CURTIR (Inalterado) ---
if ($action == 'like_post' && isset($_POST['post_id'])) {
    // ... (lógica de like inalterada)
    $postId = intval($_POST['post_id']);
    $response = ['success' => false, 'new_count' => 0, 'status' => ''];

    // Verifica se já curtiu
    $sql_check = "SELECT id FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $postId, $userId);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $alreadyLiked = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);

    if ($alreadyLiked) {
        $sql_action = "DELETE FROM curtidas_comunidade WHERE id_postagem = ? AND id_usuario = ?";
        $status = 'unliked';
    } else {
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
            mysqli_stmt_bind_param($stmt_count, "i", $postId);
            mysqli_stmt_execute($stmt_count);
            mysqli_stmt_bind_result($stmt_count, $likeCount);
            mysqli_stmt_fetch($stmt_count);
            $response['new_count'] = $likeCount;
            mysqli_stmt_close($stmt_count);
        }
        mysqli_stmt_close($stmt_action);
    }
    
    // RETORNA JSON E PARA A EXECUÇÃO
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- AJAX: COMENTAR (Levemente Alterado para incluir o ID do comentário no retorno) ---
if ($action == 'comment_post' && isset($_POST['post_id']) && isset($_POST['comment_text'])) {
    $postId = intval($_POST['post_id']);
    $commentText = trim($_POST['comment_text']);
    $response = ['success' => false];

    if (!empty($commentText)) {
        $sql = "INSERT INTO comentarios_comunidade (id_postagem, id_usuario, conteudo) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $postId, $userId, $commentText);
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $new_comment_id = mysqli_insert_id($conn); // Novo: Pega o ID do comentário
                
                // Busca apelido E foto de perfil
                $sql_user_info = "SELECT u.apelido, pu.foto_perfil FROM usuarios u LEFT JOIN perfil_usuario pu ON u.id = pu.id WHERE u.id = ?"; 
                $stmt_user_info = mysqli_prepare($conn, $sql_user_info);
                $apelido = $userName;
                $foto_perfil = $default_photo;
                
                if ($stmt_user_info) {
                    mysqli_stmt_bind_param($stmt_user_info, "i", $userId);
                    mysqli_stmt_execute($stmt_user_info);
                    $result_user_info = mysqli_stmt_get_result($stmt_user_info);
                    if ($user_info_row = mysqli_fetch_assoc($result_user_info)) {
                        $apelido = $user_info_row['apelido'];
                        if (!empty($user_info_row['foto_perfil'])) {
                             $foto_perfil = $user_info_row['foto_perfil'];
                        }
                    }
                    mysqli_stmt_close($stmt_user_info);
                }
                
                // Recalcula contagem
                $sql_count = "SELECT COUNT(*) FROM comentarios_comunidade WHERE id_postagem = ?";
                $stmt_count = mysqli_prepare($conn, $sql_count);
                mysqli_stmt_bind_param($stmt_count, "i", $postId);
                mysqli_stmt_execute($stmt_count);
                mysqli_stmt_bind_result($stmt_count, $commentCount);
                mysqli_stmt_fetch($stmt_count);
                $response['new_count'] = $commentCount; 
                mysqli_stmt_close($stmt_count);
                
                // HTML do novo comentário - Agora com o ID do comentário e o botão de exclusão
                 $response['new_comment_html'] = '
                    <div class="comment-item border-bottom pb-2 mb-2" id="comment-'.$new_comment_id.'">
                        <div class="d-flex align-items-center mb-1">
                            <img src="' . htmlspecialchars($foto_perfil) . '" alt="Foto" class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <strong class="me-2">' . htmlspecialchars($apelido) . '</strong>
                            <small class="text-muted">' . time_ago(date("Y-m-d H:i:s")) . '</small>
                            <button class="btn-delete-comment ms-auto" data-comment-id="'.$new_comment_id.'" title="Excluir Comentário">
                                <i class="fas fa-times-circle"></i> 
                            </button>
                        </div>
                        <p class="mb-0 ms-4">' . nl2br(htmlspecialchars($commentText)) . '</p>
                    </div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // RETORNA JSON E PARA A EXECUÇÃO
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- NOVO: AJAX: EXCLUIR COMENTÁRIO ---
if ($action == 'delete_comment_post' && isset($_POST['comment_id'])) {
    $commentId = intval($_POST['comment_id']);
    $response = ['success' => false];

    // 1. Buscar dados do comentário e do post para checar permissão
    $sql_check = "
        SELECT 
            cc.id_usuario AS commenter_id, 
            pc.usuario_id AS post_owner_id,
            cc.id_postagem AS post_id
        FROM 
            comentarios_comunidade cc
        JOIN 
            posts_comunidade pc ON cc.id_postagem = pc.id
        WHERE 
            cc.id = ?
    ";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $commentId);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $commentData = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);

        if ($commentData) {
            $isCommentOwner = ($userId == $commentData['commenter_id']);
            $isPostOwner = ($userId == $commentData['post_owner_id']);
            $postId = $commentData['post_id'];

            if ($isCommentOwner || $isPostOwner) {
                // 2. Excluir o comentário
                $sql_delete = "DELETE FROM comentarios_comunidade WHERE id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $commentId);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $response['success'] = true;
                        $response['post_id'] = $postId;
                        
                        // 3. Recalcula a contagem
                        $sql_count = "SELECT COUNT(*) FROM comentarios_comunidade WHERE id_postagem = ?";
                        $stmt_count = mysqli_prepare($conn, $sql_count);
                        mysqli_stmt_bind_param($stmt_count, "i", $postId);
                        mysqli_stmt_execute($stmt_count);
                        mysqli_stmt_bind_result($stmt_count, $commentCount);
                        mysqli_stmt_fetch($stmt_count);
                        $response['new_count'] = $commentCount; 
                        mysqli_stmt_close($stmt_count);
                        
                    } else {
                        $response['message'] = "Erro ao excluir: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $response['message'] = "Erro na preparação da query de exclusão.";
                }
            } else {
                $response['message'] = "Permissão negada.";
            }
        } else {
            $response['message'] = "Comentário não encontrado.";
        }
    } else {
        $response['message'] = "Erro na verificação de permissão.";
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- NOVO: AJAX: EXCLUIR POST ---
if ($action == 'delete_post' && isset($_POST['post_id'])) {
    $postId = intval($_POST['post_id']);
    $response = ['success' => false];

    // 1. Verificar se o usuário atual é o autor do post e buscar o caminho da imagem
    $sql_check = "SELECT usuario_id, imagem FROM posts_comunidade WHERE id = ?";
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

            // Permissão: Deve ser o dono do post
            if ($userId == $postOwnerId) {
                // 2. Excluir o registro do banco. (CASCADE deve lidar com curtidas/comentários)
                $sql_delete = "DELETE FROM posts_comunidade WHERE id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $postId);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        
                        // 3. Excluir o arquivo de imagem do servidor, se existir
                        if (!empty($postImagePath) && file_exists($postImagePath)) {
                            // Verifica se o caminho está no diretório correto para segurança
                            if (strpos($postImagePath, 'uploads/posts_comunidade/') === 0) {
                                unlink($postImagePath);
                            }
                        }
                        
                        $response['success'] = true;
                        
                    } else {
                        $response['message'] = "Erro ao excluir post: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $response['message'] = "Erro na preparação da query de exclusão do post.";
                }
            } else {
                $response['message'] = "Permissão negada.";
            }
        } else {
            $response['message'] = "Postagem não encontrada.";
        }
    } else {
        $response['message'] = "Erro na verificação de permissão (post).";
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


// ------------------------------------------------------------------------------------------------
// 4. INÍCIO DA GERAÇÃO DA PÁGINA HTML
// ------------------------------------------------------------------------------------------------

include "menu_navegacao.php"; // O menu só é carregado se NÃO for uma requisição AJAX

// --- BUSCA DE PREFERÊNCIAS (Inalterado) ---
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
$default_prefs = ['cor_fundo_pref' => '#f5f5f5', 'cor_texto_pref' => '#2c3e50', 'tamanho_fonte_pref' => '16px', 'fonte_preferida' => 'sans-serif'];
$prefs = array_merge($default_prefs, $user_prefs);

// --- LÓGICA DE CRIAÇÃO DE POST (POST NORMAL, NÃO AJAX) (CORRIGIDO) ---
$postMessage = '';
if ($action == 'post' && isset($_POST['conteudo'])) {
    $communityIdPost = intval($_POST['id_comunidade']);
    $conteudo = trim($_POST['conteudo']);
    $imagem_path = null;

    if ($communityIdPost > 0) {
        $sql_check_member_creator = "SELECT (SELECT id_criador FROM comunidades WHERE id = ?) AS id_criador, (SELECT 1 FROM membros_comunidade WHERE id_comunidade = ? AND id_usuario = ?) AS is_member";
        $stmt_check = mysqli_prepare($conn, $sql_check_member_creator);
        mysqli_stmt_bind_param($stmt_check, "iii", $communityIdPost, $communityIdPost, $userId);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $data_check = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);

        $can_post = ($data_check && ($data_check['id_criador'] == $userId || $data_check['is_member'] == 1));

        if ($can_post) {
            if (!empty($conteudo) || (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0)) {
                
                $post_image_processed = true; // Assume sucesso ou que não há imagem

                if (isset($_FILES['imagem_post']) && $_FILES['imagem_post']['error'] == 0) {
                    $file = $_FILES['imagem_post'];
                    $uploadDir = 'uploads/posts_comunidade/'; 
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $temp_file_upload = $file['tmp_name']; // Arquivo temporário original do PHP
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $target_file = $uploadDir . uniqid('post_') . '.' . $ext;
                    
                    // Tenta redimensionar. Retorna um novo path temporário OU o path original se não redimensionar
                    $optimized_temp_path = resizeImage($temp_file_upload); 
                    
                    $success = false;

                    if ($optimized_temp_path && $optimized_temp_path != $temp_file_upload) {
                        // O arquivo foi redimensionado e é um novo arquivo temporário
                        if (rename($optimized_temp_path, $target_file)) {
                            $imagem_path = $target_file;
                            $success = true;
                        }
                        // Limpa o temporário do resize
                        if (file_exists($optimized_temp_path)) unlink($optimized_temp_path);
                        
                    } else if ($optimized_temp_path == $temp_file_upload) {
                        // Não redimensionou, então move o arquivo de upload original
                        if (move_uploaded_file($temp_file_upload, $target_file)) {
                            $imagem_path = $target_file;
                            $success = true;
                        }
                    } 
                    
                    if (!$success) {
                        $post_image_processed = false;
                        if ($optimized_temp_path === false) {
                            $postMessage = "Erro no processamento da imagem ou formato não suportado (Verifique se a biblioteca GD está ativa).";
                        } else {
                            $postMessage = "Erro ao mover/renomear o arquivo de imagem final.";
                        }
                    }
                }

                // Só insere se tiver conteúdo OU se a imagem foi processada com sucesso
                if (!empty($conteudo) || !empty($imagem_path)) {
                    $sql_insert = "INSERT INTO posts_comunidade (id_comunidade, usuario_id, conteudo, imagem) VALUES (?, ?, ?, ?)";
                    $stmt_insert = mysqli_prepare($conn, $sql_insert);
                    mysqli_stmt_bind_param($stmt_insert, "iiss", $communityIdPost, $userId, $conteudo, $imagem_path);
                    
                    if (mysqli_stmt_execute($stmt_insert)) {
                        // Após o post, recarrega a primeira página
                        header("Location: homePage.php"); 
                        exit;
                    } else {
                        $postMessage = "Erro ao criar a publicação: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                     $postMessage = "Conteúdo necessário, e a imagem falhou no upload/processamento.";
                }
                
            } else {
                 $postMessage = "Conteúdo necessário.";
            }
        } else {
             $postMessage = "Permissão negada.";
        }
    } else {
        $postMessage = "Selecione uma comunidade.";
    }
}

// --- BUSCA COMUNIDADES DO USUÁRIO (Inalterado) ---
$user_communities = [];
$sql_fetch_user_communities = "SELECT c.id, c.nome_comunidade, c.imagem FROM comunidades c JOIN membros_comunidade mc ON c.id = mc.id_comunidade WHERE mc.id_usuario = ? ORDER BY c.nome_comunidade ASC";
$stmt_comm_form = mysqli_prepare($conn, $sql_fetch_user_communities);
if ($stmt_comm_form) {
    mysqli_stmt_bind_param($stmt_comm_form, "i", $userId);
    mysqli_stmt_execute($stmt_comm_form);
    $result_comm_form = mysqli_stmt_get_result($stmt_comm_form);
    while ($row = mysqli_fetch_assoc($result_comm_form)) { $user_communities[] = $row; }
    mysqli_stmt_close($stmt_comm_form);
}

// ------------------------------------------------------------------------------------------------
// --- PAGINAÇÃO E FEED (LÓGICA CORRIGIDA) ---
// ------------------------------------------------------------------------------------------------

$posts_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $posts_per_page;
$current_page_url = "homePage.php";

$comunidades_usuario = array_column($user_communities, 'id');
$where_clause = " WHERE p.id IS NOT NULL AND p.id_comunidade IS NOT NULL ";
$bind_types = "";
$bind_params = [];

if (!empty($comunidades_usuario)) {
    // Cláusula IN para a query: e.g., '?, ?, ?'
    $ids_placeholder = implode(',', array_fill(0, count($comunidades_usuario), '?'));
    $where_clause .= " AND p.id_comunidade IN ({$ids_placeholder})";
    
    // String de tipos de vinculação: e.g., 'iii'
    $bind_types .= str_repeat('i', count($comunidades_usuario));
    
    // Parâmetros de vinculação: [id1, id2, id3, ...]
    $bind_params = array_merge($bind_params, $comunidades_usuario);
} else {
    // Se não for membro de nenhuma, garante que não haja posts
    $where_clause .= " AND 1=0 "; 
}

// Contagem total para paginação (Usa apenas os parâmetros das comunidades)
$sql_count_posts = "SELECT COUNT(p.id) AS total_posts FROM posts_comunidade p LEFT JOIN comunidades c ON p.id_comunidade = c.id {$where_clause}"; 
$stmt_count = mysqli_prepare($conn, $sql_count_posts);
$total_posts = 0;

if ($stmt_count) {
    if (!empty($bind_types)) {
        // Prepara as referências para o call_user_func_array
        $count_bind_refs = array($bind_types); 
        foreach ($bind_params as $key => $value) $count_bind_refs[] = &$bind_params[$key];
        
        // Vincula N tipos a N parâmetros (IDs de Comunidade)
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt_count), $count_bind_refs)); 
    }
    
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_posts);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
}

$total_pages = ceil($total_posts / $posts_per_page);


// Query para buscar posts (Usa os parâmetros das comunidades + LIMIT e OFFSET)
$sql_select_posts = "SELECT p.id, p.usuario_id, u.apelido, pu.foto_perfil, p.conteudo, p.imagem, p.data_criacao,
                            (SELECT COUNT(*) FROM curtidas_comunidade lc WHERE lc.id_postagem = p.id) AS likes_count,
                            (SELECT COUNT(*) FROM comentarios_comunidade cc WHERE cc.id_postagem = p.id) AS comments_count,
                            c.nome_comunidade, c.id AS comunidade_id
                     FROM posts_comunidade p 
                     JOIN usuarios u ON p.usuario_id = u.id
                     LEFT JOIN perfil_usuario pu ON u.id = pu.id
                     LEFT JOIN comunidades c ON p.id_comunidade = c.id
                     {$where_clause}
                     GROUP BY p.id
                     ORDER BY p.data_criacao DESC
                     LIMIT ? OFFSET ?";
                     
// Adiciona os tipos e valores de LIMIT e OFFSET
$bind_types .= 'ii'; 
$bind_params[] = $posts_per_page;
$bind_params[] = $offset;

$stmt_posts = mysqli_prepare($conn, $sql_select_posts);
$result_posts = false;
if ($stmt_posts) {
    $bind_refs = array($bind_types);
    foreach ($bind_params as $key => $value) $bind_refs[] = &$bind_params[$key];
    
    // Vincula (N + 2) tipos a (N + 2) parâmetros (IDs de Comunidade, posts_per_page, offset)
    call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt_posts), $bind_refs));
    mysqli_stmt_execute($stmt_posts);
    $result_posts = mysqli_stmt_get_result($stmt_posts);
    mysqli_stmt_close($stmt_posts);
}


// --- FUNÇÃO DISPLAY POST (AGORA COM BOTÕES DE EXCLUSÃO) ---
function check_if_user_liked($conn, $postId, $userId) {
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
    global $conn, $userId, $default_photo; 
    $is_liked = check_if_user_liked($conn, $post['id'], $userId);
    $post_id = $post['id'];
    $post_owner_id = $post['usuario_id']; // <-- Dono do post
    $is_post_owner = ($userId == $post_owner_id); // <-- Check se é o dono

    $comunidade_html = !empty($post['nome_comunidade']) ? "<span class='post-community-link' data-comunidade-id='{$post['comunidade_id']}'>em <a href='comunidade.php?id={$post['comunidade_id']}'>{$post['nome_comunidade']}</a></span>" : '';
    $img_html = $post['imagem'] ? "<img src='{$post['imagem']}' style='max-width:50vh; max-weight:50vh' alt='Imagem do post' class='post-image img-fluid rounded mt-2'>" : '';
    $like_icon_style = $is_liked ? "fa-solid text-danger" : "fa-regular";
    $like_data = $is_liked ? 'true' : 'false';

    // ADIÇÃO MÍNIMA 5: Define o SRC da foto do post
    $post_photo_src = htmlspecialchars($post['foto_perfil'] ?? $default_photo);
    
    // NOVO: Botão de Excluir Post
    $delete_post_btn = '';
    if ($is_post_owner) {
        $delete_post_btn = "
            <button class='btn-action btn-delete-post ms-auto' data-post-id='{$post_id}' title='Excluir Postagem' style='color: #cc0000; font-size: 1.1em;'>
                <i class='fa-solid fa-trash-alt'></i> 
            </button>";
    }

    // HTML do Cartão
    $html = "
    <div class='card post-card shadow-sm mb-4 bg-white' data-post-id='{$post_id}'>
        <div class='post-header'>
            <img src='{$post_photo_src}' alt='Avatar' class='post-avatar'>
            <div class='post-info'>
                <span class='post-author'><a href='perfil.php?id={$post_owner_id}'>{$post['apelido']}</a></span>
                {$comunidade_html}
                <span class='post-time'>" . time_ago($post['data_criacao']) . "</span>
            </div>
            {$delete_post_btn} </div>
        <div class='post-content'>
            <p>" . nl2br(htmlspecialchars($post['conteudo'])) . "</p>
            {$img_html}
        </div>
        <div class='post-actions border-top pt-2 mt-2'>
            <button class='btn-action btn-like-post' data-post-id='{$post_id}' data-liked='{$like_data}'>
                <i class='fa-heart {$like_icon_style}'></i> 
                Curtidas (<span class='like-count'>{$post['likes_count']}</span>)
            </button>
            <button class='btn-action btn-comment' data-post-id='{$post_id}'>
                <i class='fa-solid fa-comment'></i> Comentários (<span class='comment-count'>{$post['comments_count']}</span>)
            </button>
        </div>
        <div class='comments-section' id='comments-{$post_id}'>
            <div class='comments-list' id='comments-list-{$post_id}'>";

    // ALTERAÇÃO: Adiciona c.id (ID do comentário) e u.id (ID do autor) na query de comentários
    $sql_comments = "SELECT c.id, c.conteudo, c.data_criacao, u.apelido, pu.foto_perfil, u.id AS commenter_id FROM comentarios_comunidade c JOIN usuarios u ON c.id_usuario = u.id LEFT JOIN perfil_usuario pu ON u.id = pu.id WHERE c.id_postagem = ? ORDER BY c.data_criacao ASC";
    $stmt_comments = mysqli_prepare($conn, $sql_comments);
    $has_comments = false;
    if ($stmt_comments) {
        mysqli_stmt_bind_param($stmt_comments, "i", $post_id);
        mysqli_stmt_execute($stmt_comments);
        $result_comments = mysqli_stmt_get_result($stmt_comments);
        while ($comment = mysqli_fetch_assoc($result_comments)) {
            $has_comments = true;
            $comment_id = $comment['id'];
            $commenter_id = $comment['commenter_id'];
            // Permissão para excluir: Dono do post OU Dono do comentário
            $can_delete_comment = ($userId == $commenter_id || $is_post_owner); 

            // ADIÇÃO MÍNIMA 7: Define o SRC da foto do comentário
            $comment_photo_src = htmlspecialchars($comment['foto_perfil'] ?? $default_photo);
            
            // NOVO: Botão de Excluir Comentário
            $delete_comment_btn = '';
            if ($can_delete_comment) {
                $delete_comment_btn = "
                    <button class='btn-delete-comment ms-auto' data-comment-id='{$comment_id}' title='Excluir Comentário'>
                        <i class='fas fa-times-circle'></i> 
                    </button>";
            }


            $html .= "
                <div class='comment-item border-bottom pb-2 mb-2' id='comment-{$comment_id}'>
                    <div class='d-flex align-items-center mb-1'>
                        <img src='{$comment_photo_src}' alt='Foto' class='rounded-circle me-2' style='width: 30px; height: 30px; object-fit: cover;'>
                        <strong class='me-2'><a href='perfil.php?id={$commenter_id}'>" . htmlspecialchars($comment['apelido']) . "</a></strong>
                        <small class='text-muted'>" . time_ago($comment['data_criacao']) . "</small>
                        {$delete_comment_btn}
                    </div>
                    <p class='mb-0 ms-4'>" . nl2br(htmlspecialchars($comment['conteudo'])). "</p>
                </div>";
        }
        mysqli_stmt_close($stmt_comments);
    }

    if (!$has_comments) {
        $html .= "<div class='no-comments-message text-center p-3' id='no-comments-message-{$post_id}'>Nenhum comentário ainda. Seja o primeiro!</div>";
    }

    $html .= "</div>
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

    return $html;
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
        /* Estilos de Preferência */
        body {
            background-color: <?php echo htmlspecialchars($prefs['cor_fundo_pref']); ?>;
            color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>;
            font-size: <?php echo htmlspecialchars($prefs['tamanho_fonte_pref']); ?>;
            font-family: <?php echo htmlspecialchars($prefs['fonte_preferida']); ?>;
        }
        .card, .navigation, .member-list { background-color: #ffffff !important; }
        .post-author, .post-community-link a, .post-content p, .comments-list .comment-content { color: <?php echo htmlspecialchars($prefs['cor_texto_pref']); ?>; }
        .text-danger { color: #dc3545 !important; }

        /* Estilos do Feed */
        .post-avatar { width: 50px; height: 50px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
        .comments-section .comment-item img { object-fit: cover; }
        .post-form-card { border: 1px solid #ddd; }
        .post-text-area { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 10px; resize: vertical; }
        .post-image-preview-wrapper { margin-top: 10px; margin-bottom: 10px; max-width: 100%; overflow: hidden; border-radius: 8px; border: 1px solid #eee; }
        .post-image-preview { width: 100%; height: auto; display: block; }
        .btn-primary, .btn-primary-2 { background-color: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
        .btn-primary:hover, .btn-primary-2:hover { background-color: #2980b9; }
        .btn-action { background: none; border: none; color: #34495e; padding: 5px 10px; cursor: pointer; font-size: 0.9em; transition: color 0.2s; }
        .btn-action:hover { color: #555; }
        .comments-section { border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px; display: none; }
        .comments-section.active { display: block; }
        .comment-item { padding: 8px 0; }
        .comment-form-ajax .input-group { display: flex; }
        .comment-form-ajax .form-control { flex: 1 1 auto; margin-right: 5px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; }
        .comment-form-ajax .btn-primary { background-color: #3498db; color: white; border: none; border-radius: 4px; padding: 5px 10px; cursor: pointer; }
        .post-card { border: none; }
        .post-header { display: flex; align-items: center; padding: 15px; padding-bottom: 10px; }
        .post-info { display: flex; flex-direction: column; }
        .post-author { font-weight: bold; }
        .post-time, .post-community-link { font-size: 0.85em; color: #7f8c8d; }
        .post-content { padding: 0 15px 15px 15px; }
        .post-image { max-width: 100%; height: auto; border-radius: 8px; }
        .post-actions { padding: 0 15px 10px 15px; }
        .member-list { border: 1px solid #eee; }
        .member-list h5 { padding-bottom: 10px; border-bottom: 1px solid #eee; margin-bottom: 10px; color: #34495e; font-weight: bold; }
        .member-list li a:hover strong { color: #3498db; }
        .btn-refresh { position: fixed; bottom: 20px; right: 20px; padding: 10px 15px; background-color: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; font-size: 1em; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); transition: background-color 0.3s; }
        .btn-refresh:hover { background-color: #2980b9; }

        /* NOVO: Estilos do Botão de Excluir Comentário */
        .btn-delete-comment {
            background: none;
            border: none;
            color: #cc0000;
            cursor: pointer;
            font-size: 0.9em;
            padding: 0 5px;
            opacity: 0.6;
            transition: opacity 0.2s, color 0.2s;
        }
        .btn-delete-comment:hover {
            opacity: 1;
            color: #ff0000;
        }

        /* NOVO: Estilos da Paginação */
        .pagination-controls {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .page-link {
            text-decoration: none;
            color: #3498db;
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
            background-color: #3498db;
            color: white;
            border-color: #3498db;
            font-weight: bold;
        }
        .page-dots {
            padding: 8px 4px;
            color: #999;
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

                        <?php if (!empty($postMessage)): ?>
                            <small class="text-danger mt-2 d-block"><?php echo htmlspecialchars($postMessage); ?></small>
                        <?php endif; ?>
                    </form>
                </div>

                <section class="feed-container">
                    <h2 class="feed-section-title mb-4" style="font-size: 1.25em;"><i class="fas fa-list-ul"></i> Publicações das Minhas Comunidades (Página <?= $page ?> de <?= $total_pages ?>)</h2>
                    <?php 
                    $post_count = 0;
                    if ($result_posts && mysqli_num_rows($result_posts) > 0) {
                        while ($post = mysqli_fetch_assoc($result_posts)) {
                            echo display_post_card($post);
                            $post_count++;
                        }
                    }
                    if ($post_count == 0 && $page == 1): ?>
                        <div class='alert alert-info text-center shadow-sm rounded' role='alert'>
                            Nenhuma publicação encontrada no seu feed. Junte-se ou crie uma comunidade para começar a ver postagens!
                        </div>
                    <?php elseif ($post_count == 0 && $page > 1): ?>
                        <div class='alert alert-info text-center shadow-sm rounded' role='alert'>
                            Nenhuma publicação encontrada nesta página.
                        </div>
                    <?php endif; ?>
                    
                    <div class="pagination d-flex justify-content-center my-4">
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-controls">
                                <?php 
                                $baseUrl = "homePage.php";
                                $pageRange = 2; // Número de páginas a mostrar antes e depois da atual
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl . "?page=" . ($page - 1) ?>" class="page-link">&laquo; Anterior</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php 
                                    if ($i == 1 || $i == $total_pages || ($i >= $page - $pageRange && $i <= $page + $pageRange)):
                                    ?>
                                        <a href="<?= $baseUrl . "?page=" . $i ?>" class="page-link <?= ($i == $page ? 'active' : '') ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php 
                                    elseif ($i == $page - $pageRange - 1 || $i == $page + $pageRange + 1):
                                    ?>
                                        <span class="page-dots">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= $baseUrl . "?page=" . ($page + 1) ?>" class="page-link">Próximo &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    </section>
            </div> 
            
            <div class="col-md-4">
                <div class="member-list shadow-sm p-3 rounded bg-white sticky-top" style="top: 20px;">
                    <h5 class="text-primary"></h5>
                    <?php if (empty($user_communities)): ?>
                        <div class="alert alert-warning mb-3">Você não é membro de nenhuma comunidade.</div>
                        <br>
                        <a href="comunidades.php" class="btn btn-sm btn-primary-2 w-100">Encontrar Comunidades</a>
                        <br><br>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>


<script>
    document.addEventListener('DOMContentLoaded', function() {
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

                fetch('homePage.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        likeCountSpan.textContent = data.new_count;
                        if (data.status === 'liked') {
                            button.setAttribute('data-liked', 'true');
                            heartIcon.classList.remove('fa-regular');
                            heartIcon.classList.add('fa-solid', 'text-danger');
                        } else {
                            button.setAttribute('data-liked', 'false');
                            heartIcon.classList.remove('fa-solid', 'text-danger');
                            heartIcon.classList.add('fa-regular');
                        }
                    } else { alert('Erro ao processar curtida.'); }
                })
                .catch(error => console.error('Erro:', error));
            });
        });

        // 2. Lógica de Comentar Post (AJAX) 
        document.querySelectorAll('.comment-form-ajax').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                const formData = new FormData(this);

                fetch('homePage.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const commentsList = document.getElementById(`comments-list-${postId}`);
                        const noCommentsMessage = document.getElementById(`no-comments-message-${postId}`);
                        const commentButton = document.querySelector(`.btn-comment[data-post-id='${postId}']`);
                        const commentCountElement = commentButton.querySelector('.comment-count');
                        
                        if (noCommentsMessage) noCommentsMessage.remove();
                        // Adiciona o novo comentário ao final da lista (ou onde você preferir)
                        commentsList.insertAdjacentHTML('beforeend', data.new_comment_html); 
                        this.querySelector('textarea[name="comment_text"]').value = ''; 
                        if (data.new_count !== undefined) commentCountElement.textContent = data.new_count;
                    } else { alert('Erro ao comentar.'); }
                })
                .catch(error => console.error('Erro:', error));
            });
        });

        // 3. Mostrar/Esconder Comentários
        document.querySelectorAll('.btn-comment').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                document.getElementById(`comments-${postId}`).classList.toggle('active'); 
            });
        });

        // 4. Lógica de Excluir Comentário (AJAX) - NOVO
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-delete-comment')) {
                const deleteButton = e.target.closest('.btn-delete-comment');
                const commentId = deleteButton.getAttribute('data-comment-id');
                
                if (!confirm('Tem certeza que deseja excluir este comentário?')) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'delete_comment_post');
                formData.append('comment_id', commentId);

                fetch('homePage.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const commentElement = document.getElementById(`comment-${commentId}`);
                        if(commentElement) commentElement.remove();
                        
                        if (data.post_id && data.new_count !== undefined) {
                            const commentButton = document.querySelector(`.btn-comment[data-post-id='${data.post_id}']`);
                            const commentCountElement = commentButton.querySelector('.comment-count');
                            commentCountElement.textContent = data.new_count;
                        }

                        // Se a lista ficou vazia, adiciona a mensagem padrão
                        const commentsList = document.getElementById(`comments-list-${data.post_id}`);
                        if (commentsList && commentsList.children.length === 0) {
                            commentsList.innerHTML = `<div class='no-comments-message text-center p-3' id='no-comments-message-${data.post_id}'>Nenhum comentário ainda. Seja o primeiro!</div>`;
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


        // 5. Lógica de Excluir Post (AJAX) - NOVO
        document.querySelectorAll('.btn-delete-post').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                
                if (!confirm('ATENÇÃO: Você tem certeza que deseja excluir esta postagem? Todos os comentários e curtidas serão perdidos permanentemente.')) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'delete_post');
                formData.append('post_id', postId);

                fetch('homePage.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Postagem excluída com sucesso! Recarregando a página.');
                        // Recarrega a página para garantir que a paginação seja re-renderizada corretamente
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
        
        // 6. Preview Imagem (Inalterado)
        document.getElementById('imagem_post').addEventListener('change', function(e) {
            let previewContainerWrapper = document.querySelector('.post-form-card .post-image-preview-wrapper');
            previewContainerWrapper.innerHTML = '';
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImage = document.createElement('img');
                    previewImage.src = e.target.result;
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