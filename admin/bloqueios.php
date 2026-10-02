<?php
// Bloqueios de agenda: férias, folga, compromisso. O site não oferece esses horários.
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$erros     = [];
$afetados  = []; // agendamentos confirmados dentro do período (pede confirmação)
$v = [
    'data_inicio' => $_GET['data'] ?? date('Y-m-d'),
    'data_fim'    => $_GET['data'] ?? date('Y-m-d'),
    'dia_inteiro' => true,
    'hora_inicio' => '12:00',
    'hora_fim'    => '14:00',
    'tipo'        => 'folga',
    'motivo'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    // Remover bloqueio
    if (($_POST['acao'] ?? '') === 'excluir') {
        $pdo->prepare('DELETE FROM bloqueios WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('sucesso', 'Bloqueio removido. Os horários voltaram a aparecer no site.');
        irPara('bloqueios.php');
    }

    $v = [
        'data_inicio' => (string) ($_POST['data_inicio'] ?? ''),
        'data_fim'    => (string) ($_POST['data_fim'] ?? ''),
        'dia_inteiro' => !empty($_POST['dia_inteiro']),
        'hora_inicio' => (string) ($_POST['hora_inicio'] ?? ''),
        'hora_fim'    => (string) ($_POST['hora_fim'] ?? ''),
        'tipo'        => (string) ($_POST['tipo'] ?? ''),
        'motivo'      => trim((string) ($_POST['motivo'] ?? '')),
    ];
    $decisao = (string) ($_POST['conflitos'] ?? ''); // '', 'manter' ou 'cancelar'

    $dataValida = fn($d) => ($o = DateTime::createFromFormat('!Y-m-d', $d)) && $o->format('Y-m-d') === $d;
    $horaValida = fn($h) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);

    if (!$dataValida($v['data_inicio']) || !$dataValida($v['data_fim'])) {
        $erros[] = 'Informe as datas de início e fim.';
    }
    if (!$v['dia_inteiro'] && (!$horaValida($v['hora_inicio']) || !$horaValida($v['hora_fim']))) {
        $erros[] = 'Informe os horários de início e fim.';
    }
    if (!isset(TIPOS_BLOQUEIO[$v['tipo']])) {
        $erros[] = 'Escolha o tipo do bloqueio.';
    }
    if (mb_strlen($v['motivo']) > 150) {
        $erros[] = 'O motivo pode ter no máximo 150 caracteres.';
    }

    if (!$erros) {
        if ($v['dia_inteiro']) {
            $inicio = $v['data_inicio'] . ' 00:00:00';
            $fim    = date('Y-m-d', strtotime($v['data_fim'] . ' +1 day')) . ' 00:00:00';
        } else {
            $inicio = $v['data_inicio'] . ' ' . $v['hora_inicio'] . ':00';
            $fim    = $v['data_fim'] . ' ' . $v['hora_fim'] . ':00';
        }
        if ($fim <= $inicio) {
            $erros[] = 'O fim precisa ser depois do início.';
        } elseif (strtotime($fim) - strtotime($inicio) > 366 * 86400) {
            $erros[] = 'Um bloqueio pode ter no máximo um ano.';
        }
    }

    if (!$erros) {
        try {
            // Trava todos os dias do período: nenhum agendamento novo entra enquanto isso
            iniciarTransacaoAgenda($pdo, datasDoIntervalo($inicio, $fim));

            $stmt = $pdo->prepare(
                "SELECT a.id, a.inicio, a.servico_nome, p.nome AS paciente_nome, p.telefone
                   FROM agendamentos a JOIN pacientes p ON p.id = a.paciente_id
                  WHERE a.status = 'confirmado' AND a.inicio < ? AND a.fim > ?
                  ORDER BY a.inicio"
            );
            $stmt->execute([$fim, $inicio]);
            $afetados = $stmt->fetchAll();

            if ($afetados && !in_array($decisao, ['manter', 'cancelar'], true)) {
                // Mostra quem será afetado e pede uma decisão antes de gravar
                $pdo->rollBack();
            } else {
                $pdo->prepare('INSERT INTO bloqueios (inicio, fim, tipo, motivo) VALUES (?, ?, ?, ?)')
                    ->execute([$inicio, $fim, $v['tipo'], $v['motivo'] !== '' ? $v['motivo'] : null]);

                $msg = 'Bloqueio criado. O site não vai oferecer esses horários.';
                if ($afetados && $decisao === 'cancelar') {
                    $ids = array_map('intval', array_column($afetados, 'id'));
                    $pdo->prepare(
                        "UPDATE agendamentos SET status = 'cancelado', cancelado_em = NOW()
                          WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')'
                    )->execute($ids);
                    $msg .= ' ' . count($ids) . ' agendamento(s) cancelado(s): avise os pacientes.';
                } elseif ($afetados) {
                    $msg .= ' Os ' . count($afetados) . ' agendamento(s) do período foram mantidos.';
                }
                $pdo->commit();
                flash('sucesso', $msg);
                irPara('bloqueios.php');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao criar bloqueio: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar agora. Tente novamente.';
        }
    }
}

$verAnteriores = !empty($_GET['anteriores']);
$lista = $pdo->query(
    $verAnteriores
        ? 'SELECT * FROM bloqueios WHERE fim < NOW() ORDER BY inicio DESC LIMIT 50'
        : 'SELECT * FROM bloqueios WHERE fim >= NOW() ORDER BY inicio'
)->fetchAll();

$tituloPagina = 'Bloqueios';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Bloqueios de agenda</h1>
        <p class="subtitulo">Férias, folgas e compromissos. Enquanto um bloqueio estiver ativo, o site não mostra esses horários aos pacientes.</p>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-5">
        <form method="post" class="cartao" data-form-bloqueio>
            <?= csrfCampo() ?>
            <h2 class="cartao-titulo">Novo bloqueio</h2>

            <?php if ($afetados): ?>
                <div class="alerta alerta-aviso">
                    <div>
                        <strong><?= count($afetados) ?> agendamento(s) confirmado(s) nesse período:</strong>
                        <ul class="lista-afetados">
                            <?php foreach ($afetados as $a): ?>
                                <li>
                                    <?= e(dataHoraBr($a['inicio'])) ?> · <?= e($a['paciente_nome']) ?> (<?= e($a['servico_nome']) ?>)
                                    <a href="<?= e(linkWhatsApp('', '55' . $a['telefone'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp de <?= e($a['paciente_nome']) ?>"><i class="bi bi-whatsapp"></i></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mb-2">O que fazer com eles?</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="conflitos" value="cancelar" class="btn-perigo">Bloquear e cancelar esses agendamentos</button>
                            <button type="submit" name="conflitos" value="manter" class="btn-contorno">Bloquear e manter os agendamentos</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-sm-6 campo">
                    <label for="data_inicio">De</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= e($v['data_inicio']) ?>" required>
                </div>
                <div class="col-sm-6 campo">
                    <label for="data_fim">Até</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= e($v['data_fim']) ?>" required>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="dia_inteiro" name="dia_inteiro" value="1" <?= $v['dia_inteiro'] ? 'checked' : '' ?> data-alterna="#horas-bloqueio">
                        <label class="form-check-label" for="dia_inteiro">Dia inteiro</label>
                    </div>
                </div>
                <div class="col-12" id="horas-bloqueio" <?= $v['dia_inteiro'] ? 'hidden' : '' ?>>
                    <div class="row g-3">
                        <div class="col-sm-6 campo">
                            <label for="hora_inicio">Das</label>
                            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" value="<?= e($v['hora_inicio']) ?>">
                        </div>
                        <div class="col-sm-6 campo">
                            <label for="hora_fim">Até</label>
                            <input type="time" class="form-control" id="hora_fim" name="hora_fim" value="<?= e($v['hora_fim']) ?>">
                        </div>
                        <p class="campo-ajuda col-12 mt-1">Com vários dias, vale do horário inicial do primeiro dia até o horário final do último.</p>
                    </div>
                </div>
                <div class="col-sm-5 campo">
                    <label for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <?php foreach (TIPOS_BLOQUEIO as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= $v['tipo'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-7 campo">
                    <label for="motivo">Anotação <small>(só no painel)</small></label>
                    <input type="text" class="form-control" id="motivo" name="motivo" value="<?= e($v['motivo']) ?>" maxlength="150" placeholder="Ex.: congresso em SP">
                </div>
            </div>
            <?php if (!$afetados): ?>
                <div class="rodape-form">
                    <button type="submit" class="btn-primario">Bloquear agenda</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="col-xl-7">
        <section class="cartao">
            <div class="cartao-cabeca">
                <h2><?= $verAnteriores ? 'Bloqueios anteriores' : 'Programados e em andamento' ?></h2>
                <a class="link-discreto" href="bloqueios.php<?= $verAnteriores ? '' : '?anteriores=1' ?>"><?= $verAnteriores ? '← Ver programados' : 'Ver anteriores →' ?></a>
            </div>
            <?php if (!$lista): ?>
                <div class="vazio"><p><?= $verAnteriores ? 'Nenhum bloqueio anterior.' : 'Nenhum bloqueio programado.' ?></p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tabela">
                        <thead><tr><th>Período</th><th>Tipo</th><th>Anotação</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($lista as $b):
                            $diaInteiro = substr($b['inicio'], 11) === '00:00:00' && substr($b['fim'], 11) === '00:00:00';
                            $fimExibido = $diaInteiro ? date('d/m/Y', strtotime($b['fim']) - 1) : dataHoraBr($b['fim']);
                            $iniExibido = $diaInteiro ? date('d/m/Y', strtotime($b['inicio'])) : dataHoraBr($b['inicio']); ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= e($iniExibido) ?><?= $iniExibido !== $fimExibido ? ' → ' . e($fimExibido) : '' ?>
                                    <?php if ($diaInteiro): ?><small class="d-block texto-apoio">dia inteiro</small><?php endif; ?>
                                    <?php if (strtotime($b['inicio']) <= time() && strtotime($b['fim']) > time()): ?><span class="selo selo-agora">em andamento</span><?php endif; ?>
                                </td>
                                <td><span class="selo selo-bloqueio"><?= e(TIPOS_BLOQUEIO[$b['tipo']]) ?></span></td>
                                <td><?= e($b['motivo'] ?? '') ?></td>
                                <td class="text-end">
                                    <form method="post" data-confirmar="Remover este bloqueio? Os horários voltam a aparecer no site.">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="botao-icone botao-icone-perigo" aria-label="Remover bloqueio"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
