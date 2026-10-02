<?php
// Horário de atendimento por dia da semana + regras do agendamento online.
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$ordemDias = [1, 2, 3, 4, 5, 6, 0];
$faixas    = faixasFuncionamento($pdo);
$erros     = [];

$config = [
    'intervalo_grade_min'    => (int) cfg('intervalo_grade_min', '30'),
    'antecedencia_min_horas' => (int) cfg('antecedencia_min_horas', '2'),
    'dias_max_agendamento'   => (int) cfg('dias_max_agendamento', '60'),
    'exibir_precos'          => cfg('exibir_precos') === '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    // ----- Faixas por dia -----
    $novas = array_fill(0, 7, []);
    $horaValida = fn($h) => is_string($h) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
    foreach ($ordemDias as $dia) {
        $abres  = $_POST['abre'][$dia] ?? [];
        $fechas = $_POST['fecha'][$dia] ?? [];
        if (!is_array($abres) || !is_array($fechas)) {
            continue;
        }
        foreach ($abres as $i => $abre) {
            $fecha = $fechas[$i] ?? '';
            if ($abre === '' && $fecha === '') {
                continue; // linha vazia
            }
            $nomeDia = mb_convert_case(DIAS_SEMANA[$dia], MB_CASE_TITLE, 'UTF-8');
            if (!$horaValida($abre) || !$horaValida($fecha)) {
                $erros[] = "$nomeDia: preencha os dois horários de cada faixa.";
                continue;
            }
            if ($fecha <= $abre) {
                $erros[] = "$nomeDia: o fim ($fecha) precisa ser depois do início ($abre).";
                continue;
            }
            $novas[$dia][] = [$abre, $fecha];
        }
        usort($novas[$dia], fn($a, $b) => strcmp($a[0], $b[0]));
        for ($i = 1; $i < count($novas[$dia]); $i++) {
            if ($novas[$dia][$i][0] < $novas[$dia][$i - 1][1]) {
                $erros[] = mb_convert_case(DIAS_SEMANA[$dia], MB_CASE_TITLE, 'UTF-8') . ': as faixas ' . implode('–', $novas[$dia][$i - 1]) . ' e ' . implode('–', $novas[$dia][$i]) . ' se sobrepõem.';
            }
        }
    }
    $faixas = $novas; // reexibe o que foi digitado em caso de erro

    // ----- Regras do agendamento -----
    $config = [
        'intervalo_grade_min'    => (int) ($_POST['intervalo_grade_min'] ?? 30),
        'antecedencia_min_horas' => (int) ($_POST['antecedencia_min_horas'] ?? 2),
        'dias_max_agendamento'   => (int) ($_POST['dias_max_agendamento'] ?? 60),
        'exibir_precos'          => !empty($_POST['exibir_precos']),
    ];
    if (!in_array($config['intervalo_grade_min'], [10, 15, 20, 30, 60], true)) {
        $erros[] = 'Intervalo entre horários inválido.';
    }
    if ($config['antecedencia_min_horas'] < 0 || $config['antecedencia_min_horas'] > 168) {
        $erros[] = 'A antecedência mínima deve ficar entre 0 e 168 horas.';
    }
    if ($config['dias_max_agendamento'] < 1 || $config['dias_max_agendamento'] > 365) {
        $erros[] = 'O período de agendamento deve ficar entre 1 e 365 dias.';
    }

    if (!$erros) {
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM horarios_funcionamento');
            $ins = $pdo->prepare('INSERT INTO horarios_funcionamento (dia_semana, abre, fecha) VALUES (?, ?, ?)');
            foreach ($novas as $dia => $lista) {
                foreach ($lista as [$abre, $fecha]) {
                    $ins->execute([$dia, $abre, $fecha]);
                }
            }
            $cfg = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            foreach ($config as $chave => $valor) {
                $cfg->execute([$chave, is_bool($valor) ? (string) (int) $valor : (string) $valor]);
            }
            $pdo->commit();
            flash('sucesso', 'Horários salvos. O site já mostra a nova disponibilidade.');
            irPara('horarios.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao salvar horários: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar agora. Tente novamente.';
        }
    }
}

$tituloPagina = 'Horário de atendimento';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Horário de atendimento</h1>
        <p class="subtitulo">É a partir destes horários que o site calcula quando cada serviço cabe na agenda. Use duas faixas no mesmo dia para a pausa do almoço.</p>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="row g-4">
    <?= csrfCampo() ?>

    <div class="col-xl-7">
        <section class="cartao">
            <h2 class="cartao-titulo">Dias e horários</h2>
            <div class="semana-horarios">
                <?php foreach ($ordemDias as $dia): ?>
                    <div class="dia-horario <?= $faixas[$dia] ? '' : 'is-fechado' ?>" data-dia-horario="<?= $dia ?>">
                        <p class="dia-horario-nome"><?= e(mb_convert_case(DIAS_SEMANA[$dia], MB_CASE_TITLE, 'UTF-8')) ?></p>
                        <div class="dia-horario-faixas" data-faixas>
                            <?php foreach ($faixas[$dia] as [$abre, $fecha]): ?>
                                <div class="faixa" data-faixa>
                                    <input type="time" class="form-control" name="abre[<?= $dia ?>][]" value="<?= e($abre) ?>" aria-label="Abre">
                                    <span aria-hidden="true">até</span>
                                    <input type="time" class="form-control" name="fecha[<?= $dia ?>][]" value="<?= e($fecha) ?>" aria-label="Fecha">
                                    <button type="button" class="botao-icone" data-remover-faixa aria-label="Remover faixa"><i class="bi bi-x-lg"></i></button>
                                </div>
                            <?php endforeach; ?>
                            <p class="dia-horario-fechado">Fechado</p>
                        </div>
                        <button type="button" class="botao-adicionar" data-adicionar-faixa><i class="bi bi-plus"></i> Faixa</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template id="modelo-faixa">
                <div class="faixa" data-faixa>
                    <input type="time" class="form-control" data-nome="abre" value="" aria-label="Abre">
                    <span aria-hidden="true">até</span>
                    <input type="time" class="form-control" data-nome="fecha" value="" aria-label="Fecha">
                    <button type="button" class="botao-icone" data-remover-faixa aria-label="Remover faixa"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
            <p class="campo-ajuda mt-3 mb-0">Agendamentos já marcados não mudam se você alterar os horários. Confira a agenda depois de reduzir um período.</p>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="cartao" id="config">
            <h2 class="cartao-titulo">Agendamento online</h2>
            <div class="campo">
                <label for="intervalo_grade_min">Oferecer horários a cada</label>
                <select class="form-select" id="intervalo_grade_min" name="intervalo_grade_min">
                    <?php foreach ([10, 15, 20, 30, 60] as $m): ?>
                        <option value="<?= $m ?>" <?= $config['intervalo_grade_min'] === $m ? 'selected' : '' ?>><?= $m ?> minutos</option>
                    <?php endforeach; ?>
                </select>
                <p class="campo-ajuda">Ex.: a cada 30 min, o site oferece 08:00, 08:30, 09:00…</p>
            </div>
            <div class="campo">
                <label for="antecedencia_min_horas">Antecedência mínima <small>(horas)</small></label>
                <input type="number" class="form-control" id="antecedencia_min_horas" name="antecedencia_min_horas" value="<?= (int) $config['antecedencia_min_horas'] ?>" min="0" max="168">
                <p class="campo-ajuda">Evita que alguém marque para daqui a 10 minutos sem você ver.</p>
            </div>
            <div class="campo">
                <label for="dias_max_agendamento">Agenda aberta para os próximos <small>(dias)</small></label>
                <input type="number" class="form-control" id="dias_max_agendamento" name="dias_max_agendamento" value="<?= (int) $config['dias_max_agendamento'] ?>" min="1" max="365">
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="exibir_precos" name="exibir_precos" value="1" <?= $config['exibir_precos'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="exibir_precos">Mostrar preços no site <small class="texto-apoio">(só dos serviços que têm preço)</small></label>
            </div>
        </section>

        <div class="rodape-form rodape-form-fixo">
            <button type="submit" class="btn-primario">Salvar horários</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>
