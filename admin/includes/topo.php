<?php
// Cabeçalho do painel. Defina $tituloPagina (e opcionalmente $menuAtivo) antes de incluir.
$paginaAtual = basename($_SERVER['PHP_SELF']);
$menuAtivo   = $menuAtivo ?? $paginaAtual;
$menu = [
    'Agenda' => [
        ['index.php',       'bi-house',          'Início'],
        ['agenda.php',      'bi-calendar-week',  'Agenda'],
        ['agendamento.php', 'bi-plus-lg',        'Novo agendamento'],
        ['bloqueios.php',   'bi-slash-circle',   'Bloqueios'],
    ],
    'Cadastros' => [
        ['pacientes.php',   'bi-people',         'Pacientes'],
        ['servicos.php',    'bi-clipboard2-pulse', 'Serviços'],
        ['horarios.php',    'bi-clock',          'Horário de atendimento'],
        ['fotos.php',       'bi-images',         'Fotos do consultório'],
        ['notificacoes.php', 'bi-bell',          'Notificações'],
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <title><?= e($tituloPagina ?? 'Painel') ?> · Painel · <?= e(CLINICA_NOME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css?v=3" rel="stylesheet">
</head>
<body class="painel">

<header class="painel-barra d-lg-none">
    <button class="painel-barra-botao" type="button" data-bs-toggle="offcanvas" data-bs-target="#painelMenu" aria-controls="painelMenu" aria-label="Abrir menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="painel-barra-titulo"><?= e($tituloPagina ?? 'Painel') ?></span>
    <a class="painel-barra-botao" href="agendamento.php" aria-label="Novo agendamento"><i class="bi bi-plus-lg"></i></a>
</header>

<aside class="painel-lateral offcanvas-lg offcanvas-start" tabindex="-1" id="painelMenu" aria-label="Menu do painel">
    <div class="lateral-marca">
        <span class="marca-monograma" aria-hidden="true">FP</span>
        <div>
            <strong><?= e(CLINICA_NOME) ?></strong>
            <small>Painel da clínica</small>
        </div>
        <button type="button" class="btn-close d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#painelMenu" aria-label="Fechar"></button>
    </div>

    <nav class="lateral-nav">
        <?php foreach ($menu as $grupo => $itens): ?>
            <p class="lateral-grupo"><?= e($grupo) ?></p>
            <?php foreach ($itens as [$href, $icone, $rotulo]): ?>
                <a class="lateral-link<?= $menuAtivo === $href ? ' is-ativo' : '' ?>" href="<?= $href ?>"<?= $menuAtivo === $href ? ' aria-current="page"' : '' ?>>
                    <i class="bi <?= $icone ?>" aria-hidden="true"></i> <?= e($rotulo) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="lateral-rodape">
        <a class="lateral-link" href="../index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver site</a>
        <a class="lateral-link<?= $menuAtivo === 'senha.php' ? ' is-ativo' : '' ?>" href="senha.php"><i class="bi bi-key" aria-hidden="true"></i> Alterar senha</a>
        <form action="logout.php" method="post">
            <?= csrfCampo() ?>
            <button class="lateral-link" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair <span class="lateral-usuario"><?= e(primeiroNome($_SESSION['admin_nome'] ?? '')) ?></span></button>
        </form>
    </div>
</aside>

<main class="painel-conteudo">
<?php if ($f = pegarFlash()): ?>
    <div class="alerta alerta-<?= $f['tipo'] === 'sucesso' ? 'sucesso' : 'erro' ?>" role="status" data-alerta>
        <i class="bi <?= $f['tipo'] === 'sucesso' ? 'bi-check2-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($f['mensagem']) ?></span>
        <button type="button" class="alerta-fechar" aria-label="Fechar" data-fechar-alerta>&times;</button>
    </div>
<?php endif; ?>
