<?php
// Aviso de novo agendamento no WhatsApp da dentista, via CallMeBot (gratuito).
//
// Ativação (feita uma vez, pelo celular da Fabielle):
//   1. Salvar o contato do CallMeBot (número atual em
//      https://www.callmebot.com/blog/free-api-whatsapp-messages/ — hoje +34 644 95 42 75)
//   2. Mandar para ele no WhatsApp: I allow callmebot to send me messages
//   3. Ele responde com a apikey. Número e apikey vão no painel, em "Notificações".
// A apikey e o número ficam na tabela configuracoes (chaves whatsapp_aviso_*).

const CALLMEBOT_URL = 'https://api.callmebot.com/whatsapp.php';

function avisoWhatsAppConfigurado(): bool
{
    return cfg('whatsapp_aviso_ativo') === '1'
        && cfg('whatsapp_aviso_telefone') !== ''
        && cfg('whatsapp_aviso_apikey') !== '';
}

/**
 * Envia a mensagem pelo CallMeBot.
 * Retorna [ok, detalhe]. Nunca lança exceção: falha de aviso não pode
 * derrubar o agendamento.
 */
function enviarWhatsAppAviso(string $mensagem, ?string $telefone = null, ?string $apikey = null): array
{
    $telefone = soDigitos($telefone ?? cfg('whatsapp_aviso_telefone'));
    $apikey   = trim($apikey ?? cfg('whatsapp_aviso_apikey'));
    if ($telefone === '' || $apikey === '') {
        return [false, 'Número ou apikey não configurados.'];
    }

    $url = CALLMEBOT_URL . '?' . http_build_query([
        'phone'  => '+' . $telefone,
        'text'   => $mensagem,
        'apikey' => $apikey,
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'DraFabielle-Agenda/1.0',
    ]);
    $resposta = (string) curl_exec($ch);
    $erroCurl = curl_error($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($erroCurl !== '') {
        error_log("Aviso WhatsApp: falha de conexão: $erroCurl");
        return [false, 'Não foi possível conectar ao CallMeBot: ' . $erroCurl];
    }
    // O CallMeBot responde HTML; sucesso contém "Message queued" ou "Message Sent"
    $texto = trim(preg_replace('/\s+/', ' ', strip_tags($resposta)));
    if ($status === 200 && preg_match('/message (queued|sent)/i', $texto)) {
        return [true, 'Mensagem enviada.'];
    }
    error_log("Aviso WhatsApp: HTTP $status: " . mb_substr($texto, 0, 300));
    if (stripos($texto, 'apikey is invalid') !== false) {
        return [false, 'A apikey não confere com esse número. Tente o número sem o 9 depois do DDD (muitas contas de WhatsApp são registradas assim) ou refaça a ativação no CallMeBot.'];
    }
    if (stripos($texto, 'not registered') !== false || stripos($texto, 'not authorized') !== false) {
        return [false, 'Esse número ainda não foi ativado no CallMeBot. Siga os passos de ativação ao lado.'];
    }
    return [false, 'O CallMeBot recusou o envio (HTTP ' . $status . '). Tente de novo em alguns minutos.'];
}

/** Endereço base do site (ex.: https://drafabielle.com.br), a partir da requisição atual. */
function urlSite(): string
{
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $pasta  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $pasta  = preg_replace('#/(api|admin)$#', '', rtrim($pasta, '/'));
    return ($https ? 'https' : 'http') . '://' . $host . $pasta;
}

/** Mensagem de novo agendamento feito pelo site. */
function mensagemNovoAgendamento(int $agendamentoId, string $nome, string $telefone, string $servico, string $inicio, string $fim, ?string $obs): string
{
    $linhas = [
        '🦷 *Novo agendamento pelo site*',
        '',
        '*' . $nome . '*',
        $servico . ' · ' . dataCurta($inicio) . ', ' . date('H:i', strtotime($inicio)) . '–' . date('H:i', strtotime($fim)),
        'Tel: ' . formatarTelefone($telefone),
    ];
    if ($obs) {
        $linhas[] = 'Recado: ' . mb_strimwidth($obs, 0, 200, '…');
    }
    $linhas[] = '';
    $linhas[] = 'Ver no painel: ' . urlSite() . '/admin/agendamento.php?id=' . $agendamentoId;
    return implode("\n", $linhas);
}

/**
 * Devolve a resposta ao navegador e continua rodando em segundo plano,
 * para o paciente não esperar o envio do aviso.
 */
function encerrarRespostaAoCliente(): void
{
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // PHP-FPM (comum em hospedagens como a KingHost)
        return;
    }
    // Apache com mod_php: fecha a conexão manualmente. Esvazia todos os buffers
    // (inclusive o output_buffering do php.ini), senão o navegador fica esperando.
    $conteudo = '';
    while (ob_get_level()) {
        $conteudo = ob_get_clean() . $conteudo;
    }
    header('Content-Encoding: none'); // impede o Apache de comprimir e segurar a resposta
    header('Connection: close');
    header('Content-Length: ' . strlen($conteudo));
    echo $conteudo;
    flush();
}
