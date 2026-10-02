-- Aviso no WhatsApp de novos agendamentos (CallMeBot).
-- Rode uma vez em bancos criados antes desta versão.
INSERT IGNORE INTO configuracoes (chave, valor) VALUES
('whatsapp_aviso_ativo',    '0'),
('whatsapp_aviso_telefone', '556492295095'),   -- sem o 9 extra: é assim que o WhatsApp dela está registrado
('whatsapp_aviso_apikey',   '');
