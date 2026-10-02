-- =====================================================================
-- Dra. Fabielle Pereira - Estrutura do banco de dados
-- Importe pelo phpMyAdmin (aba "Importar") ou:
--   mysql -u root < database/schema.sql
-- Em produção o banco já vem criado: remova as duas linhas
-- CREATE DATABASE / USE e importe direto no banco da hospedagem.
-- =====================================================================


-- ---------------------------------------------------------------------
-- Usuários do painel admin
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100) NOT NULL,
    usuario       VARCHAR(50)  NOT NULL UNIQUE,
    senha_hash    VARCHAR(255) NOT NULL,
    ultimo_login  DATETIME NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações gerais (chave/valor, editáveis no painel)
--   intervalo_grade_min    = de quantos em quantos minutos os horários
--                            são oferecidos (ex.: 30 -> 08:00, 08:30...)
--   antecedencia_min_horas = não deixa agendar para daqui a menos de X h
--   dias_max_agendamento   = até quantos dias à frente o site mostra
--   exibir_precos          = 0/1, mostra ou não preço no site público
-- ---------------------------------------------------------------------
CREATE TABLE configuracoes (
    chave  VARCHAR(50)  NOT NULL PRIMARY KEY,
    valor  VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Horário de funcionamento
-- dia_semana: 0 = domingo ... 6 = sábado (mesmo padrão do PHP date('w'))
-- Várias linhas por dia permitem pausa de almoço:
--   seg 08:00–12:00  e  seg 14:00–18:00
-- Um serviço só é oferecido se couber INTEIRO dentro de uma faixa.
-- ---------------------------------------------------------------------
CREATE TABLE horarios_funcionamento (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dia_semana  TINYINT UNSIGNED NOT NULL,
    abre        TIME NOT NULL,
    fecha       TIME NOT NULL,
    CONSTRAINT chk_hf_dia   CHECK (dia_semana BETWEEN 0 AND 6),
    CONSTRAINT chk_hf_faixa CHECK (fecha > abre),
    INDEX idx_hf_dia (dia_semana, abre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bloqueios de agenda (férias, folga, compromisso pessoal)
-- Intervalo livre de data/hora: pode ser 1 hora ou 15 dias.
-- ---------------------------------------------------------------------
CREATE TABLE bloqueios (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inicio     DATETIME NOT NULL,
    fim        DATETIME NOT NULL,
    tipo       ENUM('ferias','folga','compromisso','outro') NOT NULL DEFAULT 'outro',
    motivo     VARCHAR(150) NULL,              -- interno, nunca aparece no site
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_bloq_faixa CHECK (fim > inicio),
    INDEX idx_bloq_periodo (inicio, fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Serviços
-- slug  = âncora na página de serviços / pré-seleção no agendamento
-- preco = NULL quando não se quer informar
-- ativo = 0 tira do site e do agendamento, mas preserva o histórico
--         (serviço com agendamentos não pode ser apagado, só desativado)
-- ---------------------------------------------------------------------
CREATE TABLE servicos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(80)  NOT NULL,
    slug          VARCHAR(80)  NOT NULL UNIQUE,
    descricao     VARCHAR(400) NOT NULL,
    duracao_min   SMALLINT UNSIGNED NOT NULL,
    preco         DECIMAL(10,2) NULL,
    ativo         TINYINT(1) NOT NULL DEFAULT 1,
    ordem         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_serv_duracao CHECK (duracao_min BETWEEN 5 AND 600),
    INDEX idx_serv_site (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pacientes
-- telefone guardado só com dígitos (ex.: 64992927610) e é a chave de
-- identificação: se a pessoa agenda de novo com o mesmo telefone, reaproveita
-- o cadastro em vez de duplicar.
-- ---------------------------------------------------------------------
CREATE TABLE pacientes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(120) NOT NULL,
    telefone       VARCHAR(15)  NOT NULL UNIQUE,
    email          VARCHAR(160) NULL,
    observacoes    TEXT NULL,                  -- anotações internas da clínica
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pac_nome (nome),
    INDEX idx_pac_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Agendamentos
-- inicio/fim são GRAVADOS (fim = inicio + duração do serviço no momento
-- do agendamento). Assim, se a duração do serviço mudar depois, os
-- agendamentos já feitos continuam ocupando o intervalo correto.
-- servico_nome = cópia do nome, para o histórico não mudar se o
--                serviço for renomeado.
-- Todo status diferente de 'cancelado' ocupa a agenda.
-- ---------------------------------------------------------------------
CREATE TABLE agendamentos (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paciente_id        INT UNSIGNED NOT NULL,
    servico_id         INT UNSIGNED NOT NULL,
    servico_nome       VARCHAR(80)  NOT NULL,
    inicio             DATETIME NOT NULL,
    fim                DATETIME NOT NULL,
    status             ENUM('confirmado','concluido','cancelado','faltou')
                           NOT NULL DEFAULT 'confirmado',
    origem             ENUM('site','painel') NOT NULL DEFAULT 'site',
    obs_paciente       VARCHAR(500) NULL,       -- campo opcional do formulário
    obs_internas       TEXT NULL,               -- só o painel vê
    cancelado_em       DATETIME NULL,
    criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_ag_faixa CHECK (fim > inicio),
    CONSTRAINT fk_ag_paciente FOREIGN KEY (paciente_id)
        REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ag_servico FOREIGN KEY (servico_id)
        REFERENCES servicos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_ag_periodo (inicio, fim),          -- busca de conflitos / agenda
    INDEX idx_ag_status (status, inicio),        -- dashboard
    INDEX idx_ag_paciente (paciente_id, inicio)  -- histórico do paciente
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Trava por dia (evita duplo agendamento)
-- Não guarda informação: quem vai gravar na agenda de um dia faz
-- SELECT ... FOR UPDATE na linha daquela data dentro da transação.
-- Um segundo pedido para a mesma data espera o primeiro terminar e só
-- então revalida o horário (ver includes/agenda.php).
-- ---------------------------------------------------------------------
CREATE TABLE agenda_travas (
    data  DATE NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fotos do consultório
-- imagem = só o nome do arquivo (assets/uploads/consultorio/<imagem>)
-- ---------------------------------------------------------------------
CREATE TABLE fotos_consultorio (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imagem     VARCHAR(255) NOT NULL,
    legenda    VARCHAR(150) NULL,
    ordem      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo      TINYINT(1) NOT NULL DEFAULT 1,
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fotos_site (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Dados iniciais
-- =====================================================================

-- Usuário: admin / Senha: admin123  (TROQUE no painel em "Alterar senha")
INSERT INTO usuarios (nome, usuario, senha_hash) VALUES
('Fabielle Pereira', 'admin', '$2y$10$WhRVPbM7xAtsspj6ldkeher4jPZi0K1tFuanhVl3cTJuu2w1IV0KC');

INSERT INTO configuracoes (chave, valor) VALUES
('intervalo_grade_min',    '30'),
('antecedencia_min_horas', '2'),
('dias_max_agendamento',   '60'),
('exibir_precos',          '0'),
-- Aviso no WhatsApp da dentista via CallMeBot (configurado no painel > Notificações)
('whatsapp_aviso_ativo',   '0'),
('whatsapp_aviso_telefone', '556492295095'),   -- sem o 9 extra: é assim que o WhatsApp dela está registrado
('whatsapp_aviso_apikey',  '');

-- Seg a sex 08:00–12:00 e 14:00–18:00; sábado 08:00–12:00
INSERT INTO horarios_funcionamento (dia_semana, abre, fecha) VALUES
(1, '08:00', '12:00'), (1, '14:00', '18:00'),
(2, '08:00', '12:00'), (2, '14:00', '18:00'),
(3, '08:00', '12:00'), (3, '14:00', '18:00'),
(4, '08:00', '12:00'), (4, '14:00', '18:00'),
(5, '08:00', '12:00'), (5, '14:00', '18:00'),
(6, '08:00', '12:00');

INSERT INTO servicos (nome, slug, descricao, duracao_min, ordem) VALUES
('Cirurgia', 'cirurgia',
 'Extrações, inclusive de dentes do siso, e pequenas cirurgias na boca, com planejamento prévio e orientação clara para o pós-operatório.',
 90, 1),
('Clareamento', 'clareamento',
 'Clareamento no consultório ou caseiro com moldeira feita sob medida. Antes, avaliamos a sensibilidade dos dentes para escolher o protocolo certo.',
 60, 2),
('Limpeza', 'limpeza',
 'Remoção de tártaro e placa, polimento e orientação de escovação e fio dental. O ideal é repetir a cada seis meses.',
 45, 3),
('Restauração', 'restauracao',
 'Tratamento de cáries e dentes lascados com resina na cor natural do dente, devolvendo forma e mastigação.',
 60, 4),
('Prótese', 'protese',
 'Coroas, pontes e próteses removíveis ou sobre implante para repor dentes perdidos e voltar a mastigar com segurança.',
 60, 5),
('Harmonização Facial', 'harmonizacao-facial',
 'Toxina botulínica e preenchimento com ácido hialurônico, planejados a partir das proporções do seu rosto para um resultado natural.',
 60, 6),
('Aparelhos', 'aparelhos',
 'Avaliação ortodôntica, instalação e manutenção de aparelhos fixos e alinhadores transparentes para corrigir dentes e mordida.',
 30, 7),
('Facetas', 'facetas',
 'Lâminas finas de resina ou porcelana aplicadas na frente dos dentes para corrigir cor, formato e pequenos espaços.',
 120, 8);