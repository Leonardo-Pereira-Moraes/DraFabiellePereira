<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/sessao_site.php';
require_once __DIR__ . '/includes/agenda.php';

$paginaAtual     = 'agendar';
$tituloPagina    = 'Agendar consulta';
$descricaoPagina = 'Agende sua consulta com a ' . DENTISTA_NOME . ' online: escolha o tratamento, o dia e o horário livre.';
$scriptsPagina   = ['assets/js/agendar.js?v=1'];

$servicos     = $pdo->query('SELECT id, nome, slug, duracao_min, preco FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$exibirPrecos = cfg('exibir_precos') === '1';
$preSelecao   = (string) ($_GET['servico'] ?? '');
[$janelaInicio, $janelaFim] = janelaAgendamento();
$antecedencia = (int) cfg('antecedencia_min_horas', '2');
$situacao     = situacaoAgora($pdo);

require __DIR__ . '/includes/cabecalho.php';
?>

<header class="cabecalho-pagina cabecalho-compacto">
    <div class="container">
        <div class="row gy-3 align-items-end">
            <div class="col-lg-7">
                <p class="sobretitulo">Agendamento online</p>
                <h1 class="titulo-pagina">Agende sua consulta</h1>
            </div>
            <div class="col-lg-4 offset-lg-1">
                <p class="texto-secundario mb-0">Sem cadastro e sem senha. Você vê só os horários que estão realmente livres.</p>
            </div>
        </div>
    </div>
</header>

<section class="secao secao-compacta">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-7">
                <?php if (!$servicos): ?>
                    <div class="aviso">O agendamento online está temporariamente indisponível. Fale com a gente pelo <a href="<?= e(linkWhatsApp()) ?>">WhatsApp</a>.</div>
                <?php else: ?>
                <form id="agendamento" class="etapas" novalidate
                      data-api-disp="api/disponibilidade.php" data-api-agendar="api/agendar.php"
                      data-janela-inicio="<?= e($janelaInicio) ?>" data-janela-fim="<?= e($janelaFim) ?>"
                      data-whatsapp="<?= e(linkWhatsApp('Olá! Não encontrei horário no site, pode me ajudar?')) ?>">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="servico_id" value="">
                    <input type="hidden" name="data" value="">
                    <input type="hidden" name="hora" value="">

                    <div class="aviso" role="alert" hidden data-aviso></div>

                    <!-- 1. Tratamento -->
                    <section class="etapa is-ativa" data-etapa="1" aria-labelledby="etapa1">
                        <div class="etapa-marcador" aria-hidden="true"><span>1</span><i class="bi bi-check2"></i></div>
                        <div class="etapa-cabeca">
                            <h2 id="etapa1" tabindex="-1">Tratamento</h2>
                            <p class="etapa-resumo" data-resumo></p>
                            <button type="button" class="etapa-alterar" data-alterar="1">Alterar</button>
                        </div>
                        <div class="etapa-corpo">
                            <fieldset>
                                <legend class="visually-hidden">Escolha o tratamento</legend>
                                <div class="opcoes-servico">
                                    <?php foreach ($servicos as $s): ?>
                                        <label class="opcao">
                                            <input type="radio" name="servico_escolha" value="<?= (int) $s['id'] ?>"
                                                   data-nome="<?= e($s['nome']) ?>"
                                                   data-duracao="<?= e(formatarDuracao((int) $s['duracao_min'])) ?>"
                                                   <?= $preSelecao === $s['slug'] ? 'data-pre-selecao' : '' ?>>
                                            <span class="opcao-nome"><?= e($s['nome']) ?></span>
                                            <span class="opcao-meta">
                                                <?= e(formatarDuracao((int) $s['duracao_min'])) ?>
                                                <?php if ($exibirPrecos && $s['preco'] !== null): ?> · a partir de <?= e(formatarPreco($s['preco'])) ?><?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <!-- 2. Dia e horário -->
                    <section class="etapa is-pendente" data-etapa="2" aria-labelledby="etapa2">
                        <div class="etapa-marcador" aria-hidden="true"><span>2</span><i class="bi bi-check2"></i></div>
                        <div class="etapa-cabeca">
                            <h2 id="etapa2" tabindex="-1">Dia e horário</h2>
                            <p class="etapa-resumo" data-resumo></p>
                            <button type="button" class="etapa-alterar" data-alterar="2">Alterar</button>
                        </div>
                        <div class="etapa-corpo" hidden>
                            <div class="escolha-horario">
                                <div class="calendario" data-calendario aria-live="polite"></div>
                                <div class="horarios-painel" data-horarios aria-live="polite"></div>
                            </div>
                        </div>
                    </section>

                    <!-- 3. Seus dados -->
                    <section class="etapa is-pendente" data-etapa="3" aria-labelledby="etapa3">
                        <div class="etapa-marcador" aria-hidden="true"><span>3</span><i class="bi bi-check2"></i></div>
                        <div class="etapa-cabeca">
                            <h2 id="etapa3" tabindex="-1">Seus dados</h2>
                        </div>
                        <div class="etapa-corpo" hidden>
                            <div class="row g-3">
                                <div class="col-12 campo">
                                    <label for="nome">Nome completo</label>
                                    <input type="text" class="form-control" id="nome" name="nome" autocomplete="name" maxlength="120" required>
                                    <p class="campo-erro" data-erro="nome"></p>
                                </div>
                                <div class="col-md-6 campo">
                                    <label for="telefone">Celular com DDD <small>(WhatsApp)</small></label>
                                    <input type="tel" class="form-control" id="telefone" name="telefone" autocomplete="tel-national" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="16" required>
                                    <p class="campo-erro" data-erro="telefone"></p>
                                </div>
                                <div class="col-md-6 campo">
                                    <label for="email">E-mail</label>
                                    <input type="email" class="form-control" id="email" name="email" autocomplete="email" maxlength="160" required>
                                    <p class="campo-erro" data-erro="email"></p>
                                </div>
                                <div class="col-12 campo">
                                    <label for="obs">Quer contar algo antes da consulta? <small>(opcional)</small></label>
                                    <textarea class="form-control" id="obs" name="obs" rows="3" maxlength="500" placeholder="Ex.: sinto sensibilidade no dente de trás, tenho medo de anestesia..."></textarea>
                                    <p class="campo-erro" data-erro="obs"></p>
                                </div>

                                <!-- Campo armadilha para robôs: fica invisível para pessoas -->
                                <div class="campo-armadilha" aria-hidden="true">
                                    <label for="empresa">Empresa</label>
                                    <input type="text" id="empresa" name="empresa" tabindex="-1" autocomplete="off">
                                </div>

                                <div class="col-12 campo">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consentimento" name="consentimento" value="1" required>
                                        <label class="form-check-label" for="consentimento">
                                            Autorizo o consultório a usar meus dados para confirmar e falar sobre esta consulta.
                                        </label>
                                    </div>
                                    <p class="campo-erro" data-erro="consentimento"></p>
                                </div>
                            </div>

                            <div class="confirmar-linha">
                                <p class="confirmar-resumo" data-resumo-final></p>
                                <button type="submit" class="btn-primario btn-grande" data-confirmar>
                                    <span class="btn-rotulo">Confirmar agendamento</span>
                                    <span class="btn-carregando" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </section>
                </form>

                <div class="agendado" hidden data-sucesso tabindex="-1"></div>
                <?php endif; ?>
            </div>

            <aside class="col-lg-4 offset-lg-1">
                <div class="aside-agendar">
                    <h2>Antes da consulta</h2>
                    <ul class="lista-dicas">
                        <li>Chegue uns 10 minutos antes do horário.</li>
                        <li>Traga um documento com foto e, se tiver, radiografias ou exames recentes.</li>
                        <li>Precisa remarcar? Avise pelo WhatsApp com pelo menos 24h de antecedência.</li>
                        <li>Agendamentos online com no mínimo <?= $antecedencia ?>h de antecedência. Para hoje, chame no WhatsApp.</li>
                    </ul>
                    <div class="aside-rodape">
                        <p class="situacao-pilula situacao-clara <?= $situacao['aberto'] ? 'is-aberto' : '' ?>"><span aria-hidden="true"></span><?= e($situacao['texto']) ?></p>
                        <p class="aside-endereco"><?= e(CLINICA_ENDERECO) ?><br><?= e(CLINICA_BAIRRO_CIDADE) ?></p>
                        <a href="<?= e(linkWhatsApp('Olá! Tenho uma dúvida sobre o agendamento.')) ?>" class="link-seta" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(CLINICA_TELEFONE) ?> <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
