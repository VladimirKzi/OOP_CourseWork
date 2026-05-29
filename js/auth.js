'use strict';
const Auth = (() => {
  let _user = null, _token = localStorage.getItem('br_token');
  return {
    getToken:   () => _token,
    getUser:    () => _user,
    isLoggedIn: () => !!_user,
    isMod:      () => _user && ['admin','moderator'].includes(_user.role),
    isAdmin:    () => _user && _user.role === 'admin',
    setSession(token, user) { _token = token; _user = user; localStorage.setItem('br_token', token); },
    clearSession()           { _token = null; _user = null; localStorage.removeItem('br_token'); },
    async restore() {
      if (!_token) return false;
      try { _user = await Api.me(); return true; }
      catch { localStorage.removeItem('br_token'); _token = null; return false; }
    },
  };
})();
