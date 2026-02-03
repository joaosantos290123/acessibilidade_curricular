<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "coordenador") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  header("Location: usuarios.php?erro=Usu%C3%A1rio%20inv%C3%A1lido.");
  exit;
}

// Buscar usuário
$stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
  header("Location: usuarios.php?erro=Usu%C3%A1rio%20n%C3%A3o%20encontrado.");
  exit;
}

$u = $res->fetch_assoc();
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $senha = $_POST["senha"] ?? "";
  $senha2 = $_POST["senha2"] ?? "";

  if (strlen($senha) < 6) {
    $erro = "A senha deve ter pelo menos 6 caracteres.";
  } elseif ($senha !== $senha2) {
    $erro = "As senhas não conferem.";
  } else {
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $up = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $up->bind_param("si", $hash, $id);

    if ($up->execute()) {
      header("Location: ../usuarios.php?msg=Senha%20redefinida%20com%20sucesso.");
      exit;
    } else {
      $erro = "Não foi possível redefinir a senha.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir Senha</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<main class="container" style="max-width: 700px;">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="m-0">Redefinir Senha</h1>
      <p class="sub m-0">
        <?php echo htmlspecialchars($u["nome"]); ?> — <?php echo htmlspecialchars($u["email"]); ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary" href="usuarios.php">Voltar</a>
    </div>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger" role="alert">
      <?php echo htmlspecialchars($erro); ?>
    </div>
  <?php endif; ?>

  <div class="card p-3 p-md-4" style="border:1px solid #CBD5E1; border-radius: 16px;">
    <form method="POST" class="row g-3">

      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="senha">Nova senha</label>
        <input class="form-control" id="senha" name="senha" type="password" required placeholder="mínimo 6 caracteres">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="senha2">Confirmar senha</label>
        <input class="form-control" id="senha2" name="senha2" type="password" required placeholder="repita a senha">
      </div>

      <div class="col-12 d-grid mt-2">
        <button class="btn btn-primary btn-lg" type="submit">Salvar nova senha</button>
      </div>

    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
