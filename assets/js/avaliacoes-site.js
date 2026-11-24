/*
 * Avaliações da home, cadastradas pelo painel (api/avaliacoes.php).
 * Atualiza a nota, o total, o link do Google e os cards. Se a API não responder
 * (ex.: prévia estática), os cards escritos no HTML continuam valendo.
 */
(() => {
  const secao = document.getElementById('avaliacoes');
  if (!secao) return;

  const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;

  fetch('api/avaliacoes.php', { headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
    .then((dados) => {
      if (!dados || !dados.ok || !Array.isArray(dados.avaliacoes)) return;

      // Nota geral
      const score = secao.querySelector('.avaliacoes__score');
      if (score) {
        const total = Number(dados.total) || 0;
        score.setAttribute('aria-label', `Nota ${dados.nota} de 5 no Google, com ${plural(total, 'avaliação', 'avaliações')}`);
        const num = score.querySelector('.avaliacoes__score-num');
        if (num) num.textContent = dados.nota;
        const cont = score.querySelector('.avaliacoes__score-count');
        if (cont) cont.textContent = `${plural(total, 'avaliação', 'avaliações')} no Google`;
        const cheias = Math.round(Number(dados.nota_valor) || 5);
        score.querySelectorAll('.avaliacoes__stars svg').forEach((s, i) => s.classList.toggle('is-off', i >= cheias));
      }
      const cta = secao.querySelector('.avaliacoes__cta');
      if (cta && dados.link) cta.href = dados.link;

      // Cards
      const grid = secao.querySelector('.avaliacoes__grid');
      if (!grid) return;
      grid.textContent = '';
      grid.hidden = dados.avaliacoes.length === 0;
      const novos = dados.avaliacoes.map((a) => {
        const li = document.createElement('li');
        li.className = 'avaliacoes__card';
        li.dataset.reveal = 'aval';
        const q = document.createElement('blockquote');
        q.textContent = `“${a.texto}”`;
        const autor = document.createElement('p');
        autor.className = 'avaliacoes__author';
        autor.textContent = a.autor;
        const fonte = document.createElement('span');
        fonte.textContent = `Google · ${plural(Number(a.estrelas) || 5, 'estrela', 'estrelas')}`;
        autor.append(' ', fonte);
        li.append(q, autor);
        grid.appendChild(li);
        return li;
      });

      // Mesmo efeito de entrada dos cards originais
      if (!('IntersectionObserver' in window)) {
        novos.forEach((li) => li.classList.add('is-revealed'));
        return;
      }
      const obs = new IntersectionObserver((entradas) => {
        entradas.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('is-revealed');
            obs.unobserve(e.target);
          }
        });
      }, { threshold: 0.12 });
      novos.forEach((li) => obs.observe(li));
    })
    .catch(() => { /* sem API: mantém o conteúdo estático */ });
})();
