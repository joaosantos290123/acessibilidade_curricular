<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION["usuario_id"]) || $_SESSION["tipo"] !== "coordenador") {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  header("Location: ../usuarios.php?erro=Usuário%20inválido.");
  exit;
}
// Impede excluir a si mesmo
if ($id === (int)$_SESSION["usuario_id"]) {
  header("Location: usuarios.php?erro=Você não pode remover seu próprio usuário.");
exit;

}
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
 header("Location: ../usuarios.php?msg=Usuário removido com sucesso.");
exit;

}

header("Location: usuarios.php?erro=Não foi possível remover o usuário.");
exit;

