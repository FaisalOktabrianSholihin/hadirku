(() => {
  const { api, esc, toast, BLN, pad, ymd, ic } = H;
  const $ = s => document.querySelector(s);
  const NAMA_HARI = { 1: 'Sen', 2: 'Sel', 3: 'Rab', 4: 'Kam', 5: 'Jum', 6: 'Sab', 0: 'Min' };
  const ORDER = [1, 2, 3, 4, 5, 6, 0];
  const JENIS = { nasional: 'Libur nasional', cuti_bersama: 'Cuti bersama', perusahaan: 'Libur perusahaan' };
  let bulan = ymd(new Date()).slice(0, 7), items = [], rutin = new Set(), dirty = false, sel = null;

  const setDirty = v => { dirty = v; $('#dirty').classList.toggle('on', v); };

  async function load(b) {
    try {
      const r = await api('/admin/api/libur?bulan=' + b);
      bulan = r.bulan; items = r.khusus.map(x => ({ ...x })); rutin = new Set(r.rutin.filter(x => x.aktif).map(x => x.hari));
      sel = null; setDirty(false); render();
    } catch (e) { toast(e.message, 1); }
  }

  function render() { renderCal(); renderWk(); renderRows(); }

  function renderCal() {
    const [y, m] = bulan.split('-').map(Number);
    $('#judul').textContent = `${BLN[m - 1]} ${y}`;
    const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7, jml = new Date(y, m, 0).getDate(), today = ymd(new Date());
    let h = ORDER.map(d => `<div class="dn">${NAMA_HARI[d]}</div>`).join('') + '<div class="dy x"></div>'.repeat(lead);
    for (let d = 1; d <= jml; d++) {
      const t = `${bulan}-${pad(d)}`, it = items.find(x => x.tanggal === t), dow = new Date(y, m - 1, d).getDay();
      let cls = '', txt = '';
      if (it) { cls = it.jenis; txt = it.nama || '(isi keterangan)'; }
      else if (rutin.has(dow)) { cls = 'rutin'; txt = 'Libur rutin'; }
      h += `<div class="dy ${cls} ${t === today ? 'today' : ''} ${t === sel ? 'sel' : ''}" data-t="${t}" title="${esc(txt)}"><span class="n">${d}</span>${txt ? `<span class="t">${esc(txt)}</span>` : ''}</div>`;
    }
    $('#cal').innerHTML = h;
  }

  function renderWk() {
    $('#wk').innerHTML = ORDER.map(d => `<label><input type="checkbox" data-w="${d}" ${rutin.has(d) ? 'checked' : ''}> ${NAMA_HARI[d]}</label>`).join('');
  }

  function renderRows() {
    const [y, m] = bulan.split('-').map(Number), last = new Date(y, m, 0).getDate();
    const sorted = items.map((x, i) => ({ x, i })).sort((a, b) => a.x.tanggal.localeCompare(b.x.tanggal));
    $('#rows').innerHTML = sorted.map(({ x, i }) => `<div class="nr" data-i="${i}">
      <input type="date" data-f="tanggal" min="${bulan}-01" max="${bulan}-${pad(last)}" value="${esc(x.tanggal)}">
      <input data-f="nama" maxlength="100" placeholder="Nama libur (mis. Idul Fitri)" value="${esc(x.nama)}">
      <select data-f="jenis">${Object.entries(JENIS).map(([k, v]) => `<option value="${k}" ${x.jenis === k ? 'selected' : ''}>${v}</option>`).join('')}</select>
      <button class="btn ghost sm" type="button" data-rm="${i}" title="Hapus">${ic('x', 14)}</button></div>`).join('')
      || '<p class="mu sm" style="margin:6px 0">Belum ada catatan libur khusus bulan ini.</p>';
  }

  $('#cal').addEventListener('click', e => {
    const d = e.target.closest('.dy[data-t]'); if (!d) return;
    const t = d.dataset.t; sel = t;
    if (!items.some(x => x.tanggal === t)) { items.push({ tanggal: t, nama: '', jenis: 'nasional' }); setDirty(true); }
    render();
    const row = [...document.querySelectorAll('#rows .nr')].find(r => items[+r.dataset.i].tanggal === t);
    row?.querySelector('[data-f=nama]').focus();
  });

  $('#rows').addEventListener('input', e => {
    const t = e.target, r = t.closest('.nr'); if (!r || !t.dataset.f) return;
    items[+r.dataset.i][t.dataset.f] = t.value; setDirty(true); renderCal();
  });
  $('#rows').addEventListener('click', e => {
    const b = e.target.closest('[data-rm]'); if (!b) return;
    items.splice(+b.dataset.rm, 1); setDirty(true); render();
  });
  $('#wk').addEventListener('change', e => {
    const w = +e.target.dataset.w; e.target.checked ? rutin.add(w) : rutin.delete(w); setDirty(true); renderCal();
  });
  $('#bAdd').onclick = () => {
    let t = `${bulan}-01`; while (items.some(x => x.tanggal === t) && +t.slice(8) < 31) t = `${bulan}-${pad(+t.slice(8) + 1)}`;
    items.push({ tanggal: t, nama: '', jenis: 'nasional' }); sel = t; setDirty(true); render();
  };

  const geser = n => {
    if (dirty && !confirm('Perubahan belum disimpan. Pindah bulan dan buang perubahan?')) return;
    const [y, m] = bulan.split('-').map(Number), d = new Date(y, m - 1 + n, 1);
    load(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
  };
  $('#bPrev').onclick = () => geser(-1);
  $('#bNext').onclick = () => geser(1);

  $('#bSave').onclick = async () => {
    const kosong = items.find(x => !x.nama.trim());
    if (kosong) return toast(`Isi nama libur untuk tanggal ${kosong.tanggal}`, 1);
    const tg = items.map(x => x.tanggal), dup = tg.find((t, i) => tg.indexOf(t) !== i);
    if (dup) return toast(`Tanggal ${dup} ditulis dua kali`, 1);
    const b = $('#bSave'); b.disabled = true;
    try { await api('/admin/api/libur', 'POST', { bulan, items, rutin: [...rutin] }); toast('Kalender libur tersimpan'); await load(bulan); }
    catch (er) { toast(er.message, 1); }
    b.disabled = false;
  };
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  load(bulan);
})();
