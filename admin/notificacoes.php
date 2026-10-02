<?php
// Aviso no WhatsApp da dentista quando um paciente agenda pelo site.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';
exigirLogin();

$erros = [];
$v = [
    'ativo'    => cfg('whatsapp_aviso_ativo') === '1',
    'telefone' => cfg('whatsapp_aviso_telefone'),
];
$temApikey = cfg('whatsapp_aviso_apikey') !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $v = [
        'ativo'    => !empty($_POST['ativo']),
        'telefone' => soDigitos((string) ($_POST['telefone'] ?? '')),
    ];
    $apikeyNova = trim((string) ($_POST['apikey'] ?? ''));
    $apikey     = $apikeyNova !== '' ? $apikeyNova : cfg('whatsapp_aviso_apikey');

    // Aceita com ou sem o 55: guarda sempre com o código do país
    if ($v['telefone'] !== '' && strlen($v['telefone']) <= 11) {
        $v['telefone'] = '55' . $v['telefone'];
    }
    if ($v['telefone'] !== '' && !preg_match('/^55[1-9]{2}9?\d{8}$/', $v['telefone'])) {
        $erros[] = 'Número inválido. Use DDD + número, ex.: (64) 99999-9999.';
    }
    if ($apikeyNova !== '' && !preg_match('/^[A-Za-z0-9]{4,32}$/', $apikeyNova)) {
        $erros[] = 'A apikey deve ter só letras e números, como veio na mensagem do CallMeBot.';
    }
    if ($v['ativo'] && ($v['telefone'] === '' || $apikey === '')) {
        $erros[] = 'Para ativar, preencha o número e a apikey.';
    }

    if (!$erros) {
        $salvar = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $salvar->execute(['whatsapp_aviso_ativo', $v['ativo'] ? '1' : '0']);
        $salvar->execute(['whatsapp_aviso_telefone', $v['telefone']]);
        $salvar->execute(['whatsapp_aviso_apikey', $apikey]);

        if (($_POST['acao'] ?? '') === 'testar') {
            [$ok, $detalhe] = enviarWhatsAppAviso(
                "✅ Teste do site " . CLINICA_NOME . ".\nOs avisos de novos agendamentos vão chegar aqui.",
                $v['telefone'],
                $apikey
            );
            flash($ok ? 'sucesso' : 'erro', $ok
                ? 'Configuração salva e mensagem de teste enviada. Confira o WhatsApp (pode levar alguns segundos).'
                : 'Configuração salva, mas o teste falhou. ' . $detalhe);
        } else {
            flash('sucesso', 'Configuração salva.');
        }
        irPara('notificacoes.php');
    }
}

$tituloPagina = 'Notificações';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Notificações</h1>
        <p class="subtitulo">Receba no WhatsApp um aviso a cada consulta marcada pelo site, com nome, tratamento, horário e telefone do paciente.</p>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <ul class="mb-0 ps-3"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-6">
        <form method="post" class="cartao">
            <?= csrfCampo() ?>
            <div class="cartao-cabeca">
                <h2>Aviso no WhatsApp</h2>
                <?php if (avisoWhatsAppConfigurado()): ?>
                    <span class="selo selo-concluido"><i class="bi bi-check2"></i> Ativo</span>
                <?php else: ?>
                    <span class="selo">Desligado</span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="telefone">WhatsApp que recebe os avisos</label>
                <input type="tel" class="form-control" id="telefone" name="telefone"
                       value="<?= e($v['telefone'] !== '' ? formatarTelefone(preg_replace('/^55/', '', $v['telefone'])) : '') ?>"
                       placeholder="(00) 00000-0000">
                <p class="campo-ajuda">O mesmo número que fez a ativação no CallMeBot. Se o teste disser que a apikey não confere, tente o número <strong>sem o 9</strong> depois do DDD, ex.: (64) 9229-5095. Muitas contas de WhatsApp fora de SP/RJ são registradas assim.</p>
            </div>
            <div class="campo">
                <label for="apikey">Apikey do CallMeBot</label>
                <input type="password" class="form-control" id="apikey" name="apikey" autocomplete="off"
                       placeholder="<?= $temApikey ? '•••••••• (já salva, deixe em branco para manter)' : 'ex.: 123456' ?>">
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= $v['ativo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="ativo">Enviar aviso a cada agendamento feito pelo site</label>
            </div>

            <div class="rodape-form">
                <button type="submit" name="acao" value="testar" class="btn-contorno"><i class="bi bi-send"></i> Salvar e enviar teste</button>
                <button type="submit" name="acao" value="salvar" class="btn-primario">Salvar</button>
            </div>
        </form>
    </div>

    <div class="col-xl-6">
        <section class="cartao">
            <h2 class="cartao-titulo">Como ativar (uma vez só)</h2>
            <ol class="passos-ativacao">
                <li>No celular da Dra., salve o contato <strong>+34 644 95 42 75</strong> (CallMeBot). Se não funcionar, confira o número atual na <a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank" rel="noopener">página oficial</a>.</li>
                <li>Mande para ele no WhatsApp exatamente: <code>I allow callmebot to send me messages</code></li>
                <li>Em até 2 minutos chega a resposta com a <strong>apikey</strong> (um número). Se não chegar, espere 24h antes de tentar de novo.</li>
                <li>Preencha o número e a apikey aqui, ligue o aviso e clique em <em>Salvar e enviar teste</em>.</li>
            </ol>
            <p class="campo-ajuda mb-0">
                O CallMeBot é um serviço gratuito de terceiros. Se ele estiver fora do ar, o agendamento do paciente
                continua funcionando normalmente, só o aviso não chega. Os agendamentos sempre aparecem no painel.
            </p>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
