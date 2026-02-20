<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "professor") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$usuario_id = (int)$_SESSION["usuario_id"];

$stmt = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();

$nome = $prof["nome"] ?? "Professor";
$email = $prof["email"] ?? "";

// total necessidades
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM necessidades");
$stmt->execute();
$totalNec = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);

// necessidades últimos 7 dias
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM necessidades WHERE data_registro >= (NOW() - INTERVAL 7 DAY)");
$stmt->execute();
$rec7 = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);

function initials($name) {
  $name = trim($name ?? "");
  if ($name === "") return "P";
  $parts = preg_split("/\s+/", $name);
  $a = mb_substr($parts[0] ?? "P", 0, 1, "UTF-8");
  $b = mb_substr($parts[count($parts)-1] ?? "", 0, 1, "UTF-8");
  return mb_strtoupper($a . $b, "UTF-8");
}
$ini = initials($nome);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Painel do Professor</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="app-body">
<main class="dash">

  <!-- Header -->
  <header class="dash-header">
    <div class="dash-brand">
      <div class="avatar"><?php echo htmlspecialchars($ini); ?></div>
      <div class="brand-text">
        <div class="brand-title">Painel do Professor</div>
        <div class="brand-sub">
          <?php echo htmlspecialchars($nome); ?>
          <?php if ($email): ?>
            <span class="sep">•</span><?php echo htmlspecialchars($email); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="dash-actions">
      <a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </header>

  <!-- Cards resumo -->
  <section class="dash-grid mt-3">
    <div class="dash-card">
      <div class="card-top">
        <div>
          <div class="card-kicker">Necessidades registradas</div>
          <div class="card-value"><?php echo (int)$totalNec; ?></div>
        </div>
        <div class="card-icon">📝</div>
      </div>
      <div class="card-foot text-muted small">Total no sistema</div>
    </div>

    <div class="dash-card">
      <div class="card-top">
        <div>
          <div class="card-kicker">Recentes</div>
          <div class="card-value"><?php echo (int)$rec7; ?></div>
        </div>
        <div class="card-icon">⏱️</div>
      </div>
      <div class="card-foot text-muted small">Últimos 7 dias</div>
    </div>
  </section>

  <!-- Ações -->
  <section class="dash-actions-grid mt-3">

    <a class="action-card primary" href="necessidades.php">
      <div class="action-icon">👥</div>
      <div class="action-title">Necessidades dos alunos</div>
      <div class="action-sub">Consultar e registrar necessidades pedagógicas.</div>
    </a>

    <a class="action-card" href="necessidades.php#criar">
      <div class="action-icon">➕</div>
      <div class="action-title">Criar nova necessidade</div>
      <div class="action-sub">Adicionar um registro para um aluno (atividade/apoio necessário).</div>
    </a>

    <a class="action-card" href="acessibilidades.php">
      <div class="action-icon">📚</div>
      <div class="action-title">Conteúdos e acessibilidades</div>
      <div class="action-sub">Boas práticas e exemplos de adaptações curriculares.</div>
    </a>

    <div class="action-card note">
      <div class="action-icon">🎯</div>
      <div class="action-title">Foco do professor</div>
      <div class="action-sub">
        Registre necessidades educacionais observadas em sala e adapte o formato das atividades quando necessário.
      </div>
    </div>

  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
