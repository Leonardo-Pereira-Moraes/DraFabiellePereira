// Comportamentos do painel administrativo.
(() => {
    // ---------- Confirmações ----------
    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        const botao = ev.submitter;
        const msg = (botao && botao.dataset.confirmarBotao) || form.dataset.confirmar;
        if (msg && !confirm(msg)) ev.preventDefault();
    });

    // ---------- Alertas ----------
    document.querySelectorAll('[data-fechar-alerta]').forEach((b) => {
        b.addEventListener('click', () => b.closest('[data-alerta]').remove());
    });
    const alertaSucesso = document.querySelector('[data-alerta].alerta-sucesso');
    if (alertaSucesso) setTimeout(() => alertaSucesso.classList.add('is-saindo'), 6000);

    // ---------- Pequenos utilitários de formulário ----------
    document.querySelectorAll('[data-auto-enviar]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    document.querySelectorAll('[data-alterna]').forEach((chk) => {
        const alvo = document.querySelector(chk.dataset.alterna);
        const atualizar = () => { alvo.hidden = chk.checked; };
        chk.addEventListener('change', atualizar);
        atualizar();
    });

    document.querySelectorAll('[data-contador]').forEach((campo) => {
        const saida = campo.parentElement.querySelector('[data-contador-valor]');
        campo.addEventListener('input', () => { saida.textContent = campo.value.length; });
    });

    function mascaraTelefone(input) {
        input.addEventListener('input', () => {
            let d = input.value.replace(/\D/g, '');
            if (d.startsWith('55') && d.length > 11) d = d.slice(2);
            d = d.slice(0, 11);
            let v = d;
            if (d.length > 2) v = `(${d.slice(0, 2)}) ${d.slice(2)}`;
            if (d.length > 6) v = `(${d.slice(0, 2)}) ${d.slice(2, d.length === 11 ? 7 : 6)}-${d.slice(d.length === 11 ? 7 : 6)}`;
            input.value = v;
        });
    }
    document.querySelectorAll('input[type="tel"]').forEach(mascaraTelefone);

    const escapar = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Horário de atendimento: adicionar/remover faixas ----------
    const modeloFaixa = document.getElementById('modelo-faixa');
    document.querySelectorAll('[data-dia-horario]').forEach((dia) => {
        const lista = dia.querySelector('[data-faixas]');
        const atualizar = () => dia.classList.toggle('is-fechado', !lista.querySelector('[data-faixa]'));

        dia.querySelector('[data-adicionar-faixa]').addEventListener('click', () => {
            const nova = modeloFaixa.content.firstElementChild.cloneNode(true);
            nova.querySelectorAll('[data-nome]').forEach((inp) => { inp.name = `${inp.dataset.nome}[${dia.dataset.diaHorario}][]`; });
            // Sugere a faixa seguinte à última (ex.: depois do almoço)
            const ultima = [...lista.querySelectorAll('[data-faixa]')].pop();
            const [abre, fecha] = nova.querySelectorAll('input');
            if (ultima) {
                const fim = ultima.querySelectorAll('input')[1].value;
                if (fim && fim < '22:00') {
                    const h = String(Math.min(23, Number(fim.slice(0, 2)) + 2)).padStart(2, '0');
                    abre.value = `${h}${fim.slice(2)}`;
                    fecha.value = h < '18' ? '18:00' : '';
                }
            } else {
                abre.value = '08:00';
                fecha.value = '12:00';
            }
            lista.insertBefore(nova, lista.querySelector('.dia-horario-fechado'));
            atualizar();
            abre.focus();
        });

        lista.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-remover-faixa]');
            if (!b) return;
            b.closest('[data-faixa]').remove();
            atualizar();
        });
    });

    // ---------- Agenda semanal: clique num espaço vazio cria agendamento ----------
    const semana = document.querySelector('[data-semana]');
    if (semana) {
        const horaMin = Number(semana.dataset.horaMin);
        semana.querySelectorAll('.dia-col').forEach((col) => {
            col.addEventListener('click', (ev) => {
                if (ev.target.closest('.evento')) return;
                const alturaHora = parseFloat(getComputedStyle(semana).getPropertyValue('--hora')) || 56;
                const y = ev.clientY - col.getBoundingClientRect().top;
                const minutos = Math.floor((y / alturaHora) * 60 / 30) * 30; // arredonda para meia hora
                const total = horaMin * 60 + minutos;
                const hora = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
                window.location.href = `agendamento.php?data=${col.dataset.dia}&hora=${hora}`;
            });
        });
    }

    // ---------- Formulário de agendamento ----------
    const formAg = document.querySelector('[data-form-agendamento]');
    if (formAg) {
        const servico = formAg.querySelector('#servico_id');
        const duracao = formAg.querySelector('#duracao');
        const data = formAg.querySelector('#data');
        const hora = formAg.querySelector('#hora');
        const chips = formAg.querySelector('[data-livres]');
        const ocupacao = formAg.querySelector('[data-ocupacao]');
        const tituloDia = formAg.querySelector('[data-dia-titulo]');
        const ignorar = formAg.dataset.ignorar || 0;
        let duracaoManual = false;
        let pedido = 0;

        duracao.addEventListener('input', () => { duracaoManual = true; });
        servico.addEventListener('change', () => {
            if (!duracaoManual) duracao.value = servico.selectedOptions[0].dataset.duracao;
            carregarDia();
        });
        duracao.addEventListener('change', carregarDia);
        data.addEventListener('change', carregarDia);
        hora.addEventListener('change', marcarChip);

        function marcarChip() {
            chips.querySelectorAll('.chip').forEach((c) => c.classList.toggle('is-selecionado', c.dataset.hora === hora.value));
        }

        chips.addEventListener('click', (ev) => {
            const c = ev.target.closest('.chip');
            if (!c) return;
            hora.value = c.dataset.hora;
            marcarChip();
        });

        async function carregarDia() {
            if (!data.value) return;
            const meu = ++pedido;
            chips.classList.add('is-carregando');
            try {
                const r = await fetch(`ajax.php?acao=dia&data=${data.value}&duracao=${duracao.value || 30}&ignorar=${ignorar}`);
                const j = await r.json();
                if (meu !== pedido) return;
                if (!j.ok) throw new Error(j.erro);

                tituloDia.textContent = `· ${j.titulo}`;
                chips.innerHTML = j.livres.length
                    ? j.livres.map((h) => `<button type="button" class="chip" data-hora="${h}">${h}</button>`).join('')
                    : `<p class="texto-apoio mb-0">${j.faixas.length ? 'Nenhum horário livre para essa duração.' : 'Sem atendimento neste dia.'} Você ainda pode digitar um horário de encaixe.</p>`;
                marcarChip();

                const itens = j.ocupacoes.map((o) => `
                    <li class="ocupacao-${o.tipo}">
                        <span class="ocupacao-hora">${o.inicio}–${o.fim}</span>
                        <span>${escapar(o.rotulo)}</span>
                    </li>`).join('');
                const faixas = j.faixas.length ? `Atendimento: ${j.faixas.join(' · ')}` : 'Dia sem atendimento';
                ocupacao.innerHTML = `<li class="ocupacao-faixas">${escapar(faixas)}</li>` + (itens || '<li class="texto-apoio">Nada marcado neste dia.</li>');
            } catch (e) {
                chips.innerHTML = '<p class="texto-apoio mb-0">Não foi possível carregar os horários.</p>';
            } finally {
                chips.classList.remove('is-carregando');
            }
        }
        carregarDia();

        // Paciente já cadastrado? Preenche nome e e-mail pelo telefone
        const tel = formAg.querySelector('[data-busca-paciente]');
        if (tel) {
            const status = formAg.querySelector('[data-paciente-status]');
            const nome = formAg.querySelector('#nome');
            const email = formAg.querySelector('#email');
            let ultimo = '';
            tel.addEventListener('blur', async () => {
                const digitos = tel.value.replace(/\D/g, '');
                if (digitos.length < 10 || digitos === ultimo) return;
                ultimo = digitos;
                try {
                    const r = await fetch(`ajax.php?acao=paciente&telefone=${digitos}`);
                    const j = await r.json();
                    if (j.paciente) {
                        nome.value = j.paciente.nome;
                        if (j.paciente.email && !email.value) email.value = j.paciente.email;
                        status.innerHTML = `<i class="bi bi-person-check"></i> Paciente já cadastrado: <a href="paciente.php?id=${j.paciente.id}" target="_blank">${escapar(j.paciente.nome)}</a>`;
                        status.classList.add('is-encontrado');
                    } else {
                        status.textContent = 'Paciente novo: será cadastrado ao salvar.';
                        status.classList.remove('is-encontrado');
                    }
                } catch { /* sem rede: segue sem preencher */ }
            });
        }
    }
})();
