(function () {
  'use strict';
  var form = document.getElementById('compose-form');
  if (!form) { return; }

  var max = parseInt(form.getAttribute('data-max'), 10) || 50;
  var editor = document.getElementById('editor');
  var body = document.getElementById('body');
  var subject = document.getElementById('subject');
  var sendBtn = document.getElementById('send-btn');
  var status = document.getElementById('send-status');
  var DRAFT_KEY = 'maildesk.draft';

  /* ---------- recipient chips with autocomplete (ARIA combobox) ---------- */
  function totalRecipients() { return form.querySelectorAll('.chip').length; }

  function setupField(field) {
    var name = field.getAttribute('data-name');
    var chips = field.querySelector('.chips');
    var input = field.querySelector('.chip-input');
    var list = field.querySelector('.suggestions');
    var items = [];
    var active = -1;
    var timer;
    var seq = 0;

    function hasId(id) { return !!form.querySelector('.chip[data-id="' + id + '"]'); }

    function close() {
      list.hidden = true;
      list.innerHTML = '';
      items = [];
      active = -1;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    }
    function highlight(i) {
      var lis = list.querySelectorAll('li');
      lis.forEach(function (li, idx) { li.setAttribute('aria-selected', idx === i ? 'true' : 'false'); });
      active = i;
      if (i >= 0 && lis[i]) {
        input.setAttribute('aria-activedescendant', lis[i].id);
        lis[i].scrollIntoView({ block: 'nearest' });
      }
    }
    function addChip(c) {
      if (hasId(c.id)) { return; }
      if (totalRecipients() >= max) {
        window.MailDesk.toast('error', 'Maximum ' + max + ' recipients per message.');
        return;
      }
      var chip = document.createElement('span');
      chip.className = 'chip';
      chip.setAttribute('data-id', c.id);
      var label = document.createElement('span');
      label.className = 'chip-label';
      label.textContent = c.name;
      label.title = c.email;
      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name + '[]';
      hidden.value = c.id;
      var x = document.createElement('button');
      x.type = 'button';
      x.className = 'chip-x';
      x.setAttribute('aria-label', 'Remove ' + c.name);
      x.textContent = '×';
      chip.appendChild(label); chip.appendChild(hidden); chip.appendChild(x);
      chips.appendChild(chip);
      input.value = '';
      close();
      saveDraft();
    }
    function render(results) {
      list.innerHTML = '';
      items = results.filter(function (c) { return !hasId(c.id); });
      if (!items.length) { close(); return; }
      items.forEach(function (c, i) {
        var li = document.createElement('li');
        li.id = name + '-opt-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', 'false');
        var n = document.createElement('span'); n.textContent = c.name;
        var m = document.createElement('small'); m.textContent = c.email;
        li.appendChild(n); li.appendChild(m);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); addChip(c); });
        list.appendChild(li);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      highlight(0);
    }
    function search(q) {
      var mine = ++seq;
      fetch('/api/contacts/search?q=' + encodeURIComponent(q), {
        credentials: 'same-origin', headers: { 'Accept': 'application/json' }
      }).then(function (r) {
        if (r.status === 429) { window.MailDesk.toast('error', 'Too many searches. Slow down a little.'); return []; }
        return r.ok ? r.json() : [];
      }).then(function (data) {
        if (mine === seq && Array.isArray(data)) { render(data); }
      }).catch(close);
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (!q) { close(); return; }
      timer = setTimeout(function () { search(q); }, 150);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); highlight((active + 1) % items.length); }
      else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
      else if (e.key === 'Enter') {
        // Only contacts can be chosen; never submit the form from here.
        e.preventDefault();
        if (items[active]) { addChip(items[active]); }
      }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Backspace' && input.value === '') {
        var last = chips.querySelector('.chip:last-child');
        if (last) { last.remove(); saveDraft(); }
      }
    });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    chips.addEventListener('click', function (e) {
      if (e.target.classList && e.target.classList.contains('chip-x')) {
        e.target.closest('.chip').remove();
        input.focus();
        saveDraft();
      }
    });
    field.addEventListener('click', function (e) { if (e.target === field) { input.focus(); } });
  }
  form.querySelectorAll('.chips-field').forEach(setupField);

  /* ---------- rich text ---------- */
  form.querySelectorAll('[data-cmd]').forEach(function (b) {
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function () {
      editor.focus();
      var cmd = b.getAttribute('data-cmd');
      if (cmd === 'createLink') {
        var url = window.prompt('Link address (https://…)');
        if (url && /^(https?:\/\/|mailto:)/i.test(url.trim())) { document.execCommand('createLink', false, url.trim()); }
        else if (url) { window.MailDesk.toast('error', 'Links must start with http://, https:// or mailto:'); }
      } else {
        document.execCommand(cmd, false, null);
      }
      saveDraft();
    });
  });
  // Paste as plain text to avoid foreign markup.
  editor.addEventListener('paste', function (e) {
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });

  /* ---------- draft kept in sessionStorage (survives session expiry redirect) ---------- */
  function safe(fn) { try { return fn(); } catch (err) { return null; } }
  function chipData(name) {
    return Array.prototype.map.call(form.querySelectorAll('.chips-field[data-name="' + name + '"] .chip'), function (c) {
      return { id: c.getAttribute('data-id'), name: c.querySelector('.chip-label').textContent };
    });
  }
  function hasContent() {
    return totalRecipients() > 0 || subject.value.trim() !== '' || editor.textContent.trim() !== '';
  }
  function saveDraft() {
    safe(function () {
      if (!hasContent()) { sessionStorage.removeItem(DRAFT_KEY); return; }
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
        to: chipData('to'), cc: chipData('cc'), subject: subject.value, body: editor.innerHTML
      }));
    });
  }
  function restoreDraft() {
    if (hasContent()) { return; }
    var raw = safe(function () { return sessionStorage.getItem(DRAFT_KEY); });
    if (!raw) { return; }
    var d = safe(function () { return JSON.parse(raw); });
    if (!d) { return; }
    subject.value = d.subject || '';
    // The draft is the user's own text; keep it as plain text to stay safe.
    editor.textContent = '';
    var tmp = document.createElement('div');
    tmp.innerHTML = '';
    (d.body || '').split(/<\/?(?:p|div|br)[^>]*>/i).forEach(function (line) {
      if (line.trim()) { var p = document.createElement('p'); p.textContent = line.replace(/<[^>]*>/g, ''); editor.appendChild(p); }
    });
    ['to', 'cc'].forEach(function (name) {
      var field = form.querySelector('.chips-field[data-name="' + name + '"]');
      (d[name] || []).forEach(function (c) {
        if (!/^\d+$/.test(String(c.id))) { return; }
        var chip = document.createElement('span');
        chip.className = 'chip'; chip.setAttribute('data-id', c.id);
        var l = document.createElement('span'); l.className = 'chip-label'; l.textContent = c.name;
        var h = document.createElement('input'); h.type = 'hidden'; h.name = name + '[]'; h.value = c.id;
        var x = document.createElement('button'); x.type = 'button'; x.className = 'chip-x';
        x.setAttribute('aria-label', 'Remove ' + c.name); x.textContent = '×';
        chip.appendChild(l); chip.appendChild(h); chip.appendChild(x);
        field.querySelector('.chips').appendChild(chip);
      });
    });
  }
  restoreDraft();
  subject.addEventListener('input', saveDraft);
  editor.addEventListener('input', saveDraft);

  /* ---------- submit / discard ---------- */
  form.addEventListener('submit', function (e) {
    if (form.getAttribute('data-can-send') !== '1') { e.preventDefault(); return; }
    if (totalRecipients() > max) {
      e.preventDefault();
      window.MailDesk.toast('error', 'Maximum ' + max + ' recipients per message.');
      return;
    }
    body.value = editor.innerHTML;
    sendBtn.disabled = true; // prevent double-send
    status.textContent = 'Sending…';
    safe(function () { sessionStorage.removeItem(DRAFT_KEY); });
  });

  document.getElementById('discard-btn').addEventListener('click', function () {
    if (hasContent() && !window.confirm('Discard this message?')) { return; }
    safe(function () { sessionStorage.removeItem(DRAFT_KEY); });
    window.location.href = '/mail';
  });

  // A successful redirect away from compose clears the draft; failed (422/409) renders keep server values.
})();
