<?php
// Fotos do consultório exibidas na página inicial e no "Sobre".
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Arquivo maior que post_max_size chega com $_POST e $_FILES vazios (inclusive sem o token CSRF)
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('erro', 'A imagem é grande demais (máximo 5 MB).');
        irPara('fotos.php');
    }
    validarCsrf();
    $acao = (string) ($_POST['acao'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    switch ($acao) {
        case 'enviar':
            try {
                $arquivo = salvarImagem($_FILES['imagem'] ?? null);
                if (!$arquivo) {
                    throw new RuntimeException('Escolha uma imagem para enviar.');
                }
                $legenda = mb_substr(trim((string) ($_POST['legenda'] ?? '')), 0, 150);
                $ordem   = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM fotos_consultorio')->fetchColumn();
                $pdo->prepare('INSERT INTO fotos_consultorio (imagem, legenda, ordem) VALUES (?, ?, ?)')
                    ->execute([$arquivo, $legenda !== '' ? $legenda : null, min(255, $ordem)]);
                flash('sucesso', 'Foto enviada.');
            } catch (RuntimeException $e) {
                flash('erro', $e->getMessage());
            }
            break;

        case 'salvar':
            $legenda = mb_substr(trim((string) ($_POST['legenda'] ?? '')), 0, 150);
            $ordem   = max(0, min(255, (int) ($_POST['ordem'] ?? 0)));
            $pdo->prepare('UPDATE fotos_consultorio SET legenda = ?, ordem = ?, ativo = ? WHERE id = ?')
                ->execute([$legenda !== '' ? $legenda : null, $ordem, empty($_POST['ativo']) ? 0 : 1, $id]);
            flash('sucesso', 'Foto atualizada.');
            break;

        case 'excluir':
            $stmt = $pdo->prepare('SELECT imagem FROM fotos_consultorio WHERE id = ?');
            $stmt->execute([$id]);
            if ($imagem = $stmt->fetchColumn()) {
                $pdo->prepare('DELETE FROM fotos_consultorio WHERE id = ?')->execute([$id]);
                removerImagem($imagem);
                flash('sucesso', 'Foto excluída.');
            }
            break;
    }
    irPara('fotos.php');
}

$fotos = $pdo->query('SELECT * FROM fotos_consultorio ORDER BY ordem, id')->fetchAll();

$tituloPagina = 'Fotos do consultório';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Fotos do consultório</h1>
        <p class="subtitulo">Aparecem na página inicial e em “Sobre”. Fotos na horizontal (4:3) ficam melhores. JPG, PNG ou WEBP até 5 MB.</p>
    </div>
</div>

<form method="post" enctype="multipart/form-data" class="cartao envio-foto">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="enviar">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-5 campo mb-0">
            <label for="imagem">Imagem</label>
            <input type="file" class="form-control" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <div class="col-md-5 campo mb-0">
            <label for="legenda">Legenda <small>(opcional)</small></label>
            <input type="text" class="form-control" id="legenda" name="legenda" maxlength="150" placeholder="Ex.: sala de atendimento">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn-primario w-100"><i class="bi bi-upload"></i> Enviar</button>
        </div>
    </div>
</form>

<?php if (!$fotos): ?>
    <div class="cartao mt-4">
        <div class="vazio">
            <i class="bi bi-images" aria-hidden="true"></i>
            <p>Nenhuma foto ainda. Enquanto não houver fotos, a seção do consultório não aparece no site.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grade-fotos mt-4">
        <?php foreach ($fotos as $f): ?>
            <form method="post" class="cartao-foto <?= $f['ativo'] ? '' : 'is-oculta' ?>">
                <?= csrfCampo() ?>
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <img src="../<?= e(UPLOAD_URL . $f['imagem']) ?>" alt="<?= e($f['legenda'] ?? '') ?>" loading="lazy">
                <div class="cartao-foto-corpo">
                    <input type="text" class="form-control form-control-sm" name="legenda" value="<?= e($f['legenda'] ?? '') ?>" maxlength="150" placeholder="Legenda" aria-label="Legenda">
                    <div class="cartao-foto-linha">
                        <label class="texto-apoio">Ordem <input type="number" class="form-control form-control-sm" name="ordem" value="<?= (int) $f['ordem'] ?>" min="0" max="255"></label>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo<?= (int) $f['id'] ?>" name="ativo" value="1" <?= $f['ativo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo<?= (int) $f['id'] ?>">No site</label>
                        </div>
                    </div>
                    <div class="cartao-foto-linha">
                        <button type="submit" name="acao" value="salvar" class="btn-contorno btn-pequeno">Salvar</button>
                        <button type="submit" name="acao" value="excluir" class="link-perigo" data-confirmar-botao="Excluir esta foto?">Excluir</button>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
