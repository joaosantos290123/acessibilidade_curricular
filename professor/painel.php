<?php
session_start();
if (!isset($_SESSION["usuario_id"]) || $_SESSION["tipo"] !== "professor") {
    header("Location: ../auth/login.php?erro=Acesso%20negado.");
    exit;
}
?>
<h1>Painel do Professor</h1>
<p>Olá, <?php echo htmlspecialchars($_SESSION["nome"]); ?>!</p>
<a href="../auth/logout.php">Sair</a>
