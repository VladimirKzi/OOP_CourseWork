'use strict';
const AuthPage = {
  render(params = {}) {
    const isLogin = (params.mode || 'login') === 'login';
    document.getElementById('app').innerHTML = `
      <div style="max-width:400px;margin:0 auto">
        <div class="card">
          <h1 class="page-title" style="margin-bottom:4px">${isLogin ? 'Вхід' : 'Реєстрація'}</h1>
          <p class="page-subtitle">${isLogin ? 'Ласкаво просимо до BookRatings' : 'Створіть акаунт читача'}</p>
          <div id="auth-error"></div>
          <form id="auth-form">
            ${!isLogin ? `<div class="form-group"><label class="form-label">Ім'я</label><input class="form-input" id="f-name" type="text" placeholder="Ваше ім'я" required minlength="2"/></div>` : ''}
            <div class="form-group"><label class="form-label">Електронна пошта</label><input class="form-input" id="f-email" type="email" placeholder="email@example.com" required/></div>
            <div class="form-group"><label class="form-label">Пароль</label><input class="form-input" id="f-pass" type="password" placeholder="••••••••" required minlength="6"/></div>
            ${!isLogin ? `<div class="form-group"><label class="form-label">Підтвердження пароля</label><input class="form-input" id="f-confirm" type="password" placeholder="••••••••" required/></div>` : ''}
            <button class="btn btn--primary btn--block" type="submit" id="submit-btn">${isLogin ? 'Увійти' : 'Зареєструватися'}</button>
          </form>
          <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--text-muted)">
            ${isLogin ? 'Немає акаунту?' : 'Вже маєте акаунт?'}
            <button id="switch-btn" style="background:none;border:none;color:var(--amber);font-weight:600;font-size:13px;cursor:pointer">
              ${isLogin ? 'Зареєструватися' : 'Увійти'}
            </button>
          </p>
          ${isLogin ? '<p style="text-align:center;margin-top:6px;font-size:11px;color:var(--text-faint)">Тест: admin@books.ua / admin123</p>' : ''}
        </div>
      </div>`;

    document.getElementById('switch-btn').addEventListener('click', () =>
      Router.go(isLogin ? '/auth/register' : '/auth/login'));

    document.getElementById('auth-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('auth-error');
      const btn   = document.getElementById('submit-btn');
      errEl.innerHTML = '';
      btn.disabled = true;
      btn.textContent = 'Зачекайте...';

      try {
        let data;
        if (isLogin) {
          data = await Api.login(
            document.getElementById('f-email').value,
            document.getElementById('f-pass').value
          );
        } else {
          const pass = document.getElementById('f-pass').value;
          if (pass !== document.getElementById('f-confirm').value) throw new Error('Паролі не збігаються');
          data = await Api.register(
            document.getElementById('f-name').value,
            document.getElementById('f-email').value,
            pass
          );
        }
        Auth.setSession(data.token, data.user);
        Nav.render();
        Router.go('/');
      } catch (err) {
        errEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
        btn.disabled = false;
        btn.textContent = isLogin ? 'Увійти' : 'Зареєструватися';
      }
    });
  },
};
