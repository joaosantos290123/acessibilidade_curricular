<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "aluno") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$usuario_id = (int)$_SESSION["usuario_id"];

$stmt = $conn->prepare("
  SELECT
    a.id AS aluno_id,
    a.laudo_status,
    a.laudo_pdf,
    a.laudo_data_envio,
    u.nome,
    a.matricula,
    a.serie
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

$msg = "";
$erro = "";

// Caminho de upload (relativo a este arquivo aluno/)
$uploadDir = realpath(__DIR__ . "/../uploads/laudos");
if ($uploadDir === false) {
  $erro = "Pasta de upload não encontrada. Crie: uploads/laudos";
}

function ext_from_filename($name) {
  $name = $name ?? "";
  $pos = strrpos($name, ".");
  if ($pos === false) return "";
  return strtolower(substr($name, $pos + 1));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $erro === "") {
  if (!$podeEnviar) {
    $erro = "Envio não disponível. Aguarde solicitação da coordenação.";
  } elseif (!isset($_FILES["laudo"]) || $_FILES["laudo"]["error"] !== UPLOAD_ERR_OK) {
    $erro = "Selecione um arquivo PDF para enviar.";
  } else {
    $file = $_FILES["laudo"];

    // Regras
    $maxBytes = 5 * 1024 * 1024; // 5MB
    if ($file["size"] <= 0) {
      $erro = "Arquivo inválido.";
    } elseif ($file["size"] > $maxBytes) {
      $erro = "Arquivo muito grande. Máximo: 5MB.";
    } else {
      // Verifica extensão e MIME real
      $ext = ext_from_filename($file["name"]);
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($file["tmp_name"]);

      // Alguns servidores retornam application/octet-stream; aqui exigimos PDF de verdade
      if ($ext !== "pdf" || $mime !== "application/pdf") {
        $erro = "Apenas arquivos PDF são permitidos.";
      } else {
        // Nome seguro e único
        $aluno_id = (int)$al["aluno_id"];
        $unique = bin2hex(random_bytes(8));
        $filename = "laudo_aluno{$aluno_id}_{$unique}.pdf";

        // Caminho final no disco
        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file["tmp_name"], $destPath)) {
          $erro = "Falha ao salvar o arquivo. Tente novamente.";
        } else {
          // Caminho para salvar no banco (relativo ao projeto)
          $dbPath = "uploads/laudos/" . $filename;

          // Atualiza banco: status enviado + data envio + caminho
          $stmtUp = $conn->prepare("
            UPDATE alunos
            SET laudo_pdf = ?, laudo_data_envio = NOW(), laudo_status = 'enviado'
            WHERE id = ?
          ");
          $stmtUp->bind_param("si", $dbPath, $aluno_id);
          $stmtUp->execute();

          // (Opcional) apagar o arquivo antigo se existir e for diferente
          if (!empty($al["laudo_pdf"]) && $al["laudo_pdf"] !== $dbPath) {
            $old = realpath(__DIR__ . "/../" . $al["laudo_pdf"]);
            if ($old && str_starts_with($old, $uploadDir)) {
              @unlink($old);
            }
          }

          header("Location: painel.php?msg=Laudo%20enviado%20com%20sucesso.");
          exit;
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Enviar Laudo (PDF)</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="app-body">
<main class="dash">

  <header class="dash-header">
    <div class="dash-brand">
      <div class="avatar">📎</div>
      <div class="brand-text">
        <div class="brand-title">Enviar Laudo (PDF)</div>
        <div class="brand-sub">
          <?php echo htmlspecialchars($al["nome"]); ?> • Matrícula: <?php echo htmlspecialchars($al["matricula"]); ?> • Série: <?php echo htmlspecialchars($al["serie"]); ?>
        </div>
      </div>
    </div>

    <div class="dash-actions">
      <a class="btn btn-outline-primary btn-sm" href="painel.php">Voltar</a>
      <a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </header>

  <section class="dash-card mt-3">
    <?php if ($msg): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="mb-2">
      <span class="badge <?php echo ($podeEnviar ? "bg-warning text-dark" : "bg-secondary"); ?>">
        Status atual: <?php echo htmlspecialchars($laudo_status); ?>
      </span>
    </div>

    <?php if (!$podeEnviar): ?>
      <div class="alert alert-info mb-0">
        O envio do laudo só fica disponível quando a coordenação solicitar.
      </div>
    <?php else: ?>
      <p class="text-muted mb-3">
        Envie seu laudo em <strong>PDF</strong> (máx. 5MB).  
        Evite inserir informações médicas detalhadas no campo do sistema, apenas o documento necessário.
      </p>

      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold" for="laudo">Arquivo PDF</label>
          <input class="form-control" type="file" name="laudo" id="laudo" accept="application/pdf" required>
          <div class="form-text">Somente PDF. Tamanho máximo: 5MB.</div>
        </div>

        <div class="col-12 d-grid d-md-flex gap-2">
          <button class="btn btn-primary" type="submit">Enviar</button>
          <a class="btn btn-outline-secondary" href="painel.php">Cancelar</a>
        </div>
      </form>

      <?php if (!empty($al["laudo_pdf"])): ?>
        <hr>
        <div class="text-muted small">
          Último arquivo enviado: <span class="fw-semibold"><?php echo htmlspecialchars($al["laudo_pdf"]); ?></span><br>
          Data: <?php echo htmlspecialchars($al["laudo_data_envio"] ?? "—"); ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
