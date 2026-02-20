<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "coordenador") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$msg = $_GET["msg"] ?? "";
$erro = $_GET["erro"] ?? "";

/* ============================
   1) AÇÃO (POST) NA MESMA PÁGINA
============================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $aluno_id = (int)($_POST["aluno_id"] ?? 0);
  $acao     = $_POST["acao"] ?? "";
  $motivo   = trim($_POST["motivo"] ?? "");

  if ($aluno_id <= 0 || !in_array($acao, ["aprovar","recusar"], true)) {
    header("Location: laudos.php?erro=Dados%20inv%C3%A1lidos.");
    exit;
  }

  // só deixa aprovar/recusar se tiver PDF
  $stmt = $conn->prepare("SELECT laudo_pdf FROM alunos WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $aluno_id);
  $stmt->execute();
  $r = $stmt->get_result();

  if ($r->num_rows !== 1) {
    header("Location: laudos.php?erro=Aluno%20n%C3%A3o%20encontrado.");
    exit;
  }

  $al = $r->fetch_assoc();
  if (empty($al["laudo_pdf"])) {
    header("Location: laudos.php?erro=Este%20aluno%20n%C3%A3o%20enviou%20PDF.");
    exit;
  }

  if ($acao === "aprovar") {
    $stmt = $conn->prepare("
      UPDATE alunos
      SET laudo_status = 'aprovado', laudo_motivo = NULL
      WHERE id = ?
    ");
    $stmt->bind_param("i", $aluno_id);
    $stmt->execute();

    header("Location: laudos.php?msg=Laudo%20aprovado%20com%20sucesso.");
    exit;
  }

  if ($acao === "recusar") {
    if ($motivo === "") $motivo = null;

    $stmt = $conn->prepare("
      UPDATE alunos
      SET laudo_status = 'recusado', laudo_motivo = ?
      WHERE id = ?
    ");
    $stmt->bind_param("si", $motivo, $aluno_id);
    $stmt->execute();

    header("Location: laudos.php?msg=Laudo%20recusado.");
    exit;
  }
}

/* ============================
   2) LISTAGEM
============================ */
$q = trim($_GET["q"] ?? "");
$like = "%{$q}%";

$sql = "
  SELECT
    a.id AS aluno_id,
    a.matricula,
    a.serie,
    a.laudo_status,
    a.laudo_pdf,
    a.laudo_data_envio,
    a.laudo_motivo,
    u.nome,
    u.email
  FROM alunos a
  INNER JOIN usuarios u ON u.id = a.usuario_id
  WHERE a.laudo_status IN ('enviado','aprovado','recusado','solicitado')
";

if ($q !== "") {
  $sql .= " AND (u.nome LIKE ? OR u.email LIKE ? OR a.matricula LIKE ?) ";
}

$sql .= " ORDER BY
  CASE a.laudo_status
    WHEN 'enviado' THEN 1
    WHEN 'solicitado' THEN 2
    WHEN 'recusado' THEN 3
    WHEN 'aprovado' THEN 4
    ELSE 5
  END,
  a.laudo_data_envio DESC
";

$stmt = $conn->prepare($sql);
if ($q !== "") $stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$rows = $stmt->get_result();

function status_label($s) {
  return match ($s) {
    "solicitado" => "Solicitado",
    "enviado"    => "Enviado",
    "aprovado"   => "Aprovado",
    "recusado"   => "Recusado",
    "nao_possui" => "Não possui",
    default      => "Não informado",
  };
}
function status_badge($s) {
  return match ($s) {
    "enviado"    => "bg-primary",
    "solicitado" => "bg-warning text-dark",
    "aprovado"   => "bg-success",
    "recusado"   => "bg-danger",
    default      => "bg-secondary",
  };
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Laudos - Coordenação</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="app-body">
<main class="dash">

  <header class="dash-header">
    <div class="dash-brand">
      <div class="avatar">🗂️</div>
      <div class="brand-text">
        <div class="brand-title">Análise de Laudos</div>
        <div class="brand-sub">Aprovar ou recusar PDFs enviados pelos alunos.</div>
      </div>
    </div>

    <div class="dash-actions">
      <a class="btn btn-outline-primary btn-sm" href="painel.php">Voltar</a>
      <a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </header>

  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
      <?php echo htmlspecialchars($msg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
      <?php echo htmlspecialchars($erro); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <section class="dash-card mt-3">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-12 col-md-9">
        <input class="form-control" type="text" name="q"
               placeholder="Buscar por nome, e-mail ou matrícula"
               value="<?php echo htmlspecialchars($q); ?>">
      </div>
      <div class="col-6 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Buscar</button>
      </div>
      <div class="col-6 col-md-1 d-grid">
        <a class="btn btn-outline-secondary" href="laudos.php">Limpar</a>
      </div>
    </form>
  </section>

  <section class="dash-card mt-3 p-0 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover m-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Aluno</th>
            <th>Matrícula</th>
            <th>Série</th>
            <th>Status</th>
            <th>Envio</th>
            <th class="text-end">Ação</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
          <tr><td colspan="6" class="text-center text-muted p-4">Nenhum registro encontrado.</td></tr>
        <?php endif; ?>

        <?php while ($r = $rows->fetch_assoc()): ?>
          <?php
            $aluno_id = (int)$r["aluno_id"];
            $st = $r["laudo_status"] ?? "nao_informado";
          ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($r["nome"]); ?></div>
              <div class="text-muted small"><?php echo htmlspecialchars($r["email"]); ?></div>
            </td>
            <td><?php echo htmlspecialchars($r["matricula"]); ?></td>
            <td><?php echo htmlspecialchars($r["serie"]); ?></td>
            <td><span class="badge <?php echo status_badge($st); ?>"><?php echo htmlspecialchars(status_label($st)); ?></span></td>
            <td class="text-muted small"><?php echo htmlspecialchars($r["laudo_data_envio"] ?? "—"); ?></td>
            <td class="text-end">
              <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalLaudo"
                data-aluno-id="<?php echo $aluno_id; ?>"
                data-nome="<?php echo htmlspecialchars($r["nome"], ENT_QUOTES); ?>"
                data-email="<?php echo htmlspecialchars($r["email"], ENT_QUOTES); ?>"
                data-matricula="<?php echo htmlspecialchars($r["matricula"], ENT_QUOTES); ?>"
                data-serie="<?php echo htmlspecialchars($r["serie"], ENT_QUOTES); ?>"
                data-status="<?php echo htmlspecialchars($st, ENT_QUOTES); ?>"
                data-pdf="<?php echo htmlspecialchars($r["laudo_pdf"] ?? "", ENT_QUOTES); ?>"
                data-motivo="<?php echo htmlspecialchars($r["laudo_motivo"] ?? "", ENT_QUOTES); ?>"
              >
                Ver / Analisar
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>

</main>

<!-- Modal -->
<div class="modal fade" id="modalLaudo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title m-0">Analisar Laudo</h5>
          <div class="text-muted small" id="ml-sub">—</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="p-3 border rounded-3 bg-light">
              <div class="fw-semibold mb-1">Aluno</div>
              <div id="ml-nome">—</div>
              <div class="text-muted small" id="ml-email">—</div>
              <div class="mt-2 small"><strong>Matrícula:</strong> <span id="ml-matricula">—</span></div>
              <div class="small"><strong>Série:</strong> <span id="ml-serie">—</span></div>
              <div class="mt-2">
                <span class="badge" id="ml-status-badge">—</span>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="p-3 border rounded-3 bg-light">
              <div class="fw-semibold mb-2">Arquivo do laudo (PDF)</div>

              <div id="ml-arquivo-vazio" class="text-muted small">Nenhum PDF enviado.</div>

              <div id="ml-arquivo-ok" class="d-none">
                <div class="text-muted small mb-2" id="ml-pdf-path">—</div>
                <div class="d-grid gap-2">
                  <a class="btn btn-outline-primary btn-sm" id="ml-abrir" href="#" target="_blank" rel="noopener">Abrir PDF</a>
                  <a class="btn btn-outline-secondary btn-sm" id="ml-baixar" href="#" download>Baixar PDF</a>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="p-3 border rounded-3">
              <div class="fw-semibold mb-2">Motivo da recusa (opcional)</div>
              <textarea class="form-control" id="ml-motivo" rows="3"
                placeholder="Ex: arquivo ilegível / documento incompleto / reenviar PDF"></textarea>
              <div class="form-text">Use mensagens curtas e objetivas.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between gap-2">
        <form method="POST" class="d-flex gap-2" onsubmit="document.getElementById('motivoHidden').value=document.getElementById('ml-motivo').value.trim();">
          <input type="hidden" name="aluno_id" id="alunoHidden1">
          <input type="hidden" name="acao" value="recusar">
          <input type="hidden" name="motivo" id="motivoHidden">
          <button type="submit" class="btn btn-danger">Recusar</button>
        </form>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>

          <form method="POST">
            <input type="hidden" name="aluno_id" id="alunoHidden2">
            <input type="hidden" name="acao" value="aprovar">
            <button type="submit" class="btn btn-success">Aprovar</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('modalLaudo');

  function badgeClass(status){
    switch(status){
      case 'enviado': return 'bg-primary';
      case 'solicitado': return 'bg-warning text-dark';
      case 'aprovado': return 'bg-success';
      case 'recusado': return 'bg-danger';
      default: return 'bg-secondary';
    }
  }
  function statusLabel(status){
    switch(status){
      case 'solicitado': return 'Solicitado';
      case 'enviado': return 'Enviado';
      case 'aprovado': return 'Aprovado';
      case 'recusado': return 'Recusado';
      case 'nao_possui': return 'Não possui';
      default: return 'Não informado';
    }
  }

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;

    const alunoId  = btn.getAttribute('data-aluno-id');
    const nome     = btn.getAttribute('data-nome');
    const email    = btn.getAttribute('data-email');
    const matricula= btn.getAttribute('data-matricula');
    const serie    = btn.getAttribute('data-serie');
    const status   = btn.getAttribute('data-status');
    const pdf      = btn.getAttribute('data-pdf');
    const motivo   = btn.getAttribute('data-motivo') || '';

    document.getElementById('ml-nome').textContent = nome;
    document.getElementById('ml-email').textContent = email;
    document.getElementById('ml-matricula').textContent = matricula;
    document.getElementById('ml-serie').textContent = serie;
    document.getElementById('ml-sub').textContent = `Aluno #${alunoId}`;

    const badge = document.getElementById('ml-status-badge');
    badge.className = 'badge ' + badgeClass(status);
    badge.textContent = statusLabel(status);

    document.getElementById('alunoHidden1').value = alunoId;
    document.getElementById('alunoHidden2').value = alunoId;

    document.getElementById('ml-motivo').value = motivo;

    const vazio = document.getElementById('ml-arquivo-vazio');
    const ok    = document.getElementById('ml-arquivo-ok');
    const path  = document.getElementById('ml-pdf-path');
    const abrir = document.getElementById('ml-abrir');
    const baixar= document.getElementById('ml-baixar');

    if (pdf && pdf.trim() !== '') {
      vazio.classList.add('d-none');
      ok.classList.remove('d-none');
      path.textContent = pdf;
      abrir.href = "../" + pdf;
      baixar.href = "../" + pdf;
    } else {
      ok.classList.add('d-none');
      vazio.classList.remove('d-none');
      path.textContent = '';
      abrir.href = "#";
      baixar.href = "#";
    }
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
