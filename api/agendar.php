<?php
// POST api/agendar.php — confirma o agendamento feito pelo paciente no site.
// O horário é revalidado aqui dentro de uma transação com o dia travado,
// então dois pacientes nunca conseguem o mesmo horário, mesmo clicando juntos.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sessao_site.php';
require_once __DIR__ . '/../includes/agenda.php';
require_once __DIR__ . '/../includes/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
}
if (!csrfValido()) {
    responder(400, ['ok' => false, 'erro' => 'Sua sessão expirou. Recarregue a página e tente de novo.']);
}
// Campo invisível: só robôs preenchem
if (trim((string) ($_POST['empresa'] ?? '')) !== '') {
    responder(400, ['ok' => false, 'erro' => 'Não foi possível concluir o agendamento.']);
}

// Limite por visitante: no máximo N agendamentos por hora nesta sessão
$recentes = array_filter($_SESSION['agendamentos_feitos'] ?? [], fn($t) => $t > time() - 3600);
if (count($recentes) >= MAX_AGENDAMENTOS_POR_SESSAO_HORA) {
    responder(429, ['ok' => false, 'erro' => 'Você já fez vários agendamentos agora há pouco. Para marcar outro, fale com a gente pelo WhatsApp.']);
}

// ----- Validação dos campos -----
$servicoId = (int) ($_POST['servico_id'] ?? 0);
$data      = (string) ($_POST['data'] ?? '');
$hora      = (string) ($_POST['hora'] ?? '');
$nome      = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
$telefone  = normalizarTelefone((string) ($_POST['telefone'] ?? ''));
$email     = trim((string) ($_POST['email'] ?? ''));
$obs       = trim((string) ($_POST['obs'] ?? ''));

$erros = [];
$dataObj = DateTime::createFromFormat('!Y-m-d', $data);
if (!$dataObj || $dataObj->format('Y-m-d') !== $data || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $erros['horario'] = 'Escolha um dia e um horário.';
}
if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120 || !str_contains($nome, ' ')) {
    $erros['nome'] = 'Informe seu nome e sobrenome.';
}
if (!$telefone) {
    $erros['telefone'] = 'Informe um celular com DDD, ex.: (64) 99999-9999.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    $erros['email'] = 'Informe um e-mail válido.';
}
if (mb_strlen($obs) > 500) {
    $erros['obs'] = 'A observação pode ter no máximo 500 caracteres.';
}
if (empty($_POST['consentimento'])) {
    $erros['consentimento'] = 'Precisamos da sua autorização para entrar em contato sobre a consulta.';
}
if ($erros) {
    responder(422, ['ok' => false, 'erro' => 'Confira os campos destacados.', 'campos' => $erros]);
}

$stmt = $pdo->prepare('SELECT id, nome, duracao_min FROM servicos WHERE id = ? AND ativo = 1');
$stmt->execute([$servicoId]);
$servico = $stmt->fetch();
if (!$servico) {
    responder(422, ['ok' => false, 'erro' => 'Esse serviço não está mais disponível para agendamento online.']);
}

[$janelaInicio, $janelaFim] = janelaAgendamento();
if ($data < $janelaInicio || $data > $janelaFim) {
    responder(422, ['ok' => false, 'erro' => 'Essa data está fora do período de agendamento online.']);
}

// Horário que nem existe na grade (fora do atendimento, fora do intervalo, em cima da hora)
$passo = max(5, (int) cfg('intervalo_grade_min', '30'));
$grade = horariosLivresNoDia($data, (int) $servico['duracao_min'], faixasFuncionamento($pdo), [], $passo, minimoSite());
if (!in_array($hora, $grade, true)) {
    responder(422, ['ok' => false, 'erro' => 'Esse horário não está disponível para agendamento online. Escolha outro.']);
}

$inicio = "$data $hora:00";
$fim    = date('Y-m-d H:i:s', strtotime($inicio) + (int) $servico['duracao_min'] * 60);

// ----- Gravação protegida contra duplo agendamento -----
try {
    iniciarTransacaoAgenda($pdo, [$data]);

    // Revalida com o dia travado: mesma regra que gerou os horários na tela
    // (funcionamento, antecedência, bloqueios e outros agendamentos).
    $livres = disponibilidade($pdo, (int) $servico['duracao_min'], $data, $data, minimoSite())[$data] ?? [];
    if (!in_array($hora, $livres, true)) {
        $pdo->rollBack();
        responder(409, [
            'ok'     => false,
            'codigo' => 'horario_ocupado',
            'erro'   => 'Esse horário acabou de ser reservado. Escolha outro, por favor.',
        ]);
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM agendamentos a JOIN pacientes p ON p.id = a.paciente_id
          WHERE p.telefone = ? AND a.status = 'confirmado' AND a.inicio > NOW()"
    );
    $stmt->execute([$telefone]);
    if ((int) $stmt->fetchColumn() >= MAX_AGENDAMENTOS_FUTUROS_POR_TELEFONE) {
        $pdo->rollBack();
        responder(422, [
            'ok'   => false,
            'erro' => 'Este telefone já tem consultas marcadas. Para agendar outra, fale com a gente pelo WhatsApp.',
        ]);
    }

    // Paciente identificado pelo telefone. Um cadastro existente mantém o nome
    // (a clínica corrige no painel) e só ganha e-mail se ainda não tinha.
    $stmt = $pdo->prepare(
        'INSERT INTO pacientes (nome, telefone, email) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE email = COALESCE(email, VALUES(email)), id = LAST_INSERT_ID(id)'
    );
    $stmt->execute([$nome, $telefone, $email]);
    $pacienteId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO agendamentos (paciente_id, servico_id, servico_nome, inicio, fim, origem, obs_paciente)
         VALUES (?, ?, ?, ?, ?, 'site', ?)"
    )->execute([$pacienteId, $servico['id'], $servico['nome'], $inicio, $fim, $obs !== '' ? $obs : null]);
    $agendamentoId = (int) $pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao agendar: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível concluir agora. Tente novamente em instantes.']);
}

$recentes[] = time();
$_SESSION['agendamentos_feitos'] = array_values($recentes);

$local = CLINICA_ENDERECO . ' – ' . CLINICA_BAIRRO_CIDADE;
$resposta = [
    'ok'            => true,
    'primeiro_nome' => primeiroNome($nome),
    'servico'       => $servico['nome'],
    'data_extenso'  => dataExtenso($data),
    'hora'          => $hora,
    'hora_fim'      => date('H:i', strtotime($fim)),
    'google'        => linkGoogleAgenda(
        $servico['nome'] . ' · ' . CLINICA_NOME,
        $inicio,
        $fim,
        'Consulta agendada pelo site. Dúvidas: ' . CLINICA_TELEFONE,
        $local
    ),
    'whatsapp'      => linkWhatsApp('Olá! Acabei de agendar ' . $servico['nome'] . ' para ' . dataCurta($data) . " às $hora pelo site."),
];

if (!avisoWhatsAppConfigurado()) {
    responder(201, $resposta);
}

// Responde ao paciente primeiro; o aviso à dentista segue depois, sem fazê-lo esperar.
// O agendamento já está gravado: se o aviso falhar, só fica registrado no log.
session_write_close();
ob_start();
http_response_code(201);
echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
encerrarRespostaAoCliente();

enviarWhatsAppAviso(mensagemNovoAgendamento($agendamentoId, $nome, $telefone, $servico['nome'], $inicio, $fim, $obs !== '' ? $obs : null));
