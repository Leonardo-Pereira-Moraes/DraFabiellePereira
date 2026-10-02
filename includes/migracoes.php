<?php
// Mantém o banco atualizado sozinho a cada deploy pelo GitHub.
//
// - Banco vazio (primeiro acesso ao site publicado): cria tudo a partir de
//   database/schema.sql e marca as migrações existentes como aplicadas,
//   porque o schema já contém o que elas fazem.
// - Banco existente: aplica, em ordem, os arquivos database/migracao_*.sql
//   que ainda não constam na tabela "migracoes".
//
// Para mudar o banco no futuro: crie database/migracao_NNN_descricao.sql
// (e atualize o schema.sql para instalações novas). O próximo acesso aplica.

function executarArquivoSql(PDO $pdo, string $arquivo): void
{
    $sql = file_get_contents($arquivo);
    // CREATE DATABASE / USE são só para importação manual; aqui o banco já é o da conexão
    $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $comando) {
        // ignora trechos que só têm comentários
        $semComentarios = trim(preg_replace('/^\s*--.*$/m', '', $comando));
        if ($semComentarios !== '') {
            $pdo->exec($comando);
        }
    }
}

function aplicarMigracoes(PDO $pdo): void
{
    $pasta    = __DIR__ . '/../database/';
    $arquivos = glob($pasta . 'migracao_*.sql') ?: [];
    sort($arquivos);
    $nomes = array_map('basename', $arquivos);

    // Caminho rápido (toda requisição): nada pendente, sai com uma consulta só
    try {
        $aplicadas = $pdo->query('SELECT arquivo FROM migracoes')->fetchAll(PDO::FETCH_COLUMN);
        if (!array_diff($nomes, $aplicadas)) {
            return;
        }
    } catch (PDOException $e) {
        // tabela migracoes ainda não existe: segue para preparar o banco
    }

    // Só um processo migra por vez (dois acessos simultâneos logo após o deploy)
    if ((int) $pdo->query("SELECT GET_LOCK('dra_fabielle_migracoes', 30)")->fetchColumn() !== 1) {
        return;
    }
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migracoes (
                arquivo     VARCHAR(120) NOT NULL PRIMARY KEY,
                aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $registrar = $pdo->prepare('INSERT IGNORE INTO migracoes (arquivo) VALUES (?)');

        $bancoVazio = !$pdo->query("SHOW TABLES LIKE 'servicos'")->fetchColumn();
        if ($bancoVazio) {
            executarArquivoSql($pdo, $pasta . 'schema.sql');
            foreach ($nomes as $nome) {
                $registrar->execute([$nome]);
            }
            error_log('Banco criado a partir de database/schema.sql');
            return;
        }

        $aplicadas = $pdo->query('SELECT arquivo FROM migracoes')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($arquivos as $arquivo) {
            $nome = basename($arquivo);
            if (!in_array($nome, $aplicadas, true)) {
                executarArquivoSql($pdo, $arquivo);
                $registrar->execute([$nome]);
                error_log("Migração aplicada: $nome");
            }
        }
    } catch (Throwable $e) {
        error_log('Erro ao atualizar o banco: ' . $e->getMessage());
        http_response_code(503);
        die('O site está sendo atualizado. Tente novamente em alguns instantes.');
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('dra_fabielle_migracoes')");
    }
}
