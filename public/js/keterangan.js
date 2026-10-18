(() => {
  const { api, esc, toast, modal, ic } = H;
  const $ = s => document.querySelector(s);
  let list = [];
  const st = { sort: '', dir: 'asc' }; // sort kosong = urutan bawaan dari server

  // nilai yang dibandingkan untuk tiap kolom header
  const NILAI = { kode: k => k.kode, nama: k => k.nama, jenis: k => (k.sistem ? 0 : 1), menit: k => (k.pakai_menit ? 1 : 0), aktif: k => (k.aktif ? 1 : 0) };
  function urut() {
    const f = NILAI[st.sort]; if (!f) return list;
    const d = st.dir === 'asc' ? 1 : -1;
    return list.map((k, i) => [k, i]).sort((a, b) => {
      const x = f(a[0]), y = f(b[0]);
      const c = typeof x === 'string' ? x.localeCompare(y, 'id', { numeric: true, sensitivity: 'base' }) : x - y;
      return c * d || a[1] - b[1];
    }).map(p => p[0]);
  }

  async function load() {
    try {
      list = await api('/admin/api/keterangan');
      tampil();
    } catch (e) { toast(e.message, 1); }
  }

  function tampil() {
    try {
      $('#tb').innerHTML = urut().map((k, i) => `<tr style="animation-delay:${i * 25}ms">
        <td><span class="chip" style="background:${esc(k.warna)}">${esc(k.kode)}</span></td>
        <td><b>${esc(k.nama)}</b>${k.dipakai ? `<br><span class="mu sm">dipakai ${k.dipakai}×</span>` : ''}</td>
        <td>${k.sistem ? `<span class="badge b">Bawaan sistem (${esc(k.sistem)})</span>` : '<span class="badge g">Input manual</span>'}</td>
        <td>${k.pakai_menit ? '<span class="badge y">Ya, dalam menit</span>' : '<span class="mu">–</span>'}</td>
        <td><span class="sw ${k.aktif ? 'on' : ''} ${k.sistem ? 'lock' : ''}" data-tg="${k.id}"></span></td>
        <td style="text-align:right"><button class="btn ghost sm" data-ed="${k.id}">${ic('pencil', 14)} Edit</button>${k.sistem ? '' : ` <button class="btn ghost sm" data-del="${k.id}">${ic('trash', 14)}</button>`}</td></tr>`).join('');
    } catch (e) { toast(e.message, 1); }
  }

  H.sortable($('#th'), st, tampil);
  $('#tb').addEventListener('click', async e => {
    const t = e.target.closest('[data-tg],[data-ed],[data-del]'); if (!t) return;
    try {
      if (t.dataset.tg) { await api(`/admin/api/keterangan/${t.dataset.tg}/aktif`, 'PATCH'); load(); }
      else if (t.dataset.ed) form(list.find(x => x.id == t.dataset.ed));
      else if (t.dataset.del) {
        const k = list.find(x => x.id == t.dataset.del);
        if (await H.confirm({ judul: 'Hapus keterangan?', pesan: `Keterangan “${k?.nama || ''}” akan dihapus permanen.`, ok: 'Ya, hapus', tipe: 'bad' })) { await api(`/admin/api/keterangan/${t.dataset.del}`, 'DELETE'); toast('Keterangan dihapus'); load(); }
      }
    } catch (er) { toast(er.message, 1); }
  });
  $('#btnAdd').onclick = () => form();

  function form(k) {
    const m = modal(`<h3>${k ? 'Edit' : 'Tambah'} Keterangan</h3><div class="sub" style="margin-bottom:14px">${k?.sistem ? 'Keterangan bawaan sistem: nama, kode, dan warna boleh diubah.' : 'Contoh: Izin (I), Sakit (S), Cuti (C), Dinas Luar (DL).'}</div>
      <label>Nama keterangan</label><input id="kn" maxlength="50" value="${esc(k?.nama || '')}" placeholder="mis. Dinas Luar">
      <div class="row-btn" style="align-items:flex-start"><div style="flex:1"><label>Kode (maks 6 huruf/angka)</label><input id="kk" maxlength="6" value="${esc(k?.kode || '')}" placeholder="DL" style="width:100%;text-transform:uppercase"></div>
      <div><label>Warna</label><input type="color" id="kw" value="${esc(k?.warna || '#3b82f6')}"></div></div>
      <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin:4px 0 4px"><input type="checkbox" id="km" ${k?.pakai_menit ? 'checked' : ''}> Tampilkan kolom waktu (menit) di Report</label>
      <div class="hint">Aktifkan untuk keterangan yang punya durasi, misalnya Telat. Untuk Hadir/Alpa biarkan mati sehingga hanya tampil angka.</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button class="btn ghost" id="bx">Batal</button><button class="btn" id="bs">Simpan</button></div>`);
    m.querySelector('#bx').onclick = () => m.remove();
    m.querySelector('#bs').onclick = async () => {
      const b = m.querySelector('#bs'); b.disabled = true;
      try {
        await api('/admin/api/keterangan', 'POST', {
          id: k?.id || null, nama: m.querySelector('#kn').value, kode: m.querySelector('#kk').value,
          warna: m.querySelector('#kw').value, pakai_menit: m.querySelector('#km').checked
        });
        m.remove(); toast('Tersimpan'); load();
      } catch (e) { toast(e.message, 1); b.disabled = false; }
    };
  }
  load();
})();
