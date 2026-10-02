<?php
// Sessão, proteção de páginas e CSRF do painel admin.
// Toda página do admin começa com: require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/agenda.php';

iniciarSessao('DRFABI_ADMIN');

// Páginas do painel nunca ficam em cache (dados de pacientes)
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

const SESSAO_TIMEOUT = 2 * 60 * 60; // 2h sem atividade desloga

function estaLogado(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    if (time() - ($_SESSION['ultima_atividade'] ?? 0) > SESSAO_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        return false;
    }
    $_SESSION['ultima_atividade'] = time();
    return true;
}

/** Chame no topo de toda página protegida. */
// Senha criada pelo database/schema.sql. Quem entra com ela é levado a trocar.
const SENHA_PADRAO = 'admin123';

function exigirLogin(): void
{
    if (!estaLogado()) {
        header('Location: login.php');
        exit;
    }
    // Enquanto a senha padrão não for trocada, só a tela de senha fica acessível
    if (!empty($_SESSION['trocar_senha']) && basename($_SERVER['PHP_SELF']) !== 'senha.php') {
        header('Location: senha.php');
        exit;
    }
}

/** Mensagem única exibida na próxima página (sucesso/erro). */
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function pegarFlash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Redireciona e encerra. */
function irPara(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Endereço de retorno vindo de formulário (?voltar=agenda.php?semana=...).
 * Só aceita páginas do próprio painel, para não virar redirecionamento aberto.
 */
function destinoSeguro(?string $url, string $padrao = 'index.php'): string
{
    $url = (string) $url;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%\-.:]*)?$/', $url) ? $url : $padrao;
}

/** Selo de status do agendamento. */
function seloStatus(string $status): string
{
    return '<span class="selo selo-' . e($status) . '">' . e(STATUS_AGENDAMENTO[$status] ?? $status) . '</span>';
}
