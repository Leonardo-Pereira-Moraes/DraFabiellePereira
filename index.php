<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/agenda.php';
require_once __DIR__ . '/includes/conteudo.php';

$paginaAtual = 'inicio';

$servicos = $pdo->query('SELECT nome, slug, descricao, duracao_min FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$fotos    = $pdo->query('SELECT imagem, legenda FROM fotos_consultorio WHERE ativo = 1 ORDER BY ordem, id LIMIT 8')->fetchAll();

// Próximo horário livre real, calculado da agenda (para o serviço mais curto)
$proximo = null;
if ($servicos) {
    $maisCurto = $servicos;
    usort($maisCurto, fn($a, $b) => $a['duracao_min'] <=> $b['duracao_min']);
    $maisCurto = $maisCurto[0];
    if ($vaga = proximoHorarioLivre($pdo, (int) $maisCurto['duracao_min'])) {
        $proximo = $vaga + ['servico' => $maisCurto];
    }
}

$situacao  = situacaoAgora($pdo);
$horarios  = resumoHorarios($pdo);
$diasAbertos = array_values(array_filter($horarios, fn($h) => !$h['fechado']));
$fotoDra   = arquivoSeExistir(FOTO_DENTISTA);

require __DIR__ . '/includes/cabecalho.php';
?>

<section class="hero">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6 col-xl-6">
                <p class="sobretitulo" data-revelar>Consultório odontológico · <?= e(CLINICA_CIDADE) ?></p>
                <h1 class="titulo-display" data-revelar>
                    Tratamento dentário com <em>hora marcada</em> e explicação em cada etapa.
                </h1>
                <p class="texto-lead" data-revelar>
                    Sou a <?= e(DENTISTA_NOME) ?>. Cuido da saúde e da estética do seu sorriso, da limpeza de rotina às facetas, com consultas sem pressa e um plano que você entende antes de começar.
                </p>
                <div class="hero-acoes" data-revelar>
                    <a href="agendar.php" class="btn-primario btn-grande">Agendar consulta <span class="seta" aria-hidden="true">→</span></a>
                    <a href="<?= e(linkWhatsApp('Olá! Gostaria de tirar uma dúvida antes de agendar.')) ?>" class="btn-secundario btn-grande" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i> Tirar dúvida no WhatsApp
                    </a>
                </div>

                <dl class="hero-fatos" data-revelar>
                    <div>
                        <dt>Registro profissional</dt>
                        <dd><?= e(DENTISTA_CRO) ?></dd>
                    </div>
                    <div>
                        <dt>Atendimento</dt>
                        <dd><?= e($diasAbertos ? implode(' e ', array_map(fn($h) => $h['dias'], array_slice($diasAbertos, 0, 2))) : 'Com hora marcada') ?></dd>
                    </div>
                    <div>
                        <dt>Agora</dt>
                        <dd class="situacao <?= $situacao['aberto'] ? 'is-aberto' : '' ?>"><?= e($situacao['texto']) ?></dd>
                    </div>
                </dl>
            </div>

            <div class="col-lg-5 offset-lg-1">
                <div class="hero-foto" data-revelar>
                    <div class="arco">
                        <?php if ($fotoDra): ?>
                            <img src="<?= e($fotoDra) ?>" alt="<?= e(DENTISTA_NOME) ?> no consultório" width="852" height="1280" fetchpriority="high">
                        <?php else: ?>
                            <div class="arco-vazio" role="img" aria-label="Espaço para a foto da <?= e(DENTISTA_NOME) ?>">
                                <span class="arco-monograma">FP</span>
                                <span class="arco-legenda">Foto da Dra. Fabielle<br><small>assets/img/dra-fabielle.jpg</small></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($proximo): ?>
                        <a class="cartao-vaga" href="agendar.php?servico=<?= e($proximo['servico']['slug']) ?>">
                            <span class="cartao-vaga-rotulo"><span class="ponto-vivo" aria-hidden="true"></span> Próximo horário livre</span>
                            <strong><?= e(dataRelativa($proximo['data'])) ?> · <?= e($proximo['hora']) ?></strong>
                            <span class="cartao-vaga-meta"><?= e($proximo['servico']['nome']) ?> · <?= e(formatarDuracao((int) $proximo['servico']['duracao_min'])) ?></span>
                            <span class="cartao-vaga-seta" aria-hidden="true">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="secao secao-primeira" aria-labelledby="titulo-primeira">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-5">
                <div class="fixo-lateral">
                    <p class="sobretitulo">Primeira consulta</p>
                    <h2 class="titulo-secao" id="titulo-primeira">O que acontece quando você chega ao consultório</h2>
                    <p class="texto-secundario">Nenhum tratamento começa na primeira visita sem que você saiba exatamente o que vai ser feito.</p>
                </div>
            </div>
            <div class="col-lg-6 offset-lg-1">
                <ol class="passos">
                    <?php foreach (ETAPAS_PRIMEIRA_CONSULTA as $i => $etapa): ?>
                        <li data-revelar>
                            <span class="passo-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <div>
                                <h3><?= e($etapa['titulo']) ?></h3>
                                <p><?= e($etapa['texto']) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    </div>
</section>

<?php if ($servicos): ?>
<section class="secao secao-papel" aria-labelledby="titulo-tratamentos">
    <div class="container">
        <div class="cabeca-secao">
            <div>
                <p class="sobretitulo">Tratamentos</p>
                <h2 class="titulo-secao" id="titulo-tratamentos">Do check-up ao sorriso novo</h2>
            </div>
            <a href="servicos.php" class="link-seta">Ver detalhes de cada tratamento <span aria-hidden="true">→</span></a>
        </div>

        <ul class="lista-servicos">
            <?php foreach ($servicos as $i => $s): ?>
                <li data-revelar>
                    <a class="item-servico" href="servicos.php#<?= e($s['slug']) ?>">
                        <span class="item-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="item-corpo">
                            <span class="item-nome"><?= e($s['nome']) ?></span>
                            <span class="item-desc"><?= e($s['descricao']) ?></span>
                        </span>
                        <span class="item-seta" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="secao" aria-labelledby="titulo-sobre">
    <div class="container">
        <div class="faixa-sobre">
            <div class="row gy-5 align-items-end">
                <div class="col-lg-7">
                    <h2 class="visually-hidden" id="titulo-sobre">Sobre a <?= e(DENTISTA_NOME) ?></h2>
                    <figure class="citacao" data-revelar>
                        <blockquote><p><?= e(CITACAO_DENTISTA) ?></p></blockquote>
                        <figcaption><strong><?= e(DENTISTA_NOME) ?></strong> <span><?= e(DENTISTA_CRO) ?></span></figcaption>
                    </figure>
                </div>
                <div class="col-lg-4 offset-lg-1">
                    <p class="sobretitulo">Formação</p>
                    <ul class="formacao-curta">
                        <?php foreach (FORMACAO_DENTISTA as $f): ?>
                            <li><span><?= e($f['ano']) ?></span><?= e($f['titulo']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="sobre.php" class="link-seta">Conheça a Dra. Fabielle <span aria-hidden="true">→</span></a>
                </div>
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
                <h2 class="titulo-secao" id="titulo-consultorio">Onde você vai ser atendido</h2>
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

<section class="secao secao-sem-topo" aria-labelledby="titulo-cta">
    <div class="container">
        <div class="cta-bloco">
            <div class="row gy-5 align-items-center">
                <div class="col-lg-6">
                    <h2 class="titulo-secao" id="titulo-cta">Escolha o dia e o horário direto pelo site.</h2>
                    <p>Você vê só os horários realmente livres, já considerando o tempo de cada tratamento. Leva menos de um minuto.</p>
                    <div class="hero-acoes">
                        <a href="agendar.php" class="btn-claro btn-grande">Ver horários livres <span class="seta" aria-hidden="true">→</span></a>
                        <a href="<?= e(linkWhatsApp()) ?>" class="link-claro" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(CLINICA_TELEFONE) ?></a>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1">
                    <p class="situacao-pilula <?= $situacao['aberto'] ? 'is-aberto' : '' ?>"><span aria-hidden="true"></span><?= e($situacao['texto']) ?></p>
                    <dl class="horarios-escuro">
                        <?php foreach ($horarios as $h): ?>
                            <div class="<?= $h['hoje'] ? 'is-hoje' : '' ?> <?= $h['fechado'] ? 'is-fechado' : '' ?>">
                                <dt><?= e($h['dias']) ?><?= $h['hoje'] ? ' <small>hoje</small>' : '' ?></dt>
                                <dd><?= e($h['horas']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                    <p class="cta-endereco"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e(CLINICA_ENDERECO) ?> · <?= e(CLINICA_BAIRRO_CIDADE) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
