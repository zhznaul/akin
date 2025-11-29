<?php
include "conexao.php";

session_start();
$nivel_admin = 3; 

if (!isset($_SESSION['usuario']) || $_SESSION['nivel'] != $nivel_admin) {
    header("Location: login.php");
    exit;
}

// 1. LÓGICA DO BUSCADOR: RECEBE E TRATA OS TERMOS DE BUSCA SEPARADAMENTE
$search_id = isset($_GET['search_id']) ? mysqli_real_escape_string($conn, trim($_GET['search_id'])) : '';
$search_text = isset($_GET['search_text']) ? mysqli_real_escape_string($conn, trim($_GET['search_text'])) : '';
$search_nivel = isset($_GET['search_nivel']) ? mysqli_real_escape_string($conn, trim($_GET['search_nivel'])) : '';

// ALTERADO: Troca 'nome' por 'apelido' na consulta SELECT
$sql = "SELECT id, apelido, email, nivel FROM usuarios";
$where_clauses = [];

// A. Filtro por ID (Permite ID 0)
if ($search_id !== '') {
    if ($search_id === '0') {
        $where_clauses[] = "id = 0";
    } else {
        $where_clauses[] = "id LIKE '%$search_id%'";
    }
}

// B. Filtro por Apelido ou E-mail (Case-Insensitive)
if ($search_text !== '') {
    // ALTERADO: Troca 'nome' por 'apelido' na cláusula WHERE de busca
    $where_clauses[] = "(BINARY apelido LIKE '%$search_text%' OR BINARY email LIKE '%$search_text%')";
}

// C. Filtro por Nível (Permite Nível 0)
if ($search_nivel !== '') {
    if ($search_nivel === '0') {
        $where_clauses[] = "nivel = 0";
    } else {
        $where_clauses[] = "CAST(nivel AS CHAR) LIKE '%$search_nivel%'";
    }
}

// Combina as cláusulas com AND
if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Ordena os resultados
$sql .= " ORDER BY id DESC";

$resultado = mysqli_query($conn, $sql);

// Verifica se há alguma busca ativa (reconhece '0')
$is_searching = ($search_id !== '') || ($search_text !== '') || ($search_nivel !== '');
?>

<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <title>Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="painel.css">
  </head>
  <body class="conteiner py-5">
    <div class="container">
        <h1>Bem-Vindo ao Painel, <?= $_SESSION['usuario' ]?>!</h1><br>
    
        <h3>Gerenciamento de Usuários</h3>
        
        <div class="d-flex justify-content-between mb-3">
            <div>
                <a href="logout.php" class="btn btn-danger">Sair</a>
                <a href="registrar.php" class="btn btn-primary">Novo Usuário</a>
            </div>
        </div>
        
        <?php 
        if (isset($_GET['sucesso']) && $_GET['sucesso'] == 'excluido') {
            echo '<div class="alert alert-success">Usuário excluído com sucesso!</div>';
        }
        if (isset($_GET['erro'])) {
            $msg = "Erro ao processar a requisição.";
            if ($_GET['erro'] == 'autoexclusao') {
                $msg = "Você não pode excluir a sua própria conta de administrador!";
            }
             if ($_GET['erro'] == 'falha_exclusao') {
                $msg = "Falha ao excluir usuário. Verifique as permissões do banco de dados.";
            }
            echo '<div class="alert alert-danger">' . $msg . '</div>';
        }
        ?>

        <form id="search-form" method="GET" class="mb-4">
            <div class="row g-2">
                <div class="col-md-2">
                    <input type="text" name="search_id" id="search-id-input" class="form-control" placeholder="Buscar por ID" 
                           value="<?= htmlspecialchars($search_id) ?>">
                </div>
                <div class="col-md-5">
                    <input type="text" name="search_text" id="search-text-input" class="form-control" placeholder="Buscar por Apelido ou E-mail" 
                           value="<?= htmlspecialchars($search_text) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="search_nivel" id="search-nivel-input" class="form-control" placeholder="Buscar por Nível" 
                           value="<?= htmlspecialchars($search_nivel) ?>">
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary w-100 me-2">Buscar</button>
                    <?php if ($is_searching): ?>
                        <button type="button" class="btn btn-secondary" onclick="clearAllSearchFields()">Limpar</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <table class="table table-striped table-bordered">
            <thead>
                <tr><th>ID</th><th>Apelido</th><th>E-mail</th><th>Nível</th><th>Ações</th></tr>
            </thead>
            <tbody>
            <?php
            if (mysqli_num_rows($resultado) > 0) {
                while ($linha = mysqli_fetch_assoc($resultado)) {
                    // ALTERADO: Troca $linha['nome'] por $linha['apelido']
                    echo "<tr>
                            <td>{$linha['id']}</td>
                            <td>{$linha['apelido']}</td>
                            <td>{$linha['email']}</td> 
                            <td>{$linha['nivel']}</td>
                            <td>
                              <a href='editar.php?id={$linha['id']}' class='btn btn-warning btn-sm'>Editar</a>
                              <a href='excluir.php?id={$linha['id']}' class='btn btn-danger btn-sm' 
                                onclick=\"return confirm('ATENÇÃO: Tem certeza que deseja excluir o usuário {$linha['apelido']}? Esta ação é IRREVERSÍVEL!');\">Excluir</a>
                            </td>
                          </tr>";
                }
            } else {
                $colspan = 5;
                if ($is_searching) {
                     echo "<tr><td colspan='{$colspan}' class='text-center'>Nenhum usuário encontrado com os filtros aplicados.</td></tr>";
                } else {
                    echo "<tr><td colspan='{$colspan}' class='text-center'>Nenhum usuário encontrado.</td></tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function clearAllSearchFields() {
        // Limpa o valor de cada campo de busca
        document.getElementById('search-id-input').value = '';
        document.getElementById('search-text-input').value = '';
        document.getElementById('search-nivel-input').value = '';
        
        // Redireciona para painel.php, limpando todos os parâmetros GET da URL
        window.location.href = 'painel.php'; 
    }
    </script>
  </body>
</html>