<?php
session_start();
require_once "../config/db.php";

// aluno, professor e coordenador podem ver (ajusta se quiser)
$tipo = $_SESSION["tipo"] ?? "";
if (!isset($_SESSION["usuario_id"]) || !in_array($tipo, ["aluno","professor","coordenador"], true)) {
  header("Location: ../auth/login.php?erro=Acesso%20restrito.");
  exit;
}

$q = trim($_GET["q"] ?? "");

$conteudos = [
  [
    "titulo" => "Adaptações de conteúdo",
    "tags" => ["conteudo", "adaptacao", "material"],
    "texto" => "Ajustes no modo de apresentar o conteúdo: reduzir complexidade, usar exemplos concretos, dividir em etapas, e oferecer materiais alternativos (resumo, mapa mental, áudio).",
    "exemplos" => [
      "Fornecer resumo do assunto antes da aula.",
      "Dividir uma atividade longa em partes menores.",
      "Usar linguagem mais simples e frases curtas."
    ]
  ],
  [
    "titulo" => "Avaliação alternativa",
    "tags" => ["avaliacao", "prova", "atividade"],
    "texto" => "Oferecer formas diferentes de avaliar o aprendizado, respeitando as necessidades do aluno (sem reduzir a importância do conteúdo).",
    "exemplos" => [
      "Prova oral em vez de escrita (quando apropriado).",
      "Trabalho prático no lugar de teste tradicional.",
      "Avaliação por etapas ao longo da unidade."
    ]
  ],
  [
    "titulo" => "Tempo extra e flexibilização",
    "tags" => ["tempo", "flexibilizacao"],
    "texto" => "Dar tempo adicional e flexibilizar prazos pode melhorar o desempenho sem mudar o objetivo pedagógico.",
    "exemplos" => [
      "Tempo extra em prova/atividade.",
      "Prazos maiores para entrega.",
      "Pausas durante avaliações longas."
    ]
  ],
  [
    "titulo" => "Recursos visuais e multimodais",
    "tags" => ["visual", "imagem", "video", "multimodal"],
    "texto" => "Uso de imagens, gráficos, vídeos e esquemas para tornar o conteúdo mais acessível e compreensível.",
    "exemplos" => [
      "Slides com pouco texto e fontes legíveis.",
      "Vídeo curto explicando o tópico.",
      "Mapa mental ou linha do tempo."
    ]
  ],
  [
    "titulo" => "Acessibilidade digital (interface)",
    "tags" => ["digital", "interface", "site", "contraste"],
    "texto" => "Boas práticas para o site e materiais digitais: contraste, fontes legíveis, botões grandes, navegação simples.",
    "exemplos" => [
      "Usar azul/ branco com bom contraste.",
      "Fonte mínima 16px e espaçamento adequado.",
      "Botões com área clicável grande."
    ]
  ],
  [
    "titulo" => "Rotina, previsibilidade e apoio",
    "tags" => ["rotina", "apoio", "organizacao"],
    "texto" => "Organização e previsibilidade ajudam muitos alunos: deixar claro objetivos, etapas e critérios.",
    "exemplos" => [
      "Mostrar agenda da aula no começo.",
      "Explicar critérios de avaliação antes.",
      "Checklist do que precisa ser feito."
    ]
  ],
];

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

$qNorm = normalize($q);
$filtrados = [];

foreach ($conteudos as $c) {
  if ($q === "") { $filtrados[] = $c; continue; }
  $hay = normalize($c["titulo"]." ".$c["texto"]." ".implode(" ", $c["tags"]));
  if (strpos($hay, $qNorm) !== false) $filtrados[] = $c;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Conteúdos e Acessibilidades</title>

  <link rel="stylesheet" href="../assets/css/css.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="app-body">
<main class="dash">

  <header class="dash-header">
    <div class="dash-brand">
      <div class="avatar">📚</div>
      <div class="brand-text">
        <div class="brand-title">Conteúdos e Acessibilidades</div>
        <div class="brand-sub">Orientações simples e exemplos práticos para acessibilidade curricular.</div>
      </div>
    </div>

    <div class="dash-actions">
      <?php if ($tipo === "aluno"): ?>
        <a class="btn btn-outline-primary btn-sm" href="painel.php">Voltar</a>
      <?php elseif ($tipo === "professor"): ?>
        <a class="btn btn-outline-primary btn-sm" href="../professor/painel.php">Voltar</a>
      <?php else: ?>
        <a class="btn btn-outline-primary btn-sm" href="../coordenador/painel.php">Voltar</a>
      <?php endif; ?>

      <a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Sair</a>
    </div>
  </header>

  <section class="dash-card mt-3">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-12 col-md-9">
        <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>"
          placeholder="Buscar: avaliação, tempo extra, visual, acessibilidade digital..." />
      </div>
      <div class="col-6 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Buscar</button>
      </div>
      <div class="col-6 col-md-1 d-grid">
        <a class="btn btn-outline-secondary" href="acessibilidades.php">Limpar</a>
      </div>
    </form>
  </section>

  <section class="dash-card mt-3">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <div>
        <div class="fw-semibold">Sugestões rápidas</div>
        <div class="text-muted small">Escolha adaptações que mantenham o objetivo pedagógico.</div>
      </div>
      <span class="badge bg-secondary">
        <?php echo count($filtrados); ?> tópico(s)
      </span>
    </div>
  </section>

  <section class="dash-card mt-3">
    <div class="accordion" id="accAcess">
      <?php if (count($filtrados) === 0): ?>
        <div class="text-muted">Nenhum conteúdo encontrado.</div>
      <?php endif; ?>

      <?php foreach ($filtrados as $i => $c): ?>
        <?php $hid = "h".$i; $cid="c".$i; ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="<?php echo $hid; ?>">
            <button class="accordion-button <?php echo $i===0 ? "" : "collapsed"; ?>" type="button"
              data-bs-toggle="collapse" data-bs-target="#<?php echo $cid; ?>"
              aria-expanded="<?php echo $i===0 ? "true" : "false"; ?>" aria-controls="<?php echo $cid; ?>">
              <?php echo htmlspecialchars($c["titulo"]); ?>
            </button>
          </h2>

          <div id="<?php echo $cid; ?>" class="accordion-collapse collapse <?php echo $i===0 ? "show" : ""; ?>"
            aria-labelledby="<?php echo $hid; ?>" data-bs-parent="#accAcess">
            <div class="accordion-body">
              <p class="mb-2"><?php echo htmlspecialchars($c["texto"]); ?></p>

              <div class="mb-2">
                <?php foreach ($c["tags"] as $t): ?>
                  <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t); ?></span>
                <?php endforeach; ?>
              </div>

              <div class="fw-semibold">Exemplos práticos</div>
              <ul class="mb-0">
                <?php foreach ($c["exemplos"] as $ex): ?>
                  <li><?php echo htmlspecialchars($ex); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
