<?php
// Copie este arquivo para config/db.php e preencha com as credenciais reais.
// Em produção, pegue estes valores no painel da hospedagem (MySQL Databases).
define('DB_HOST', 'localhost');
define('DB_NAME', 'dra_fabielle');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro na conexão com o banco: ' . $e->getMessage());
    die('Erro na conexão com o banco de dados.');
}
