<?php
session_start();
require_once "../config/db.php";

// professor e coordenador podem ver
$tipo = $_SESSION["tipo"] ?? "";
if (!isset($_SESSION["usuario_id"]) || !in_array($tipo, ["professor", "coordenador"], true)) {
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

function fmt_status($s) {
  return match ($s) {
    "solicitado" => "Solicitado",
    "enviado"    => "Enviado",
    "aprovado"   => "Aprovado",
    "recusado"   => "Recusado",
    "nao_possui" => "Não possui",
    default      => "Não informado",
  };
}

$q = trim($_GET["q"] ?? "");
$msg  = $_GET["msg"] ?? "";
$erro = $_GET["erro"] ?? "";
$qNorm = normalize($q);

/**
 * POST: Criar nova necessidade (modal)
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "criar_necessidade") {
  $aluno_id = (int)($_POST["aluno_id"] ?? 0);
  $descricao = trim($_POST["descricao"] ?? "");

  if ($aluno_id <= 0 || $descricao === "") {
    header("Location: necessidades.php?erro=Preencha%20o%20aluno%20e%20a%20descri%C3%A7%C3%A3o.");
    exit;
  }

  // garante que aluno existe
  $chk = $conn->prepare("SELECT 1 FROM alunos WHERE id = ? LIMIT 1");
  $chk->bind_param("i", $aluno_id);
  $chk->execute();
  if ($chk->get_result()->num_rows !== 1) {
    header("Location: necessidades.php?erro=Aluno%20inv%C3%A1lido.");
    exit;
  }

  $ins = $conn->prepare("INSERT INTO necessidades (aluno_id, descricao) VALUES (?, ?)");
  $ins->bind_param("is", $aluno_id, $descricao);

  if ($ins->execute()) {
    header("Location: necessidades.php?msg=Necessidade%20criada%20com%20sucesso.");
    exit;
  }

  header("Location: necessidades.php?erro=N%C3%A3o%20foi%20poss%C3%ADvel%20criar%20a%20necessidade.");
  exit;
}

/**
 * Lista de alunos para o select do modal de criação
 */
$alunosStmt = $conn->prepare("
  SELECT a.id AS aluno_id, a.matricula, a.serie, u.nome, u.email
  FROM alunos a
  JOIN usuarios u ON u.id = a.usuario_id
  ORDER BY u.nome
");
$alunosStmt->execute();
$alunosRes = $alunosStmt->get_result();
$alunos = [];
while ($row = $alunosRes->fetch_assoc()) $alunos[] = $row;

/**
 * Lista de necessidades para a tabela
 */
$sqlBase = "
  SELECT
    n.id AS necessidade_id,
    n.descricao,
    n.data_registro,
    a.id AS aluno_id,
    a.matricula,
    a.serie,
    a.laudo_status,
    u.nome AS aluno_nome,
    u.email AS aluno_email
  FROM necessidades n
  INNER JOIN alunos a ON a.id = n.aluno_id
  INNER JOIN usuarios u ON u.id = a.usuario_id
  ORDER BY n.data_registro DESC
";

$stmt = $conn->prepare($sqlBase);
$stmt->execute();
$rows = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Necessidades dos Alunos</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .page-wrap { max-width: 1100px; margin: 48px auto; padding: 0 16px; }
    .card-clean { border: 1px solid #CBD5E1; border-radius: 16px; }
    .chip { display:inline-block; padding: 4px 10px; border-radius: 999px; border:1px solid #CBD5E1; font-size: 12px; }
    tr.clickable { cursor: pointer; }
  </style>
</head>

<body>
<main class="page-wrap">

  <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h1 class="m-0" style="color:#1E3A8A;">Necessidades dos Alunos</h1>
      <p class="text-muted m-0">Clique em um registro para ver detalhes.</p>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriar">
        + Nova necessidade
      </button>

      <?php if ($tipo === "coordenador"): ?>
        <a class="btn btn-outline-primary" href="../coordenador/painel.php">Voltar</a>
      <?php else: ?>
        <a class="btn btn-outline-primary" href="painel.php">Voltar</a>
      <?php endif; ?>

      <a class="btn btn-outline-secondary" href="../auth/logout.php">Sair</a>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($msg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($erro); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card card-clean p-3 p-md-4 mb-3">
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
        <a class="btn btn-outline-secondary" href="necessidades.php">Limpar</a>
      </div>
    </form>
  </div>

  <div class="card card-clean p-0 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover m-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Aluno</th>
            <th>Matrícula</th>
            <th>Série</th>
            <th>Laudo</th>
            <th>Necessidade</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $achou = 0;
          while ($r = $rows->fetch_assoc()):
            if ($q !== "") {
              $nomeNorm = normalize($r["aluno_nome"]);
              $emailNorm = normalize($r["aluno_email"]);
              $matNorm = normalize($r["matricula"]);

              if (
                strpos($nomeNorm, $qNorm) === false &&
                strpos($emailNorm, $qNorm) === false &&
                strpos($matNorm, $qNorm) === false
              ) {
                continue;
              }
            }
            $achou++;

            // dados para o modal
            $descPlain = $r["descricao"];
            $descModal = htmlspecialchars($descPlain, ENT_QUOTES, 'UTF-8');
            $nomeModal = htmlspecialchars($r["aluno_nome"], ENT_QUOTES, 'UTF-8');
            $emailModal = htmlspecialchars($r["aluno_email"], ENT_QUOTES, 'UTF-8');
            $matModal = htmlspecialchars($r["matricula"], ENT_QUOTES, 'UTF-8');
            $serieModal = htmlspecialchars($r["serie"], ENT_QUOTES, 'UTF-8');
            $statusModal = htmlspecialchars(fmt_status($r["laudo_status"]), ENT_QUOTES, 'UTF-8');
            $dataModal = htmlspecialchars($r["data_registro"], ENT_QUOTES, 'UTF-8');
        ?>
          <tr class="clickable"
              data-bs-toggle="modal"
              data-bs-target="#modalDetalhe"
              data-nome="<?php echo $nomeModal; ?>"
              data-email="<?php echo $emailModal; ?>"
              data-matricula="<?php echo $matModal; ?>"
              data-serie="<?php echo $serieModal; ?>"
              data-status="<?php echo $statusModal; ?>"
              data-data="<?php echo $dataModal; ?>"
              data-desc="<?php echo $descModal; ?>"
          >
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($r["aluno_nome"]); ?></div>
              <div class="text-muted small"><?php echo htmlspecialchars($r["aluno_email"]); ?></div>
            </td>
            <td><?php echo htmlspecialchars($r["matricula"]); ?></td>
            <td><?php echo htmlspecialchars($r["serie"]); ?></td>
            <td><span class="chip"><?php echo htmlspecialchars(fmt_status($r["laudo_status"])); ?></span></td>
            <td style="max-width: 420px;">
              <?php
                $resumo = mb_substr($descPlain, 0, 80, "UTF-8");
                echo nl2br(htmlspecialchars($resumo . (mb_strlen($descPlain, "UTF-8") > 80 ? "..." : "")));
              ?>
            </td>
            <td class="text-muted small">
              <?php echo htmlspecialchars($r["data_registro"]); ?>
            </td>
          </tr>
        <?php endwhile; ?>

        <?php if ($achou === 0): ?>
          <tr>
            <td colspan="6" class="text-center text-muted p-4">
              Nenhuma necessidade encontrada.
            </td>
          </tr>
        <?php endif; ?>

        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- Modal Detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalhes da Necessidade</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>Aluno:</strong> <span id="mNome"></span></div>
        <div class="mb-2"><strong>E-mail:</strong> <span id="mEmail"></span></div>
        <div class="row g-2 mb-2">
          <div class="col-md-4"><strong>Matrícula:</strong> <span id="mMat"></span></div>
          <div class="col-md-4"><strong>Série:</strong> <span id="mSerie"></span></div>
          <div class="col-md-4"><strong>Laudo:</strong> <span id="mStatus"></span></div>
        </div>
        <div class="mb-2"><strong>Data:</strong> <span id="mData"></span></div>
        <hr>
        <div><strong>Necessidade:</strong></div>
        <div class="mt-2" style="white-space: pre-wrap;" id="mDesc"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Criar -->
<div class="modal fade" id="modalCriar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="criar_necessidade">

        <div class="modal-header">
          <h5 class="modal-title">Nova Necessidade</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Aluno</label>
            <select class="form-select" name="aluno_id" required>
              <option value="">Selecione um aluno...</option>
              <?php foreach ($alunos as $a): ?>
                <option value="<?php echo (int)$a["aluno_id"]; ?>">
                  <?php echo htmlspecialchars($a["nome"] . " — " . $a["matricula"] . " (" . $a["serie"] . ")"); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Descrição da necessidade</label>
            <textarea class="form-control" name="descricao" rows="5" required
              placeholder="Ex: Precisa de mais tempo em avaliações e prefere material visual."></textarea>
            <div class="form-text">Evite informações médicas detalhadas. Foque no pedagógico.</div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Preenche o modal de detalhes quando clicar em uma linha
  const modal = document.getElementById('modalDetalhe');
  modal.addEventListener('show.bs.modal', function (event) {
    const tr = event.relatedTarget; // linha clicada
    document.getElementById('mNome').textContent  = tr.getAttribute('data-nome') || '';
    document.getElementById('mEmail').textContent = tr.getAttribute('data-email') || '';
    document.getElementById('mMat').textContent   = tr.getAttribute('data-matricula') || '';
    document.getElementById('mSerie').textContent = tr.getAttribute('data-serie') || '';
    document.getElementById('mStatus').textContent= tr.getAttribute('data-status') || '';
    document.getElementById('mData').textContent  = tr.getAttribute('data-data') || '';
    document.getElementById('mDesc').textContent  = tr.getAttribute('data-desc') || '';
  });
</script>
</body>
</html>
