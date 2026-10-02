<?php
// Pedaços de interface repetidos em várias telas do painel.

/** Link de WhatsApp para o paciente, já com a mensagem de confirmação. */
function whatsConfirmacao(array $ag): string
{
    $msg = 'Olá, ' . primeiroNome($ag['paciente_nome']) . '! Aqui é do consultório da ' . DENTISTA_NOME
         . '. Passando para confirmar sua consulta (' . $ag['servico_nome'] . ') '
         . mb_strtolower(dataRelativa($ag['inicio'])) . ' às ' . date('H:i', strtotime($ag['inicio'])) . '. Podemos confirmar?';
    return linkWhatsApp($msg, '55' . $ag['telefone']);
}

/**
 * Menu de ações de um agendamento (concluir, faltou, cancelar, editar).
 * $voltar: página do painel para onde voltar depois da ação.
 */
function acoesAgendamento(array $ag, string $voltar): string
{
    $id = (int) $ag['id'];
    $opcoes = [];
    if ($ag['status'] === 'confirmado') {
        $opcoes[] = ['concluir', 'bi-check2', 'Marcar como concluído', ''];
        $opcoes[] = ['faltou', 'bi-person-x', 'Paciente faltou', ''];
        $opcoes[] = ['cancelar', 'bi-x-circle', 'Cancelar agendamento', 'perigo'];
    } else {
        $opcoes[] = ['reativar', 'bi-arrow-counterclockwise', 'Voltar para confirmado', ''];
    }

    ob_start(); ?>
    <div class="dropdown acoes">
        <button class="botao-icone" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do agendamento">
            <i class="bi bi-three-dots"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="agendamento.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Editar / remarcar</a></li>
            <?php if ($ag['status'] === 'confirmado' && strtotime($ag['inicio']) > time()): ?>
                <li><a class="dropdown-item" href="<?= e(whatsConfirmacao($ag)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Confirmar pelo WhatsApp</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <?php foreach ($opcoes as [$acao, $icone, $rotulo, $classe]): ?>
                <li>
                    <form method="post" action="agendamento_acao.php" <?= $acao === 'cancelar' ? 'data-confirmar="Cancelar este agendamento? O horário volta a ficar livre no site."' : '' ?>>
                        <?= csrfCampo() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="acao" value="<?= $acao ?>">
                        <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                        <button type="submit" class="dropdown-item <?= $classe ? 'item-' . $classe : '' ?>"><i class="bi <?= $icone ?>"></i> <?= $rotulo ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
