<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

// Ativar / desativar direto da lista
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    if (($_POST['acao'] ?? '') === 'alternar') {
        $pdo->prepare('UPDATE servicos SET ativo = 1 - ativo WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('sucesso', 'Visibilidade do serviço atualizada.');
    }
    irPara('servicos.php');
}

$servicos = $pdo->query(
    "SELECT s.*, COUNT(a.id) AS total_agendamentos
       FROM servicos s LEFT JOIN agendamentos a ON a.servico_id = s.id
      GROUP BY s.id ORDER BY s.ordem, s.nome"
)->fetchAll();
$exibirPrecos = cfg('exibir_precos') === '1';

$tituloPagina = 'Serviços';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Serviços</h1>
        <p class="subtitulo">A duração de cada serviço define quanto tempo ele ocupa na agenda. Preços estão <?= $exibirPrecos ? '<strong>visíveis</strong>' : '<strong>ocultos</strong>' ?> no site (<a href="horarios.php#config">alterar</a>).</p>
    </div>
    <a href="servico_form.php" class="btn-primario"><i class="bi bi-plus-lg"></i> Novo serviço</a>
</div>

<section class="cartao cartao-tabela">
    <?php if (!$servicos): ?>
        <div class="vazio"><p>Nenhum serviço cadastrado.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabela">
                <thead>
                    <tr><th class="text-center">Ordem</th><th>Serviço</th><th>Duração</th><th>Preço</th><th>No site</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($servicos as $s): ?>
                    <tr class="<?= $s['ativo'] ? '' : 'is-apagado' ?>">
                        <td class="text-center texto-apoio"><?= (int) $s['ordem'] ?></td>
                        <td>
                            <a href="servico_form.php?id=<?= (int) $s['id'] ?>" class="tabela-link"><?= e($s['nome']) ?></a>
                            <small class="d-block texto-apoio texto-cortado"><?= e($s['descricao']) ?></small>
                        </td>
                        <td class="text-nowrap"><?= e(formatarDuracao((int) $s['duracao_min'])) ?></td>
                        <td class="text-nowrap"><?= $s['preco'] !== null ? e(formatarPreco($s['preco'])) : '<span class="texto-apoio">—</span>' ?></td>
                        <td>
                            <form method="post">
                                <?= csrfCampo() ?>
                                <input type="hidden" name="acao" value="alternar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="interruptor <?= $s['ativo'] ? 'is-ligado' : '' ?>" aria-pressed="<?= $s['ativo'] ? 'true' : 'false' ?>" title="<?= $s['ativo'] ? 'Visível e agendável no site. Clique para ocultar.' : 'Oculto no site. Clique para mostrar.' ?>">
                                    <span></span><?= $s['ativo'] ? 'Ativo' : 'Oculto' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="servico_form.php?id=<?= (int) $s['id'] ?>" class="botao-icone" aria-label="Editar <?= e($s['nome']) ?>"><i class="bi bi-pencil"></i></a>
                            <?php if ((int) $s['total_agendamentos'] === 0): ?>
                                <form method="post" action="servico_excluir.php" class="d-inline" data-confirmar="Excluir o serviço &quot;<?= e($s['nome']) ?>&quot;?">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="botao-icone botao-icone-perigo" aria-label="Excluir <?= e($s['nome']) ?>"><i class="bi bi-trash3"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="botao-icone is-desabilitado" title="Tem <?= (int) $s['total_agendamentos'] ?> agendamento(s) no histórico: oculte em vez de excluir."><i class="bi bi-trash3"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
