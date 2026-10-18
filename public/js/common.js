window.H = (() => {
  const tok = document.querySelector('meta[name=csrf-token]')?.content;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ===== Ikon =====
  const IC = {
    pencil: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 3 3 3-3"/><path d="M12 18v-6"/>',
    back: '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    lock: '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  };
  const ic = (n, s = 16) => `<svg class="ic" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${IC[n] || ''}</svg>`;

  // ===== Toast (notifikasi kecil) =====
  const tc = () => document.querySelector('.tc') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'tc' }));
  // toast('Tersimpan') | toast('Gagal', 1) | toast('Perhatian', 'warn') | toast('Info', 'info')
  function toast(msg, tipe) {
    const t = (tipe === true || tipe === 1 || tipe === 'err') ? 'err' : (tipe || 'ok');
    const el = document.createElement('div');
    el.className = 'toast ' + t; el.setAttribute('role', t === 'err' ? 'alert' : 'status');
    const dur = t === 'err' ? 5200 : 3400;
    el.style.setProperty('--d', dur + 'ms');
    el.innerHTML = `<span class="ti">${ic({ ok: 'check', err: 'x', warn: 'alert', info: 'info' }[t] || 'check', 16)}</span><span class="tm"></span><i class="tp"></i>`;
    el.querySelector('.tm').textContent = msg;
    const kill = () => { el.classList.add('out'); setTimeout(() => el.remove(), 240); };
    el.onclick = kill; setTimeout(kill, dur);
    tc().append(el);
  }

  // ===== Modal =====
  const stack = []; let locks = 0;
  document.addEventListener('keydown', e => {
    const top = stack[stack.length - 1]; if (!top) return;
    if (e.key === 'Escape' && top._dismiss) { e.stopPropagation(); top.remove(); }
    if (e.key === 'Tab') { // fokus tetap di dalam modal
      const f = [...top.querySelectorAll('button:not([disabled]),input:not([disabled]),textarea,[href],[tabindex]:not([tabindex="-1"])')].filter(x => x.offsetParent !== null);
      if (!f.length) return;
      const a = f[0], z = f[f.length - 1];
      if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
      else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
    }
  }, true);

  // modal(html, { cls: 'kelas-tambahan', dismiss: false })  -> elemen .modal; tutup dengan m.remove() / m.close()
  function modal(html, opt = {}) {
    const prev = document.activeElement;
    const m = document.createElement('div'); m.className = 'modal';
    m._dismiss = opt.dismiss !== false;
    m.innerHTML = `<div class="card ${opt.cls || ''}" role="dialog" aria-modal="true" tabindex="-1">${m._dismiss ? `<button class="mclose" type="button" aria-label="Tutup">${ic('x', 18)}</button>` : ''}${html}</div>`;
    let down = false, closed = false;
    m.addEventListener('mousedown', e => { down = e.target === m; });
    m.addEventListener('mouseup', e => { if (down && e.target === m && m._dismiss) m.remove(); down = false; });
    m.querySelector('.mclose')?.addEventListener('click', () => m.remove());
    m.remove = () => {
      if (closed) return; closed = true;
      const i = stack.indexOf(m); if (i > -1) stack.splice(i, 1);
      if (--locks <= 0) { locks = 0; document.body.classList.remove('nolock'); }
      m.classList.add('out');
      m.dispatchEvent(new Event('close'));
      setTimeout(() => { HTMLElement.prototype.remove.call(m); try { prev?.focus?.({ preventScroll: true }); } catch (e) { } }, 190);
    };
    m.close = m.remove;
    locks++; document.body.classList.add('nolock');
    document.body.append(m); stack.push(m);
    requestAnimationFrame(() => { const f = m.querySelector('[autofocus],input:not([type=hidden]):not([disabled]):not(.cb-in),textarea'); (f && !opt.noFocus ? f : m.firstElementChild).focus({ preventScroll: true }); });
    return m;
  }

  // ===== Dialog konfirmasi (pengganti confirm()) =====
  // if (await H.confirm({ judul:'Hapus?', pesan:'…', tipe:'bad', ok:'Ya, hapus' })) { … }
  function confirmBox({ judul = 'Konfirmasi', pesan = '', ok = 'Ya, lanjutkan', batal = 'Batal', tipe = 'warn' } = {}) {
    return new Promise(res => {
      const ikon = { bad: 'trash', warn: 'alert', info: 'info' }[tipe] || 'alert';
      const m = modal(`<div class="dlg"><div class="dlg-ic ${tipe}">${ic(ikon, 30)}</div><h3>${esc(judul)}</h3><p class="mu">${esc(pesan)}</p>
        <div class="dlg-act"><button class="btn ghost" type="button" data-r="0">${esc(batal)}</button><button class="btn ${tipe === 'bad' ? 'danger' : ''}" type="button" data-r="1">${esc(ok)}</button></div></div>`, { cls: 'dlg-card', noFocus: true });
      let done = false;
      const fin = v => { if (done) return; done = true; m.remove(); res(v); };
      m.addEventListener('click', e => { const b = e.target.closest('[data-r]'); if (b) fin(b.dataset.r === '1'); });
      m.addEventListener('close', () => fin(false));
      m.querySelector('[data-r="1"]').focus({ preventScroll: true });
    });
  }

  // ===== API =====
  async function api(url, method = 'GET', body) {
    const r = await fetch(url, {
      method, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': tok, ...(body ? { 'Content-Type': 'application/json' } : {}) },
      body: body ? JSON.stringify(body) : undefined,
    });
    let d = null; try { d = await r.json(); } catch (e) { }
    if (r.status === 401 || r.status === 419) { location.href = '/admin/login'; throw new Error('Sesi berakhir, silakan login ulang.'); }
    if (!r.ok) { const e = new Error(d?.errors ? Object.values(d.errors)[0][0] : (d?.message || 'Terjadi kesalahan (' + r.status + ')')); e.status = r.status; e.data = d; throw e; }
    return d;
  }

  // ===== Header tabel bisa diurutkan (asc / desc) =====
  // <th data-sort="nama">Nama<i class="so"></i></th>   state = { sort:'', dir:'asc' }
  function sortable(thead, state, onChange) {
    const paint = () => thead.querySelectorAll('th[data-sort]').forEach(th => {
      const on = state.sort === th.dataset.sort;
      th.classList.toggle('asc', on && state.dir === 'asc'); th.classList.toggle('desc', on && state.dir === 'desc');
      th.setAttribute('aria-sort', on ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
    thead.addEventListener('click', e => {
      const th = e.target.closest('th[data-sort]'); if (!th) return;
      if (state.sort === th.dataset.sort) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      else { state.sort = th.dataset.sort; state.dir = 'asc'; }
      paint(); onChange();
    });
    thead.querySelectorAll('th[data-sort]').forEach(th => { th.tabIndex = 0; th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); th.click(); } }); });
    paint();
  }

  const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const pad = n => String(n).padStart(2, '0');
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const BLN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const tglIndo = s => { const [y, m, d] = s.split('-'); return `${+d} ${BLN[m - 1].slice(0, 3)} ${y}`; };
  const download = (blob, nama) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nama; document.body.append(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500); };
  return { api, esc, toast, modal, confirm: confirmBox, ic, sortable, debounce, pad, ymd, BLN, tglIndo, download };
})();
