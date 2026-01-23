<?php
session_start();
if (!isset($_SESSION["usuario_id"]) || $_SESSION["tipo"] !== "aluno") {
    header("Location: ../auth/login.php?erro=Acesso%20negado.");
    exit;
}
?>
<h1>Painel do Aluno</h1>
<p>Olá, <?php echo htmlspecialchars($_SESSION["nome"]); ?>!</p>
<a href="../auth/logout.php">Sair</a>
