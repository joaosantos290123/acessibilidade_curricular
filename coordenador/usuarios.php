<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "coordenador") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

function normalize($s) {
  $s = mb_strtolower($s ?? "", "UTF-8");
  $map = [
    'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
    'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
    'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
    'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
    'ç'=>'c'
  ];
  return strtr($s, $map);
}

$busca = trim($_GET["q"] ?? "");
$msg  = $_GET["msg"] ?? "";
$erro = $_GET["erro"] ?? "";

// SELECT com perfil dinâmico (sem usuarios.tipo)
$sqlBase = "
  SELECT 
    u.id,
    u.nome,
    u.email,
    CASE
      WHEN c.usuario_id IS NOT NULL THEN 'Coordenador'
      WHEN p.usuario_id IS NOT NULL THEN 'Professor'
      WHEN a.usuario_id IS NOT NULL THEN 'Aluno'
      ELSE 'Sem perfil'
    END AS perfil
  FROM usuarios u
  LEFT JOIN coordenadores c ON c.usuario_id = u.id
  LEFT JOIN professores   p ON p.usuario_id = u.id
  LEFT JOIN alunos        a ON a.usuario_id = u.id
 WHERE c.usuario_id IS NOT NULL
     OR p.usuario_id IS NOT NULL
     OR a.usuario_id IS NOT NULL
";

// Sempre busca tudo no SQL (mais estável) e filtra no PHP (sem acento)
$stmt = $conn->prepare($sqlBase . " ORDER BY u.nome");
$stmt->execute();
$usuarios = $stmt->get_result();

$buscaNorm = normalize($busca);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gerenciar Usuários</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<main class="container" style="max-width: 900px;">

  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($msg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>

  <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($erro); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>

  <div class="topbar">
    <div>
      <h1>Gerenciar Usuários</h1>
      <p class="sub">Crie, edite perfil, redefina senha e remova usuários.</p>
    </div>

    <div class="actions">
      <a class="btn-sm" href="user/criar_usuario.php">+ Novo usuário</a>
      <a class="btn-sm" href="painel.php">Voltar</a>
      <a class="btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </div>

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

      <?php
      $achou = 0;
      while ($u = $usuarios->fetch_assoc()):
        // filtro sem acento (João = Joao)
        if ($busca !== "") {
          $nomeNorm  = normalize($u["nome"]);
          $emailNorm = normalize($u["email"]);
          if (strpos($nomeNorm, $buscaNorm) === false && strpos($emailNorm, $buscaNorm) === false) {
            continue;
          }
        }
        $achou++;
      ?>
        <tr>
          <td><?php echo htmlspecialchars($u["nome"]); ?></td>
          <td><?php echo htmlspecialchars($u["email"]); ?></td>
          <td><?php echo htmlspecialchars($u["perfil"]); ?></td>
          <td>
            <div class="actions">
              <a class="btn-sm" href="user/editar_usuario.php?id=<?php echo (int)$u["id"]; ?>">Editar</a>
              <a class="btn-sm" href="user/redefinir_senha.php?id=<?php echo (int)$u["id"]; ?>">Senha</a>

              <?php if ((int)$u["id"] !== (int)$_SESSION["usuario_id"]): ?>
                <a class="btn-sm btn-danger"
                   href="user/excluir_usuario.php?id=<?php echo (int)$u["id"]; ?>"
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

      <?php if ($achou === 0): ?>
        <tr>
          <td colspan="4" style="text-align:center; padding:16px; color:#64748B;">
            Nenhum usuário foi encontrado.
          </td>
        </tr>
      <?php endif; ?>

      </tbody>
    </table>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
