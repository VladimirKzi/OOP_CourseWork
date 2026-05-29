'use strict';
const Nav = {
  render() {
    const user    = Auth.getUser();
    const actions = document.getElementById('nav-actions');
    actions.innerHTML = '';

    if (user) {
      if (Auth.isMod()) {
        actions.appendChild(Utils.el('button', { class: 'btn btn--sm', onclick: () => Router.go('/admin') }, 'Адмін'));
      }
      actions.appendChild(Utils.el('div', {
        class: 'nav__avatar', title: user.name,
        onclick: () => Router.go('/profile'),
      }, Utils.initials(user.name)));
      actions.appendChild(Utils.el('button', {
        class: 'btn btn--sm',
        onclick: () => { Auth.clearSession(); Nav.render(); Router.go('/'); },
      }, 'Вийти'));
    } else {
      actions.appendChild(Utils.el('button', { class: 'btn btn--sm',          onclick: () => Router.go('/auth/login')    }, 'Вхід'));
      actions.appendChild(Utils.el('button', { class: 'btn btn--sm btn--primary', onclick: () => Router.go('/auth/register') }, 'Реєстрація'));
    }
  },
};

document.getElementById('search-input').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && e.target.value.trim())
    Router.go('/?q=' + encodeURIComponent(e.target.value.trim()));
});
document.querySelector('.nav__brand').addEventListener('click', (e) => {
  e.preventDefault(); Router.go('/');
});
