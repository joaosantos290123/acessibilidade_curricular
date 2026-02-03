<?php
session_start();
require_once "../config/db.php";

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";

if ($email === "" || $senha === "") {
  header("Location: login.php?erro=Preencha%20todos%20os%20campos.");
  exit;
}

$stmt = $conn->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
  header("Location: login.php?erro=E-mail%20ou%20senha%20inv%C3%A1lidos.");
  exit;
}

$usuario = $result->fetch_assoc();

if (!password_verify($senha, $usuario["senha"])) {
  header("Location: login.php?erro=E-mail%20ou%20senha%20inv%C3%A1lidos.");
  exit;
}

$usuario_id = (int)$usuario["id"];

// Descobrir perfil (ordem: coordenador > professor > aluno)
$tipo = null;

$check = $conn->prepare("SELECT 1 FROM coordenadores WHERE usuario_id = ? LIMIT 1");
$check->bind_param("i", $usuario_id);
$check->execute();
if ($check->get_result()->num_rows === 1) $tipo = "coordenador";

if ($tipo === null) {
  $check = $conn->prepare("SELECT 1 FROM professores WHERE usuario_id = ? LIMIT 1");
  $check->bind_param("i", $usuario_id);
  $check->execute();
  if ($check->get_result()->num_rows === 1) $tipo = "professor";
}

if ($tipo === null) {
  $check = $conn->prepare("SELECT 1 FROM alunos WHERE usuario_id = ? LIMIT 1");
  $check->bind_param("i", $usuario_id);
  $check->execute();
  if ($check->get_result()->num_rows === 1) $tipo = "aluno";
}

if ($tipo === null) {
  // Usuário existe mas não tem perfil cadastrado em nenhuma tabela
  session_destroy();
  header("Location: login.php?erro=Usu%C3%A1rio%20sem%20perfil%20cadastrado.%20Procure%20a%20coordena%C3%A7%C3%A3o.");
  exit;
}

// Sessão
$_SESSION["usuario_id"] = $usuario_id;
$_SESSION["nome"] = $usuario["nome"];
$_SESSION["tipo"] = $tipo;

// Redireciona
if ($tipo === "aluno") {
  header("Location: ../aluno/painel.php"); exit;
}
if ($tipo === "professor") {
  header("Location: ../professor/painel.php"); exit;
}
header("Location: ../coordenador/painel.php"); exit;
