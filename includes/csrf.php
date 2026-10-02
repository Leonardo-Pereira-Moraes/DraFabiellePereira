<?php
// Proteção CSRF compartilhada entre o site (agendamento) e o painel.
// Requer uma sessão já iniciada (ver includes/sessao_site.php e admin/includes/auth.php).

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function csrfValido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && $enviado !== '' && hash_equals(csrfToken(), $enviado);
}

/** Aborta a requisição se o token CSRF do POST não bater. */
function validarCsrf(): void
{
    if (!csrfValido()) {
        http_response_code(400);
        die('Sessão expirada ou requisição inválida. Volte e tente novamente.');
    }
}

/** Inicia a sessão com cookie seguro. */
function iniciarSessao(string $nome): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_name($nome);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
