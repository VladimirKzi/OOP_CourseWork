'use strict';
const BookPage = {
  _sort: 'new', _comPage: 1, _bookId: null, _myRating: 0,

  async render(params = {}) {
    const id = parseInt(params.id);
    this._bookId = id; this._sort = 'new'; this._comPage = 1; this._myRating = 0;

    const app = document.getElementById('app');
    app.innerHTML = `<button class="back-btn" id="back-btn">← Назад</button>
      <div id="book-detail">${Utils.skeletons(1, '')}</div>
      <div id="comments-section"></div>`;
    document.getElementById('back-btn').addEventListener('click', () => history.back());

    try {
      const book = await Api.getBook(id);
      if (Auth.isLoggedIn()) Api.myRating(id).then(r => { this._myRating = r.value; this._renderHeader(book); }).catch(() => this._renderHeader(book));
      else this._renderHeader(book);
      this._renderCommentSection(id);
    } catch (err) {
      document.getElementById('book-detail').innerHTML = `<p class="error-banner">${err.message}</p>`;
    }
  },

  _renderHeader(book) {
    const avg = parseFloat(book.average_rating) || 0;
    const bg  = book.id % 2 === 0 ? '#FAEEDA' : '#E1F5EE';
    const user = Auth.getUser();
    document.getElementById('book-detail').innerHTML = `
      <div class="card" style="display:flex;gap:24px;margin-bottom:28px">
        <div style="width:88px;height:120px;border-radius:8px;flex-shrink:0;background:${bg};display:flex;align-items:center;justify-content:center;font-size:36px">${book.cover_emoji || '📚'}</div>
        <div style="flex:1">
          ${book.genre ? `<div style="font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">${book.genre}${book.published_year ? ' · ' + book.published_year : ''}</div>` : ''}
          <h1 style="font-family:var(--serif);font-size:24px;font-weight:700;margin-bottom:4px">${book.title}</h1>
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:10px">${book.author}</p>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <span class="stars stars--lg">${Utils.starsHtml(avg)}</span>
            <span style="font-size:16px;font-weight:700;color:var(--amber-dark)">${avg.toFixed(1)}</span>
            <span style="font-size:12px;color:var(--text-faint)">· ${book.ratings_count} оцінок · ${book.comment_count} коментарів</span>
          </div>
          ${book.description ? `<p style="font-size:13px;color:var(--text-muted);line-height:1.7">${book.description}</p>` : ''}
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px" id="rating-label">
              ${user ? (this._myRating ? 'Ваша оцінка: ' + this._myRating + ' ★' : 'Оцініть книгу:') : 'Увійдіть, щоб оцінити'}
            </div>
            <div id="star-picker-wrap"></div>
          </div>
        </div>
      </div>`;

    if (user) {
      document.getElementById('star-picker-wrap').appendChild(
        Utils.starPicker(this._myRating, async (val) => {
          try {
            await Api.rateBook(book.id, val);
            this._myRating = val;
            document.getElementById('rating-label').textContent = 'Ваша оцінка: ' + val + ' ★';
            Api.getBook(book.id).then(updated => this._renderHeader(updated)).catch(() => {});
            Toast.success('Оцінку збережено');
          } catch (err) { Toast.error(err.message); }
        })
      );
    }
  },

  _renderCommentSection(bookId) {
    document.getElementById('comments-section').innerHTML = `
      <div class="section-label" id="com-label">Коментарі</div>
      <div class="sort-row">
        <button class="sort-btn sort-btn--active" data-s="new">Нові</button>
        <button class="sort-btn" data-s="popular">Популярні</button>
      </div>
      <div id="comments-list">${Utils.skeletons(3, '')}</div>
      <div id="com-pag"></div>
      <hr class="divider"/>
      <div class="section-label">Залишити коментар</div>
      <div id="comment-form-wrap"></div>`;

    document.querySelectorAll('.sort-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('sort-btn--active'));
        btn.classList.add('sort-btn--active');
        this._sort = btn.dataset.s; this._comPage = 1; this._loadComments(bookId);
      });
    });

    this._renderCommentForm(bookId);
    this._loadComments(bookId);
  },

  async _loadComments(bookId) {
    const list = document.getElementById('comments-list');
    list.innerHTML = Utils.skeletons(3, '');
    try {
      const data = await Api.getComments(bookId, { sort: this._sort, page: this._comPage, per_page: 15 });
      document.getElementById('com-label').textContent = `Коментарі (${data.total})`;
      list.innerHTML = '';
      if (!data.items.length) { list.innerHTML = '<div class="empty">Коментарів ще немає. Будьте першим!</div>'; }
      else data.items.forEach(c => list.appendChild(CommentComponent.build(c, bookId)));

      const pagWrap = document.getElementById('com-pag');
      pagWrap.innerHTML = '';
      const pag = Utils.pagination(data.page, data.pages, p => { this._comPage = p; this._loadComments(bookId); });
      if (pag) pagWrap.appendChild(pag);
    } catch (err) { list.innerHTML = `<p class="error-banner">${err.message}</p>`; }
  },

  _renderCommentForm(bookId) {
    const wrap = document.getElementById('comment-form-wrap');
    if (!Auth.isLoggedIn()) {
      wrap.innerHTML = `<p style="font-size:14px;color:var(--text-muted)"><button class="btn btn--primary btn--sm" id="login-cta">Увійдіть</button> щоб залишити коментар</p>`;
      wrap.querySelector('#login-cta').addEventListener('click', () => Router.go('/auth/login'));
      return;
    }
    const area   = Utils.el('textarea', { class: 'form-input', placeholder: 'Ваші враження від книги...', style: 'width:100%;min-height:90px;resize:vertical' });
    const row    = Utils.el('div', { style: 'display:flex;justify-content:flex-end;margin-top:10px' });
    const submit = Utils.el('button', { class: 'btn btn--primary' }, 'Коментувати');
    row.appendChild(submit);
    wrap.appendChild(area);
    wrap.appendChild(row);
    submit.addEventListener('click', async () => {
      if (!area.value.trim()) return;
      submit.disabled = true;
      try {
        const c    = await Api.createComment({ text: area.value.trim(), book_id: bookId });
        const list = document.getElementById('comments-list');
        const node = CommentComponent.build(c, bookId);
        if (this._sort === 'new') list.prepend(node); else list.appendChild(node);
        area.value = '';
        Toast.success('Коментар додано');
      } catch (err) { Toast.error(err.message); }
      finally { submit.disabled = false; }
    });
  },
};
