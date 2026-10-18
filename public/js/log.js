(() => {
  const { api, esc, toast, modal, debounce } = H;
  const $ = s => document.querySelector(s);
  let page = 1, last = 1, rows = [], modulOk = '';
  const st = { sort: 'waktu', dir: 'desc' };
  const LV = { info: ['b', 'Info'], warning: ['y', 'Peringatan'], danger: ['r', 'Kecurangan'] };
  const nama = s => String(s).replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());

  const param = () => new URLSearchParams({ page, q: $('#cari').value.trim(), modul: $('#fmodul').value, level: $('#flevel').value, dari: $('#dari').value, sampai: $('#sampai').value, sort: st.sort, dir: st.dir });

  async function load() {
    try {
      const r = await api('/admin/api/log?' + param());
      rows = r.data; last = r.meta.terakhir;
      const kunci = r.modul.join('|');
      if (kunci !== modulOk) { const v = $('#fmodul').value; $('#fmodul').innerHTML = '<option value="">Semua modul</option>' + r.modul.map(m => `<option value="${esc(m)}">${esc(nama(m))}</option>`).join(''); $('#fmodul').value = v; modulOk = kunci; }
      $('#tb').innerHTML = rows.map((l, i) => `<tr class="${l.level === 'danger' ? 'lg-danger' : ''}" style="animation-delay:${i * 15}ms">
        <td class="mu">${esc(l.waktu)}</td><td><b>${esc(l.pelaku)}</b><br><span class="mu sm">${esc(l.tipe)}</span></td>
        <td><span class="badge g">${esc(nama(l.modul))}</span></td><td>${esc(nama(l.aksi))}</td>
        <td><span class="badge ${LV[l.level]?.[0] || 'g'}">${esc(LV[l.level]?.[1] || l.level)}</span></td>
        <td class="wrap-t">${esc(l.deskripsi)}</td><td class="mu">${esc(l.ip || '-')}</td>
        <td><button class="btn ghost sm" data-d="${l.id}" title="Detail">${H.ic('eye', 14)}</button></td></tr>`).join('')
        || '<tr><td colspan="8" class="mu">Belum ada aktivitas yang cocok dengan filter.</td></tr>';
      $('#pinfo').textContent = `Hal ${r.meta.halaman}/${r.meta.terakhir} · ${r.meta.total} aktivitas`;
      $('#prev').disabled = page <= 1; $('#next').disabled = page >= last;
    } catch (e) { toast(e.message, 1); }
  }

  H.sortable($('#th'), st, () => { page = 1; load(); });
  $('#prev').onclick = () => { page--; load(); };
  $('#next').onclick = () => { page++; load(); };
  $('#btnCari').onclick = () => { page = 1; load(); };
  $('#cari').addEventListener('input', debounce(() => { page = 1; load(); }, 400));
  ['#fmodul', '#flevel', '#dari', '#sampai'].forEach(s => $(s).addEventListener('change', () => { page = 1; load(); }));

  $('#tb').addEventListener('click', e => {
    const b = e.target.closest('[data-d]'); if (!b) return;
    const l = rows.find(x => x.id == b.dataset.d); if (!l) return;
    const lv = LV[l.level] || ['g', l.level];
    const bukti = l.data && Object.keys(l.data).length ? `<label style="margin-top:14px">Data pendukung</label><pre class="json">${esc(JSON.stringify(l.data, null, 2))}</pre>` : '';
    const m = modal(`<h3>Detail Aktivitas</h3><div class="sub" style="margin-bottom:14px">#${l.id} · ${esc(l.waktu)}</div>
      <div class="kv"><span>Pelaku</span><b>${esc(l.pelaku)} <span class="badge g">${esc(l.tipe)}</span></b>
      <span>Modul / Aksi</span><b>${esc(nama(l.modul))} › ${esc(nama(l.aksi))}</b>
      <span>Level</span><b><span class="badge ${lv[0]}">${esc(lv[1] || '')}</span></b>
      <span>IP</span><b>${esc(l.ip || '-')}</b>
      <span>Perangkat</span><b class="mu sm" style="font-weight:500">${esc(l.ua || '-')}</b></div>
      <label style="margin-top:14px">Deskripsi</label><div class="desc-box">${esc(l.deskripsi)}</div>${bukti}
      <div class="mfoot"><button class="btn" id="bx" type="button">Tutup</button></div>`, { cls: 'wide' });
    m.querySelector('#bx').onclick = () => m.remove();
  });
  load();
})();
