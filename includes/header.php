<?php

require_once __DIR__ . "/auth.php";

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">


<title>
Portal Aluno
</title>


</head>


<body>


<header>

<h1>
Portal Aluno
</h1>


<nav>

<?php if(usuarioLogado()): ?>


<span>
Olá,
<?= htmlspecialchars($_SESSION["nome"]) ?>
</span>


<a href="../login/logout.php">
Sair
</a>


<?php endif; ?>


</nav>


</header>