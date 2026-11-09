(function () {
  'use strict';
  var form = document.getElementById('config-form');
  var btn = document.getElementById('test-btn');
  var result = document.getElementById('test-result');
  var port = document.getElementById('smtp_port');
  var enc = document.getElementById('encryption');
  var hint = document.getElementById('enc-hint');
  var warn = document.getElementById('enc-warning');
  if (!form || !btn) { return; }

  function updateHints() {
    warn.hidden = enc.value !== 'none';
    var p = parseInt(port.value, 10);
    var msg = '';
    if (p === 465 && enc.value !== 'ssl') { msg = 'Port 465 normally uses SSL.'; }
    if (p === 587 && enc.value !== 'tls') { msg = 'Port 587 normally uses TLS (STARTTLS).'; }
    hint.textContent = msg;
    hint.hidden = msg === '';
  }
  port.addEventListener('input', updateHints);
  enc.addEventListener('change', updateHints);
  updateHints();

  btn.addEventListener('click', function () {
    result.className = 'test-result';
    result.textContent = 'Testing connection…';
    btn.disabled = true;
    fetch(btn.getAttribute('data-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': window.MailDesk.csrf(), 'Accept': 'application/json' },
      body: new FormData(form)
    }).then(function (r) {
      return r.json().then(function (j) { return { status: r.status, json: j }; });
    }).then(function (res) {
      var j = res.json;
      if (res.status === 429 || res.status === 401 || res.status === 419) {
        result.className = 'test-result fail';
        result.textContent = j.error || 'Request refused.';
        return;
      }
      if (j.ok) {
        result.className = 'test-result ok';
        result.textContent = '✔ ' + j.message;
      } else {
        result.className = 'test-result fail';
        result.textContent = '✖ ' + (j.message || 'Connection failed.');
      }
    }).catch(function () {
      result.className = 'test-result fail';
      result.textContent = '✖ Could not reach the server.';
    }).finally(function () { btn.disabled = false; });
  });
})();
