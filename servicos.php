<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/agenda.php';

$paginaAtual     = 'servicos';
$tituloPagina    = 'Tratamentos';
$descricaoPagina = 'Tratamentos odontológicos da ' . DENTISTA_NOME . ': cirurgia, clareamento, limpeza, restauração, prótese, harmonização facial, aparelhos e facetas.';

$servicos     = $pdo->query('SELECT nome, slug, descricao, duracao_min, preco FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$exibirPrecos = cfg('exibir_precos') === '1';

require __DIR__ . '/includes/cabecalho.php';
?>

<header class="cabecalho-pagina">
    <div class="container">
        <div class="row gy-4 align-items-end">
            <div class="col-lg-7">
                <p class="sobretitulo">Tratamentos</p>
                <h1 class="titulo-pagina">O que eu faço no consultório</h1>
            </div>
            <div class="col-lg-4 offset-lg-1">
                <p class="texto-secundario mb-0">A duração indicada é a de uma sessão e já é usada para reservar o seu horário na agenda. Casos que pedem mais de uma sessão são planejados na primeira consulta.</p>
            </div>
        </div>
    </div>
</header>

<section class="secao secao-compacta">
    <div class="container">
        <?php if (!$servicos): ?>
            <p class="texto-secundario">Os tratamentos estão sendo atualizados. Fale com a gente pelo <a href="<?= e(linkWhatsApp()) ?>">WhatsApp</a>.</p>
        <?php endif; ?>

        <div class="servicos-completo">
            <?php foreach ($servicos as $i => $s): ?>
                <article class="servico-linha" id="<?= e($s['slug']) ?>" data-revelar>
                    <div class="servico-titulo">
                        <span class="item-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h2><?= e($s['nome']) ?></h2>
                    </div>
                    <p class="servico-desc"><?= e($s['descricao']) ?></p>
                    <div class="servico-acao">
                        <dl class="servico-meta">
                            <div><dt>Duração</dt><dd><?= e(formatarDuracao((int) $s['duracao_min'])) ?></dd></div>
                            <?php if ($exibirPrecos && $s['preco'] !== null): ?>
                                <div><dt>A partir de</dt><dd><?= e(formatarPreco($s['preco'])) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                        <a href="agendar.php?servico=<?= e($s['slug']) ?>" class="btn-contorno">Agendar <span class="visually-hidden"><?= e($s['nome']) ?></span><span class="seta" aria-hidden="true">→</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="nota-servicos">
            <p><strong>Não sabe qual tratamento escolher?</strong> Agende uma <em>Limpeza</em> ou chame no WhatsApp: na consulta eu avalio e indico o caminho.</p>
            <a href="<?= e(linkWhatsApp('Olá! Não sei qual tratamento agendar, pode me ajudar?')) ?>" class="link-seta" target="_blank" rel="noopener">Perguntar no WhatsApp <span aria-hidden="true">→</span></a>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
