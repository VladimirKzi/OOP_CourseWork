'use strict';
const ProfilePage = {
  async render() {
    const user = Auth.getUser();
    if (!user) { Router.go('/auth/login'); return; }

    const roleMap = { admin: 'Адміністратор', moderator: 'Модератор', user: 'Читач' };
    document.getElementById('app').innerHTML = `
      <button class="back-btn" id="back-btn">← Назад</button>
      <div class="card" style="margin-bottom:28px">
        <div style="display:flex;align-items:flex-start;gap:20px">
          <div class="profile-avatar">${Utils.initials(user.name)}</div>
          <div style="flex:1" id="profile-info">
            <h2 style="font-family:var(--serif);font-size:22px;font-weight:700;margin-bottom:4px">${user.name}</h2>
            <p style="font-size:13px;color:var(--text-muted)">${user.email}</p>
            <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
              <span class="badge badge--${user.role === 'admin' ? 'admin' : user.role === 'moderator' ? 'mod' : 'active'}">${roleMap[user.role]}</span>
              <span style="font-size:12px;color:var(--text-faint)">З ${Utils.fmtDate(user.created_at)}</span>
            </div>
            <div style="display:flex;gap:8px;margin-top:14px">
              <button class="btn btn--sm" id="edit-btn">Редагувати</button>
              <button class="btn btn--sm btn--danger" id="logout-btn">Вийти</button>
            </div>
          </div>
        </div>
      </div>
      <div class="section-label">Останні коментарі</div>
      <div id="user-comments">${Utils.skeletons(3)}</div>`;

    document.getElementById('back-btn').addEventListener('click', () => history.back());

    document.getElementById('logout-btn').addEventListener('click', () => {
      Auth.clearSession(); Nav.render(); Router.go('/');
    });

    document.getElementById('edit-btn').addEventListener('click', () => {
      const info = document.getElementById('profile-info');
      info.innerHTML = `
        <div class="form-group"><label class="form-label">Ім'я</label><input class="form-input" id="new-name" value="${user.name}"/></div>
        <div style="display:flex;gap:8px">
          <button class="btn btn--primary btn--sm" id="save-btn">Зберегти</button>
          <button class="btn btn--sm" id="cancel-btn">Скасувати</button>
        </div>`;
      document.getElementById('cancel-btn').addEventListener('click', () => ProfilePage.render());
      document.getElementById('save-btn').addEventListener('click', async () => {
        const name = document.getElementById('new-name').value.trim();
        if (!name) return;
        try {
          const updated = await Api.updateProfile({ name });
          Auth.setSession(Auth.getToken(), updated);
          Nav.render(); ProfilePage.render();
          Toast.success('Профіль оновлено');
        } catch (err) { Toast.error(err.message); }
      });
    });

    try {
      const comments = await Api.getUserComments(user.id);
      const wrap = document.getElementById('user-comments');
      if (!comments.length) { wrap.innerHTML = '<div class="empty">Ви ще не залишали коментарів</div>'; return; }
      wrap.innerHTML = '';
      comments.forEach(c => {
        const card = Utils.el('div', {
          class: 'card',
          style: 'cursor:pointer;padding:14px 18px;margin-bottom:10px',
          onclick: () => Router.go('/books/' + c.book_id),
        },
          Utils.el('div', { style: 'font-size:12px;color:var(--text-muted);margin-bottom:4px' },
            c.cover_emoji + ' ', Utils.el('strong', {}, c.book_title), ' · ' + Utils.fmtDate(c.created_at)),
          Utils.el('p', { style: 'font-size:14px;line-height:1.6' },
            c.text.length > 140 ? c.text.slice(0, 140) + '…' : c.text)
        );
        wrap.appendChild(card);
      });
    } catch (err) {
      document.getElementById('user-comments').innerHTML = `<p class="error-banner">${err.message}</p>`;
    }
  },
};
