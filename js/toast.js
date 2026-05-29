'use strict';
const Toast = {
  show(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.textContent = msg;
    document.getElementById('toast-container').appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('toast--show')));
    setTimeout(() => { el.classList.remove('toast--show'); setTimeout(() => el.remove(), 300); }, 3000);
  },
  success: (m) => Toast.show(m, 'success'),
  error:   (m) => Toast.show(m, 'error'),
  warning: (m) => Toast.show(m, 'warning'),
};
