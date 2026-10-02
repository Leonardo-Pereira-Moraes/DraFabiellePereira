<?php
// Conexão com o banco. Escolhe sozinho o ambiente:
//   - no computador (XAMPP, acessado por localhost) usa o banco local;
//   - em qualquer outro endereço (site publicado na KingHost) usa o da hospedagem.
// Este arquivo vai para o repositório (privado) para o deploy pelo GitHub
// funcionar sem passo manual. Não torne o repositório público.

$host     = strtolower(strtok($_SERVER['HTTP_HOST'] ?? '', ':'));
$ehLocal  = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
         || (PHP_SAPI === 'cli' && PHP_OS_FAMILY === 'Windows');

if ($ehLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'dra_fabielle');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // KingHost
    define('DB_HOST', 'mysql.drafabiellepereira.com.br');
    define('DB_NAME', 'drafabielleper');
    define('DB_USER', 'drafabielleper');
    define('DB_PASS', 'leonardo84');
}
unset($host, $ehLocal);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro na conexão com o banco: ' . $e->getMessage());
    die('Erro na conexão com o banco de dados.');
}
