/* =====================================================================
   Emerald Central Hub V11 — shared front-end helpers
   Exposes window.EM (used by views, modules and the public notepad):
     EM.post(url, data)      POST with CSRF field → Promise<json>
     EM.get(url)             GET → Promise<json>
     EM.toast(msg, type)     toast notification (ok|err|'')
     EM.modal(html, opts)    open modal, returns {close, el}
     EM.escapeHtml(s)        XSS-safe string
     EM.fmtTime(ts)          readable date from unix ts
     EM.fmtDate(str)         readable date from "Y-m-d H:i:s"
     EM.theme() / EM.applyTheme / toggle in sidebar + login
     EM.heartbeat()          presence ping (dashboard), throttled server-side
     EM.ready(fn)            run after DOM ready
   ===================================================================== */
(function () {
  'use strict';

  function get(url) {
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      });
  }

  function post(url, data) {
    var fd = new FormData();
    if (window.EM && window.EM.csrf) { fd.append('csrf', window.EM.csrf); }
    Object.keys(data || {}).forEach(function (k) {
      if (data[k] !== undefined && data[k] !== null) { fd.append(k, data[k]); }
    });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      });
  }

  var toastTimer = null;
  function toast(msg, type) {
    var el = document.getElementById('toast');
    if (!el) { return; }
    el.textContent = msg;
    el.className = 'toast show' + (type === 'ok' ? ' ok' : (type === 'err' ? ' err' : ''));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.className = 'toast'; }, 2800);
  }

  function modal(html, opts) {
    opts = opts || {};
    var mask = document.getElementById('modalMask');
    var box = document.getElementById('modalBox');
    if (!mask || !box) { return { close: function () {} }; }
    box.innerHTML = html;
    mask.classList.remove('hide');
    var close = function () { mask.classList.add('hide'); box.innerHTML = ''; };
    if (opts.dismissible !== false) {
      mask.addEventListener('click', function (e) { if (e.target === mask) { close(); } });
    }
    var esc = function (e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); } };
    document.addEventListener('keydown', esc);
    return { close: close, el: box };
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fmtTime(ts) {
    ts = parseInt(ts, 10) || 0;
    if (!ts) { return '—'; }
    var d = new Date(ts * 1000);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  function fmtDate(s) {
    if (!s) { return '—'; }
    return String(s).replace('T', ' ');
  }

  /* ----------------------------- theme ----------------------------- */
  function theme() {
    try { return localStorage.getItem('emerald_theme') || 'dark'; } catch (e) { return 'dark'; }
  }
  function applyTheme() {
    var t = theme();
    document.documentElement.setAttribute('data-theme', t);
    var toggles = document.querySelectorAll('.theme-toggle');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].textContent = t === 'dark' ? '◐ Light' : '◑ Dark';
    }
  }
  function toggleTheme() {
    var t = theme() === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('emerald_theme', t); } catch (e) {}
    applyTheme();
  }

  /* --------------------------- heartbeat --------------------------- */
  var hbTimer = null;
  function heartbeat() {
    var dot = document.getElementById('hbDot');
    if (dot) { dot.className = 'online-dot'; }
    get('index.php?api=heartbeat').then(function (res) {
      if (dot) { dot.className = 'online-dot ' + (res && res.status === 'success' ? 'live' : 'dead'); }
    }).catch(function () { if (dot) { dot.className = 'online-dot dead'; } });
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else { fn(); }
  }

  /* ------------------------- initialise ---------------------------- */
  ready(function () {
    applyTheme();
    var toggles = document.querySelectorAll('.theme-toggle');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].addEventListener('click', toggleTheme);
    }
    // presence ping on the dashboard shell
    if (document.body.classList.contains('app-body')) {
      heartbeat();
      hbTimer = setInterval(heartbeat, 30000);
    }
  });

  window.EM = {
    csrf: window.EM && window.EM.csrf ? window.EM.csrf : '',
    user: window.EM && window.EM.user ? window.EM.user : '',
    role: window.EM && window.EM.role ? window.EM.role : '',
    page: window.EM && window.EM.page ? window.EM.page : '',
    get: get, post: post, toast: toast, modal: modal,
    escapeHtml: escapeHtml, fmtTime: fmtTime, fmtDate: fmtDate,
    theme: theme, applyTheme: applyTheme, toggleTheme: toggleTheme,
    heartbeat: heartbeat, ready: ready
  };
})();
