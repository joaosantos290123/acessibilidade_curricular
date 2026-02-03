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
  // Usuário logado mas sem registro em alunos -> bloqueia
  header("Location: ../auth/login.php?erro=Perfil%20de%20aluno%20n%C3%A3o%20encontrado.");
  exit;
}

$al = $res->fetch_assoc();

$laudo_status = $al["laudo_status"] ?? "nao_informado";

// Regras de notificação:
// - Se coord solicitou -> "solicitado"
// - Se coord recusou -> aluno precisa reenviar -> "recusado"
$temNotificacao = in_array($laudo_status, ["solicitado", "recusado"], true);
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
    "solicitado" => "text-bg-warning",
    "enviado"    => "text-bg-info",
    "aprovado"   => "text-bg-success",
    "recusado"   => "text-bg-danger",
    default      => "text-bg-secondary",
  };
}

// Botão de enviar laudo aparece quando precisa ação do aluno
$podeEnviar = in_array($laudo_status, ["solicitado", "recusado"], true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Painel do Aluno</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .page-wrap { max-width: 1100px; margin: 32px auto; padding: 0 16px; }
    .topbar {
      display:flex; justify-content:space-between; align-items:center; gap:16px;
      padding: 14px 16px; border:1px solid #CBD5E1; border-radius:16px; background:#fff;
    }
    .brand { display:flex; flex-direction:column; }
    .brand h1 { margin:0; color:#1E3A8A; font-size: 20px; }
    .brand small { color:#64748B; }
    .rightbox { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
    .icon-btn { position:relative; }
    .notif-dot {
      position:absolute; top:-4px; right:-4px;
      min-width: 18px; height: 18px; padding: 0 5px;
      border-radius: 999px; font-size: 12px;
      display:flex; align-items:center; justify-content:center;
      background:#dc3545; color:#fff;
    }
    .card-clean { border:1px solid #CBD5E1; border-radius:16px; }
  </style>
</head>
<body>

<main class="page-wrap">

  <!-- TOPBAR -->
  <div class="topbar mb-3">
    <div class="brand">
      <h1>Plataforma de Acessibilidade Curricular</h1>
      <small>Área do aluno</small>
    </div>

    <div class="rightbox">
      <!-- Sininho (dropdown) -->
      <div class="dropdown icon-btn">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          🔔
        </button>
        <?php if ($notCount > 0): ?>
          <span class="notif-dot"><?php echo (int)$notCount; ?></span>
        <?php endif; ?>

        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 320px;">
          <li class="px-3 py-2">
            <div class="fw-semibold">Notificações</div>
            <div class="text-muted small">Acompanhe solicitações do coordenador.</div>
          </li>
          <li><hr class="dropdown-divider"></li>

          <?php if ($temNotificacao): ?>
            <li class="px-3 pb-2">
              <?php if ($laudo_status === "solicitado"): ?>
                <div class="fw-semibold">📄 Laudo solicitado</div>
                <div class="small text-muted">O coordenador solicitou o envio do laudo em PDF.</div>
              <?php else: ?>
                <div class="fw-semibold">⚠️ Laudo recusado</div>
                <div class="small text-muted">Seu laudo foi recusado. Envie novamente o PDF.</div>
              <?php endif; ?>

              <div class="mt-2 d-grid">
                <a class="btn btn-primary btn-sm" href="enviar_laudo.php">Enviar laudo (PDF)</a>
              </div>
            </li>
          <?php else: ?>
            <li class="px-3 pb-2 text-muted small">Sem notificações no momento.</li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Status do laudo -->
      <span class="badge <?php echo badge_class($laudo_status); ?>">
        Laudo: <?php echo htmlspecialchars(label_status($laudo_status)); ?>
      </span>

      <!-- Botão enviar laudo (só quando solicitado/recusado) -->
      <?php if ($podeEnviar): ?>
        <a class="btn btn-primary" href="enviar_laudo.php">Enviar laudo (PDF)</a>
      <?php endif; ?>

      <a class="btn btn-outline-danger" href="../auth/logout.php">Sair</a>
    </div>
  </div>

  <!-- CONTEÚDO -->
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card card-clean p-3 p-md-4">
        <h5 class="mb-2" style="color:#1E3A8A;">Meus dados</h5>
        <div class="mb-1"><strong>Nome:</strong> <?php echo htmlspecialchars($al["nome"]); ?></div>
        <div class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($al["email"]); ?></div>
        <div class="mb-1"><strong>Matrícula:</strong> <?php echo htmlspecialchars($al["matricula"]); ?></div>
        <div class="mb-1"><strong>Série:</strong> <?php echo htmlspecialchars($al["serie"]); ?></div>

        <hr>
        <div class="d-flex align-items-center justify-content-between">
          <div class="text-muted small">Status do laudo</div>
          <span class="badge <?php echo badge_class($laudo_status); ?>">
            <?php echo htmlspecialchars(label_status($laudo_status)); ?>
          </span>
        </div>

        <?php if ($laudo_status === "enviado"): ?>
          <div class="text-muted small mt-2">
            Enviado em: <?php echo htmlspecialchars($al["laudo_data_envio"] ?? "—"); ?>
          </div>
        <?php endif; ?>

        <?php if ($podeEnviar): ?>
          <div class="alert alert-warning mt-3 mb-0">
            Você tem uma pendência de laudo. Clique em <strong>Enviar laudo (PDF)</strong>.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card card-clean p-3 p-md-4">
        <h5 class="mb-2" style="color:#1E3A8A;">Ações</h5>
        <p class="text-muted">Registre suas necessidades de aprendizagem e acesse conteúdos de acessibilidade.</p>

        <div class="d-grid gap-2">
          <a class="btn btn-primary" href="minhas_necessidades.php">Minhas necessidades</a>
          <a class="btn btn-outline-primary" href="acessibilidades.php">Conteúdos / Acessibilidades</a>

          <?php if ($podeEnviar): ?>
            <a class="btn btn-outline-warning" href="enviar_laudo.php">Enviar laudo (PDF)</a>
          <?php endif; ?>
        </div>

        <div class="text-muted small mt-3">
          * O laudo é opcional e só é solicitado quando necessário pelo coordenador.
        </div>
      </div>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
