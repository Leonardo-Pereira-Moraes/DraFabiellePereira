<?php
// Rodapé do site público. Requer includes/agenda.php carregado (horários).
$horariosRodape = resumoHorarios($pdo);
?>
</main>

<footer class="rodape">
    <div class="container">
        <div class="row gy-5 gx-lg-5">
            <div class="col-lg-4">
                <a class="marca marca-rodape" href="index.php">
                    <span class="marca-monograma" aria-hidden="true">FP</span>
                    <span class="marca-texto">
                        <strong><?= e(CLINICA_NOME) ?></strong>
                        <small><?= e(CLINICA_SEGMENTO) ?></small>
                    </span>
                </a>
                <p class="rodape-texto">Consultas com hora marcada e tempo reservado para cada tratamento.</p>
            </div>

            <div class="col-6 col-lg-2">
                <h2 class="rodape-titulo">Site</h2>
                <ul class="rodape-lista">
                    <li><a href="servicos.php">Tratamentos</a></li>
                    <li><a href="sobre.php">Sobre a Dra.</a></li>
                    <li><a href="contato.php">Contato</a></li>
                    <li><a href="agendar.php">Agendar consulta</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h2 class="rodape-titulo">Contato</h2>
                <ul class="rodape-lista">
                    <li><a href="<?= e(linkWhatsApp()) ?>" target="_blank" rel="noopener">WhatsApp <?= e(CLINICA_TELEFONE) ?></a></li>
                    <li><a href="mailto:<?= e(CLINICA_EMAIL) ?>"><?= e(CLINICA_EMAIL) ?></a></li>
                    <li><?= e(CLINICA_ENDERECO) ?><br><?= e(CLINICA_BAIRRO_CIDADE) ?></li>
                    <?php if (INSTAGRAM_USUARIO !== ''): ?>
                        <li><a href="https://instagram.com/<?= e(INSTAGRAM_USUARIO) ?>" target="_blank" rel="noopener">@<?= e(INSTAGRAM_USUARIO) ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-3">
                <h2 class="rodape-titulo">Atendimento</h2>
                <dl class="rodape-horarios">
                    <?php foreach ($horariosRodape as $h): ?>
                        <div class="<?= $h['hoje'] ? 'is-hoje' : '' ?>">
                            <dt><?= e($h['dias']) ?></dt>
                            <dd><?= e($h['horas']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="rodape-legal">
            <p>© <?= date('Y') ?> <?= e(CLINICA_NOME) ?> · Responsável técnica: <?= e(DENTISTA_NOME) ?> · <?= e(DENTISTA_CRO) ?></p>
            <a href="admin/login.php" rel="nofollow">Área restrita</a>
        </div>
    </div>
</footer>

<a class="whats-flutuante" href="<?= e(linkWhatsApp('Olá! Vim pelo site e gostaria de uma informação.')) ?>" target="_blank" rel="noopener" aria-label="Conversar no WhatsApp">
    <i class="bi bi-whatsapp" aria-hidden="true"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js?v=1"></script>
<?php foreach ($scriptsPagina ?? [] as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
