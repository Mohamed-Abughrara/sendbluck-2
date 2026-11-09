(function () {
  'use strict';

  // Toasts: dismiss button + auto-dismiss after 5 s (errors stay until closed).
  function wireToast(t) {
    var close = t.querySelector('.toast-close');
    var remove = function () { t.remove(); };
    if (close) { close.addEventListener('click', remove); }
    if (t.getAttribute('data-type') !== 'error') { setTimeout(remove, 5000); }
  }
  document.querySelectorAll('.toast').forEach(wireToast);

  window.MailDesk = window.MailDesk || {};
  window.MailDesk.toast = function (type, message) {
    var box = document.getElementById('toasts');
    if (!box) { return; }
    var t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.setAttribute('data-type', type);
    var span = document.createElement('span');
    span.textContent = message;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'toast-close';
    btn.setAttribute('aria-label', 'Dismiss notification');
    btn.textContent = '×';
    t.appendChild(span);
    t.appendChild(btn);
    box.appendChild(t);
    wireToast(t);
  };
  window.MailDesk.csrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };

  // Prevent double submits on buttons marked data-once.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    form.querySelectorAll('button[data-once]').forEach(function (b) {
      setTimeout(function () { b.disabled = true; }, 0);
    });
  });

  // Close the user menu when clicking elsewhere.
  document.addEventListener('click', function (e) {
    var menu = document.querySelector('.user-menu[open]');
    if (menu && !menu.contains(e.target)) { menu.removeAttribute('open'); }
  });
})();
