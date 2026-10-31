/* Small dependency-free searchable dropdowns for existing report filters. */
(function () {
  'use strict';
  function init() {
    document.querySelectorAll('.iad-filter-bar select.iad-select, .ias-filters select.iad-select').forEach(function (select) {
      if (select.dataset.iadEnhanced === '1') return;
      select.dataset.iadEnhanced = '1';
      var choices = Array.from(select.options).map(function (o) {
        return { value: o.value, label: o.textContent.trim() };
      });
      if (!choices.length) return;
      var placeholder = choices[0].label;
      var wrapper = document.createElement('div');
      wrapper.className = 'iad-searchable';
      var input = document.createElement('input');
      input.type = 'text'; input.className = 'iad-searchable-input';
      input.autocomplete = 'off'; input.setAttribute('role', 'combobox');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-label', 'Search ' + (select.name || 'filter'));
      input.placeholder = 'Search ' + placeholder.replace(/^All\s+/i, '') + '...';
      var menu = document.createElement('div');
      menu.className = 'iad-searchable-menu'; menu.setAttribute('role', 'listbox');
      menu.hidden = true;
      select.parentNode.insertBefore(wrapper, select);
      wrapper.appendChild(select); wrapper.appendChild(input); wrapper.appendChild(menu);
      select.classList.add('iad-searchable-native');
      function selectedLabel() {
        var item = choices.find(function (c) { return c.value === select.value; });
        return select.value && item ? item.label : '';
      }
      input.value = selectedLabel();
      var activeIndex = -1;
      function close() { menu.hidden = true; input.setAttribute('aria-expanded', 'false'); activeIndex = -1; }
      function choose(item) {
        select.value = item.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        input.value = item.value ? item.label : '';
        close();
      }
      function render() {
        var query = input.value.trim().toLocaleLowerCase();
        var matching = choices.filter(function (c, i) {
          return i === 0 || !query || c.label.toLocaleLowerCase().includes(query);
        });
        var truncated = matching.length > 101;
        matching = matching.slice(0, 101);
        menu.replaceChildren();
        matching.forEach(function (item) {
          var button = document.createElement('button');
          button.type = 'button'; button.className = 'iad-searchable-option';
          button.setAttribute('role', 'option');
          button.textContent = item.label;
          if (select.value === item.value) button.classList.add('iad-searchable-selected');
          button.addEventListener('mousedown', function (e) { e.preventDefault(); });
          button.addEventListener('click', function () { choose(item); });
          menu.appendChild(button);
        });
        if (truncated) {
          var hint = document.createElement('div'); hint.className = 'iad-searchable-hint';
          hint.textContent = 'More matches available — keep typing'; menu.appendChild(hint);
        }
        menu.hidden = false; input.setAttribute('aria-expanded', 'true'); activeIndex = -1;
      }
      input.addEventListener('focus', function () { input.select(); render(); });
      input.addEventListener('input', render);
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { close(); input.value = selectedLabel(); return; }
        if (event.key === 'Tab') { close(); input.value = selectedLabel(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault(); if (menu.hidden) render();
          var buttons = Array.from(menu.querySelectorAll('button'));
          if (!buttons.length) return;
          activeIndex = event.key === 'ArrowDown' ? Math.min(activeIndex + 1, buttons.length - 1) : Math.max(activeIndex - 1, 0);
          buttons.forEach(function (b, i) { b.classList.toggle('iad-searchable-highlight', i === activeIndex); });
          buttons[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && !menu.hidden) {
          event.preventDefault();
          var buttons = Array.from(menu.querySelectorAll('button'));
          if (buttons.length) buttons[activeIndex >= 0 ? activeIndex : (buttons.length === 1 ? 0 : -1)]?.click();
        }
      });
      document.addEventListener('pointerdown', function (event) {
        if (!wrapper.contains(event.target)) { close(); input.value = selectedLabel(); }
      });
      select.form && select.form.addEventListener('reset', function () {
        setTimeout(function () { input.value = selectedLabel(); close(); }, 0);
      });
      select.form && select.form.addEventListener('submit', function () {
        if (!menu.hidden) {
          var exact = choices.find(function(c) { return c.label.toLocaleLowerCase() === input.value.trim().toLocaleLowerCase(); });
          if (exact) select.value = exact.value;
          else if (input.value !== selectedLabel()) input.value = selectedLabel();
        }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
