'use strict';
const Utils = {
  el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') node.className = v;
      else if (k === 'html') node.innerHTML = v;
      else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else node.setAttribute(k, v);
    }
    for (const child of children) {
      if (child == null) continue;
      node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }
    return node;
  },

  fmtDate(s) {
    return new Date(s).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
  },

  starsHtml(rating) {
    const full = Math.floor(rating), half = rating % 1 >= 0.5, empty = 5 - full - (half ? 1 : 0);
    return '★'.repeat(full) + (half ? '½' : '') + '☆'.repeat(empty);
  },

  initials(name) {
    return (name || '?').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
  },

  skeletons(n = 4, cls = 'skeleton--book') {
    return Array.from({ length: n }, () => `<div class="skeleton ${cls}" style="height:80px;margin-bottom:10px"></div>`).join('');
  },

  starPicker(current, onPick) {
    const wrap = Utils.el('div', { class: 'star-picker' });
    const update = (v) => wrap.querySelectorAll('.star-picker__star').forEach((s, i) => s.classList.toggle('star-picker__star--filled', i < v));
    for (let i = 1; i <= 5; i++) {
      const s = Utils.el('span', { class: 'star-picker__star' + (i <= current ? ' star-picker__star--filled' : '') }, '★');
      s.addEventListener('mouseenter', () => update(i));
      s.addEventListener('mouseleave', () => update(current));
      s.addEventListener('click', () => { current = i; onPick(i); update(i); });
      wrap.appendChild(s);
    }
    return wrap;
  },

  pagination(currentPage, totalPages, onChange) {
    if (totalPages <= 1) return null;
    const bar = Utils.el('div', { class: 'pagination' });
    for (let i = 1; i <= totalPages; i++) {
      const btn = Utils.el('button', {
        class: 'pagination__btn' + (i === currentPage ? ' pagination__btn--active' : ''),
        onclick: () => onChange(i),
      }, String(i));
      bar.appendChild(btn);
    }
    return bar;
  },
};
