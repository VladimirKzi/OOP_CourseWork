'use strict';
const HomePage = {
  async render(params = {}) {
    const q    = params.q || '';
    const page = parseInt(params.page || '1');
    document.getElementById('search-input').value = q;

    const app = document.getElementById('app');
    app.innerHTML = `
      ${!q ? `<h1 class="page-title">Головна сторінка</h1><p class="page-subtitle">Читайте. Обговорюйте. Діліться враженнями.</p>` : ''}
      <div class="section-label">${q ? 'Знайдено' : 'Популярні книги'}</div>
      <div id="book-list">${Utils.skeletons(5)}</div>`;

    try {
      const data = await Api.getBooks({ q: q || undefined, page, per_page: 10 });
      const list = document.getElementById('book-list');

      if (q) {
        const info = document.createElement('p');
        info.className = 'search-info';
        info.textContent = `Результати для «${q}» — ${data.total} книг`;
        list.before(info);
      }

      if (!data.items.length) { list.innerHTML = '<div class="empty">📚 Нічого не знайдено</div>'; return; }

      list.innerHTML = data.items.map(b => {
        const avg = parseFloat(b.average_rating) || 0;
        const bg  = b.id % 2 === 0 ? '#FAEEDA' : '#E1F5EE';
        return `<div class="book-card" data-id="${b.id}">
          <div class="book-card__cover" style="background:${bg}">${b.cover_emoji || '📚'}</div>
          <div class="book-card__info">
            <div class="book-card__title">${b.title}</div>
            <div class="book-card__author">${b.author}${b.genre ? ' · ' + b.genre : ''}${b.published_year ? ' · ' + b.published_year : ''}</div>
            <div class="book-card__meta">
              <span class="stars stars--sm">${Utils.starsHtml(avg)}</span>
              <span class="book-card__rating">${avg.toFixed(1)}</span>
              ${b.ratings_count > 0 ? `<span style="font-size:11px;color:var(--text-faint)">(${b.ratings_count} оцінок)</span>` : ''}
            </div>
          </div>
          <span class="book-card__comments">💬 ${b.comment_count || 0}</span>
        </div>`;
      }).join('');

      list.querySelectorAll('.book-card').forEach(card =>
        card.addEventListener('click', () => Router.go('/books/' + card.dataset.id)));

      const pag = Utils.pagination(page, data.pages, (p) => {
        const ps = new URLSearchParams({ page: p });
        if (q) ps.set('q', q);
        Router.go('/?' + ps.toString());
      });
      if (pag) app.appendChild(pag);

    } catch (err) {
      document.getElementById('book-list').innerHTML = `<p class="error-banner">${err.message}</p>`;
    }
  },
};
