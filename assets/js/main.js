// Comportamentos gerais do site público.
(() => {
    const reduzirMovimento = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Cabeçalho ganha borda/fundo ao rolar
    const topo = document.querySelector('[data-topo]');
    if (topo) {
        const atualizar = () => topo.classList.toggle('is-rolado', window.scrollY > 8);
        atualizar();
        window.addEventListener('scroll', atualizar, { passive: true });
    }

    // Botão do menu mobile: estado visual acompanha o collapse do Bootstrap
    const menu = document.getElementById('menuSite');
    const botaoMenu = document.querySelector('.menu-botao');
    if (menu && botaoMenu) {
        menu.addEventListener('show.bs.collapse', () => botaoMenu.classList.add('is-aberto'));
        menu.addEventListener('hide.bs.collapse', () => botaoMenu.classList.remove('is-aberto'));
    }

    // Revelação suave dos blocos ao entrar na tela (desligada para quem prefere menos movimento)
    const revelar = document.querySelectorAll('[data-revelar]');
    if (!reduzirMovimento && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entradas) => {
            entradas.forEach((en) => {
                if (en.isIntersecting) {
                    en.target.classList.add('is-visivel');
                    obs.unobserve(en.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        // Irmãos entram em cascata, com atraso pequeno e crescente
        revelar.forEach((el) => {
            const irmaos = [...el.parentElement.children].filter((c) => c.hasAttribute('data-revelar'));
            el.style.setProperty('--atraso', `${Math.min(irmaos.indexOf(el), 6) * 70}ms`);
            obs.observe(el);
        });
    } else {
        revelar.forEach((el) => el.classList.add('is-visivel'));
    }

    // Galeria do consultório: setas rolam uma foto por vez
    document.querySelectorAll('[data-galeria]').forEach((botao) => {
        botao.addEventListener('click', () => {
            const trilho = botao.closest('section').querySelector('[data-galeria-trilho]');
            const foto = trilho.querySelector('figure');
            const passo = foto ? foto.getBoundingClientRect().width + 16 : 300;
            trilho.scrollBy({ left: passo * Number(botao.dataset.galeria), behavior: reduzirMovimento ? 'auto' : 'smooth' });
        });
    });
})();
