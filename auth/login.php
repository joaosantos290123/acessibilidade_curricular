<?php
require_once "../config/db.php";
session_start();
if (isset($_SESSION["usuario_id"])) {
    if ($_SESSION["tipo"] === "aluno") {
        header("Location: ../aluno/painel.php");
        exit;
    } elseif ($_SESSION["tipo"] === "professor") {
        header("Location: ../professor/painel.php");
        exit;
    } elseif ($_SESSION["tipo"] === "coordenador") {
        header("Location: ../coordenador/painel.php");
        exit;
    }
}

$erro = $_GET["erro"] ?? "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="../assets/css/css.css">
</head>
<body>
  <main class="container">
    <h1>Entrar</h1>
    <p class="sub">Acesse com seu e-mail e senha.</p>

    <?php if ($erro): ?>
      <div class="alert"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form class="card" action="processar_login.php" method="POST">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required placeholder="exemplo@email.com">

      <label for="senha">Senha</label>
      <div class="senha-wrap">
        <input id="senha" name="senha" type="password" required placeholder="Sua senha">
        <button type="button" class="btn-ghost" onclick="toggleSenha()">Mostrar</button>
      </div>

      <button class="btn" type="submit">Entrar</button>

     <p class="link">
  Acesso restrito a alunos e professores cadastrados pela instituição. Para se cadastrar, entre em contato com a coordenação.
</p>

    </form>
  </main>

  <script src="../assets/js/script.js"></script>
</body>
</html>
