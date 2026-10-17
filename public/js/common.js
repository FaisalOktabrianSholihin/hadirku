window.H = (() => {
  const tok = document.querySelector('meta[name=csrf-token]')?.content;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (m, err) => { const t = document.createElement('div'); t.className = 'toast' + (err ? ' err' : ''); t.textContent = m; document.body.append(t); setTimeout(() => t.remove(), 3500); };
  async function api(url, method = 'GET', body) {
    const r = await fetch(url, {
      method, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': tok, ...(body ? { 'Content-Type': 'application/json' } : {}) },
      body: body ? JSON.stringify(body) : undefined,
    });
    let d = null; try { d = await r.json(); } catch (e) {}
    if (r.status === 401 || r.status === 419) { location.href = '/admin/login'; throw new Error('Sesi berakhir, silakan login ulang.'); }
    if (!r.ok) { const e = new Error(d?.errors ? Object.values(d.errors)[0][0] : (d?.message || 'Terjadi kesalahan (' + r.status + ')')); e.status = r.status; throw e; }
    return d;
  }
  function modal(html) {
    const m = document.createElement('div'); m.className = 'modal';
    m.innerHTML = `<div class="card">${html}</div>`;
    m.addEventListener('mousedown', e => { if (e.target === m) m.remove(); });
    document.body.append(m); return m;
  }
  const IC = {
    pencil: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
  };
  const ic = (n, s = 16) => `<svg class="ic" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${IC[n]}</svg>`;
  const pad = n => String(n).padStart(2, '0');
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const BLN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const tglIndo = s => { const [y, m, d] = s.split('-'); return `${+d} ${BLN[m - 1].slice(0, 3)} ${y}`; };
  const download = (blob, nama) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nama; document.body.append(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500); };
  return { api, esc, toast, modal, ic, pad, ymd, BLN, tglIndo, download };
})();
