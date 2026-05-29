'use strict';
const Router = {
  _routes: [],

  add(pattern, handler) {
    this._routes.push({ pattern, handler });
  },

  go(path) {
    window.location.hash = '#' + path;
  },

  _match(path) {
    for (const route of this._routes) {
      const keys   = [];
      const regStr = '^' + route.pattern.replace(/:([a-zA-Z_]+)/g, (_, k) => { keys.push(k); return '([^/?]+)'; }) + '(?:\\?.*)?$';
      const m      = path.match(new RegExp(regStr));
      if (m) {
        const params = {};
        keys.forEach((k, i) => { params[k] = decodeURIComponent(m[i + 1]); });
        return { handler: route.handler, params };
      }
    }
    return null;
  },

  _dispatch() {
    const raw    = window.location.hash.slice(1) || '/';
    const [path, qs] = raw.split('?');
    const qsParams   = Object.fromEntries(new URLSearchParams(qs || ''));
    const match      = this._match(path);
    if (match) match.handler({ ...match.params, ...qsParams });
    else       HomePage.render(qsParams);
  },

  init() {
    window.addEventListener('hashchange', () => this._dispatch());
    this._dispatch();
  },
};

Router.add('/',              (p) => HomePage.render(p));
Router.add('/books/:id',     (p) => BookPage.render(p));
Router.add('/auth/login',    ()  => AuthPage.render({ mode: 'login' }));
Router.add('/auth/register', ()  => AuthPage.render({ mode: 'register' }));
Router.add('/profile',       ()  => ProfilePage.render());
Router.add('/admin',         ()  => AdminPage.render());
