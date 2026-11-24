/*
 * Link da bio: liga o vídeo de fundo (só quando faz sentido) e puxa do painel
 * o próximo evento e o link de avaliação do Google. Sem API, a página segue estática.
 */
(() => {
  // Vídeo: não carrega com economia de dados ou movimento reduzido; o poster fica.
  const video = document.querySelector('.bio__video');
  const conexao = navigator.connection || {};
  const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (video && !reduzido && !conexao.saveData && !/(^|-)2g$/.test(conexao.effectiveType || '')) {
    video.src = video.dataset.src;
    video.addEventListener('playing', () => video.classList.add('is-playing'), { once: true });
    video.play().catch(() => { /* autoplay bloqueado: fica o poster */ });
  }

  const json = (url) => fetch(url, { headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)));

  // Próximo evento publicado
  json('api/eventos.php').then((d) => {
    const ev = d && d.ok && d.eventos && d.eventos[0];
    const card = document.querySelector('.bio__evento');
    if (!ev || !card) return;
    const img = card.querySelector('img');
    img.src = ev.foto;
    img.alt = '';
    const titulo = card.querySelector('[data-evento="titulo"]');
    const [primeira, ...resto] = String(ev.titulo).split(' ');
    const em = document.createElement('em');
    em.textContent = primeira;
    titulo.append(em, resto.length ? ' ' + resto.join(' ') : '');
    card.querySelector('[data-evento="meta"]').textContent = [ev.data_card, ev.horario].filter(Boolean).join(' · ');
    card.setAttribute('aria-label', `Próximo evento: ${ev.titulo}, ${ev.data_card}`);
    card.hidden = false;
  }).catch(() => {});

  // Avaliação: abre direto a janela de avaliar quando o link está cadastrado no painel
  json('api/avaliacoes.php').then((d) => {
    if (!d || !d.ok) return;
    const link = document.querySelector('[data-link="avaliar"]');
    if (link && (d.avaliar || d.link)) link.href = d.avaliar || d.link;
    const nota = document.querySelector('small[data-link="nota"]');
    if (nota && d.nota) nota.textContent = `Nota ${d.nota} com ${d.total} avaliações · conte a sua`;
  }).catch(() => {});
})();
