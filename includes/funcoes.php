<?php
// Funções de uso geral (site e painel).

const DIAS_SEMANA       = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
const DIAS_SEMANA_CURTO = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const MESES             = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const MESES_CURTO       = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/** Escapa texto para saída em HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Formata preço no padrão brasileiro: 129.9 -> "R$ 129,90". */
function formatarPreco(float|string $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** 90 -> "1h30", 60 -> "1h", 45 -> "45 min". */
function formatarDuracao(int $minutos): string
{
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    return $h . 'h' . ($m ? str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

function soDigitos(string $texto): string
{
    return preg_replace('/\D+/', '', $texto);
}

/**
 * Deixa o telefone só com DDD + número (10 ou 11 dígitos).
 * Aceita com ou sem +55, parênteses, traço. Retorna null se inválido.
 */
function normalizarTelefone(string $telefone): ?string
{
    $d = soDigitos($telefone);
    if (strlen($d) >= 12 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && str_starts_with($d, '0')) {
        $d = substr($d, 1); // 0 + DDD + fixo
    }
    if (!preg_match('/^[1-9]{2}9?\d{8}$/', $d)) {
        return null;
    }
    return $d;
}

/** "64992927610" -> "(64) 99292-7610". */
function formatarTelefone(string $digitos): string
{
    if (strlen($digitos) === 11) {
        return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7));
    }
    if (strlen($digitos) === 10) {
        return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
    }
    return $digitos;
}

function linkWhatsApp(string $mensagem = '', string $numero = WHATSAPP_NUMERO): string
{
    return 'https://wa.me/' . $numero . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}

function linkTelefone(): string
{
    return 'tel:+' . soDigitos(WHATSAPP_NUMERO);
}

/** Caminho do arquivo se ele existir na raiz do site; senão null. */
function arquivoSeExistir(string $relativo): ?string
{
    return is_file(__DIR__ . '/../' . $relativo) ? $relativo : null;
}

/** Valor da tabela configuracoes (carregada uma vez por requisição). */
function cfg(string $chave, string $padrao = ''): string
{
    static $valores = null;
    if ($valores === null) {
        global $pdo;
        $valores = $pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $valores[$chave] ?? $padrao;
}

/** "Cirurgia Oral" -> "cirurgia-oral" (sem depender de iconv/intl). */
function gerarSlug(string $texto): string
{
    $mapa = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ];
    $s = strtr(mb_strtolower(trim($texto), 'UTF-8'), $mapa);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// ---------------------------------------------------------------------
// Datas em português (sem depender da extensão intl)
// ---------------------------------------------------------------------

/** "2026-10-01" -> "qua, 01 out". */
function dataCurta(string $data): string
{
    $t = strtotime($data);
    return DIAS_SEMANA_CURTO[(int) date('w', $t)] . ', ' . date('d', $t) . ' ' . MESES_CURTO[(int) date('n', $t) - 1];
}

/** "2026-10-01" -> "quarta-feira, 1 de outubro". */
function dataExtenso(string $data, bool $comAno = false): string
{
    $t = strtotime($data);
    $texto = DIAS_SEMANA[(int) date('w', $t)] . ', ' . date('j', $t) . ' de ' . MESES[(int) date('n', $t) - 1];
    return $comAno ? $texto . ' de ' . date('Y', $t) : $texto;
}

/** "2026-10-01 09:00:00" -> "01/10/2026 09:00". */
function dataHoraBr(?string $dataHora): string
{
    return $dataHora ? date('d/m/Y H:i', strtotime($dataHora)) : '—';
}

/** "Hoje", "Amanhã" ou "qua, 01 out". */
function dataRelativa(string $data): string
{
    $d = date('Y-m-d', strtotime($data));
    if ($d === date('Y-m-d')) {
        return 'Hoje';
    }
    if ($d === date('Y-m-d', strtotime('+1 day'))) {
        return 'Amanhã';
    }
    return dataCurta($d);
}

/** Primeiro nome, com a inicial maiúscula. */
function primeiroNome(string $nome): string
{
    $p = explode(' ', trim($nome))[0] ?? '';
    return mb_convert_case(mb_strtolower($p, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}
