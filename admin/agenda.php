<?php
// Agenda semanal: grade por hora no desktop, lista por dia no celular.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/componentes.php';
exigirLogin();

$ref = (string) ($_GET['semana'] ?? date('Y-m-d'));
$refObj = DateTime::createFromFormat('!Y-m-d', $ref);
if (!$refObj || $refObj->format('Y-m-d') !== $ref) {
    $ref = date('Y-m-d');
}
// Segunda-feira da semana
$segunda  = date('Y-m-d', strtotime($ref . ' -' . (((int) date('N', strtotime($ref))) - 1) . ' days'));
$proxSeg  = date('Y-m-d', strtotime("$segunda +7 days"));
$antSeg   = date('Y-m-d', strtotime("$segunda -7 days"));
$hoje     = date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT a.id, a.inicio, a.fim, a.status, a.origem, a.servico_nome, a.obs_paciente,
            p.id AS paciente_id, p.nome AS paciente_nome, p.telefone
       FROM agendamentos a JOIN pacientes p ON p.id = a.paciente_id
      WHERE a.status <> 'cancelado' AND a.inicio < ? AND a.fim > ?
      ORDER BY a.inicio"
);
$stmt->execute(["$proxSeg 00:00:00", "$segunda 00:00:00"]);
$agendamentos = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, inicio, fim, tipo, motivo FROM bloqueios WHERE inicio < ? AND fim > ? ORDER BY inicio');
$stmt->execute(["$proxSeg 00:00:00", "$segunda 00:00:00"]);
$bloqueios = $stmt->fetchAll();

$faixas = faixasFuncionamento($pdo);

// Dias exibidos: seg a sex sempre; sábado e domingo só se atendem ou têm algo marcado
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $data = date('Y-m-d', strtotime("$segunda +$i days"));
    $w    = (int) date('w', strtotime($data));
    $temAlgo = (bool) array_filter($agendamentos, fn($a) => substr($a['inicio'], 0, 10) === $data);
    if ($i < 5 || $faixas[$w] || $temAlgo) {
        $dias[] = $data;
    }
}

// Faixa de horas da grade: do primeiro horário de abertura ao último de fechamento (ou do que estiver marcado)
$horaMin = 24;
$horaMax = 0;
foreach ($dias as $data) {
    foreach ($faixas[(int) date('w', strtotime($data))] as [$abre, $fecha]) {
        $horaMin = min($horaMin, (int) substr($abre, 0, 2));
        $horaMax = max($horaMax, (int) ceil(((int) substr($fecha, 0, 2) * 60 + (int) substr($fecha, 3, 2)) / 60));
    }
}
foreach ($agendamentos as $a) {
    $horaMin = min($horaMin, (int) date('G', strtotime($a['inicio'])));
    $horaMax = max($horaMax, (int) ceil((strtotime($a['fim']) - strtotime(substr($a['fim'], 0, 10))) / 3600));
}
if ($horaMin >= $horaMax) {
    [$horaMin, $horaMax] = [8, 18];
}
$totalHoras = $horaMax - $horaMin;

/** Posição (em horas a partir do topo da grade) de um intervalo recortado ao dia. */
function posicao(string $data, string $inicio, string $fim, int $horaMin, int $horaMax): ?array
{
    $base = strtotime($data) + $horaMin * 3600;
    $ini  = max(strtotime($inicio), $base);
    $fim  = min(strtotime($fim), strtotime($data) + $horaMax * 3600);
    if ($fim <= $ini) {
        return null;
    }
    return [round(($ini - $base) / 3600, 4), round(($fim - $ini) / 3600, 4)];
}

$fmtCurto = fn($d) => date('j', strtotime($d)) . ' ' . MESES_CURTO[(int) date('n', strtotime($d)) - 1];
$ultimoDia = end($dias);
$tituloSemana = $fmtCurto($segunda) . ' – ' . $fmtCurto($ultimoDia) . ' ' . date('Y', strtotime($ultimoDia));

$tituloPagina = 'Agenda';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina cabeca-agenda">
    <div>
        <h1 class="titulo-pagina">Agenda</h1>
        <p class="subtitulo"><?= e($tituloSemana) ?> · <?= count($agendamentos) ?> <?= count($agendamentos) === 1 ? 'atendimento' : 'atendimentos' ?></p>
    </div>
    <div class="agenda-controles">
        <div class="grupo-botoes">
            <a class="botao-icone" href="agenda.php?semana=<?= $antSeg ?>" aria-label="Semana anterior"><i class="bi bi-chevron-left"></i></a>
            <a class="btn-contorno" href="agenda.php">Hoje</a>
            <a class="botao-icone" href="agenda.php?semana=<?= $proxSeg ?>" aria-label="Próxima semana"><i class="bi bi-chevron-right"></i></a>
        </div>
        <form method="get" class="ir-para-data">
            <label for="semana" class="visually-hidden">Ir para a data</label>
            <input type="date" id="semana" name="semana" class="form-control" value="<?= e($ref) ?>" data-auto-enviar>
        </form>
        <a href="agendamento.php" class="btn-primario"><i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Novo</span></a>
    </div>
</div>

<!-- Grade semanal (tablet/desktop) -->
<div class="semana d-none d-md-block" style="--dias: <?= count($dias) ?>; --horas: <?= $totalHoras ?>;" data-semana data-hora-min="<?= $horaMin ?>">
    <div class="semana-cabeca">
        <span></span>
        <?php foreach ($dias as $data): $w = (int) date('w', strtotime($data)); ?>
            <div class="semana-dia <?= $data === $hoje ? 'is-hoje' : '' ?> <?= $data < $hoje ? 'is-passado' : '' ?>">
                <span><?= DIAS_SEMANA_CURTO[$w] ?></span>
                <strong><?= date('j', strtotime($data)) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="semana-corpo">
        <div class="semana-horas" aria-hidden="true">
            <?php for ($h = $horaMin; $h < $horaMax; $h++): ?>
                <span style="--ini: <?= $h - $horaMin ?>"><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>:00</span>
            <?php endfor; ?>
        </div>

        <?php foreach ($dias as $data): $w = (int) date('w', strtotime($data)); ?>
            <div class="dia-col <?= $data < $hoje ? 'is-passado' : '' ?>" data-dia="<?= $data ?>" title="Clique num espaço livre para agendar">
                <?php
                // Períodos fora do atendimento (hachurados)
                $cursor = sprintf('%02d:00', $horaMin);
                $limites = array_merge($faixas[$w], [[sprintf('%02d:00', $horaMax), null]]);
                foreach ($limites as [$abre, $fecha]) {
                    if ($abre > $cursor && ($p = posicao($data, "$data $cursor", "$data $abre", $horaMin, $horaMax))) {
                        echo '<div class="fora" style="--ini: ' . $p[0] . '; --dur: ' . $p[1] . '"></div>';
                    }
                    if ($fecha !== null) {
                        $cursor = max($cursor, $fecha);
                    }
                }
                ?>

                <?php foreach ($bloqueios as $b): if ($p = posicao($data, $b['inicio'], $b['fim'], $horaMin, $horaMax)): ?>
                    <a class="evento evento-bloqueio" href="bloqueios.php" style="--ini: <?= $p[0] ?>; --dur: <?= $p[1] ?>">
                        <strong><?= e(TIPOS_BLOQUEIO[$b['tipo']]) ?></strong>
                        <?php if ($b['motivo']): ?><span><?= e($b['motivo']) ?></span><?php endif; ?>
                    </a>
                <?php endif; endforeach; ?>

                <?php foreach ($agendamentos as $a): if ($p = posicao($data, $a['inicio'], $a['fim'], $horaMin, $horaMax)): ?>
                    <a class="evento evento-<?= e($a['status']) ?> <?= $p[1] < .75 ? 'is-curto' : '' ?>"
                       href="agendamento.php?id=<?= (int) $a['id'] ?>"
                       style="--ini: <?= $p[0] ?>; --dur: <?= $p[1] ?>"
                       title="<?= e($a['paciente_nome'] . ' · ' . $a['servico_nome'] . ' · ' . date('H:i', strtotime($a['inicio'])) . '–' . date('H:i', strtotime($a['fim']))) ?>">
                        <span class="evento-hora"><?= date('H:i', strtotime($a['inicio'])) ?></span>
                        <strong><?= e($a['paciente_nome']) ?></strong>
                        <span class="evento-servico"><?= e($a['servico_nome']) ?></span>
                    </a>
                <?php endif; endforeach; ?>

                <?php if ($data === $hoje && ($p = posicao($data, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + 60), $horaMin, $horaMax))): ?>
                    <div class="linha-agora" style="--ini: <?= $p[0] ?>" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="legenda-agenda d-none d-md-flex">
    <span><i class="legenda-cor evento-confirmado"></i> Confirmado</span>
    <span><i class="legenda-cor evento-concluido"></i> Concluído</span>
    <span><i class="legenda-cor evento-faltou"></i> Faltou</span>
    <span><i class="legenda-cor evento-bloqueio"></i> Bloqueio</span>
    <span><i class="legenda-cor legenda-fora"></i> Fora do atendimento</span>
</div>

<!-- Lista por dia (celular) -->
<div class="agenda-lista d-md-none">
    <?php foreach ($dias as $data):
        $doDia = array_filter($agendamentos, fn($a) => substr($a['inicio'], 0, 10) === $data);
        $bloqDia = array_filter($bloqueios, fn($b) => $b['inicio'] < date('Y-m-d', strtotime("$data +1 day")) . ' 00:00:00' && $b['fim'] > "$data 00:00:00"); ?>
        <section class="cartao agenda-lista-dia <?= $data === $hoje ? 'is-hoje' : '' ?>">
            <div class="cartao-cabeca">
                <h2><?= e(mb_convert_case(dataCurta($data), MB_CASE_TITLE, 'UTF-8')) ?><?= $data === $hoje ? ' <span class="selo selo-agora">hoje</span>' : '' ?></h2>
                <a href="agendamento.php?data=<?= $data ?>" class="botao-icone" aria-label="Agendar em <?= e(dataCurta($data)) ?>"><i class="bi bi-plus-lg"></i></a>
            </div>
            <?php foreach ($bloqDia as $b): ?>
                <p class="bloqueio-linha"><i class="bi bi-slash-circle"></i> <?= e(TIPOS_BLOQUEIO[$b['tipo']]) ?> · <?= e(dataHoraBr($b['inicio'])) ?> → <?= e(dataHoraBr($b['fim'])) ?></p>
            <?php endforeach; ?>
            <?php if (!$doDia): ?>
                <p class="texto-apoio mb-0"><?= $faixas[(int) date('w', strtotime($data))] ? 'Nenhum atendimento.' : 'Sem atendimento neste dia.' ?></p>
            <?php else: ?>
                <ul class="lista-compacta">
                    <?php foreach ($doDia as $a): ?>
                        <li>
                            <span class="lista-compacta-hora"><?= date('H:i', strtotime($a['inicio'])) ?></span>
                            <span class="lista-compacta-corpo">
                                <a href="agendamento.php?id=<?= (int) $a['id'] ?>"><?= e($a['paciente_nome']) ?></a>
                                <small><?= e($a['servico_nome']) ?> · até <?= date('H:i', strtotime($a['fim'])) ?></small>
                            </span>
                            <?= $a['status'] !== 'confirmado' ? seloStatus($a['status']) : '' ?>
                            <?= acoesAgendamento($a, 'agenda.php?semana=' . $segunda) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
