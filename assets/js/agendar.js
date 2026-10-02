// Agendamento online: tratamento -> dia e horário -> dados do paciente.
// Os horários vêm de api/disponibilidade.php; a confirmação vai para
// api/agendar.php, que revalida tudo no servidor (o front só facilita).
(() => {
    const form = document.getElementById('agendamento');
    if (!form) return;

    const API_DISP    = form.dataset.apiDisp;
    const API_AGENDAR = form.dataset.apiAgendar;
    const JANELA      = { inicio: form.dataset.janelaInicio, fim: form.dataset.janelaFim };

    const MESES  = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const MESES_CURTO = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const DIAS   = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    const DIAS_CURTO = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    const etapas     = [...form.querySelectorAll('.etapa')];
    const aviso      = form.querySelector('[data-aviso]');
    const calendario = form.querySelector('[data-calendario]');
    const painelHoras = form.querySelector('[data-horarios]');
    const sucesso    = document.querySelector('[data-sucesso]');
    const botao      = form.querySelector('[data-confirmar]');

    const estado = { servico: null, mes: JANELA.inicio.slice(0, 7), data: null, hora: null };
    const cache  = new Map(); // "servico|mes" -> { dias }

    // ---------- utilidades ----------
    const $campo = (nome) => form.elements[nome];
    const parseData = (s) => { const [a, m, d] = s.split('-').map(Number); return new Date(a, m - 1, d); };
    const fmtISO = (a, m, d) => `${a}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const dataCurta = (s) => { const d = parseData(s); return `${DIAS_CURTO[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')} ${MESES_CURTO[d.getMonth()]}`; };
    const dataLonga = (s) => { const d = parseData(s); return `${DIAS[d.getDay()]}, ${d.getDate()} de ${MESES[d.getMonth()]}`; };
    const somarMes = (mes, n) => { const [a, m] = mes.split('-').map(Number); const d = new Date(a, m - 1 + n, 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; };
    const escapar = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function mostrarAviso(texto, tipo = 'erro') {
        if (!texto) { aviso.hidden = true; return; }
        aviso.className = 'aviso' + (tipo === 'info' ? ' aviso-info' : '');
        aviso.textContent = texto;
        aviso.hidden = false;
    }

    // ---------- etapas ----------
    function abrirEtapa(n, focar = true) {
        etapas.forEach((el) => {
            const i = Number(el.dataset.etapa);
            el.classList.toggle('is-ativa', i === n);
            el.classList.toggle('is-feita', i < n);
            el.classList.toggle('is-pendente', i > n);
            el.querySelector('.etapa-corpo').hidden = i !== n;
        });
        if (focar) {
            const alvo = etapas[n - 1];
            alvo.querySelector('h2').focus({ preventScroll: true });
            const topo = alvo.getBoundingClientRect().top;
            if (topo < 80 || topo > window.innerHeight * 0.6) {
                alvo.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            }
        }
    }

    function resumo(n, texto) {
        etapas[n - 1].querySelector('[data-resumo]').textContent = texto;
    }

    form.querySelectorAll('[data-alterar]').forEach((b) => {
        b.addEventListener('click', () => abrirEtapa(Number(b.dataset.alterar)));
    });

    // ---------- 1. tratamento ----------
    form.querySelectorAll('input[name="servico_escolha"]').forEach((radio) => {
        radio.addEventListener('change', () => escolherServico(radio));
    });

    function escolherServico(radio, focar = true) {
        estado.servico = { id: radio.value, nome: radio.dataset.nome, duracao: radio.dataset.duracao };
        estado.data = null;
        estado.hora = null;
        $campo('servico_id').value = radio.value;
        $campo('data').value = '';
        $campo('hora').value = '';
        resumo(1, `${estado.servico.nome} · ${estado.servico.duracao}`);
        resumo(2, '');
        mostrarAviso('');
        abrirEtapa(2, focar);
        carregarMes(estado.mes, true);
    }

    // ---------- 2. calendário ----------
    async function buscarMes(mes) {
        const chave = `${estado.servico.id}|${mes}`;
        if (cache.has(chave)) return cache.get(chave);
        const resp = await fetch(`${API_DISP}?servico=${encodeURIComponent(estado.servico.id)}&mes=${mes}`, { headers: { Accept: 'application/json' } });
        const json = await resp.json();
        if (!resp.ok || !json.ok) throw new Error(json.erro || 'Não foi possível carregar os horários.');
        cache.set(chave, json);
        return json;
    }

    // autoAvancar: se o mês não tiver nenhum dia livre, pula para o próximo (uma vez)
    async function carregarMes(mes, autoAvancar = false) {
        estado.mes = mes;
        const servicoAtual = estado.servico.id;
        calendario.classList.add('is-carregando');
        if (!calendario.innerHTML) desenharCalendario(mes, {});
        try {
            let json = await buscarMes(mes);
            if (servicoAtual !== estado.servico.id) return; // trocou de serviço no meio
            const ultimoMes = JANELA.fim.slice(0, 7);
            if (autoAvancar && !Object.keys(json.dias).length && mes < ultimoMes) {
                estado.mes = mes = somarMes(mes, 1);
                json = await buscarMes(mes);
            }
            desenharCalendario(mes, json.dias);
            const dias = Object.keys(json.dias);
            if (estado.data && json.dias[estado.data]) {
                desenharHorarios(estado.data, json.dias[estado.data]);
            } else if (dias.length) {
                selecionarDia(dias[0], json.dias[dias[0]]);
            } else {
                estado.data = estado.hora = null;
                $campo('data').value = $campo('hora').value = '';
                painelHoras.innerHTML = `<div class="horarios-vazio"><p>Não há horários livres em ${MESES[Number(mes.slice(5)) - 1]} para este tratamento.</p><a class="link-seta" href="${escapar(form.dataset.whatsapp)}" target="_blank" rel="noopener">Pedir um encaixe no WhatsApp <span aria-hidden="true">→</span></a></div>`;
            }
        } catch (err) {
            painelHoras.innerHTML = '';
            mostrarAviso(err.message || 'Não foi possível carregar os horários. Verifique sua conexão.');
        } finally {
            calendario.classList.remove('is-carregando');
        }
    }

    function desenharCalendario(mes, dias) {
        const [a, m] = mes.split('-').map(Number);
        const primeiroDia = new Date(a, m - 1, 1).getDay();
        const totalDias   = new Date(a, m, 0).getDate();
        const hoje        = new Date();
        const hojeISO     = fmtISO(hoje.getFullYear(), hoje.getMonth() + 1, hoje.getDate());
        const podeVoltar  = mes > JANELA.inicio.slice(0, 7);
        const podeAvancar = mes < JANELA.fim.slice(0, 7);

        let html = `
            <div class="cal-topo">
                <button type="button" class="cal-nav" data-mes="-1" ${podeVoltar ? '' : 'disabled'} aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></button>
                <p class="cal-mes">${MESES[m - 1]} <span>${a}</span></p>
                <button type="button" class="cal-nav" data-mes="1" ${podeAvancar ? '' : 'disabled'} aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="cal-grade" role="grid" aria-label="Dias de ${MESES[m - 1]}">
                ${DIAS_CURTO.map((d) => `<span class="cal-semana" aria-hidden="true">${d}</span>`).join('')}`;

        for (let i = 0; i < primeiroDia; i++) html += '<span class="cal-vazio"></span>';

        for (let d = 1; d <= totalDias; d++) {
            const iso    = fmtISO(a, m, d);
            const livres = dias[iso];
            const classes = ['cal-dia'];
            if (livres) classes.push('is-livre');
            if (iso === estado.data) classes.push('is-selecionado');
            if (iso === hojeISO) classes.push('is-hoje');
            const rotulo = `${dataLonga(iso)}${livres ? `, ${livres.length} ${livres.length === 1 ? 'horário livre' : 'horários livres'}` : ', sem horários'}`;
            html += `<button type="button" class="${classes.join(' ')}" data-dia="${iso}" ${livres ? '' : 'disabled'} aria-label="${rotulo}" aria-pressed="${iso === estado.data}">${d}</button>`;
        }
        html += '</div><p class="cal-legenda"><span class="cal-legenda-livre"></span> dias com horário livre</p>';
        calendario.innerHTML = html;
    }

    calendario.addEventListener('click', (ev) => {
        const nav = ev.target.closest('[data-mes]');
        if (nav && !nav.disabled) {
            carregarMes(somarMes(estado.mes, Number(nav.dataset.mes)));
            return;
        }
        const dia = ev.target.closest('[data-dia]');
        if (dia && !dia.disabled) {
            const json = cache.get(`${estado.servico.id}|${estado.mes}`);
            selecionarDia(dia.dataset.dia, json.dias[dia.dataset.dia]);
            calendario.querySelectorAll('.cal-dia').forEach((b) => {
                const sel = b === dia;
                b.classList.toggle('is-selecionado', sel);
                b.setAttribute('aria-pressed', sel);
            });
        }
    });

    function selecionarDia(iso, horas) {
        if (estado.data !== iso) {
            estado.hora = null;
            $campo('hora').value = '';
        }
        estado.data = iso;
        $campo('data').value = iso;
        const botaoDia = calendario.querySelector(`[data-dia="${iso}"]`);
        if (botaoDia) {
            calendario.querySelectorAll('.cal-dia').forEach((b) => b.classList.toggle('is-selecionado', b === botaoDia));
        }
        desenharHorarios(iso, horas);
    }

    // ---------- 2b. horários ----------
    function desenharHorarios(iso, horas) {
        const grupos = [['Manhã', (h) => h < '12:00'], ['Tarde', (h) => h >= '12:00' && h < '18:00'], ['Noite', (h) => h >= '18:00']];
        let html = `<p class="horarios-dia">${dataLonga(iso)}</p>`;
        grupos.forEach(([nome, filtro]) => {
            const lista = horas.filter(filtro);
            if (!lista.length) return;
            html += `<div class="horarios-grupo"><p class="horarios-grupo-titulo">${nome}</p><div class="horarios">`;
            html += lista.map((h) => `<button type="button" class="horario${h === estado.hora ? ' is-selecionado' : ''}" data-hora="${h}" aria-pressed="${h === estado.hora}">${h}</button>`).join('');
            html += '</div></div>';
        });
        painelHoras.innerHTML = html;
    }

    painelHoras.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-hora]');
        if (!b) return;
        estado.hora = b.dataset.hora;
        $campo('hora').value = estado.hora;
        painelHoras.querySelectorAll('.horario').forEach((x) => {
            x.classList.toggle('is-selecionado', x === b);
            x.setAttribute('aria-pressed', x === b);
        });
        resumo(2, `${dataCurta(estado.data)} às ${estado.hora}`);
        form.querySelector('[data-resumo-final]').innerHTML =
            `<strong>${escapar(estado.servico.nome)}</strong> · ${dataLonga(estado.data)} às ${estado.hora}`;
        mostrarAviso('');
        // pequena pausa para o clique "assentar" visualmente antes de mudar de etapa
        setTimeout(() => {
            abrirEtapa(3);
            if (!$campo('nome').value) $campo('nome').focus({ preventScroll: true });
        }, 180);
    });

    // ---------- 3. dados ----------
    const telefone = $campo('telefone');
    telefone.addEventListener('input', () => {
        let d = telefone.value.replace(/\D/g, '');
        if (d.startsWith('55') && d.length > 11) d = d.slice(2);
        d = d.slice(0, 11);
        let v = d;
        if (d.length > 2) v = `(${d.slice(0, 2)}) ${d.slice(2)}`;
        if (d.length > 6) v = `(${d.slice(0, 2)}) ${d.slice(2, d.length === 11 ? 7 : 6)}-${d.slice(d.length === 11 ? 7 : 6)}`;
        telefone.value = v;
    });

    function marcarErro(nome, texto) {
        const el = form.querySelector(`[data-erro="${nome}"]`);
        const input = $campo(nome);
        if (el) el.textContent = texto || '';
        if (input && input.classList) input.classList.toggle('is-invalid', !!texto);
    }

    ['nome', 'telefone', 'email', 'obs', 'consentimento'].forEach((n) => {
        $campo(n).addEventListener('input', () => marcarErro(n, ''));
        $campo(n).addEventListener('change', () => marcarErro(n, ''));
    });

    function validarLocal() {
        const erros = {};
        const nome = $campo('nome').value.trim();
        if (nome.length < 3 || !nome.includes(' ')) erros.nome = 'Informe seu nome e sobrenome.';
        const tel = telefone.value.replace(/\D/g, '');
        if (tel.length < 10 || tel.length > 11) erros.telefone = 'Informe um celular com DDD.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($campo('email').value.trim())) erros.email = 'Informe um e-mail válido.';
        if (!$campo('consentimento').checked) erros.consentimento = 'Marque a autorização para podermos confirmar sua consulta.';
        return erros;
    }

    function carregando(sim) {
        botao.disabled = sim;
        botao.classList.toggle('is-carregando', sim);
    }

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        mostrarAviso('');
        if (!estado.servico || !estado.data || !estado.hora) {
            abrirEtapa(!estado.servico ? 1 : 2);
            mostrarAviso('Escolha o tratamento, o dia e o horário.');
            return;
        }
        const erros = validarLocal();
        Object.keys(erros).forEach((n) => marcarErro(n, erros[n]));
        if (Object.keys(erros).length) {
            $campo(Object.keys(erros)[0]).focus();
            return;
        }

        carregando(true);
        try {
            const resp = await fetch(API_AGENDAR, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const json = await resp.json().catch(() => ({ ok: false }));

            if (resp.ok && json.ok) {
                mostrarSucesso(json);
                return;
            }
            if (json.codigo === 'horario_ocupado') {
                cache.delete(`${estado.servico.id}|${estado.mes}`);
                estado.hora = null;
                $campo('hora').value = '';
                resumo(2, '');
                abrirEtapa(2);
                await carregarMes(estado.data.slice(0, 7));
                mostrarAviso(json.erro);
                return;
            }
            if (json.campos) {
                Object.entries(json.campos).forEach(([n, t]) => {
                    if (n === 'horario') { abrirEtapa(2); }
                    marcarErro(n, t);
                });
            }
            mostrarAviso(json.erro || 'Não foi possível concluir. Tente novamente.');
        } catch {
            mostrarAviso('Sem conexão com o servidor. Verifique sua internet e tente de novo.');
        } finally {
            carregando(false);
        }
    });

    function mostrarSucesso(r) {
        sucesso.innerHTML = `
            <svg class="agendado-check" viewBox="0 0 52 52" aria-hidden="true">
                <circle cx="26" cy="26" r="24" />
                <path d="M15 27 l7 7 l15 -16" />
            </svg>
            <p class="sobretitulo">Consulta marcada</p>
            <h2 class="titulo-secao">Tudo certo, ${escapar(r.primeiro_nome)}.</h2>
            <p class="texto-secundario">Seu horário está reservado. Se precisar remarcar, é só chamar no WhatsApp.</p>
            <dl class="agendado-resumo">
                <div><dt>Tratamento</dt><dd>${escapar(r.servico)}</dd></div>
                <div><dt>Dia</dt><dd>${escapar(r.data_extenso)}</dd></div>
                <div><dt>Horário</dt><dd>${escapar(r.hora)} às ${escapar(r.hora_fim)}</dd></div>
            </dl>
            <div class="hero-acoes">
                <a class="btn-primario" href="${escapar(r.google)}" target="_blank" rel="noopener"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Adicionar à minha agenda</a>
                <a class="btn-secundario" href="${escapar(r.whatsapp)}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> Avisar no WhatsApp</a>
            </div>
            <a class="link-seta mt-4 d-inline-block" href="index.php">Voltar para o início <span aria-hidden="true">→</span></a>`;
        form.hidden = true;
        sucesso.hidden = false;
        sucesso.focus({ preventScroll: true });
        sucesso.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ---------- início ----------
    const pre = form.querySelector('input[name="servico_escolha"][data-pre-selecao]');
    if (pre) {
        pre.checked = true;
        escolherServico(pre, false);
    }
})();
