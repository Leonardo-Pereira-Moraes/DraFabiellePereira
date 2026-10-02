<?php
// Textos da Dra. Fabielle usados no site (página inicial e "Sobre").
// Tudo aqui é editável: troque pelos dados reais antes de publicar.
// Itens marcados com TROCAR são dados de exemplo e NÃO podem ir ao ar assim.

// Frase de destaque (aparece na página inicial e no "Sobre")
const CITACAO_DENTISTA = 'Antes de começar qualquer tratamento, eu explico o que vamos fazer, por que e quanto tempo leva. Paciente que entende o próprio tratamento fica mais tranquilo na cadeira.';

// Parágrafos da página "Sobre". O primeiro aparece maior, como abertura.
const BIO_DENTISTA = [
    'Sou dentista e atendo em consultório próprio, com agenda organizada para que cada consulta tenha o tempo que precisa, sem sala de espera cheia e sem pressa na cadeira.',
    'Meu trabalho vai da prevenção, com limpezas e check-ups, aos tratamentos restauradores e estéticos, como clareamento, facetas e harmonização facial. Em todos eles sigo a mesma ordem: avaliar com cuidado, explicar as opções e só então propor um plano, com etapas e valores claros.',
    'Atendo adultos, adolescentes e pacientes que chegam com medo de dentista. Para esses, o primeiro encontro pode ser só uma conversa.',
];

// Formação e cursos (do mais recente para o mais antigo)
const FORMACAO_DENTISTA = [
    ['ano' => '20XX', 'titulo' => 'Especialização / curso de atualização', 'local' => 'Instituição – TROCAR'],
    ['ano' => '20XX', 'titulo' => 'Curso de Harmonização Orofacial',     'local' => 'Instituição – TROCAR'],
    ['ano' => '20XX', 'titulo' => 'Graduação em Odontologia',            'local' => 'Instituição – TROCAR'],
];

// Princípios de atendimento ("Como eu trabalho")
const PRINCIPIOS_ATENDIMENTO = [
    ['titulo' => 'Diagnóstico antes do orçamento', 'texto' => 'Examino, peço os exames necessários e só então apresento as opções de tratamento, com prós e contras de cada uma.'],
    ['titulo' => 'Tempo reservado para você',       'texto' => 'A agenda respeita a duração real de cada procedimento. Você não espera horas nem é atendido às pressas.'],
    ['titulo' => 'Material e esterilização',        'texto' => 'Instrumentais esterilizados em autoclave e embalados individualmente, abertos na sua frente.'],
];

// Etapas da primeira consulta (página inicial)
const ETAPAS_PRIMEIRA_CONSULTA = [
    ['titulo' => 'Conversa e avaliação',       'texto' => 'Você conta o que incomoda e o que gostaria de mudar. Faço o exame clínico completo e, se preciso, peço radiografias.'],
    ['titulo' => 'Plano explicado com calma',  'texto' => 'Mostro o que encontrei, as opções possíveis e o que cada uma envolve: número de sessões, cuidados e investimento.'],
    ['titulo' => 'Tratamento no seu ritmo',    'texto' => 'Com o plano aprovado, marcamos as sessões nos horários que funcionam para você, já pensando na duração de cada uma.'],
];
