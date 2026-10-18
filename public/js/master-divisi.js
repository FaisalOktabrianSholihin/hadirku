(() => {
  const { api, esc, toast, debounce } = H;
  const $ = s => document.querySelector(s);
  let q = '';
  const st = { sort: 'nama', dir: 'asc' };

  async function load() {
    try {
      const r = await api('/admin/api/master/divisi?' + new URLSearchParams({ q, sort: st.sort, dir: st.dir }));
      $('#tb').innerHTML = r.data.map((d, i) => `<tr style="animation-delay:${i * 20}ms">
        <td><b>${esc(d.nama)}</b></td><td>${d.total}</td><td><span class="badge">${d.aktif} aktif</span></td>
        <td>${d.terdaftar ? `<span class="badge b">${d.terdaftar} karyawan</span>` : '<span class="mu">–</span>'}</td></tr>`).join('')
        || '<tr><td colspan="4" class="mu">Tidak ada divisi yang cocok.</td></tr>';
      $('#pinfo').textContent = `${r.data.length} divisi`;
    } catch (e) { toast(e.message, 1); }
  }
  H.sortable($('#th'), st, load);
  $('#cari').oninput = debounce(e => { q = e.target.value.trim(); load(); });
  load();
})();
