/* Summary-only, on-demand application autocomplete (no external dependency). */
(function () {
  'use strict';
  function setup(input) {
    var form = input.form, wrap = document.createElement('div');
    wrap.className = 'ias-async-wrap';
    input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    var menu = document.createElement('div'); menu.className = 'ias-async-menu';
    menu.setAttribute('role', 'listbox'); menu.hidden = true; wrap.appendChild(menu);
    var timer, pending, requestId = 0, values = [];
    function close() { menu.hidden = true; input.setAttribute('aria-expanded', 'false'); }
    function paint(items) {
      menu.replaceChildren();
      items.forEach(function (name) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'ias-async-option';
        b.setAttribute('role', 'option'); b.textContent = name;
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { input.value = name; close(); input.focus(); });
        menu.appendChild(b);
      });
      if (!items.length) {
        var note = document.createElement('div'); note.className = 'ias-async-hint';
        note.textContent = 'No matching software titles'; menu.appendChild(note);
      }
      menu.hidden = false; input.setAttribute('aria-expanded', 'true');
    }
    input.addEventListener('input', function () {
      clearTimeout(timer); if (pending) pending.abort();
      var q = input.value.trim(), id = ++requestId;
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        pending = new AbortController();
        fetch(input.dataset.source + '?q=' + encodeURIComponent(q), {
          credentials: 'same-origin', signal: pending.signal,
          headers: { 'Accept': 'application/json' }
        }).then(function (r) { if (!r.ok) throw Error('Search failed'); return r.json(); })
          .then(function (data) { if (id === requestId && input.value.trim() === q) paint(Array.isArray(data.items) ? data.items : []); })
          .catch(function (e) { if (e.name !== 'AbortError') close(); });
      }, 350);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'ArrowDown' && !menu.hidden) { e.preventDefault(); var first = menu.querySelector('button'); if (first) first.focus(); }
    });
    menu.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); input.focus(); }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); var all = Array.from(menu.querySelectorAll('button'));
        var i = all.indexOf(document.activeElement), n = e.key === 'ArrowDown' ? Math.min(i + 1, all.length - 1) : Math.max(0, i - 1);
        if (all[n]) all[n].focus();
      }
    });
    document.addEventListener('pointerdown', function (e) { if (!wrap.contains(e.target)) close(); });
    if (form) form.addEventListener('submit', function () { clearTimeout(timer); if (pending) pending.abort(); });
  }
  function init() { document.querySelectorAll('.iad-summary-ui input.ias-app-autocomplete').forEach(setup); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
