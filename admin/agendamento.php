<?php
// Novo agendamento / editar / remarcar.
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$erros  = [];
$ag     = null;

if ($id) {
    $stmt = $pdo->prepare(
        'SELECT a.*, p.nome AS paciente_nome, p.telefone, p.email
           FROM agendamentos a JOIN pacientes p ON p.id = a.paciente_id WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $ag = $stmt->fetch();
    if (!$ag) {
        flash('erro', 'Agendamento não encontrado.');
        irPara('agenda.php');
    }
}

// Serviços: ativos + o do agendamento (mesmo se desativado depois)
$stmt = $pdo->prepare('SELECT id, nome, duracao_min, ativo FROM servicos WHERE ativo = 1 OR id = ? ORDER BY ordem, nome');
$stmt->execute([$ag['servico_id'] ?? 0]);
$servicos = array_column($stmt->fetchAll(), null, 'id');

// Valores do formulário (edição, pré-preenchimento por GET ou reenvio com erro)
$v = [
    'servico_id'   => $ag['servico_id'] ?? (int) array_key_first($servicos),
    'data'         => $ag ? substr($ag['inicio'], 0, 10) : ($_GET['data'] ?? date('Y-m-d')),
    'hora'         => $ag ? substr($ag['inicio'], 11, 5) : ($_GET['hora'] ?? ''),
    'duracao'      => $ag ? (int) ((strtotime($ag['fim']) - strtotime($ag['inicio'])) / 60) : null,
    'status'       => $ag['status'] ?? 'confirmado',
    'obs_internas' => $ag['obs_internas'] ?? '',
    'nome'         => '',
    'telefone'     => '',
    'email'        => '',
    'fora_horario' => false,
];

// Novo agendamento a partir da ficha do paciente
if (!$ag && !empty($_GET['paciente'])) {
    $stmt = $pdo->prepare('SELECT nome, telefone, email FROM pacientes WHERE id = ?');
    $stmt->execute([(int) $_GET['paciente']]);
    if ($p = $stmt->fetch()) {
        $v['nome'] = $p['nome'];
        $v['telefone'] = formatarTelefone($p['telefone']);
        $v['email'] = (string) $p['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $v['servico_id']   = (int) ($_POST['servico_id'] ?? 0);
    $v['data']         = (string) ($_POST['data'] ?? '');
    $v['hora']         = (string) ($_POST['hora'] ?? '');
    $v['duracao']      = (int) ($_POST['duracao'] ?? 0);
    $v['status']       = $ag ? (string) ($_POST['status'] ?? 'confirmado') : 'confirmado';
    $v['obs_internas'] = trim((string) ($_POST['obs_internas'] ?? ''));
    $v['fora_horario'] = !empty($_POST['fora_horario']);

    if (!$ag) {
        $v['nome']     = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
        $v['telefone'] = (string) ($_POST['telefone'] ?? '');
        $v['email']    = trim((string) ($_POST['email'] ?? ''));
        $telefone      = normalizarTelefone($v['telefone']);
        if (mb_strlen($v['nome']) < 2 || mb_strlen($v['nome']) > 120) {
            $erros[] = 'Informe o nome do paciente.';
        }
        if (!$telefone) {
            $erros[] = 'Telefone inválido. Use DDD + número, ex.: (64) 99999-9999.';
        }
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        }
    }

    $servico = $servicos[$v['servico_id']] ?? null;
    if (!$servico) {
        $erros[] = 'Escolha um serviço.';
    }
    $dataOk = DateTime::createFromFormat('!Y-m-d', $v['data']);
    if (!$dataOk || $dataOk->format('Y-m-d') !== $v['data']) {
        $erros[] = 'Data inválida.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v['hora'])) {
        $erros[] = 'Horário inválido.';
    }
    if ($v['duracao'] < 5 || $v['duracao'] > 600) {
        $erros[] = 'A duração precisa ficar entre 5 e 600 minutos.';
    }
    if (!isset(STATUS_AGENDAMENTO[$v['status']])) {
        $erros[] = 'Status inválido.';
    }

    if (!$erros) {
        $inicio = "{$v['data']} {$v['hora']}:00";
        $fimTs  = strtotime($inicio) + $v['duracao'] * 60;
        $fim    = date('Y-m-d H:i:s', $fimTs);
        if (date('Y-m-d', $fimTs - 1) !== $v['data']) {
            $erros[] = 'O atendimento precisa terminar no mesmo dia.';
        } elseif ($v['status'] !== 'cancelado' && !$v['fora_horario'] && !dentroDoFuncionamento($pdo, $v['data'], $v['hora'], date('H:i', $fimTs))) {
            $erros[] = 'Esse horário fica fora do horário de atendimento. Se for um encaixe, marque "Permitir fora do horário de atendimento".';
        }
    }

    if (!$erros) {
        try {
            iniciarTransacaoAgenda($pdo, [$v['data']]);

            // Revalida com o dia travado: ninguém grava neste dia até o commit
            $conflitos = $v['status'] !== 'cancelado' ? ocupacoes($pdo, $inicio, $fim, $id) : [];
            if ($conflitos) {
                $pdo->rollBack();
                foreach ($conflitos as $c) {
                    $erros[] = 'Conflita com ' . $c['rotulo'] . ' (' . date('H:i', strtotime($c['inicio'])) . '–' . date('H:i', strtotime($c['fim'])) . ').';
                }
            } else {
                if (!$ag) {
                    // No painel, quem digita é a clínica: nome e e-mail informados atualizam o cadastro
                    $pdo->prepare(
                        'INSERT INTO pacientes (nome, telefone, email) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE nome = VALUES(nome), email = COALESCE(VALUES(email), email), id = LAST_INSERT_ID(id)'
                    )->execute([$v['nome'], $telefone, $v['email'] !== '' ? $v['email'] : null]);
                    $pacienteId = (int) $pdo->lastInsertId();

                    $pdo->prepare(
                        "INSERT INTO agendamentos (paciente_id, servico_id, servico_nome, inicio, fim, origem, obs_internas)
                         VALUES (?, ?, ?, ?, ?, 'painel', ?)"
                    )->execute([$pacienteId, $servico['id'], $servico['nome'], $inicio, $fim, $v['obs_internas'] ?: null]);
                } else {
                    // Mantém o nome gravado no histórico, a menos que o serviço tenha mudado
                    $nomeServico = (int) $ag['servico_id'] === (int) $servico['id'] ? $ag['servico_nome'] : $servico['nome'];
                    $canceladoEm = $v['status'] === 'cancelado' ? ($ag['cancelado_em'] ?? date('Y-m-d H:i:s')) : null;
                    $pdo->prepare(
                        'UPDATE agendamentos
                            SET servico_id = ?, servico_nome = ?, inicio = ?, fim = ?, status = ?, obs_internas = ?, cancelado_em = ?
                          WHERE id = ?'
                    )->execute([$servico['id'], $nomeServico, $inicio, $fim, $v['status'], $v['obs_internas'] ?: null, $canceladoEm, $id]);
                }
                $pdo->commit();

                flash('sucesso', $ag ? 'Agendamento atualizado.' : 'Agendamento criado para ' . dataCurta($v['data']) . ' às ' . $v['hora'] . '.');
                irPara('agenda.php?semana=' . $v['data']);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao salvar agendamento: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar agora. Tente novamente.';
        }
    }
}

if ($v['duracao'] === null) {
    $v['duracao'] = (int) ($servicos[$v['servico_id']]['duracao_min'] ?? 30);
}

$tituloPagina = $ag ? 'Editar agendamento' : 'Novo agendamento';
$menuAtivo    = $ag ? 'agenda.php' : 'agendamento.php';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <a href="agenda.php?semana=<?= e($v['data']) ?>" class="voltar"><i class="bi bi-arrow-left"></i> Agenda</a>
        <h1 class="titulo-pagina"><?= e($tituloPagina) ?></h1>
        <?php if ($ag): ?>
            <p class="subtitulo">
                <?= seloStatus($ag['status']) ?>
                Criado <?= $ag['origem'] === 'site' ? 'pelo paciente no site' : 'no painel' ?> em <?= e(dataHoraBr($ag['criado_em'])) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="row g-4" data-form-agendamento data-ignorar="<?= (int) $id ?>">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="col-xl-7">
        <section class="cartao">
            <h2 class="cartao-titulo">Paciente</h2>
            <?php if ($ag): ?>
                <div class="paciente-fixo">
                    <div>
                        <a href="paciente.php?id=<?= (int) $ag['paciente_id'] ?>" class="paciente-fixo-nome"><?= e($ag['paciente_nome']) ?></a>
                        <span><?= e(formatarTelefone($ag['telefone'])) ?><?= $ag['email'] ? ' · ' . e($ag['email']) : '' ?></span>
                    </div>
                    <a class="btn-contorno" href="<?= e(linkWhatsApp('', '55' . $ag['telefone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                </div>
                <?php if ($ag['obs_paciente']): ?>
                    <p class="obs-paciente"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> <strong>Recado do paciente:</strong> <?= e($ag['obs_paciente']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-5 campo">
                        <label for="telefone">Telefone / WhatsApp</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= e($v['telefone']) ?>" placeholder="(00) 00000-0000" required data-busca-paciente>
                        <p class="campo-ajuda" data-paciente-status>Se o telefone já estiver cadastrado, o nome é preenchido sozinho.</p>
                    </div>
                    <div class="col-md-7 campo">
                        <label for="nome">Nome completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= e($v['nome']) ?>" maxlength="120" required>
                    </div>
                    <div class="col-12 campo">
                        <label for="email">E-mail <small>(opcional)</small></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($v['email']) ?>" maxlength="160">
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="cartao mt-4">
            <h2 class="cartao-titulo">Atendimento</h2>
            <div class="row g-3">
                <div class="col-md-7 campo">
                    <label for="servico_id">Serviço</label>
                    <select class="form-select" id="servico_id" name="servico_id" required>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" data-duracao="<?= (int) $s['duracao_min'] ?>" <?= (int) $v['servico_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['nome']) ?> (<?= e(formatarDuracao((int) $s['duracao_min'])) ?>)<?= $s['ativo'] ? '' : ' – inativo' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 campo">
                    <label for="duracao">Duração <small>(minutos)</small></label>
                    <input type="number" class="form-control" id="duracao" name="duracao" value="<?= (int) $v['duracao'] ?>" min="5" max="600" step="5" required>
                </div>
                <div class="col-md-7 campo">
                    <label for="data">Dia</label>
                    <input type="date" class="form-control" id="data" name="data" value="<?= e($v['data']) ?>" required>
                </div>
                <div class="col-md-5 campo">
                    <label for="hora">Início</label>
                    <input type="time" class="form-control" id="hora" name="hora" value="<?= e($v['hora']) ?>" step="300" required>
                </div>
                <div class="col-12">
                    <p class="campo-rotulo">Horários livres neste dia <small>(para a duração escolhida)</small></p>
                    <div class="chips-horario" data-livres aria-live="polite"></div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="fora_horario" name="fora_horario" value="1" <?= $v['fora_horario'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fora_horario">Permitir fora do horário de atendimento (encaixe)</label>
                    </div>
                </div>

                <?php if ($ag): ?>
                    <div class="col-md-6 campo">
                        <label for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (STATUS_AGENDAMENTO as $valor => $rotulo): ?>
                                <option value="<?= $valor ?>" <?= $v['status'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-12 campo">
                    <label for="obs_internas">Anotações internas <small>(o paciente não vê)</small></label>
                    <textarea class="form-control" id="obs_internas" name="obs_internas" rows="3"><?= e($v['obs_internas']) ?></textarea>
                </div>
            </div>

            <div class="rodape-form">
                <a href="agenda.php?semana=<?= e($v['data']) ?>" class="btn-contorno">Voltar</a>
                <button type="submit" class="btn-primario"><?= $ag ? 'Salvar alterações' : 'Criar agendamento' ?></button>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="cartao cartao-lateral">
            <h2 class="cartao-titulo">Agenda do dia <span data-dia-titulo></span></h2>
            <ol class="ocupacao-dia" data-ocupacao aria-live="polite">
                <li class="texto-apoio">Carregando…</li>
            </ol>
        </section>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>
