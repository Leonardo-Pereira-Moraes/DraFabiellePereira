<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/agenda.php';
require_once __DIR__ . '/includes/conteudo.php';

$paginaAtual     = 'sobre';
$tituloPagina    = 'Sobre a Dra. Fabielle';
$descricaoPagina = 'Conheça a ' . DENTISTA_NOME . ', ' . DENTISTA_CRO . ': formação, forma de atendimento e o consultório.';

$fotos   = $pdo->query('SELECT imagem, legenda FROM fotos_consultorio WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
$fotoDra = arquivoSeExistir(FOTO_DENTISTA);

require __DIR__ . '/includes/cabecalho.php';
?>

<section class="secao secao-sobre">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-5">
                <div class="fixo-lateral">
                    <div class="arco arco-sobre">
                        <?php if ($fotoDra): ?>
                            <img src="<?= e($fotoDra) ?>" alt="<?= e(DENTISTA_NOME) ?>" width="852" height="1280">
                        <?php else: ?>
                            <div class="arco-vazio" role="img" aria-label="Espaço para a foto da <?= e(DENTISTA_NOME) ?>">
                                <span class="arco-monograma">FP</span>
                                <span class="arco-legenda">Foto da Dra. Fabielle<br><small>assets/img/dra-fabielle.jpg</small></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="legenda-registro"><?= e(DENTISTA_NOME) ?> · <?= e(DENTISTA_CRO) ?></p>
                </div>
            </div>

            <div class="col-lg-6 offset-lg-1">
                <p class="sobretitulo">Sobre</p>
                <h1 class="titulo-pagina"><?= e(DENTISTA_NOME) ?></h1>

                <div class="bio">
                    <?php foreach (BIO_DENTISTA as $i => $paragrafo): ?>
                        <p<?= $i === 0 ? ' class="bio-abertura"' : '' ?>><?= e($paragrafo) ?></p>
                    <?php endforeach; ?>
                </div>

                <h2 class="titulo-bloco">Formação</h2>
                <ol class="linha-tempo">
                    <?php foreach (FORMACAO_DENTISTA as $f): ?>
                        <li>
                            <span class="linha-tempo-ano"><?= e($f['ano']) ?></span>
                            <div>
                                <strong><?= e($f['titulo']) ?></strong>
                                <span><?= e($f['local']) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <h2 class="titulo-bloco">Como eu trabalho</h2>
                <div class="principios">
                    <?php foreach (PRINCIPIOS_ATENDIMENTO as $p): ?>
                        <div class="principio" data-revelar>
                            <h3><?= e($p['titulo']) ?></h3>
                            <p><?= e($p['texto']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a href="agendar.php" class="btn-primario btn-grande mt-4">Agendar uma consulta <span class="seta" aria-hidden="true">→</span></a>
            </div>
        </div>
    </div>
</section>

<?php if ($fotos): ?>
<section class="secao secao-sem-topo" aria-labelledby="titulo-consultorio">
    <div class="container">
        <div class="cabeca-secao">
            <div>
                <p class="sobretitulo">O consultório</p>
                <h2 class="titulo-secao" id="titulo-consultorio">Um espaço pensado para você ficar à vontade</h2>
            </div>
            <div class="galeria-controles" aria-hidden="true">
                <button type="button" class="botao-redondo" data-galeria="-1" tabindex="-1"><i class="bi bi-arrow-left"></i></button>
                <button type="button" class="botao-redondo" data-galeria="1" tabindex="-1"><i class="bi bi-arrow-right"></i></button>
            </div>
        </div>
    </div>
    <div class="galeria" data-galeria-trilho tabindex="0" aria-label="Fotos do consultório">
        <?php foreach ($fotos as $f): ?>
            <figure>
                <img src="<?= e(UPLOAD_URL . $f['imagem']) ?>" alt="<?= e($f['legenda'] ?: 'Consultório da ' . DENTISTA_NOME) ?>" loading="lazy">
                <?php if ($f['legenda']): ?><figcaption><?= e($f['legenda']) ?></figcaption><?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
