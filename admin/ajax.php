<?php
// Consultas rápidas usadas pelo JavaScript do painel (só leitura, só logado).
//   ?acao=dia&data=YYYY-MM-DD&duracao=60&ignorar=ID  -> horários livres + ocupação do dia
//   ?acao=paciente&telefone=...                       -> cadastro existente pelo telefone
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!estaLogado()) {
    responder(401, ['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.']);
}

switch ($_GET['acao'] ?? '') {
    case 'dia':
        $data    = (string) ($_GET['data'] ?? '');
        $duracao = max(5, min(600, (int) ($_GET['duracao'] ?? 30)));
        $ignorar = (int) ($_GET['ignorar'] ?? 0);
        $d = DateTime::createFromFormat('!Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            responder(400, ['ok' => false, 'erro' => 'Data inválida.']);
        }
        $amanha = date('Y-m-d', strtotime("$data +1 day"));
        $ocup   = array_map(fn($o) => [
            'tipo'   => $o['tipo'],
            'inicio' => substr($o['inicio'], 0, 10) < $data ? '00:00' : date('H:i', strtotime($o['inicio'])),
            'fim'    => date('Y-m-d', strtotime($o['fim'])) > $data ? '24:00' : date('H:i', strtotime($o['fim'])),
            'rotulo' => $o['rotulo'],
        ], ocupacoes($pdo, "$data 00:00:00", "$amanha 00:00:00", $ignorar));

        $faixas = faixasFuncionamento($pdo)[(int) $d->format('w')];
        responder(200, [
            'ok'        => true,
            'titulo'    => dataCurta($data),
            'livres'    => disponibilidade($pdo, $duracao, $data, $data, 0, $ignorar)[$data] ?? [],
            'ocupacoes' => $ocup,
            'faixas'    => array_map(fn($f) => $f[0] . '–' . $f[1], $faixas),
        ]);

    case 'paciente':
        $tel = normalizarTelefone((string) ($_GET['telefone'] ?? ''));
        if (!$tel) {
            responder(200, ['ok' => true, 'paciente' => null]);
        }
        $stmt = $pdo->prepare('SELECT id, nome, email FROM pacientes WHERE telefone = ?');
        $stmt->execute([$tel]);
        responder(200, ['ok' => true, 'paciente' => $stmt->fetch() ?: null]);

    default:
        responder(400, ['ok' => false, 'erro' => 'Ação inválida.']);
}
