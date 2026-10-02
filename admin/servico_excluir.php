<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    irPara('servicos.php');
}
validarCsrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT nome FROM servicos WHERE id = ?');
$stmt->execute([$id]);
$nome = $stmt->fetchColumn();

if ($nome === false) {
    flash('erro', 'Serviço não encontrado.');
    irPara('servicos.php');
}

try {
    // A FK (ON DELETE RESTRICT) impede apagar serviço que já tem agendamentos
    $pdo->prepare('DELETE FROM servicos WHERE id = ?')->execute([$id]);
    flash('sucesso', 'Serviço "' . $nome . '" excluído.');
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') {
        throw $e;
    }
    flash('erro', 'O serviço "' . $nome . '" tem agendamentos no histórico. Em vez de excluir, deixe-o oculto.');
}

irPara('servicos.php');
