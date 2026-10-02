<?php
// Cabeçalho do site público.
// Defina antes de incluir: $tituloPagina, $descricaoPagina e $paginaAtual
// ('inicio', 'servicos', 'sobre', 'contato' ou 'agendar').
$paginaAtual     = $paginaAtual ?? '';
$descricaoPagina = $descricaoPagina ?? 'Consultório odontológico da ' . DENTISTA_NOME . '. Limpeza, clareamento, restaurações, próteses, facetas, aparelhos e harmonização facial. Agende online.';
$logo            = arquivoSeExistir(LOGO_ARQUIVO);
$menu = [
    'inicio'   => ['index.php', 'Início'],
    'servicos' => ['servicos.php', 'Tratamentos'],
    'sobre'    => ['sobre.php', 'Sobre a Dra.'],
    'contato'  => ['contato.php', 'Contato'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($tituloPagina) ? $tituloPagina . ' · ' . CLINICA_NOME : CLINICA_NOME . ' · ' . CLINICA_SEGMENTO) ?></title>
    <meta name="description" content="<?= e($descricaoPagina) ?>">
    <meta name="theme-color" content="#FAF7F4">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e(CLINICA_NOME . ' · ' . CLINICA_SEGMENTO) ?>">
    <meta property="og:description" content="<?= e($descricaoPagina) ?>">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=3" rel="stylesheet">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="pagina-<?= e($paginaAtual) ?>">
<a class="pular-conteudo" href="#conteudo">Pular para o conteúdo</a>

<header class="topo" data-topo>
    <nav class="navbar navbar-expand-lg" aria-label="Principal">
        <div class="container">
            <a class="marca" href="index.php" aria-label="<?= e(CLINICA_NOME) ?> – página inicial">
                <?php if ($logo): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e(CLINICA_NOME) ?>" class="marca-logo">
                <?php else: ?>
                    <!-- Espaço reservado para o logo: salve o arquivo em assets/img/logo.png -->
                    <span class="marca-monograma" aria-hidden="true">FP</span>
                    <span class="marca-texto">
                        <strong><?= e(CLINICA_NOME) ?></strong>
                        <small><?= e(CLINICA_SEGMENTO) ?></small>
                    </span>
                <?php endif; ?>
            </a>

            <button class="menu-botao d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#menuSite"
                    aria-controls="menuSite" aria-expanded="false" aria-label="Abrir menu">
                <span></span><span></span>
            </button>

            <div class="collapse navbar-collapse" id="menuSite">
                <ul class="navbar-nav ms-lg-auto">
                    <?php foreach ($menu as $chave => [$href, $rotulo]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $paginaAtual === $chave ? ' active' : '' ?>" href="<?= $href ?>"<?= $paginaAtual === $chave ? ' aria-current="page"' : '' ?>><?= $rotulo ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="agendar.php" class="btn-primario btn-topo<?= $paginaAtual === 'agendar' ? ' is-atual' : '' ?>">
                    Agendar consulta <span class="seta" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </nav>
</header>

<main id="conteudo">
