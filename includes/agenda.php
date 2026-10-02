<?php
// Regras da agenda: cálculo de horários livres e gravação sem duplo agendamento.
// Usado pelo site (api/) e pelo painel (admin/).
//
// Um intervalo [inicio, fim) conflita com outro quando:
//     inicio < fim_do_outro  E  fim > inicio_do_outro
// Por isso um serviço de 60 min às 09:00 ocupa 09:00–10:00 e impede
// tanto 09:30 quanto 08:30 para um serviço que terminaria depois das 09:00.

const TIPOS_BLOQUEIO = [
    'ferias'      => 'Férias',
    'folga'       => 'Folga',
    'compromisso' => 'Compromisso',
    'outro'       => 'Outro',
];

const STATUS_AGENDAMENTO = [
    'confirmado' => 'Confirmado',
    'concluido'  => 'Concluído',
    'faltou'     => 'Faltou',
    'cancelado'  => 'Cancelado',
];

/** Faixas de funcionamento por dia da semana: [1 => [['08:00','12:00'], ...], ...]. */
function faixasFuncionamento(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $faixas = array_fill(0, 7, []);
    $rows = $pdo->query('SELECT dia_semana, abre, fecha FROM horarios_funcionamento ORDER BY dia_semana, abre');
    foreach ($rows as $f) {
        $faixas[(int) $f['dia_semana']][] = [substr($f['abre'], 0, 5), substr($f['fecha'], 0, 5)];
    }
    return $cache = $faixas;
}

/**
 * Tudo o que ocupa a agenda entre $de e $ate: agendamentos não cancelados e bloqueios.
 * Cada item: ['tipo' => 'agendamento'|'bloqueio', 'id', 'inicio', 'fim', 'rotulo'].
 * $ignorarId: agendamento sendo editado (não conflita consigo mesmo).
 * ATENÇÃO: 'rotulo' tem dados do paciente, nunca devolva ao site público.
 */
function ocupacoes(PDO $pdo, string $de, string $ate, int $ignorarId = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT 'agendamento' AS tipo, a.id, a.inicio, a.fim,
                CONCAT(p.nome, ' · ', a.servico_nome) AS rotulo
           FROM agendamentos a
           JOIN pacientes p ON p.id = a.paciente_id
          WHERE a.status <> 'cancelado' AND a.inicio < ? AND a.fim > ? AND a.id <> ?
         UNION ALL
         SELECT 'bloqueio', b.id, b.inicio, b.fim, COALESCE(NULLIF(b.motivo, ''), b.tipo)
           FROM bloqueios b
          WHERE b.inicio < ? AND b.fim > ?
         ORDER BY inicio"
    );
    $stmt->execute([$ate, $de, $ignorarId, $ate, $de]);
    return array_map(function ($o) {
        if ($o['tipo'] === 'bloqueio') {
            $o['rotulo'] = 'Bloqueio: ' . (TIPOS_BLOQUEIO[$o['rotulo']] ?? $o['rotulo']);
        }
        return $o;
    }, $stmt->fetchAll());
}

/** Converte ocupações em pares de timestamps [inicio, fim]. */
function intervalos(array $ocupacoes): array
{
    return array_map(fn($o) => [strtotime($o['inicio']), strtotime($o['fim'])], $ocupacoes);
}

function sobrepoe(int $inicio, int $fim, array $intervalos): bool
{
    foreach ($intervalos as [$oInicio, $oFim]) {
        if ($inicio < $oFim && $fim > $oInicio) {
            return true;
        }
    }
    return false;
}

/**
 * Horários de início livres ("HH:MM") de um dia para um serviço de $duracaoMin.
 * O serviço precisa caber inteiro dentro de uma faixa de funcionamento.
 * $minimo: timestamp a partir do qual se pode marcar (agora + antecedência).
 */
function horariosLivresNoDia(string $data, int $duracaoMin, array $faixas, array $intervalos, int $passoMin, int $minimo): array
{
    $dia    = (int) date('w', strtotime($data));
    $dur    = $duracaoMin * 60;
    $passo  = $passoMin * 60;
    $livres = [];

    foreach ($faixas[$dia] as [$abre, $fecha]) {
        $limite = strtotime("$data $fecha");
        for ($t = strtotime("$data $abre"); $t + $dur <= $limite; $t += $passo) {
            if ($t >= $minimo && !sobrepoe($t, $t + $dur, $intervalos)) {
                $livres[] = date('H:i', $t);
            }
        }
    }
    return $livres;
}

/** Primeiro e último dia que o site aceita agendamento (Y-m-d). */
function janelaAgendamento(): array
{
    $dias = max(1, (int) cfg('dias_max_agendamento', '60'));
    return [date('Y-m-d'), date('Y-m-d', strtotime("+$dias days"))];
}

/** Timestamp mínimo para o site: agora + antecedência configurada. */
function minimoSite(): int
{
    return time() + (int) cfg('antecedencia_min_horas', '2') * 3600;
}

/**
 * Horários livres de cada dia do período (só dias com vaga):
 *   ['2026-10-01' => ['08:00', '08:30', ...], ...]
 * $minimo = 0 no painel (a clínica pode registrar atendimento retroativo).
 */
function disponibilidade(PDO $pdo, int $duracaoMin, string $de, string $ate, int $minimo, int $ignorarId = 0): array
{
    $faixas     = faixasFuncionamento($pdo);
    $fimPeriodo = date('Y-m-d', strtotime("$ate +1 day"));
    $intervalos = intervalos(ocupacoes($pdo, "$de 00:00:00", "$fimPeriodo 00:00:00", $ignorarId));
    $passo      = max(5, (int) cfg('intervalo_grade_min', '30'));

    $dias = [];
    for ($t = strtotime($de); $t <= strtotime($ate); $t = strtotime('+1 day', $t)) {
        $data   = date('Y-m-d', $t);
        $livres = horariosLivresNoDia($data, $duracaoMin, $faixas, $intervalos, $passo, $minimo);
        if ($livres) {
            $dias[$data] = $livres;
        }
    }
    return $dias;
}

/** O intervalo cabe inteiro dentro de alguma faixa de funcionamento do dia? */
function dentroDoFuncionamento(PDO $pdo, string $data, string $horaInicio, string $horaFim): bool
{
    $dia = (int) date('w', strtotime($data));
    foreach (faixasFuncionamento($pdo)[$dia] as [$abre, $fecha]) {
        if ($horaInicio >= $abre && $horaFim <= $fecha) {
            return true;
        }
    }
    return false;
}

/** Todas as datas (Y-m-d) tocadas pelo intervalo [inicio, fim). */
function datasDoIntervalo(string $inicio, string $fim): array
{
    $datas  = [];
    $ultimo = date('Y-m-d', strtotime($fim) - 1);
    for ($t = strtotime(date('Y-m-d', strtotime($inicio))); date('Y-m-d', $t) <= $ultimo; $t = strtotime('+1 day', $t)) {
        $datas[] = date('Y-m-d', $t);
    }
    return $datas;
}

/**
 * Abre a transação da agenda e trava os dias informados.
 *
 * Enquanto a transação estiver aberta, ninguém mais grava nesses dias:
 * um segundo pedido para a mesma data espera aqui e, quando segue,
 * já enxerga o agendamento do primeiro. Toda revalidação de horário
 * precisa acontecer DEPOIS desta chamada, e o chamador faz commit/rollBack.
 */
function iniciarTransacaoAgenda(PDO $pdo, array $datas): void
{
    $datas = array_values(array_unique($datas));
    sort($datas); // sempre na mesma ordem: evita deadlock entre travas de vários dias

    // As linhas de trava são criadas fora da transação: dois INSERTs
    // concorrentes da mesma data dentro dela podem gerar deadlock.
    $criar = $pdo->prepare('INSERT IGNORE INTO agenda_travas (data) VALUES (?)');
    foreach ($datas as $d) {
        $criar->execute([$d]);
    }

    // READ COMMITTED: as leituras feitas depois da trava enxergam
    // o que outras transações já confirmaram (e não um retrato antigo).
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();

    $travar = $pdo->prepare('SELECT data FROM agenda_travas WHERE data = ? FOR UPDATE');
    foreach ($datas as $d) {
        $travar->execute([$d]);
        $travar->fetchAll();
    }
}

/**
 * Horários vagos do dia de hoje em diante para o card "próximo horário livre".
 * Retorna ['data' => ..., 'hora' => ...] ou null.
 */
function proximoHorarioLivre(PDO $pdo, int $duracaoMin, int $diasBusca = 21): ?array
{
    [$de, $ateJanela] = janelaAgendamento();
    $ate  = min($ateJanela, date('Y-m-d', strtotime("+$diasBusca days")));
    $dias = disponibilidade($pdo, $duracaoMin, $de, $ate, minimoSite());
    foreach ($dias as $data => $horas) {
        return ['data' => $data, 'hora' => $horas[0]];
    }
    return null;
}

/**
 * Situação agora: ['aberto' => bool, 'texto' => 'Aberto agora · até 18:00'].
 * Considera bloqueios em andamento (férias, folga).
 */
function situacaoAgora(PDO $pdo): array
{
    $agora  = time();
    $faixas = faixasFuncionamento($pdo);

    $stmt = $pdo->prepare('SELECT fim FROM bloqueios WHERE inicio <= ? AND fim > ? ORDER BY fim DESC LIMIT 1');
    $stmt->execute([date('Y-m-d H:i:s', $agora), date('Y-m-d H:i:s', $agora)]);
    $bloqueioAte = $stmt->fetchColumn();

    if (!$bloqueioAte) {
        $hoje = date('Y-m-d', $agora);
        $hora = date('H:i', $agora);
        foreach ($faixas[(int) date('w', $agora)] as [$abre, $fecha]) {
            if ($hora >= $abre && $hora < $fecha) {
                return ['aberto' => true, 'texto' => "Aberto agora · até $fecha"];
            }
        }
    }

    // Próxima abertura nos próximos 14 dias (a partir do fim do bloqueio, se houver)
    $desde = $bloqueioAte ? strtotime($bloqueioAte) : $agora;
    for ($i = 0; $i < 14; $i++) {
        $t    = strtotime(date('Y-m-d', $desde) . " +$i days");
        $data = date('Y-m-d', $t);
        foreach ($faixas[(int) date('w', $t)] as [$abre]) {
            if (strtotime("$data $abre") > $desde) {
                if ($data === date('Y-m-d', $agora)) {
                    $quando = 'hoje';
                } elseif ($data === date('Y-m-d', strtotime('+1 day', $agora))) {
                    $quando = 'amanhã';
                } else {
                    $quando = DIAS_SEMANA_CURTO[(int) date('w', $t)];
                    if ($t - $agora > 6 * 86400) {
                        $quando .= ' ' . date('d/m', $t);
                    }
                }
                return ['aberto' => false, 'texto' => "Fechado agora · abre $quando às $abre"];
            }
        }
    }
    return ['aberto' => false, 'texto' => 'Fechado no momento'];
}

/**
 * Horário semanal agrupado para exibição:
 *   [['dias' => 'Seg a Sex', 'horas' => '08:00–12:00 · 14:00–18:00', 'hoje' => bool], ...]
 */
function resumoHorarios(PDO $pdo): array
{
    $faixas = faixasFuncionamento($pdo);
    $nomes  = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $ordem  = [1, 2, 3, 4, 5, 6, 0];
    $hoje   = (int) date('w');

    $grupos = [];
    foreach ($ordem as $dia) {
        $horas = $faixas[$dia]
            ? implode(' · ', array_map(fn($f) => $f[0] . '–' . $f[1], $faixas[$dia]))
            : 'Fechado';
        $ultimo = array_key_last($grupos);
        if ($ultimo !== null && $grupos[$ultimo]['horas'] === $horas) {
            $grupos[$ultimo]['ate'] = $dia;
            $grupos[$ultimo]['hoje'] = $grupos[$ultimo]['hoje'] || $dia === $hoje;
        } else {
            $grupos[] = ['de' => $dia, 'ate' => $dia, 'horas' => $horas, 'hoje' => $dia === $hoje];
        }
    }

    return array_map(fn($g) => [
        'dias'    => $g['de'] === $g['ate'] ? $nomes[$g['de']] : $nomes[$g['de']] . ' a ' . $nomes[$g['ate']],
        'horas'   => $g['horas'],
        'hoje'    => $g['hoje'],
        'fechado' => $g['horas'] === 'Fechado',
    ], $grupos);
}

/** Link "adicionar ao Google Agenda" para o paciente. */
function linkGoogleAgenda(string $titulo, string $inicio, string $fim, string $detalhes, string $local): string
{
    $fmt = fn($d) => gmdate('Ymd\THis\Z', strtotime($d));
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $titulo,
        'dates'    => $fmt($inicio) . '/' . $fmt($fim),
        'details'  => $detalhes,
        'location' => $local,
    ]);
}
