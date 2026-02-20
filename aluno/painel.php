<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "aluno") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$usuario_id = (int)$_SESSION["usuario_id"];

// Busca dados do aluno
$stmt = $conn->prepare("
  SELECT
    u.nome, u.email,
    a.id AS aluno_id,
    a.matricula,
    a.serie,
    a.laudo_status,
    a.laudo_pdf,
    a.laudo_data_envio
  FROM alunos a
  INNER JOIN usuarios u ON u.id = a.usuario_id
  WHERE a.usuario_id = ?
  LIMIT 1
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
  header("Location: ../auth/login.php?erro=Perfil%20de%20aluno%20n%C3%A3o%20encontrado.");
  exit;
}

$al = $res->fetch_assoc();

$laudo_status = $al["laudo_status"] ?? "nao_informado";
$podeEnviar = in_array($laudo_status, ["solicitado", "recusado"], true);

$temNotificacao = $podeEnviar; // notificação só quando precisa ação
$notCount = $temNotificacao ? 1 : 0;

function label_status($s) {
  return match ($s) {
    "solicitado" => "Solicitado",
    "enviado"    => "Enviado",
    "aprovado"   => "Aprovado",
    "recusado"   => "Recusado",
    "nao_possui" => "Não possui",
    default      => "Não solicitado",
  };
}

function badge_class($s) {
  return match ($s) {
    "solicitado" => "bg-warning text-dark",
    "enviado"    => "bg-info text-dark",
    "aprovado"   => "bg-success",
    "recusado"   => "bg-danger",
    default      => "bg-secondary",
  };
}

function initials($name) {
  $name = trim($name ?? "");
  if ($name === "") return "A";
  $parts = preg_split("/\s+/", $name);
  $a = mb_substr($parts[0] ?? "A", 0, 1, "UTF-8");
  $b = mb_substr($parts[count($parts)-1] ?? "", 0, 1, "UTF-8");
  $out = mb_strtoupper($a . $b, "UTF-8");
  return $out;
}

$nome = $al["nome"];
$ini = initials($nome);
$statusLabel = label_status($laudo_status);
$statusClass = badge_class($laudo_status);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Painel do Aluno</title>

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
        <div class="brand-title">Painel do Aluno</div>
        <div class="brand-sub">
          <?php echo htmlspecialchars($nome); ?>
          <span class="sep">•</span>
          <?php echo htmlspecialchars($al["email"]); ?>
        </div>
      </div>
    </div>

    <div class="dash-actions">
      <!-- Chip status -->
      <!-- <div class="chip">
        <span class="chip-label">Laudo</span>
        <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
      </div> -->

      <!-- Notificações -->
      <div class="dropdown notif-wrap">
        <button class="btn btn-outline-secondary btn-sm notif-btn" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="me-1">🔔</span> Notificações
          <?php if ($notCount > 0): ?>
            <span class="notif-badge"><?php echo (int)$notCount; ?></span>
          <?php endif; ?>
        </button>

        <ul class="dropdown-menu dropdown-menu-end notif-menu">
          <li class="px-3 pt-2">
            <div class="fw-semibold">Notificações</div>
            <div class="text-muted small">Solicitações e atualizações.</div>
          </li>
          <li><hr class="dropdown-divider"></li>

          <?php if ($podeEnviar && $laudo_status === "solicitado"): ?>
            <li class="px-3 pb-3">
              <div class="fw-semibold">📄 Laudo solicitado</div>
              <div class="small text-muted">Envie o laudo em PDF para análise.</div>
              <div class="mt-2 d-grid">
                <a class="btn btn-primary btn-sm" href="enviar_laudo.php">Enviar laudo</a>
              </div>
            </li>
          <?php elseif ($podeEnviar && $laudo_status === "recusado"): ?>
            <li class="px-3 pb-3">
              <div class="fw-semibold">⚠️ Laudo recusado</div>
              <div class="small text-muted">Envie novamente o PDF.</div>
              <div class="mt-2 d-grid">
                <a class="btn btn-primary btn-sm" href="enviar_laudo.php">Reenviar laudo</a>
              </div>
            </li>
          <?php else: ?>
            <li class="px-3 pb-3 text-muted small">Sem notificações no momento.</li>
          <?php endif; ?>
        </ul>
      </div>

      <a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </header>

  <!-- Banner pendência -->
  <?php if ($podeEnviar): ?>
    <section class="dash-banner">
      <div class="banner-icon">📄</div>
      <div class="banner-text">
        <div class="banner-title">
          <?php echo ($laudo_status === "solicitado") ? "Envio de laudo solicitado" : "Laudo recusado"; ?>
        </div>
        <div class="banner-sub">
          Envie um arquivo <strong>PDF</strong>. Evite dados médicos detalhados: foque no necessário para comprovação.
        </div>
      </div>
      <div class="banner-cta">
        <a class="btn btn-primary" href="enviar_laudo.php">Enviar laudo (PDF)</a>
      </div>
    </section>
  <?php endif; ?>

  <!-- Resumo -->
  <section class="dash-grid">
    <div class="dash-card">
      <div class="card-top">
        <div>
          <div class="card-kicker">Identificação</div>
          <div class="card-value"><?php echo htmlspecialchars($al["matricula"]); ?></div>
        </div>
        <div class="card-icon">🆔</div>
      </div>
      <div class="card-foot text-muted small">Matrícula</div>
    </div>

    <div class="dash-card">
      <div class="card-top">
        <div>
          <div class="card-kicker">Turma</div>
          <div class="card-value"><?php echo htmlspecialchars($al["serie"]); ?></div>
        </div>
        <div class="card-icon">🏫</div>
      </div>
      <div class="card-foot text-muted small">Série</div>
    </div>

    <div class="dash-card">
      <div class="card-top">
        <div>
          <div class="card-kicker">Status do laudo</div>
          <div class="card-value">
            <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
          </div>
        </div>
        <div class="card-icon">✅</div>
      </div>
      <div class="card-foot text-muted small">
        <?php if (!empty($al["laudo_data_envio"])): ?>
          Último envio: <?php echo htmlspecialchars($al["laudo_data_envio"]); ?>
        <?php else: ?>
          Nenhum envio registrado
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Ações -->
  <section class="dash-actions-grid">
    <a class="action-card primary" href="minhas_necessidades.php">
      <div class="action-icon">📝</div>
      <div class="action-title">Minhas necessidades</div>
      <div class="action-sub">Registrar e acompanhar necessidades pedagógicas.</div>
    </a>

    <a class="action-card" href="acessibilidades.php">
      <div class="action-icon">📚</div>
      <div class="action-title">Conteúdos e acessibilidades</div>
      <div class="action-sub">Consultar exemplos e orientações de acessibilidade curricular.</div>
    </a>

    <?php if ($podeEnviar): ?>
      <a class="action-card warn" href="enviar_laudo.php">
        <div class="action-icon">📎</div>
        <div class="action-title">Enviar laudo (PDF)</div>
        <div class="action-sub">Pendência aberta. Envie o documento em PDF.</div>
      </a>
    <?php else: ?>
      <div class="action-card disabled" aria-disabled="true">
        <div class="action-icon">📎</div>
        <div class="action-title">Enviar laudo (PDF)</div>
        <div class="action-sub">Disponível quando solicitado pela coordenação.</div>
      </div>
    <?php endif; ?>

    <a class="action-card" href="acessibilidades.php">
      <div class="action-icon"></div>
      <div class="action-title">...</div>
      <div class="action-sub">...</div>
    </a>

  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
