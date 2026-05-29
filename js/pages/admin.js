'use strict';
const AdminPage = {
  _tab: 'books', _bPage: 1,

  async render() {
    if (!Auth.isMod()) { Router.go('/'); return; }
    const user = Auth.getUser();
    document.getElementById('app').innerHTML = `
      <button class="back-btn" id="back-btn">← На головну</button>
      <h1 class="page-title">Панель адміністратора</h1>
      <p class="page-subtitle">${user.name} · ${user.role === 'admin' ? 'Адміністратор' : 'Модератор'}</p>
      <div class="tabs">
        <button class="tab tab--active" data-tab="books">Книги</button>
        <button class="tab" data-tab="users">Користувачі</button>
        <button class="tab" data-tab="flagged">Скарги</button>
      </div>
      <div id="tab-content"></div>`;

    document.getElementById('back-btn').addEventListener('click', () => Router.go('/'));
    document.querySelectorAll('.tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(b => b.classList.remove('tab--active'));
        btn.classList.add('tab--active');
        this._tab = btn.dataset.tab;
        this._loadTab();
      });
    });
    this._loadTab();
  },

  _loadTab() {
    if (this._tab === 'books')   this._renderBooks();
    if (this._tab === 'users')   this._renderUsers();
    if (this._tab === 'flagged') this._renderFlagged();
  },

  async _renderBooks() {
    const wrap = document.getElementById('tab-content');
    wrap.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <span style="font-size:13px;color:var(--text-muted)" id="books-total">...</span>
        <button class="btn btn--primary btn--sm" id="add-btn">+ Додати</button>
      </div>
      <div id="add-form"></div>
      <div id="books-table">${Utils.skeletons(3)}</div>
      <div id="books-pag"></div>`;
    document.getElementById('add-btn').addEventListener('click', () => this._toggleAddForm());
    await this._loadBooks();
  },

  _toggleAddForm() {
    const wrap = document.getElementById('add-form');
    if (wrap.innerHTML) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = `
      <div class="card" style="margin-bottom:20px">
        <h3 style="font-family:var(--serif);font-size:18px;margin-bottom:16px">Нова книга</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label class="form-label">Назва *</label><input class="form-input" id="nb-title"/></div>
          <div class="form-group"><label class="form-label">Автор *</label><input class="form-input" id="nb-author"/></div>
          <div class="form-group"><label class="form-label">Жанр</label><input class="form-input" id="nb-genre"/></div>
          <div class="form-group"><label class="form-label">Рік</label><input class="form-input" type="number" id="nb-year"/></div>
          <div class="form-group"><label class="form-label">Емодзі</label><input class="form-input" id="nb-emoji" value="📚"/></div>
        </div>
        <div class="form-group"><label class="form-label">Опис</label><textarea class="form-input" id="nb-desc" style="min-height:70px;resize:vertical"></textarea></div>
        <button class="btn btn--primary btn--sm" id="save-book-btn">Додати</button>
      </div>`;
    document.getElementById('save-book-btn').addEventListener('click', async () => {
      const title = document.getElementById('nb-title').value.trim();
      const author = document.getElementById('nb-author').value.trim();
      if (!title || !author) { Toast.warning("Назва та автор обов'язкові"); return; }
      try {
        await Api.createBook({ title, author, description: document.getElementById('nb-desc').value, genre: document.getElementById('nb-genre').value, cover_emoji: document.getElementById('nb-emoji').value || '📚', published_year: document.getElementById('nb-year').value ? parseInt(document.getElementById('nb-year').value) : null });
        Toast.success('Книгу додано'); wrap.innerHTML = ''; this._loadBooks();
      } catch (err) { Toast.error(err.message); }
    });
  },

  async _loadBooks() {
    try {
      const data = await Api.getBooks({ page: this._bPage, per_page: 10 });
      const el = document.getElementById('books-total');
      if (el) el.textContent = 'Всього: ' + data.total + ' книг';
      document.getElementById('books-table').innerHTML = `
        <table class="admin-table">
          <thead><tr><th>Книга</th><th>Жанр</th><th>Рейтинг</th><th>Коментарі</th><th>Дія</th></tr></thead>
          <tbody>${data.items.map(b => `
            <tr>
              <td><div style="font-weight:600">${b.cover_emoji} ${b.title}</div><div style="font-size:12px;color:var(--text-muted)">${b.author}</div></td>
              <td>${b.genre || '—'}</td>
              <td style="color:var(--amber-dark);font-weight:600">${parseFloat(b.average_rating).toFixed(1)} ★</td>
              <td>${b.comment_count}</td>
              <td><button class="btn btn--sm btn--danger" data-del="${b.id}">Видалити</button></td>
            </tr>`).join('')}
          </tbody>
        </table>`;
      document.querySelectorAll('[data-del]').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('Видалити книгу?')) return;
          try { await Api.deleteBook(btn.dataset.del); Toast.success('Видалено'); this._loadBooks(); }
          catch (err) { Toast.error(err.message); }
        });
      });
      const pagWrap = document.getElementById('books-pag');
      if (pagWrap) { pagWrap.innerHTML = ''; const pag = Utils.pagination(data.page, data.pages, p => { this._bPage = p; this._loadBooks(); }); if (pag) pagWrap.appendChild(pag); }
    } catch (err) { Toast.error(err.message); }
  },

  async _renderUsers() {
    const wrap = document.getElementById('tab-content');
    wrap.innerHTML = `<div id="users-table">${Utils.skeletons(3)}</div>`;
    try {
      const users = await Api.getUsers();
      wrap.innerHTML = `
        <table class="admin-table">
          <thead><tr><th>Ім'я</th><th>Email</th><th>Роль</th><th>Статус</th><th>Дата</th><th>Дії</th></tr></thead>
          <tbody>${users.map(u => `
            <tr>
              <td style="font-weight:600">${u.name}</td>
              <td style="color:var(--text-muted);font-size:12px">${u.email}</td>
              <td><select data-role-uid="${u.id}" style="font-size:12px;padding:2px 6px;border:1px solid var(--border-md);border-radius:6px;background:var(--bg)">
                <option value="user"      ${u.role==='user'      ?'selected':''}>Читач</option>
                <option value="moderator" ${u.role==='moderator' ?'selected':''}>Модератор</option>
                <option value="admin"     ${u.role==='admin'     ?'selected':''}>Адмін</option>
              </select></td>
              <td><span class="badge badge--${u.is_blocked ? 'block' : 'active'}">${u.is_blocked ? 'Заблоковано' : 'Активний'}</span></td>
              <td style="font-size:12px;color:var(--text-faint)">${Utils.fmtDate(u.created_at)}</td>
              <td>${u.role !== 'admin' ? `<button class="btn btn--sm ${u.is_blocked ? '' : 'btn--danger'}" data-block-uid="${u.id}">${u.is_blocked ? 'Розблокувати' : 'Заблокувати'}</button>` : ''}</td>
            </tr>`).join('')}
          </tbody>
        </table>`;
      document.querySelectorAll('[data-role-uid]').forEach(sel => {
        sel.addEventListener('change', async () => {
          try { await Api.changeRole(sel.dataset.roleUid, sel.value); Toast.success('Роль змінено'); }
          catch (err) { Toast.error(err.message); }
        });
      });
      document.querySelectorAll('[data-block-uid]').forEach(btn => {
        btn.addEventListener('click', async () => {
          try { await Api.blockUser(btn.dataset.blockUid); Toast.success('Оновлено'); this._renderUsers(); }
          catch (err) { Toast.error(err.message); }
        });
      });
    } catch (err) { wrap.innerHTML = `<p class="error-banner">${err.message}</p>`; }
  },

  async _renderFlagged() {
    const wrap = document.getElementById('tab-content');
    wrap.innerHTML = `<div>${Utils.skeletons(2)}</div>`;
    try {
      const list = await Api.getFlagged();
      if (!list.length) { wrap.innerHTML = '<div class="empty">Скарг немає ✓</div>'; return; }
      wrap.innerHTML = `
        <table class="admin-table">
          <thead><tr><th>Автор</th><th>Коментар</th><th>Дата</th><th>Дія</th></tr></thead>
          <tbody>${list.map(c => `
            <tr>
              <td style="font-weight:600;white-space:nowrap">${c.author.name}</td>
              <td><p style="font-size:13px;line-height:1.5">${c.text.slice(0, 120)}${c.text.length > 120 ? '…' : ''}</p></td>
              <td style="font-size:12px;color:var(--text-faint);white-space:nowrap">${Utils.fmtDate(c.created_at)}</td>
              <td><button class="btn btn--sm btn--danger" data-del-flag="${c.id}">Видалити</button></td>
            </tr>`).join('')}
          </tbody>
        </table>`;
      document.querySelectorAll('[data-del-flag]').forEach(btn => {
        btn.addEventListener('click', async () => {
          try { await Api.deleteComment(btn.dataset.delFlag); Toast.success('Видалено'); this._renderFlagged(); }
          catch (err) { Toast.error(err.message); }
        });
      });
    } catch (err) { wrap.innerHTML = `<p class="error-banner">${err.message}</p>`; }
  },
};
