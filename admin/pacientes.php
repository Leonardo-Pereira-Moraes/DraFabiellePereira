<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();

const POR_PAGINA = 30;

$busca  = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['p'] ?? 1));

$where  = '';
$params = [];
if ($busca !== '') {
    $digitos = soDigitos($busca);
    $where   = 'WHERE p.nome LIKE ? OR p.email LIKE ?' . ($digitos !== '' ? ' OR p.telefone LIKE ?' : '');
    $like    = '%' . addcslashes($busca, '%_\\') . '%';
    $params  = [$like, $like];
    if ($digitos !== '') {
        $params[] = '%' . $digitos . '%';
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes p $where");
$stmt->execute($params);
$total   = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * POR_PAGINA;

// LIMIT/OFFSET são inteiros calculados aqui (não vêm do usuário sem conversão)
$stmt = $pdo->prepare(
    "SELECT p.id, p.nome, p.telefone, p.email, p.criado_em,
            SUM(a.status = 'concluido') AS atendidos,
            SUM(a.status = 'faltou') AS faltas,
            MAX(CASE WHEN a.inicio < NOW() AND a.status IN ('concluido','faltou','confirmado') THEN a.inicio END) AS ultimo,
            MIN(CASE WHEN a.inicio >= NOW() AND a.status = 'confirmado' THEN a.inicio END) AS proximo
       FROM pacientes p
       LEFT JOIN agendamentos a ON a.paciente_id = p.id
       $where
      GROUP BY p.id
      ORDER BY p.nome
      LIMIT " . POR_PAGINA . " OFFSET " . (int) $offset
);
$stmt->execute($params);
$pacientes = $stmt->fetchAll();

$tituloPagina = 'Pacientes';
require __DIR__ . '/includes/topo.php';
?>

<div class="cabeca-pagina">
    <div>
        <h1 class="titulo-pagina">Pacientes</h1>
        <p class="subtitulo"><?= $total ?> <?= $total === 1 ? 'paciente' : 'pacientes' ?><?= $busca !== '' ? ' encontrados para “' . e($busca) . '”' : ' cadastrados' ?></p>
    </div>
    <form method="get" class="busca" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <label for="q" class="visually-hidden">Buscar paciente</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($busca) ?>" placeholder="Nome, telefone ou e-mail" autofocus>
    </form>
</div>

<section class="cartao cartao-tabela">
    <?php if (!$pacientes): ?>
        <div class="vazio">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p><?= $busca !== '' ? 'Nenhum paciente encontrado.' : 'Os pacientes aparecem aqui assim que fizerem o primeiro agendamento.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabela tabela-clicavel">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Telefone</th>
                        <th class="text-center">Atendimentos</th>
                        <th>Último</th>
                        <th>Próximo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td>
                            <a href="paciente.php?id=<?= (int) $p['id'] ?>" class="tabela-link"><?= e($p['nome']) ?></a>
                            <?php if ($p['email']): ?><small class="d-block texto-apoio"><?= e($p['email']) ?></small><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= e(formatarTelefone($p['telefone'])) ?></td>
                        <td class="text-center">
                            <?= (int) $p['atendidos'] ?>
                            <?php if ((int) $p['faltas']): ?><small class="texto-alerta" title="Faltas">· <?= (int) $p['faltas'] ?> falta<?= (int) $p['faltas'] > 1 ? 's' : '' ?></small><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= $p['ultimo'] ? e(date('d/m/Y', strtotime($p['ultimo']))) : '<span class="texto-apoio">—</span>' ?></td>
                        <td class="text-nowrap"><?= $p['proximo'] ? '<span class="selo selo-confirmado">' . e(dataCurta($p['proximo'])) . ' ' . date('H:i', strtotime($p['proximo'])) . '</span>' : '<span class="texto-apoio">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($paginas > 1): ?>
    <nav class="paginacao" aria-label="Páginas">
        <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <a href="?<?= e(http_build_query(['q' => $busca, 'p' => $i])) ?>" class="<?= $i === $pagina ? 'is-atual' : '' ?>"<?= $i === $pagina ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
