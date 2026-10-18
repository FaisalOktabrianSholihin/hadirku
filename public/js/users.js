(() => {
  const { api, esc, toast, modal, debounce } = H;
  const $ = s => document.querySelector(s);
  let page = 1, q = '', last = 1, list = [], roles = [];
  const st = { sort: 'nama', dir: 'asc' };

  async function load() {
    try {
      const r = await api('/admin/api/users?' + new URLSearchParams({ page, q, sort: st.sort, dir: st.dir }));
      list = r.data; roles = r.roles; last = r.meta.terakhir;
      $('#tb').innerHTML = list.map((u, i) => `<tr style="animation-delay:${i * 20}ms">
        <td><b>${esc(u.nama)}</b>${u.saya ? ' <span class="badge b">Anda</span>' : ''}</td><td>${esc(u.email)}</td>
        <td><span class="badge g">${esc(u.role)}</span></td><td class="mu">${esc(u.login || 'Belum pernah')}</td>
        <td><span class="sw ${u.aktif ? 'on' : ''} ${u.saya ? 'lock' : ''}" data-tg="${u.id}" title="${u.saya ? 'Tidak bisa menonaktifkan akun sendiri' : (u.aktif ? 'Klik untuk menonaktifkan login' : 'Klik untuk mengaktifkan login')}"></span></td>
        <td style="text-align:right"><button class="btn ghost sm" data-ed="${u.id}">${H.ic('pencil', 14)} Edit</button>${u.saya ? '' : ` <button class="btn ghost sm" data-del="${u.id}" title="Hapus">${H.ic('trash', 14)}</button>`}</td></tr>`).join('')
        || '<tr><td colspan="6" class="mu">Tidak ada user.</td></tr>';
      $('#pinfo').textContent = `Hal ${r.meta.halaman}/${r.meta.terakhir} · ${r.meta.total} user`;
      $('#prev').disabled = page <= 1; $('#next').disabled = page >= last;
    } catch (e) { toast(e.message, 1); }
  }
  H.sortable($('#th'), st, () => { page = 1; load(); });
  $('#prev').onclick = () => { page--; load(); };
  $('#next').onclick = () => { page++; load(); };
  $('#cari').oninput = debounce(e => { q = e.target.value.trim(); page = 1; load(); });
  $('#btnAdd').onclick = () => form();

  $('#tb').addEventListener('click', async e => {
    const t = e.target.closest('[data-tg],[data-ed],[data-del]'); if (!t) return;
    try {
      if (t.dataset.tg) {
        const u = list.find(x => x.id == t.dataset.tg);
        if (u.saya) return toast('Anda tidak bisa menonaktifkan akun sendiri.', 'warn');
        if (u.aktif && !(await H.confirm({ judul: 'Nonaktifkan login?', pesan: `${u.nama} tidak akan bisa login ke panel admin sampai diaktifkan lagi.`, ok: 'Ya, nonaktifkan', tipe: 'warn' }))) return;
        await api(`/admin/api/users/${u.id}/aktif`, 'PATCH'); toast(u.aktif ? 'Login dinonaktifkan' : 'Login diaktifkan'); load();
      } else if (t.dataset.ed) form(list.find(x => x.id == t.dataset.ed));
      else if (t.dataset.del) {
        const u = list.find(x => x.id == t.dataset.del);
        if (await H.confirm({ judul: 'Hapus user?', pesan: `Akun ${u.nama} (${u.email}) akan dihapus permanen.`, ok: 'Ya, hapus', tipe: 'bad' })) { await api(`/admin/api/users/${u.id}`, 'DELETE'); toast('User dihapus'); load(); }
      }
    } catch (er) { toast(er.message, 1); }
  });

  function form(u) {
    const m = modal(`<h3>${u ? 'Edit' : 'Tambah'} User</h3><div class="sub" style="margin-bottom:16px">Akun untuk login ke panel admin Hadirku.</div>
      <label>Nama</label><input id="un" maxlength="100" value="${esc(u?.nama || '')}" placeholder="Nama lengkap">
      <label>Email (untuk login)</label><input id="ue" type="email" maxlength="150" value="${esc(u?.email || '')}" placeholder="nama@perusahaan.co.id" autocomplete="off">
      <label>Password ${u ? '<span class="mu" style="font-weight:400">(kosongkan jika tidak diganti)</span>' : ''}</label>
      <div class="pwrow"><input id="up" type="password" autocomplete="new-password" placeholder="${u ? '••••••••' : 'Minimal 8 karakter'}"><button class="btn ghost sm" type="button" id="ue2">Lihat</button></div>
      <label>Role</label>
      <select id="ur" data-placeholder="Pilih role">${u ? '' : '<option value="">— Pilih role —</option>'}${roles.map(r => `<option value="${r.id}" ${u && u.role_id == r.id ? 'selected' : ''}>${esc(r.nama)}</option>`).join('')}</select>
      <label class="tgl"><span><b>Boleh login</b><br><span class="mu sm">Matikan untuk menahan akun tanpa menghapusnya.</span></span><input type="checkbox" id="ua" ${!u || u.aktif ? 'checked' : ''} ${u?.saya ? 'disabled' : ''}></label>
      <div class="mfoot"><button class="btn ghost" id="bx" type="button">Batal</button><button class="btn" id="bs" type="button">Simpan</button></div>`);
    m.querySelector('#bx').onclick = () => m.remove();
    m.querySelector('#ue2').onclick = e => { const i = m.querySelector('#up'), s = i.type === 'password'; i.type = s ? 'text' : 'password'; e.target.textContent = s ? 'Sembunyi' : 'Lihat'; };
    m.querySelector('#bs').onclick = async () => {
      const b = m.querySelector('#bs'); b.disabled = true;
      try {
        await api('/admin/api/users', 'POST', { id: u?.id || null, name: m.querySelector('#un').value, email: m.querySelector('#ue').value,
          password: m.querySelector('#up').value || null, role_id: m.querySelector('#ur').value || null, aktif: m.querySelector('#ua').checked });
        m.remove(); toast('User tersimpan'); load();
      } catch (e) { toast(e.message, 1); b.disabled = false; }
    };
  }
  load();
})();
