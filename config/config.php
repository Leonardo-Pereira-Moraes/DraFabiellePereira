<?php
// Configurações gerais da clínica.
// Os campos marcados com TROCAR ainda estão com dados de exemplo.

date_default_timezone_set('America/Sao_Paulo');

// Não revela a versão do PHP nos cabeçalhos da resposta
header_remove('X-Powered-By');

define('CLINICA_NOME', 'Dra. Fabielle Pereira');
define('CLINICA_SEGMENTO', 'Odontologia');
define('DENTISTA_NOME', 'Dra. Fabielle Pereira');
define('DENTISTA_CRO', 'CRO-GO 22484');

// Endereço exibido no site
define('CLINICA_ENDERECO', 'Rua Sebastianinha Silvania');       // TROCAR: completar com número/sala
define('CLINICA_BAIRRO_CIDADE', 'Santo Antônio da Barra – GO · 75935-000');
define('CLINICA_CIDADE', 'Santo Antônio da Barra – GO');
// Posição exata do consultório no mapa (latitude,longitude do pino no Google Maps)
define('CLINICA_MAPA_BUSCA', '-17.5604115,-50.6349945');

// Telefone exibido e número do WhatsApp só com dígitos: 55 + DDD + número
define('CLINICA_TELEFONE', '(64) 99229-5095');
define('WHATSAPP_NUMERO', '5564992295095');
define('CLINICA_EMAIL', 'contato@exemplo.com.br');              // TROCAR
define('INSTAGRAM_USUARIO', '');                                // ex.: 'drafabiellepereira' (vazio = oculto)

// Imagens fixas. Enquanto o arquivo não existir, o site mostra um espaço reservado.
define('LOGO_ARQUIVO', 'assets/img/logo.png');
define('FOTO_DENTISTA', 'assets/img/dra-fabielle.jpg');

// Upload das fotos do consultório (relativo à raiz do site)
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/consultorio/');
define('UPLOAD_URL', 'assets/uploads/consultorio/');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

// Anti-abuso do agendamento pelo site
define('MAX_AGENDAMENTOS_FUTUROS_POR_TELEFONE', 2);
define('MAX_AGENDAMENTOS_POR_SESSAO_HORA', 3);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/funcoes.php';

// Mantém NOW() e DEFAULT CURRENT_TIMESTAMP no horário de Brasília
// mesmo que o servidor MySQL esteja em UTC.
$pdo->exec("SET time_zone = '-03:00'");
