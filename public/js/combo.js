/*
 * Combobox: semua <select> otomatis bisa diketik untuk mencari (tanpa scroll panjang),
 * tetapi nilainya TETAP harus dipilih dari daftar sehingga tidak ada salah ketik.
 * <select> asli tetap ada (tersembunyi) dan tetap dipakai form / JS lain (value, change, innerHTML dinamis).
 * Kecualikan satu select dengan atribut data-native.
 */
(() => {
  const list = document.createElement('div');
  list.className = 'cb-list'; list.setAttribute('role', 'listbox'); list.hidden = true;
  let cur = null; // combo yang sedang terbuka
  const norm = s => String(s ?? '').toLowerCase();
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const attach = () => { if (!list.isConnected) document.body.append(list); };

  function place(c) {
    const r = c.input.getBoundingClientRect(), vh = window.innerHeight;
    const bawah = vh - r.bottom, atas = r.top;
    const tinggi = Math.min(280, Math.max(120, (bawah < 200 && atas > bawah ? atas : bawah) - 14));
    list.style.maxHeight = tinggi + 'px';
    list.style.minWidth = Math.max(r.width, 160) + 'px';
    list.style.left = Math.min(r.left, window.innerWidth - Math.max(r.width, 160) - 8) + 'px';
    if (bawah < 200 && atas > bawah) { list.style.top = 'auto'; list.style.bottom = (vh - r.top + 6) + 'px'; }
    else { list.style.bottom = 'auto'; list.style.top = (r.bottom + 6) + 'px'; }
  }

  function enhance(sel) {
    if (sel._cb || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native') || !sel.parentNode) return;
    const wrap = document.createElement('div'); wrap.className = 'cb';
    if (sel.style.width) wrap.style.width = sel.style.width;
    const input = document.createElement('input');
    input.type = 'text'; input.className = 'cb-in'; input.autocomplete = 'off'; input.spellcheck = false;
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-expanded', 'false');
    input.placeholder = sel.dataset.placeholder || 'Ketik untuk mencari…';
    const arrow = document.createElement('span'); arrow.className = 'cb-ar'; arrow.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.append(input, arrow, sel);
    sel.classList.add('cb-native'); sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');

    const c = { sel, wrap, input, typed: false, hi: -1, items: [] };
    sel._cb = c;

    const label = () => (sel.selectedOptions[0]?.textContent || '').trim();
    const sync = () => { input.disabled = sel.disabled; wrap.classList.toggle('dis', sel.disabled); if (document.activeElement !== input || !c.typed) input.value = label(); };

    function build() {
      const q = c.typed ? norm(input.value).trim() : '';
      const tokens = q ? q.split(/\s+/) : [];
      c.items = [...sel.options].map((o, i) => ({ o, i, t: o.textContent.trim() })).filter(x => !tokens.length || tokens.every(k => norm(x.t).includes(k)));
      const aktif = sel.selectedIndex;
      c.hi = c.items.findIndex(x => x.i === aktif && !c.typed); if (c.hi < 0) c.hi = c.items.length ? 0 : -1;
      list.innerHTML = c.items.map((x, k) => {
        let t = esc(x.t);
        if (q) { const p = norm(x.t).indexOf(q); if (p > -1) t = esc(x.t.slice(0, p)) + '<mark>' + esc(x.t.slice(p, p + q.length)) + '</mark>' + esc(x.t.slice(p + q.length)); }
        return `<div class="cb-opt${x.i === aktif ? ' sel' : ''}${x.o.disabled ? ' dis' : ''}${k === c.hi ? ' hi' : ''}" role="option" data-k="${k}">${t}</div>`;
      }).join('') || '<div class="cb-empty">Tidak ada pilihan yang cocok</div>';
      scrollHi();
    }
    const scrollHi = () => { const e = list.querySelector('.hi'); if (e) e.scrollIntoView({ block: 'nearest' }); };
    function paintHi() { list.querySelectorAll('.cb-opt').forEach((e, k) => e.classList.toggle('hi', k === c.hi)); scrollHi(); }

    function open() {
      if (sel.disabled) return;
      if (cur && cur !== c) cur.close();
      cur = c; attach(); build(); list.hidden = false; place(c);
      input.setAttribute('aria-expanded', 'true'); wrap.classList.add('open');
      clearInterval(c.tick); c.tick = setInterval(() => { if (!input.isConnected) c.close(); }, 300);
    }
    c.close = () => {
      if (cur === c) { list.hidden = true; cur = null; }
      clearInterval(c.tick); input.setAttribute('aria-expanded', 'false'); wrap.classList.remove('open');
      c.typed = false; input.value = label();
    };
    function pick(k) {
      const x = c.items[k]; if (!x || x.o.disabled) return;
      const berubah = sel.selectedIndex !== x.i;
      sel.selectedIndex = x.i;
      c.close();
      if (berubah) { sel.dispatchEvent(new Event('input', { bubbles: true })); sel.dispatchEvent(new Event('change', { bubbles: true })); }
    }
    c.pick = pick;

    input.addEventListener('focus', () => { c.typed = false; input.value = label(); open(); setTimeout(() => input.select(), 0); });
    arrow.addEventListener('mousedown', e => { e.preventDefault(); if (sel.disabled) return; if (cur === c) { c.close(); input.blur(); } else { input.focus(); } });
    input.addEventListener('click', () => { if (cur !== c) open(); });
    input.addEventListener('input', () => { c.typed = true; if (cur !== c) open(); else { build(); } place(c); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); if (cur !== c) { open(); return; }
        if (!c.items.length) return;
        const d = e.key === 'ArrowDown' ? 1 : -1; let k = c.hi;
        for (let n = 0; n < c.items.length; n++) { k = (k + d + c.items.length) % c.items.length; if (!c.items[k].o.disabled) break; }
        c.hi = k; paintHi();
      } else if (e.key === 'Enter') {
        if (cur === c) { e.preventDefault(); if (c.hi > -1) pick(c.hi); else c.close(); }
      } else if (e.key === 'Escape') {
        if (cur === c) { e.stopPropagation(); c.close(); }
      } else if (e.key === 'Tab') {
        if (cur === c && c.typed && c.hi > -1 && c.items.length === 1) pick(c.hi); else if (cur === c) c.close();
      }
    });
    input.addEventListener('blur', () => { setTimeout(() => { if (cur === c && document.activeElement !== input) c.close(); }, 120); });

    // Opsi / nilai select berubah dari luar (innerHTML, disabled, value=...)
    const mo = new MutationObserver(() => { sync(); if (cur === c) { build(); place(c); } });
    mo.observe(sel, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled'] });
    sel.addEventListener('change', sync);
    sel.form?.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
  }

  // satu daftar bersama: pilih dengan mouse / sentuh tanpa membuat input kehilangan fokus
  list.addEventListener('mousedown', e => {
    e.preventDefault();
    const o = e.target.closest('.cb-opt'); if (o && cur) cur.pick(+o.dataset.k);
  });
  window.addEventListener('resize', () => cur && place(cur));
  window.addEventListener('scroll', e => { if (cur && !list.contains(e.target)) place(cur); }, true);

  const scan = root => (root.querySelectorAll ? root.querySelectorAll('select') : []).forEach(enhance);
  const mulai = () => {
    scan(document);
    new MutationObserver(ms => ms.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) { if (n.tagName === 'SELECT') enhance(n); else scan(n); } })))
      .observe(document.body, { childList: true, subtree: true });
  };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', mulai) : mulai();
  window.Combo = { refresh: sel => sel._cb && sel.dispatchEvent(new Event('change')) };
})();
