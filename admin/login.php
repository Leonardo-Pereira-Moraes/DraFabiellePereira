<?php
require_once __DIR__ . '/includes/auth.php';

if (estaLogado()) {
    irPara('index.php');
}

const MAX_TENTATIVAS = 5;
const BLOQUEIO_SEGUNDOS = 5 * 60;

$erro = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    $bloqueadoAte = $_SESSION['login_bloqueado_ate'] ?? 0;
    if ($bloqueadoAte > time()) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    } elseif ($usuario === '' || $senha === '') {
        $erro = 'Informe usuário e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE usuario = ?');
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate']);
            $_SESSION['admin_id']         = (int) $admin['id'];
            $_SESSION['admin_nome']       = $admin['nome'];
            $_SESSION['ultima_atividade'] = time();

            // Atualiza o hash se o PHP passou a usar um algoritmo/custo melhor
            if (password_needs_rehash($admin['senha_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $admin['id']]);
            }
            $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$admin['id']]);

            // Ainda com a senha padrão do schema.sql: obriga a trocar antes de usar o painel
            if ($senha === SENHA_PADRAO) {
                $_SESSION['trocar_senha'] = true;
                flash('erro', 'Por segurança, troque a senha padrão antes de continuar.');
                irPara('senha.php');
            }
            irPara('index.php');
        }

        $_SESSION['login_tentativas'] = ($_SESSION['login_tentativas'] ?? 0) + 1;
        if ($_SESSION['login_tentativas'] >= MAX_TENTATIVAS) {
            $_SESSION['login_bloqueado_ate'] = time() + BLOQUEIO_SEGUNDOS;
            $_SESSION['login_tentativas'] = 0;
        }
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <title>Entrar · Painel · <?= e(CLINICA_NOME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css?v=3" rel="stylesheet">
</head>
<body class="pagina-login">
<main class="login-caixa">
    <div class="lateral-marca">
        <span class="marca-monograma" aria-hidden="true">FP</span>
        <div>
            <strong><?= e(CLINICA_NOME) ?></strong>
            <small>Painel da clínica</small>
        </div>
    </div>

    <h1>Entrar</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><span><?= e($erro) ?></span></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
        <?= csrfCampo() ?>
        <div class="campo">
            <label for="usuario">Usuário</label>
            <input type="text" class="form-control" id="usuario" name="usuario" value="<?= e($usuario) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-primario w-100">Entrar</button>
    </form>
    <a href="../index.php" class="login-voltar">← Voltar para o site</a>
</main>
</body>
</html>
