(() => {
  const { api, esc, toast, modal } = H;
  const $ = s => document.querySelector(s);
  let roles = [], katalog = [];
  const label = {}; 

  async function load() {
    try {
      const r = await api('/admin/api/roles'); roles = r.roles; katalog = r.katalog;
      katalog.forEach(g => g.item.forEach(i => label[i.kunci] = i.label));
      $('#rg').innerHTML = roles.map((x, i) => {
        const penuh = x.izin.includes('*');
        const grup = katalog.map(g => ({ g: g.grup, n: g.item.filter(i => penuh || x.izin.includes(i.kunci)).length, t: g.item.length })).filter(g => g.n);
        return `<div class="rc ${x.sistem ? 'sys' : ''}" style="animation-delay:${i * 40}ms">
          <div class="rc-h"><span class="rc-ic">${H.ic('shield', 20)}</span><div><b>${esc(x.nama)}</b><div class="mu sm">${esc(x.deskripsi || 'Tanpa deskripsi')}</div></div>
            ${x.sistem ? '<span class="badge b" style="margin-left:auto">Bawaan</span>' : ''}</div>
          <div class="rc-s"><span class="badge ${penuh ? '' : 'g'}">${penuh ? 'Akses penuh' : x.izin.length + ' izin menu'}</span><span class="badge g">${x.jumlah_user} user</span></div>
          <div class="rc-g">${grup.map(g => `<span class="chip2">${esc(g.g)} <b>${g.n}/${g.t}</b></span>`).join('')}</div>
          <div class="rc-a">${x.sistem ? `<span class="mu sm">${H.ic('lock', 14)} Terkunci</span>` : `<button class="btn ghost sm" data-ed="${x.id}">${H.ic('pencil', 14)} Edit</button> <button class="btn ghost sm" data-del="${x.id}" title="Hapus">${H.ic('trash', 14)}</button>`}</div>
        </div>`;
      }).join('') || '<p class="mu">Belum ada role.</p>';
    } catch (e) { toast(e.message, 1); }
  }

  $('#rg').addEventListener('click', async e => {
    const t = e.target.closest('[data-ed],[data-del]'); if (!t) return;
    try {
      if (t.dataset.ed) form(roles.find(x => x.id == t.dataset.ed));
      else {
        const x = roles.find(r => r.id == t.dataset.del);
        if (await H.confirm({ judul: 'Hapus role?', pesan: `Role “${x.nama}” akan dihapus.${x.jumlah_user ? ` Masih dipakai ${x.jumlah_user} user.` : ''}`, ok: 'Ya, hapus', tipe: 'bad' })) { await api(`/admin/api/roles/${x.id}`, 'DELETE'); toast('Role dihapus'); load(); }
      }
    } catch (er) { toast(er.message, 1); }
  });
  $('#btnAdd').onclick = () => form();

  function form(x) {
    const pilih = new Set(x ? x.izin : ['dashboard']);
    const m = modal(`<h3>${x ? 'Edit' : 'Tambah'} Role</h3><div class="sub" style="margin-bottom:16px">Centang menu yang boleh dibuka oleh role ini.</div>
      <label>Nama role</label><input id="rn" maxlength="50" value="${esc(x?.nama || '')}" placeholder="mis. Supervisor Lapangan">
      <label>Deskripsi <span class="mu" style="font-weight:400">(opsional)</span></label><input id="rd" maxlength="255" value="${esc(x?.deskripsi || '')}" placeholder="Untuk siapa role ini">
      <div class="perm-h"><label style="margin:0">Izin menu</label><button class="btn ghost sm" type="button" id="pall">Pilih semua</button></div>
      <div class="perm" id="pm">${katalog.map(g => `<div class="pg"><div class="pg-h"><b>${esc(g.grup)}</b><a href="#" data-g="${esc(g.grup)}">semua</a></div>
        ${g.item.map(i => `<label class="pi"><input type="checkbox" value="${esc(i.kunci)}" ${pilih.has(i.kunci) ? 'checked' : ''}><span>${esc(i.label)}</span></label>`).join('')}</div>`).join('')}</div>
      <div class="mfoot"><button class="btn ghost" id="bx" type="button">Batal</button><button class="btn" id="bs" type="button">Simpan</button></div>`, { cls: 'wide' });
    const cbs = () => [...m.querySelectorAll('#pm input[type=checkbox]')];
    m.querySelector('#bx').onclick = () => m.remove();
    m.querySelector('#pall').onclick = () => { const semua = cbs().every(c => c.checked); cbs().forEach(c => c.checked = !semua); m.querySelector('#pall').textContent = semua ? 'Pilih semua' : 'Kosongkan'; };
    m.querySelector('#pm').addEventListener('click', e => {
      const a = e.target.closest('[data-g]'); if (!a) return; e.preventDefault();
      const c = [...a.closest('.pg').querySelectorAll('input')], semua = c.every(i => i.checked); c.forEach(i => i.checked = !semua);
    });
    m.querySelector('#bs').onclick = async () => {
      const b = m.querySelector('#bs'); b.disabled = true;
      try {
        await api('/admin/api/roles', 'POST', { id: x?.id || null, nama: m.querySelector('#rn').value, deskripsi: m.querySelector('#rd').value || null, izin: cbs().filter(c => c.checked).map(c => c.value) });
        m.remove(); toast('Role tersimpan'); load();
      } catch (er) { toast(er.message, 1); b.disabled = false; }
    };
  }
  load();
})();
