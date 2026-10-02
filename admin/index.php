<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/componentes.php';
exigirLogin();

$hoje    = date('Y-m-d');
$amanha  = date('Y-m-d', strtotime('+1 day'));
$em7dias = date('Y-m-d', strtotime('+8 days'));

$sqlBase = "SELECT a.id, a.inicio, a.fim, a.status, a.origem, a.servico_nome, a.obs_paciente,
                   p.id AS paciente_id, p.nome AS paciente_nome, p.telefone
              FROM agendamentos a JOIN pacientes p ON p.id = a.paciente_id";

$stmt = $pdo->prepare("$sqlBase WHERE a.inicio >= ? AND a.inicio < ? ORDER BY a.inicio");
$stmt->execute(["$hoje 00:00:00", "$amanha 00:00:00"]);
$agendaHoje = $stmt->fetchAll();

$stmt = $pdo->prepare("$sqlBase WHERE a.status = 'confirmado' AND a.inicio >= ? ORDER BY a.inicio LIMIT 12");
$stmt->execute(["$amanha 00:00:00"]);
$proximos = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE status = 'confirmado' AND inicio >= ? AND inicio < ?");
$stmt->execute(["$amanha 00:00:00", "$em7dias 00:00:00"]);
$qtdSemana = (int) $stmt->fetchColumn();

$qtdNovosPacientes = (int) $pdo->query('SELECT COUNT(*) FROM pacientes WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
$qtdPeloSite       = (int) $pdo->query("SELECT COUNT(*) FROM agendamentos WHERE origem = 'site' AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$bloqueios = $pdo->query('SELECT inicio, fim, tipo, motivo FROM bloqueios WHERE fim >= NOW() ORDER BY inicio LIMIT 3')->fetchAll();

$ativosHoje = array_filter($agendaHoje, fn($a) => $a['status'] !== 'cancelado');
$agora      = time();
$proximoHoje = null;
foreach ($ativosHoje as $a) {
    if ($a['status'] === 'confirmado' && strtotime($a['fim']) > $agora) {
        $proximoHoje = $a;
        break;
    }
}

$hora = (int) date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

// Próximos agrupados por dia
$porDia = [];
foreach ($proximos as $a) {
    $porDia[substr($a['inicio'], 0, 10)][] = $a;
}

$tituloPagina = 'Início';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <p class="sobretitulo"><?= e(mb_convert_case(dataExtenso($hoje), MB_CASE_TITLE, 'UTF-8')) ?></p>
        <h1 class="titulo-pagina"><?= $saudacao ?>, <?= e(primeiroNome($_SESSION['admin_nome'] ?? '')) ?>.</h1>
        <p class="subtitulo">
            <?php if (!$ativosHoje): ?>
                Nenhum atendimento marcado para hoje.
            <?php elseif ($proximoHoje): ?>
                Próximo: <strong><?= e($proximoHoje['paciente_nome']) ?></strong> às <?= date('H:i', strtotime($proximoHoje['inicio'])) ?> · <?= e($proximoHoje['servico_nome']) ?>
            <?php else: ?>
                Os atendimentos de hoje já terminaram.
            <?php endif; ?>
        </p>
    </div>
    <a href="agendamento.php" class="btn-primario"><i class="bi bi-plus-lg"></i> Novo agendamento</a>
</div>

<div class="numeros">
    <div class="numero">
        <span class="numero-rotulo">Hoje</span>
        <strong><?= count($ativosHoje) ?></strong>
        <span class="numero-dica"><?= count($ativosHoje) === 1 ? 'atendimento' : 'atendimentos' ?></span>
    </div>
    <div class="numero">
        <span class="numero-rotulo">Próximos 7 dias</span>
        <strong><?= $qtdSemana ?></strong>
        <span class="numero-dica">confirmados</span>
    </div>
    <div class="numero">
        <span class="numero-rotulo">Últimos 30 dias</span>
        <strong><?= $qtdPeloSite ?></strong>
        <span class="numero-dica">agendados pelo site</span>
    </div>
    <div class="numero">
        <span class="numero-rotulo">Últimos 30 dias</span>
        <strong><?= $qtdNovosPacientes ?></strong>
        <span class="numero-dica"><?= $qtdNovosPacientes === 1 ? 'paciente novo' : 'pacientes novos' ?></span>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <section class="cartao">
            <div class="cartao-cabeca">
                <h2>Hoje</h2>
                <a href="agenda.php" class="link-discreto">Abrir agenda da semana →</a>
            </div>
            <?php if (!$agendaHoje): ?>
                <div class="vazio">
                    <i class="bi bi-cup-hot" aria-hidden="true"></i>
                    <p>Agenda livre hoje.</p>
                </div>
            <?php else: ?>
                <ol class="linha-dia">
                    <?php foreach ($agendaHoje as $a):
                        $passou = strtotime($a['fim']) <= $agora;
                        $agoraNele = strtotime($a['inicio']) <= $agora && !$passou && $a['status'] === 'confirmado'; ?>
                        <li class="<?= $a['status'] === 'cancelado' ? 'is-cancelado' : '' ?> <?= $agoraNele ? 'is-agora' : '' ?> <?= $passou ? 'is-passado' : '' ?>">
                            <div class="linha-dia-hora">
                                <strong><?= date('H:i', strtotime($a['inicio'])) ?></strong>
                                <span><?= date('H:i', strtotime($a['fim'])) ?></span>
                            </div>
                            <div class="linha-dia-corpo">
                                <a href="paciente.php?id=<?= (int) $a['paciente_id'] ?>" class="linha-dia-nome"><?= e($a['paciente_nome']) ?></a>
                                <span class="linha-dia-meta">
                                    <?= e($a['servico_nome']) ?> · <?= e(formatarTelefone($a['telefone'])) ?>
                                    <?php if ($a['origem'] === 'site'): ?><span class="etiqueta">site</span><?php endif; ?>
                                </span>
                                <?php if ($a['obs_paciente']): ?>
                                    <p class="linha-dia-obs"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> <?= e($a['obs_paciente']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="linha-dia-acoes">
                                <?= $a['status'] !== 'confirmado' ? seloStatus($a['status']) : ($agoraNele ? '<span class="selo selo-agora">agora</span>' : '') ?>
                                <?= acoesAgendamento($a, 'index.php') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="cartao">
            <div class="cartao-cabeca">
                <h2>Próximos agendamentos</h2>
            </div>
            <?php if (!$porDia): ?>
                <div class="vazio"><p>Nenhum agendamento futuro.</p></div>
            <?php else: ?>
                <?php foreach ($porDia as $dia => $itens): ?>
                    <p class="grupo-dia"><?= e(dataRelativa($dia)) ?><?= dataRelativa($dia) === 'Amanhã' ? ' · ' . e(dataCurta($dia)) : '' ?></p>
                    <ul class="lista-compacta">
                        <?php foreach ($itens as $a): ?>
                            <li>
                                <span class="lista-compacta-hora"><?= date('H:i', strtotime($a['inicio'])) ?></span>
                                <span class="lista-compacta-corpo">
                                    <a href="paciente.php?id=<?= (int) $a['paciente_id'] ?>"><?= e($a['paciente_nome']) ?></a>
                                    <small><?= e($a['servico_nome']) ?></small>
                                </span>
                                <?= acoesAgendamento($a, 'index.php') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="cartao mt-4">
            <div class="cartao-cabeca">
                <h2>Bloqueios</h2>
                <a href="bloqueios.php" class="link-discreto">Gerenciar →</a>
            </div>
            <?php if (!$bloqueios): ?>
                <p class="texto-apoio mb-0">Nenhum bloqueio programado. Vai tirar folga ou férias? <a href="bloqueios.php">Bloqueie a agenda</a> para o site não oferecer esses horários.</p>
            <?php else: ?>
                <ul class="lista-compacta">
                    <?php foreach ($bloqueios as $b): ?>
                        <li>
                            <span class="selo selo-bloqueio"><?= e(TIPOS_BLOQUEIO[$b['tipo']]) ?></span>
                            <span class="lista-compacta-corpo">
                                <span><?= e(dataHoraBr($b['inicio'])) ?> → <?= e(dataHoraBr($b['fim'])) ?></span>
                                <?php if ($b['motivo']): ?><small><?= e($b['motivo']) ?></small><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
