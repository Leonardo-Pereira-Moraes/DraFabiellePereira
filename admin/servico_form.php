<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$servico = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ?');
    $stmt->execute([$id]);
    $servico = $stmt->fetch();
    if (!$servico) {
        flash('erro', 'Serviço não encontrado.');
        irPara('servicos.php');
    }
}

$v = [
    'nome'        => $servico['nome'] ?? '',
    'slug'        => $servico['slug'] ?? '',
    'descricao'   => $servico['descricao'] ?? '',
    'duracao_min' => $servico['duracao_min'] ?? 60,
    'preco'       => $servico && $servico['preco'] !== null ? number_format((float) $servico['preco'], 2, ',', '.') : '',
    'ativo'       => $servico ? (bool) $servico['ativo'] : true,
    'ordem'       => $servico['ordem'] ?? ((int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM servicos')->fetchColumn()),
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $v = [
        'nome'        => trim((string) ($_POST['nome'] ?? '')),
        'slug'        => trim((string) ($_POST['slug'] ?? '')),
        'descricao'   => trim((string) ($_POST['descricao'] ?? '')),
        'duracao_min' => (int) ($_POST['duracao_min'] ?? 0),
        'preco'       => trim((string) ($_POST['preco'] ?? '')),
        'ativo'       => !empty($_POST['ativo']),
        'ordem'       => max(0, min(255, (int) ($_POST['ordem'] ?? 0))),
    ];

    if (mb_strlen($v['nome']) < 2 || mb_strlen($v['nome']) > 80) {
        $erros[] = 'Informe o nome do serviço (até 80 caracteres).';
    }
    if (mb_strlen($v['descricao']) < 10 || mb_strlen($v['descricao']) > 400) {
        $erros[] = 'A descrição precisa ter entre 10 e 400 caracteres.';
    }
    if ($v['duracao_min'] < 5 || $v['duracao_min'] > 600) {
        $erros[] = 'A duração precisa ficar entre 5 e 600 minutos.';
    }

    $slug = gerarSlug($v['slug'] !== '' ? $v['slug'] : $v['nome']);
    if ($slug === '') {
        $erros[] = 'Endereço (slug) inválido.';
    }

    // Preço opcional no formato brasileiro: 1.250,00 ou 1250
    $preco = null;
    if ($v['preco'] !== '') {
        $normalizado = str_replace(['R$', ' '], '', $v['preco']);
        if (str_contains($normalizado, ',')) {
            $normalizado = str_replace(['.', ','], ['', '.'], $normalizado); // 1.250,00
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalizado)) {
            $normalizado = str_replace('.', '', $normalizado);              // 1.250
        }                                                                    // 150.50 fica como está
        if (!is_numeric($normalizado) || (float) $normalizado < 0) {
            $erros[] = 'Preço inválido. Use o formato 150,00 ou deixe em branco.';
        } else {
            $preco = round((float) $normalizado, 2);
        }
    }

    if (!$erros) {
        try {
            if ($servico) {
                $pdo->prepare('UPDATE servicos SET nome = ?, slug = ?, descricao = ?, duracao_min = ?, preco = ?, ativo = ?, ordem = ? WHERE id = ?')
                    ->execute([$v['nome'], $slug, $v['descricao'], $v['duracao_min'], $preco, (int) $v['ativo'], $v['ordem'], $id]);
                $msg = 'Serviço atualizado.';
                if ((int) $servico['duracao_min'] !== $v['duracao_min']) {
                    $msg .= ' A nova duração vale para os próximos agendamentos; os já marcados mantêm o horário reservado.';
                }
            } else {
                $pdo->prepare('INSERT INTO servicos (nome, slug, descricao, duracao_min, preco, ativo, ordem) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$v['nome'], $slug, $v['descricao'], $v['duracao_min'], $preco, (int) $v['ativo'], $v['ordem']]);
                $msg = 'Serviço criado.';
            }
            flash('sucesso', $msg);
            irPara('servicos.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erros[] = 'Já existe um serviço com esse endereço (slug). Escolha outro.';
            } else {
                throw $e;
            }
        }
    }
}

$tituloPagina = $servico ? 'Editar serviço' : 'Novo serviço';
$menuAtivo    = 'servicos.php';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <a href="servicos.php" class="voltar"><i class="bi bi-arrow-left"></i> Serviços</a>
        <h1 class="titulo-pagina"><?= e($tituloPagina) ?></h1>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="cartao" style="max-width: 46rem">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="row g-3">
        <div class="col-md-8 campo">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?= e($v['nome']) ?>" maxlength="80" required>
        </div>
        <div class="col-md-4 campo">
            <label for="slug">Endereço <small>(slug)</small></label>
            <input type="text" class="form-control" id="slug" name="slug" value="<?= e($v['slug']) ?>" maxlength="80" placeholder="gerado do nome">
        </div>
        <div class="col-12 campo">
            <label for="descricao">Descrição curta <small>(aparece no site)</small></label>
            <textarea class="form-control" id="descricao" name="descricao" rows="3" maxlength="400" required data-contador><?= e($v['descricao']) ?></textarea>
            <p class="campo-ajuda"><span data-contador-valor><?= mb_strlen($v['descricao']) ?></span>/400 caracteres</p>
        </div>
        <div class="col-sm-4 campo">
            <label for="duracao_min">Duração <small>(minutos)</small></label>
            <input type="number" class="form-control" id="duracao_min" name="duracao_min" value="<?= (int) $v['duracao_min'] ?>" min="5" max="600" step="5" required>
        </div>
        <div class="col-sm-4 campo">
            <label for="preco">Preço <small>(opcional)</small></label>
            <div class="input-prefixo">
                <span>R$</span>
                <input type="text" class="form-control" id="preco" name="preco" value="<?= e($v['preco']) ?>" inputmode="decimal" placeholder="0,00">
            </div>
        </div>
        <div class="col-sm-4 campo">
            <label for="ordem">Ordem no site</label>
            <input type="number" class="form-control" id="ordem" name="ordem" value="<?= (int) $v['ordem'] ?>" min="0" max="255">
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= $v['ativo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="ativo">Visível no site e disponível para agendamento online</label>
            </div>
        </div>
    </div>
    <div class="rodape-form">
        <a href="servicos.php" class="btn-contorno">Cancelar</a>
        <button type="submit" class="btn-primario">Salvar serviço</button>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>
