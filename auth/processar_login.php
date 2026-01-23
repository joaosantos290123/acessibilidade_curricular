<?php
session_start();
require_once "../config/db.php";

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";

if ($email === "" || $senha === "") {
    header("Location: login.php?erro=Preencha%20todos%20os%20campos.");
    exit;
}

$stmt = $conn->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = ?");
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


$_SESSION["usuario_id"] = $usuario["id"];
$_SESSION["nome"] = $usuario["nome"];
$_SESSION["tipo"] = $usuario["tipo"];

if ($usuario["tipo"] === "aluno") {
  header("Location: ../aluno/painel.php"); exit;
}
if ($usuario["tipo"] === "professor") {
  header("Location: ../professor/painel.php"); exit;
}
header("Location: ../coordenador/painel.php"); exit;
