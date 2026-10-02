<?php
// Sessão do site público (só usada no agendamento, para o token CSRF
// e o limite de agendamentos por visitante). Separada da sessão do painel.
require_once __DIR__ . '/csrf.php';

iniciarSessao('DRFABI_SITE');
