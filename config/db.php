<?php

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "acessibilidade_curricular";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>


















