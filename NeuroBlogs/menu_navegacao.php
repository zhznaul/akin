<?php
// PHP - Arquivo: menu_navegacao.php
// ... [código de definição de variáveis]
$userName = $userName ?? 'Usuário';
$userId = $userId ?? 0;
?>

<div class="main-content">
    
    <nav class="navigation">
        <ul class="nav-list">
            <li><a href="homePage.php?view=all" title="Comunidades"><i class="fa-solid fa-house"></i></a></li>
            <li><a href="comunidades.php" title="Minhas Comunidades"><i class="fa-solid fa-users"></i></a></li>
            <li><a href="perfil.php" title="Meu Perfil"><i class="fa-solid fa-user"></i></a></li>
            <li><a href="logout.php" title="Sair"><i class="fa-solid fa-right-from-bracket"></i></a></li>
        </ul>
    </nav>