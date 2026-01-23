<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || $_SESSION["tipo"] !== "coordenador") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$busca = trim($_GET["q"] ?? "");

// Busca usuários
if ($busca !== "") {
  $like = "%{$busca}%";
  $stmt = $conn->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE nome LIKE ? OR email LIKE ? ORDER BY nome");
  $stmt->bind_param("ss", $like, $like);
} else {
  $stmt = $conn->prepare("SELECT id, nome, email, tipo FROM usuarios ORDER BY nome");
}
$stmt->execute();
$usuarios = $stmt->get_result();

$msg = $_GET["msg"] ?? "";
$erro = $_GET["erro"] ?? "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gerenciar Usuários</title>
  <link rel="stylesheet" href="../assets/css/css.css">
  <style>
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom: 16px; }
    .topbar a { text-decoration:none; }
    .table-wrap { overflow:auto; }
    table { width:100%; border-collapse: collapse; background:#fff; border:1px solid #CBD5E1; border-radius: 12px; overflow:hidden; }
    th, td { padding: 10px; border-bottom: 1px solid #E2E8F0; text-align:left; }
    th { background: #EFF6FF; color:#1E3A8A; }
    .actions { display:flex; gap:8px; flex-wrap: wrap; }
    .btn-sm { padding: 8px 10px; border-radius: 10px; border:1px solid #94A3B8; background:#fff; color:#1E3A8A; cursor:pointer; text-decoration:none; font-size: 14px; }
    .btn-sm:hover { background:#EFF6FF; }
    .btn-danger { border-color:#FCA5A5; color:#991B1B; }
    .btn-danger:hover { background:#FEE2E2; }
    .search { display:flex; gap:8px; align-items:center; margin: 12px 0 16px; }
    .search input { margin:0; }
  </style>
</head>
<body>
<main class="container" style="max-width: 900px;">
  <div class="topbar">
    <div>
      <h1>Gerenciar Usuários</h1>
      <p class="sub">Crie, edite perfil, redefina senha e remova usuários.</p>
    </div>
    <div class="actions">
      <a class="btn-sm" href="criar_usuario.php">+ Novo usuário</a>
      <a class="btn-sm" href="painel.php">Voltar</a>
      <a class="btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>
  <?php if ($erro): ?>
    <div class="alert" style="border-color:#FCA5A5; background:#FEE2E2; color:#991B1B;">
      <?php echo htmlspecialchars($erro); ?>
    </div>
  <?php endif; ?>

  <form class="search" method="GET">
    <input type="text" name="q" placeholder="Buscar por nome ou e-mail" value="<?php echo htmlspecialchars($busca); ?>">
    <button class="btn-sm" type="submit">Buscar</button>
    <?php if ($busca !== ""): ?>
      <a class="btn-sm" href="usuarios.php">Limpar</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Perfil</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($u = $usuarios->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($u["nome"]); ?></td>
          <td><?php echo htmlspecialchars($u["email"]); ?></td>
          <td><?php echo htmlspecialchars($u["tipo"]); ?></td>
          <td>
            <div class="actions">
              <a class="btn-sm" href="editar_usuario.php?id=<?php echo (int)$u["id"]; ?>">Editar</a>
              <a class="btn-sm" href="redefinir_senha.php?id=<?php echo (int)$u["id"]; ?>">Senha</a>

              <?php if ((int)$u["id"] !== (int)$_SESSION["usuario_id"]): ?>
                <a class="btn-sm btn-danger"
                   href="excluir_usuario.php?id=<?php echo (int)$u["id"]; ?>"
                   onclick="return confirm('Tem certeza que deseja excluir este usuário?');">
                   Excluir
                </a>
              <?php else: ?>
                <span class="btn-sm" style="opacity:.5; cursor:not-allowed;">Excluir</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
