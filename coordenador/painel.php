<?php
session_start();
if (!isset($_SESSION["usuario_id"]) || $_SESSION["tipo"] !== "coordenador") {
    header("Location: ../auth/login.php?erro=Acesso%20restrito.");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel do Coordenador</title>
  <link rel="stylesheet" href="../assets/css/css.css">
</head>
<body>

<main class="container">
  <h1>Painel do Coordenador</h1>
  <p>Olá, <?php echo htmlspecialchars($_SESSION["nome"]); ?>.</p>

  <div class="card">
  <div class="btn-group">
    <a class="btn" href="usuarios.php">Gerenciar usuários</a>
    <a class="btn" href="../professor/painel.php">Visualizar necessidades</a>
    <a class="btn" href="../auth/logout.php">Sair</a>
  </div>
</div>

</main>

</body>
</html>
