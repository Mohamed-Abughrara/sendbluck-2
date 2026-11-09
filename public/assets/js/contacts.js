(function () {
  'use strict';
  var dlg = document.getElementById('contact-dialog');
  var form = document.getElementById('contact-form');
  var del = document.getElementById('delete-dialog');
  var delForm = document.getElementById('delete-form');
  if (!dlg) { return; }

  function resetErrors() {
    dlg.querySelectorAll('.field-error').forEach(function (n) { n.remove(); });
    dlg.querySelectorAll('[aria-invalid]').forEach(function (n) { n.removeAttribute('aria-invalid'); });
  }
  function openContact(mode, data) {
    resetErrors();
    document.getElementById('contact-dialog-title').textContent = mode === 'edit' ? 'Edit contact' : 'Add contact';
    form.action = mode === 'edit' ? '/contacts/' + data.id : '/contacts';
    document.getElementById('contact-name').value = data.name || '';
    document.getElementById('contact-email').value = data.email || '';
    dlg.showModal();
    document.getElementById('contact-name').focus();
  }

  document.getElementById('add-contact-btn').addEventListener('click', function () { openContact('add', {}); });
  document.querySelectorAll('[data-open-add]').forEach(function (b) {
    b.addEventListener('click', function () { openContact('add', {}); });
  });
  document.querySelectorAll('[data-edit]').forEach(function (b) {
    b.addEventListener('click', function () {
      openContact('edit', { id: b.dataset.id, name: b.dataset.name, email: b.dataset.email });
    });
  });
  document.querySelectorAll('[data-delete]').forEach(function (b) {
    b.addEventListener('click', function () {
      delForm.action = '/contacts/' + b.dataset.id + '/delete';
      document.getElementById('delete-text').textContent =
        'Delete ' + b.dataset.name + ' (' + b.dataset.email + ')? This cannot be undone.';
      del.showModal();
    });
  });
  document.querySelectorAll('dialog [data-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });
  if (dlg.getAttribute('data-open') === '1') { dlg.showModal(); }

  // Debounced server-side search (300 ms).
  var search = document.getElementById('q');
  var searchForm = document.getElementById('contact-search');
  var timer;
  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { searchForm.submit(); }, 300);
    });
    if (search.value) { var l = search.value.length; search.focus(); search.setSelectionRange(l, l); }
  }

  // Selection + "Send to selected".
  var all = document.getElementById('select-all');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
  var count = document.getElementById('selected-count');
  var sendBtn = document.getElementById('send-selected');
  function refresh() {
    var n = rows.filter(function (r) { return r.checked; }).length;
    if (count) { count.textContent = String(n); }
    if (sendBtn) { sendBtn.disabled = n === 0; }
    if (all) { all.checked = n > 0 && n === rows.length; }
  }
  rows.forEach(function (r) { r.addEventListener('change', refresh); });
  if (all) {
    all.addEventListener('change', function () {
      rows.forEach(function (r) { r.checked = all.checked; });
      refresh();
    });
  }
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      var ids = rows.filter(function (r) { return r.checked; }).map(function (r) { return 'to[]=' + encodeURIComponent(r.value); });
      if (ids.length) { window.location.href = '/mail/compose?' + ids.join('&'); }
    });
  }
  refresh();
})();
