<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $atual     = $_POST['senha_atual'] ?? '';
    $nova      = $_POST['senha_nova'] ?? '';
    $confirmar = $_POST['senha_confirmar'] ?? '';

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($atual, $hash)) {
        $erros[] = 'Senha atual incorreta.';
    }
    if (mb_strlen($nova) < 8) {
        $erros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
    }
    if ($nova !== $confirmar) {
        $erros[] = 'A confirmação não confere com a nova senha.';
    }
    if ($nova === SENHA_PADRAO || $nova === $atual) {
        $erros[] = 'Escolha uma senha diferente da atual.';
    }

    if (!$erros) {
        $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        session_regenerate_id(true);
        unset($_SESSION['trocar_senha']);
        flash('sucesso', 'Senha alterada com sucesso.');
        irPara('index.php');
    }
}

$tituloPagina = 'Alterar senha';
require __DIR__ . '/includes/topo.php';
?>
<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Alterar senha</h1>
        <p class="subtitulo">Use pelo menos 8 caracteres. Uma frase curta é mais fácil de lembrar e difícil de adivinhar.</p>
    </div>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="cartao" style="max-width: 30rem">
    <?= csrfCampo() ?>
    <div class="campo">
        <label for="senha_atual">Senha atual</label>
        <input type="password" class="form-control" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
    </div>
    <div class="campo">
        <label for="senha_nova">Nova senha</label>
        <input type="password" class="form-control" id="senha_nova" name="senha_nova" minlength="8" autocomplete="new-password" required>
    </div>
    <div class="campo">
        <label for="senha_confirmar">Confirmar nova senha</label>
        <input type="password" class="form-control" id="senha_confirmar" name="senha_confirmar" minlength="8" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn-primario">Salvar nova senha</button>
</form>
<?php require __DIR__ . '/includes/rodape.php'; ?>
