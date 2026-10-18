(() => {
  const { api, esc, toast, debounce } = H;
  const $ = s => document.querySelector(s);
  let page = 1, q = '', dv = '', stt = '', last = 1, divisiOk = false;
  const st = { sort: 'nama', dir: 'asc' };

  async function load() {
    try {
      const p = new URLSearchParams({ page, q, divisi: dv, status: stt, sort: st.sort, dir: st.dir });
      const r = await api('/admin/api/master/karyawan?' + p);
      last = r.meta.terakhir;
      if (!divisiOk) { $('#fdiv').innerHTML = '<option value="">Semua divisi</option>' + r.divisi.map(d => `<option value="${d.id}">${esc(d.nama_divisi)}</option>`).join(''); divisiOk = true; }
      const pres = { aktif: '<span class="badge">Aktif</span>', nonaktif: '<span class="badge g">Nonaktif</span>', belum: '<span class="badge y">Belum didaftarkan</span>' };
      $('#tb').innerHTML = r.data.map((c, i) => `<tr style="animation-delay:${i * 20}ms">
        <td><span class="mono">${esc(c.nik)}</span></td><td><b>${esc(c.nama)}</b></td><td>${esc(c.jabatan)}</td><td>${esc(c.divisi)}</td>
        <td>${c.aktif ? '<span class="badge">Aktif</span>' : '<span class="badge r">Nonaktif</span>'}</td><td>${pres[c.presensi]}</td></tr>`).join('')
        || '<tr><td colspan="6" class="mu">Tidak ada karyawan yang cocok.</td></tr>';
      $('#pinfo').textContent = `Hal ${r.meta.halaman}/${r.meta.terakhir} · ${r.meta.total} karyawan`;
      $('#prev').disabled = page <= 1; $('#next').disabled = page >= last;
    } catch (e) { toast(e.message, 1); }
  }
  H.sortable($('#th'), st, () => { page = 1; load(); });
  $('#prev').onclick = () => { page--; load(); };
  $('#next').onclick = () => { page++; load(); };
  $('#cari').oninput = debounce(e => { q = e.target.value.trim(); page = 1; load(); });
  $('#fdiv').onchange = e => { dv = e.target.value; page = 1; load(); };
  $('#fst').onchange = e => { stt = e.target.value; page = 1; load(); };
  load();
})();
