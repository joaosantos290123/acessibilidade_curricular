<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["tipo"] ?? "") !== "coordenador") {
  header("Location: ../../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$erro = "";
$nome = "";
$email = "";
$perfil = "aluno";

$cpf = "";
$matricula = "";
$serie = "";
$solicitar_laudo = 0;

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v) ?? '';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nome = trim($_POST["nome"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $perfil = $_POST["perfil"] ?? "aluno";
  $senha = $_POST["senha"] ?? "";

  // Campos aluno
  $cpf = only_digits($_POST["cpf"] ?? "");
  $matricula = trim($_POST["matricula"] ?? "");
  $serie = trim($_POST["serie"] ?? "");
  $solicitar_laudo = isset($_POST["solicitar_laudo"]) ? 1 : 0;

  if ($nome === "" || $email === "" || $senha === "") {
    $erro = "Preencha nome, e-mail e senha.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erro = "Digite um e-mail válido.";
  } elseif (!in_array($perfil, ["aluno","professor","coordenador"], true)) {
    $erro = "Perfil inválido.";
  } elseif (strlen($senha) < 6) {
    $erro = "A senha deve ter pelo menos 6 caracteres.";
  } elseif ($perfil === "aluno") {
    if (strlen($cpf) !== 11) $erro = "CPF inválido. Digite 11 números.";
    elseif ($matricula === "") $erro = "Informe a matrícula do aluno.";
    elseif ($serie === "") $erro = "Informe a série do aluno.";
  }

  if ($erro === "") {
    $conn->begin_transaction();
    try {
      $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

      // 1) cria usuário
      $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $nome, $email, $senha_hash);
      $stmt->execute();
      $usuario_id = $conn->insert_id;

      // 2) cria perfil
      if ($perfil === "coordenador") {
        $stmt = $conn->prepare("INSERT INTO coordenadores (usuario_id) VALUES (?)");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();

      } elseif ($perfil === "professor") {
        $stmt = $conn->prepare("INSERT INTO professores (usuario_id) VALUES (?)");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();

      } else {
        $laudo_status = $solicitar_laudo ? "solicitado" : "nao_informado";

        $stmt = $conn->prepare("
          INSERT INTO alunos (usuario_id, cpf, matricula, serie, laudo_status)
          VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $usuario_id, $cpf, $matricula, $serie, $laudo_status);
        $stmt->execute();
      }

      $conn->commit();
      header("Location: ../usuarios.php?msg=Usu%C3%A1rio%20criado%20com%20sucesso.");
      exit;

    } catch (Throwable $e) {
      $conn->rollback();
      $erro = ($conn->errno == 1062)
        ? "Dados duplicados (e-mail/CPF/matrícula). Verifique."
        : "Erro ao criar usuário.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Novo Usuário</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/css.css">

  <script>
    function toggleFields() {
      const perfil = document.getElementById("perfil").value;
      const alunoBox = document.getElementById("campos-aluno");
      alunoBox.style.display = (perfil === "aluno") ? "block" : "none";
    }
  </script>

  <style>
    /* só um ajuste visual pequeno para combinar bootstrap + teu css */
    .page-wrap { max-width: 720px; margin: 48px auto; padding: 0 16px; }
    .card-clean { border: 1px solid #CBD5E1; border-radius: 16px; }
  </style>
</head>

<body onload="toggleFields()">
  <main class="page-wrap">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
      <div>
        <h1 class="m-0" style="color:#1E3A8A;">Novo Usuário</h1>
        <p class="text-muted m-0">Crie um aluno, professor ou coordenador.</p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="../usuarios.php">Voltar</a>
        <a class="btn btn-outline-secondary" href="../../auth/logout.php">Sair</a>
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
              <input class="form-control" id="cpf" name="cpf" inputmode="numeric" placeholder="00000000000"
                     value="<?php echo htmlspecialchars($cpf); ?>">
              <div class="form-text">Apenas números (sem pontos e sem traço).</div>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold" for="matricula">Matrícula</label>
              <input class="form-control" id="matricula" name="matricula" placeholder="Ex: 202600123"
                     value="<?php echo htmlspecialchars($matricula); ?>">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold" for="serie">Série</label>
              <input class="form-control" id="serie" name="serie" placeholder="Ex: 2º ano"
                     value="<?php echo htmlspecialchars($serie); ?>">
            </div>

            <div class="col-12">
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="solicitar_laudo" name="solicitar_laudo"
                  <?php echo $solicitar_laudo ? "checked" : ""; ?>>
                <label class="form-check-label" for="solicitar_laudo">
                  Solicitar envio de laudo (PDF) ao aluno
                </label>
              </div>
              <div class="form-text">
                Ao marcar, o sistema enviará uma solicitação para o aluno colocar o laudo em PDF no painel dele.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold" for="senha">Senha inicial</label>
          <input class="form-control" id="senha" name="senha" type="password" required placeholder="mínimo 6 caracteres">
        </div>

        <div class="col-12 d-grid mt-2">
          <button class="btn btn-primary btn-lg" type="submit">Criar usuário</button>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
