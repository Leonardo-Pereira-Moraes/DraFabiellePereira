<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/agenda.php';

$paginaAtual     = 'contato';
$tituloPagina    = 'Contato';
$descricaoPagina = 'Endereço, telefone, WhatsApp e horário de atendimento da ' . DENTISTA_NOME . '.';

$situacao = situacaoAgora($pdo);
$faixas   = faixasFuncionamento($pdo);
$hoje     = (int) date('w');
$mapaUrl  = 'https://www.google.com/maps?q=' . rawurlencode(CLINICA_MAPA_BUSCA) . '&z=17&hl=pt-BR&output=embed';
$rotaUrl  = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(CLINICA_MAPA_BUSCA);

require __DIR__ . '/includes/cabecalho.php';
?>

<header class="cabecalho-pagina">
    <div class="container">
        <div class="row gy-4 align-items-end">
            <div class="col-lg-7">
                <p class="sobretitulo">Contato</p>
                <h1 class="titulo-pagina">Como chegar e falar com a gente</h1>
            </div>
            <div class="col-lg-4 offset-lg-1">
                <p class="situacao-pilula situacao-clara <?= $situacao['aberto'] ? 'is-aberto' : '' ?>"><span aria-hidden="true"></span><?= e($situacao['texto']) ?></p>
            </div>
        </div>
    </div>
</header>

<section class="secao secao-compacta">
    <div class="container">
        <div class="row gy-5 gx-lg-5">
            <div class="col-lg-5">
                <div class="contato-blocos">
                    <div class="contato-bloco">
                        <h2>Endereço</h2>
                        <p><?= e(CLINICA_ENDERECO) ?><br><?= e(CLINICA_BAIRRO_CIDADE) ?></p>
                        <a href="<?= e($rotaUrl) ?>" class="link-seta" target="_blank" rel="noopener">Traçar rota no Google Maps <span aria-hidden="true">→</span></a>
                    </div>

                    <div class="contato-bloco">
                        <h2>Telefone e WhatsApp</h2>
                        <p class="contato-destaque"><a href="<?= e(linkTelefone()) ?>"><?= e(CLINICA_TELEFONE) ?></a></p>
                        <a href="<?= e(linkWhatsApp('Olá! Vim pelo site.')) ?>" class="link-seta" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> Abrir conversa <span aria-hidden="true">→</span></a>
                    </div>

                    <div class="contato-bloco">
                        <h2>E-mail</h2>
                        <p><a href="mailto:<?= e(CLINICA_EMAIL) ?>"><?= e(CLINICA_EMAIL) ?></a></p>
                    </div>

                    <div class="contato-bloco">
                        <h2>Horário de atendimento</h2>
                        <table class="tabela-horarios">
                            <tbody>
                            <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $dia): ?>
                                <tr class="<?= $dia === $hoje ? 'is-hoje' : '' ?> <?= $faixas[$dia] ? '' : 'is-fechado' ?>">
                                    <th scope="row"><?= e(mb_convert_case(DIAS_SEMANA[$dia], MB_CASE_TITLE, 'UTF-8')) ?><?= $dia === $hoje ? ' <span class="etiqueta-hoje">hoje</span>' : '' ?></th>
                                    <td><?= $faixas[$dia] ? e(implode(' · ', array_map(fn($f) => $f[0] . '–' . $f[1], $faixas[$dia]))) : 'Fechado' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="mapa">
                    <iframe src="<?= e($mapaUrl) ?>" title="Mapa com a localização do consultório" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </div>
                <div class="mapa-rodape">
                    <p>Consultas somente com hora marcada.</p>
                    <a href="agendar.php" class="btn-primario">Agendar consulta <span class="seta" aria-hidden="true">→</span></a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
