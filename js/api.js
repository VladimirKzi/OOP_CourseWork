'use strict';
const Api = (() => {
  async function req(method, path, body) {
    const token = Auth.getToken();
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;
    const res = await fetch(API + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    if (res.status === 204) return null;
    const data = await res.json();
    if (!res.ok) throw new Error(data?.error || 'HTTP ' + res.status);
    return data;
  }
  function qs(p) { const s = new URLSearchParams(); for (const [k,v] of Object.entries(p)) { if (v != null && v !== '') s.set(k, v); } const r = s.toString(); return r ? '?' + r : ''; }
  return {
    register: (name, email, password) => req('POST', '/auth/register', { name, email, password }),
    login:    (email, password)        => req('POST', '/auth/login',    { email, password }),
    me:       ()                       => req('GET',  '/auth/me'),
    getBooks:   (p = {}) => req('GET', '/books' + qs(p)),
    getBook:    (id)     => req('GET', '/books/' + id),
    createBook: (d)      => req('POST',   '/books', d),
    updateBook: (id, d)  => req('PUT',    '/books/' + id, d),
    deleteBook: (id)     => req('DELETE', '/books/' + id),
    getComments:   (bookId, p = {}) => req('GET', '/comments/book/' + bookId + qs(p)),
    getReplies:    (id)              => req('GET', '/comments/' + id + '/replies'),
    getFlagged:    ()                => req('GET', '/comments/flagged'),
    createComment: (d)               => req('POST',   '/comments', d),
    updateComment: (id, text)        => req('PUT',    '/comments/' + id, { text }),
    deleteComment: (id)              => req('DELETE', '/comments/' + id),
    voteComment:   (cid, vt)         => req('POST', '/comments/vote', { comment_id: cid, vote_type: vt }),
    flagComment:   (id)              => req('POST', '/comments/' + id + '/flag', {}),
    rateBook: (book_id, value) => req('POST', '/ratings', { book_id, value }),
    myRating: (bookId)         => req('GET',  '/ratings/book/' + bookId + '/my'),
    getUsers:        ()          => req('GET',  '/users'),
    getUser:         (id)        => req('GET',  '/users/' + id),
    getUserComments: (id)        => req('GET',  '/users/' + id + '/comments'),
    updateProfile:   (d)         => req('PUT',  '/users/me', d),
    blockUser:       (id)        => req('POST', '/users/' + id + '/block', {}),
    changeRole:      (id, role)  => req('POST', '/users/' + id + '/role', { role }),
  };
})();
