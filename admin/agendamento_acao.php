<?php
// Mudanças rápidas de status: concluir, faltou, cancelar, reativar.
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    irPara('index.php');
}
validarCsrf();

$id     = (int) ($_POST['id'] ?? 0);
$acao   = (string) ($_POST['acao'] ?? '');
$voltar = destinoSeguro($_POST['voltar'] ?? null);

$stmt = $pdo->prepare('SELECT id, inicio, fim, status FROM agendamentos WHERE id = ?');
$stmt->execute([$id]);
$ag = $stmt->fetch();
if (!$ag) {
    flash('erro', 'Agendamento não encontrado.');
    irPara($voltar);
}

switch ($acao) {
    case 'concluir':
    case 'faltou':
        $novo = $acao === 'concluir' ? 'concluido' : 'faltou';
        $pdo->prepare('UPDATE agendamentos SET status = ?, cancelado_em = NULL WHERE id = ?')->execute([$novo, $id]);
        flash('sucesso', $acao === 'concluir' ? 'Atendimento marcado como concluído.' : 'Falta registrada.');
        break;

    case 'cancelar':
        $pdo->prepare("UPDATE agendamentos SET status = 'cancelado', cancelado_em = NOW() WHERE id = ?")->execute([$id]);
        flash('sucesso', 'Agendamento cancelado. O horário voltou a ficar livre.');
        break;

    case 'reativar':
        // Voltar a ocupar a agenda exige a mesma checagem de conflito de um agendamento novo
        try {
            iniciarTransacaoAgenda($pdo, datasDoIntervalo($ag['inicio'], $ag['fim']));
            $conflitos = ocupacoes($pdo, $ag['inicio'], $ag['fim'], $id);
            if ($conflitos) {
                $pdo->rollBack();
                flash('erro', 'Não dá para reativar: o horário agora está ocupado por "' . $conflitos[0]['rotulo'] . '". Remarque para outro horário.');
                break;
            }
            $pdo->prepare("UPDATE agendamentos SET status = 'confirmado', cancelado_em = NULL WHERE id = ?")->execute([$id]);
            $pdo->commit();
            flash('sucesso', 'Agendamento voltou para confirmado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao reativar agendamento: ' . $e->getMessage());
            flash('erro', 'Não foi possível reativar agora. Tente novamente.');
        }
        break;

    default:
        flash('erro', 'Ação inválida.');
}

irPara($voltar);
