<?php
// GET api/disponibilidade.php?servico=ID&mes=YYYY-MM
// Horários livres de um serviço em cada dia do mês (só dias com vaga).
// Só devolve horários: nenhuma informação sobre quem ocupa a agenda.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/agenda.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

$servicoId = (int) ($_GET['servico'] ?? 0);
$mes       = (string) ($_GET['mes'] ?? date('Y-m'));

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
    responder(400, ['ok' => false, 'erro' => 'Mês inválido.']);
}

$stmt = $pdo->prepare('SELECT id, duracao_min FROM servicos WHERE id = ? AND ativo = 1');
$stmt->execute([$servicoId]);
$servico = $stmt->fetch();
if (!$servico) {
    responder(404, ['ok' => false, 'erro' => 'Serviço não encontrado.']);
}

[$janelaInicio, $janelaFim] = janelaAgendamento();
$de  = max("$mes-01", $janelaInicio);
$ate = min(date('Y-m-t', strtotime("$mes-01")), $janelaFim);

$dias = $de <= $ate
    ? disponibilidade($pdo, (int) $servico['duracao_min'], $de, $ate, minimoSite())
    : [];

responder(200, [
    'ok'     => true,
    'dias'   => (object) $dias,
    'janela' => ['inicio' => $janelaInicio, 'fim' => $janelaFim],
]);
