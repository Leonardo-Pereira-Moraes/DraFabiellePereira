<?php
// Ficha do paciente: dados, anotações e histórico de atendimentos.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/componentes.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM pacientes WHERE id = ?');
$stmt->execute([$id]);
$paciente = $stmt->fetch();
if (!$paciente) {
    flash('erro', 'Paciente não encontrado.');
    irPara('pacientes.php');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    if (($_POST['acao'] ?? '') === 'excluir') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM agendamentos WHERE paciente_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('erro', 'Este paciente tem histórico de agendamentos e não pode ser excluído.');
            irPara('paciente.php?id=' . $id);
        }
        $pdo->prepare('DELETE FROM pacientes WHERE id = ?')->execute([$id]);
        flash('sucesso', 'Paciente excluído.');
        irPara('pacientes.php');
    }

    $nome        = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
    $telefone    = normalizarTelefone((string) ($_POST['telefone'] ?? ''));
    $email       = trim((string) ($_POST['email'] ?? ''));
    $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
        $erros[] = 'Informe o nome do paciente.';
    }
    if (!$telefone) {
        $erros[] = 'Telefone inválido. Use DDD + número.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }

    if (!$erros) {
        try {
            $pdo->prepare('UPDATE pacientes SET nome = ?, telefone = ?, email = ?, observacoes = ? WHERE id = ?')
                ->execute([$nome, $telefone, $email !== '' ? $email : null, $observacoes !== '' ? $observacoes : null, $id]);
            flash('sucesso', 'Dados do paciente atualizados.');
            irPara('paciente.php?id=' . $id);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erros[] = 'Já existe outro paciente com esse telefone.';
            } else {
                throw $e;
            }
        }
    }
    $paciente = array_merge($paciente, [
        'nome' => $nome, 'telefone' => (string) ($_POST['telefone'] ?? ''), 'email' => $email, 'observacoes' => $observacoes,
    ]);
}

$stmt = $pdo->prepare(
    'SELECT a.*, ? AS paciente_nome, ? AS telefone FROM agendamentos a WHERE a.paciente_id = ? ORDER BY a.inicio DESC'
);
$stmt->execute([$paciente['nome'], soDigitos($paciente['telefone']), $id]);
$historico = $stmt->fetchAll();

$futuros   = array_filter($historico, fn($a) => $a['status'] === 'confirmado' && strtotime($a['inicio']) >= time());
$passados  = array_filter($historico, fn($a) => !($a['status'] === 'confirmado' && strtotime($a['inicio']) >= time()));
$contagem  = array_count_values(array_column($historico, 'status'));

$tituloPagina = $paciente['nome'];
$menuAtivo    = 'pacientes.php';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <a href="pacientes.php" class="voltar"><i class="bi bi-arrow-left"></i> Pacientes</a>
        <h1 class="titulo-pagina"><?= e($paciente['nome']) ?></h1>
        <p class="subtitulo">
            Paciente desde <?= e(date('m/Y', strtotime($paciente['criado_em']))) ?>
            · <?= (int) ($contagem['concluido'] ?? 0) ?> atendimento(s) concluído(s)
            <?php if (!empty($contagem['faltou'])): ?> · <span class="texto-alerta"><?= (int) $contagem['faltou'] ?> falta(s)</span><?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn-contorno" href="<?= e(linkWhatsApp('', '55' . soDigitos($paciente['telefone']))) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <a class="btn-primario" href="agendamento.php?paciente=<?= $id ?>"><i class="bi bi-plus-lg"></i> Agendar</a>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7 order-2 order-xl-1">
        <?php if ($futuros): ?>
            <section class="cartao mb-4">
                <h2 class="cartao-titulo">Próximos</h2>
                <ul class="lista-compacta">
                    <?php foreach (array_reverse($futuros) as $a): ?>
                        <li>
                            <span class="lista-compacta-hora"><?= e(dataCurta($a['inicio'])) ?><small><?= date('H:i', strtotime($a['inicio'])) ?></small></span>
                            <span class="lista-compacta-corpo">
                                <a href="agendamento.php?id=<?= (int) $a['id'] ?>"><?= e($a['servico_nome']) ?></a>
                                <small><?= $a['origem'] === 'site' ? 'Agendado pelo site' : 'Agendado no painel' ?></small>
                            </span>
                            <?= acoesAgendamento($a, 'paciente.php?id=' . $id) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="cartao cartao-tabela">
            <div class="cartao-cabeca"><h2>Histórico</h2></div>
            <?php if (!$passados): ?>
                <div class="vazio"><p>Ainda sem atendimentos anteriores.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tabela">
                        <thead><tr><th>Data</th><th>Serviço</th><th>Status</th><th>Anotações</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($passados as $a): ?>
                            <tr class="<?= $a['status'] === 'cancelado' ? 'is-apagado' : '' ?>">
                                <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($a['inicio']))) ?></td>
                                <td><?= e($a['servico_nome']) ?></td>
                                <td><?= seloStatus($a['status']) ?></td>
                                <td class="texto-apoio"><?= e(mb_strimwidth((string) $a['obs_internas'], 0, 80, '…')) ?></td>
                                <td class="text-end"><?= acoesAgendamento($a, 'paciente.php?id=' . $id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-xl-5 order-1 order-xl-2">
        <form method="post" class="cartao">
            <?= csrfCampo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <h2 class="cartao-titulo">Dados</h2>
            <div class="campo">
                <label for="nome">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?= e($paciente['nome']) ?>" maxlength="120" required>
            </div>
            <div class="campo">
                <label for="telefone">Telefone / WhatsApp</label>
                <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= e(ctype_digit($paciente['telefone']) ? formatarTelefone($paciente['telefone']) : $paciente['telefone']) ?>" required>
            </div>
            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($paciente['email'] ?? '') ?>" maxlength="160">
            </div>
            <div class="campo">
                <label for="observacoes">Anotações da clínica <small>(alergias, preferências…)</small></label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?= e($paciente['observacoes'] ?? '') ?></textarea>
            </div>
            <div class="rodape-form">
                <button type="submit" class="btn-primario">Salvar</button>
            </div>
        </form>

        <?php if (!$historico): ?>
            <form method="post" class="mt-3 text-end" data-confirmar="Excluir este paciente definitivamente?">
                <?= csrfCampo() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="acao" value="excluir">
                <button type="submit" class="link-perigo">Excluir paciente</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
