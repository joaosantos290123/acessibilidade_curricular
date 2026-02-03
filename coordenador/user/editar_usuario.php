<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "coordenador") {
  header("Location: ../../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v) ?? '';
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  header("Location: usuarios.php?erro=Usu%C3%A1rio%20inv%C3%A1lido.");
  exit;
}

// Buscar usuário base
$stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
  header("Location: ../usuarios.php?erro=Usu%C3%A1rio%20n%C3%A3o%20encontrado.");
  exit;
}

$u = $res->fetch_assoc();

// Descobrir perfil atual e dados do aluno (se tiver)
$perfil_atual = "sem_perfil";
$aluno = null;

$check = $conn->prepare("SELECT 1 FROM coordenadores WHERE usuario_id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
if ($check->get_result()->num_rows === 1) $perfil_atual = "coordenador";

if ($perfil_atual === "sem_perfil") {
  $check = $conn->prepare("SELECT 1 FROM professores WHERE usuario_id = ? LIMIT 1");
  $check->bind_param("i", $id);
  $check->execute();
  if ($check->get_result()->num_rows === 1) $perfil_atual = "professor";
}

if ($perfil_atual === "sem_perfil") {
  $check = $conn->prepare("SELECT cpf, matricula, serie, laudo_status FROM alunos WHERE usuario_id = ? LIMIT 1");
  $check->bind_param("i", $id);
  $check->execute();
  $r = $check->get_result();
  if ($r->num_rows === 1) {
    $perfil_atual = "aluno";
    $aluno = $r->fetch_assoc();
  }
}

// Valores para o formulário
$nome  = $u["nome"];
$email = $u["email"];
$perfil = $perfil_atual;

$cpf = $aluno["cpf"] ?? "";
$matricula = $aluno["matricula"] ?? "";
$serie = $aluno["serie"] ?? "";
$solicitar_laudo = (($aluno["laudo_status"] ?? "") === "solicitado") ? 1 : 0;

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nome  = trim($_POST["nome"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $perfil = $_POST["perfil"] ?? "sem_perfil";

  // Campos aluno (só se perfil=aluno)
  $cpf = only_digits($_POST["cpf"] ?? "");
  $matricula = trim($_POST["matricula"] ?? "");
  $serie = trim($_POST["serie"] ?? "");
  $solicitar_laudo = isset($_POST["solicitar_laudo"]) ? 1 : 0;

  if ($nome === "" || $email === "") {
    $erro = "Preencha nome e e-mail.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erro = "Digite um e-mail válido.";
  } elseif (!in_array($perfil, ["aluno","professor","coordenador"], true)) {
    $erro = "Perfil inválido.";
  } elseif ($perfil === "aluno") {
    if (strlen($cpf) !== 11) $erro = "CPF inválido. Digite 11 números.";
    elseif ($matricula === "") $erro = "Informe a matrícula do aluno.";
    elseif ($serie === "") $erro = "Informe a série do aluno.";
  }

  // Impede o coordenador remover o próprio perfil de coordenador
  if ($erro === "" && (int)$_SESSION["usuario_id"] === $id && $perfil !== "coordenador") {
    $erro = "Você não pode remover seu próprio perfil de coordenador.";
  }

  if ($erro === "") {
    $conn->begin_transaction();
    try {
      // 1) Atualiza dados do usuário
      $up = $conn->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
      $up->bind_param("ssi", $nome, $email, $id);
      $up->execute();

      // 2) Remove perfil antigo (se existir) - garantimos 1 perfil apenas
      $del = $conn->prepare("DELETE FROM alunos WHERE usuario_id = ?");
      $del->bind_param("i", $id);
      $del->execute();

      $del = $conn->prepare("DELETE FROM professores WHERE usuario_id = ?");
      $del->bind_param("i", $id);
      $del->execute();

      $del = $conn->prepare("DELETE FROM coordenadores WHERE usuario_id = ?");
      $del->bind_param("i", $id);
      $del->execute();

      // 3) Insere o novo perfil
      if ($perfil === "coordenador") {
        $ins = $conn->prepare("INSERT INTO coordenadores (usuario_id) VALUES (?)");
        $ins->bind_param("i", $id);
        $ins->execute();

      } elseif ($perfil === "professor") {
        $ins = $conn->prepare("INSERT INTO professores (usuario_id) VALUES (?)");
        $ins->bind_param("i", $id);
        $ins->execute();

      } else { // aluno
        $laudo_status = $solicitar_laudo ? "solicitado" : "nao_informado";

        $ins = $conn->prepare("
          INSERT INTO alunos (usuario_id, cpf, matricula, serie, laudo_status)
          VALUES (?, ?, ?, ?, ?)
        ");
        $ins->bind_param("issss", $id, $cpf, $matricula, $serie, $laudo_status);
        $ins->execute();
      }

      $conn->commit();
      header("Location: ../usuarios.php?msg=Usu%C3%A1rio%20atualizado%20com%20sucesso.");
      exit;

    } catch (Throwable $e) {
      $conn->rollback();
      $erro = ($conn->errno == 1062)
        ? "Dados duplicados (e-mail/CPF/matrícula). Verifique."
        : "Erro ao atualizar usuário.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Usuário</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <script>
    function toggleFields() {
      const perfil = document.getElementById("perfil").value;
      document.getElementById("campos-aluno").style.display = (perfil === "aluno") ? "block" : "none";
    }
  </script>
  <style>
    .page-wrap { max-width: 780px; margin: 48px auto; padding: 0 16px; }
    .card-clean { border: 1px solid #CBD5E1; border-radius: 16px; }
  </style>
</head>
<body onload="toggleFields()">

<main class="page-wrap">
  <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h1 class="m-0" style="color:#1E3A8A;">Editar Usuário</h1>
      <p class="text-muted m-0"><?php echo htmlspecialchars($u["email"]); ?></p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary" href="../usuarios.php">Voltar</a>
    </div>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger" role="alert">
      <?php echo htmlspecialchars($erro); ?>
    </div>
  <?php endif; ?>

  <div class="card card-clean p-3 p-md-4">
    <form method="POST" class="row g-3">

      <div class="col-12">
        <label class="form-label fw-semibold" for="nome">Nome</label>
        <input class="form-control" id="nome" name="nome" required value="<?php echo htmlspecialchars($nome); ?>">
      </div>

      <div class="col-12 col-md-7">
        <label class="form-label fw-semibold" for="email">E-mail</label>
        <input class="form-control" id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email); ?>">
      </div>

      <div class="col-12 col-md-5">
        <label class="form-label fw-semibold" for="perfil">Perfil</label>
        <select class="form-select" id="perfil" name="perfil" onchange="toggleFields()" required>
          <option value="aluno" <?php echo $perfil==="aluno" ? "selected" : ""; ?>>Aluno</option>
          <option value="professor" <?php echo $perfil==="professor" ? "selected" : ""; ?>>Professor</option>
          <option value="coordenador" <?php echo $perfil==="coordenador" ? "selected" : ""; ?>>Coordenador</option>
        </select>
      </div>

      <div id="campos-aluno" class="col-12" style="display:none;">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold" for="cpf">CPF (11 números)</label>
            <input class="form-control" id="cpf" name="cpf" inputmode="numeric"
                   placeholder="00000000000" value="<?php echo htmlspecialchars($cpf); ?>">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold" for="matricula">Matrícula</label>
            <input class="form-control" id="matricula" name="matricula"
                   placeholder="Ex: 202600123" value="<?php echo htmlspecialchars($matricula); ?>">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold" for="serie">Série</label>
            <input class="form-control" id="serie" name="serie"
                   placeholder="Ex: 2º ano" value="<?php echo htmlspecialchars($serie); ?>">
          </div>

          <div class="col-12">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="solicitar_laudo" name="solicitar_laudo"
                <?php echo $solicitar_laudo ? "checked" : ""; ?>>
              <label class="form-check-label" for="solicitar_laudo">
                Solicitar envio de laudo (PDF) ao aluno
              </label>
            </div>
            <div class="form-text">Se marcado, o aluno verá a solicitação no painel e poderá enviar o PDF.</div>
          </div>
        </div>
      </div>

      <div class="col-12 d-grid mt-2">
        <button class="btn btn-primary btn-lg" type="submit">Salvar alterações</button>
      </div>

    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
