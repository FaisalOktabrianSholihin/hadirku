(() => {
  const { api, esc, toast, modal, ic } = H;
  const $ = s => document.querySelector(s);
  let page = 1, q = '', dv = '', last = 1;

  async function load() {
    try {
      const r = await api(`/admin/api/pengaturan?page=${page}&q=${encodeURIComponent(q)}&divisi=${dv}`);
      last = r.meta.terakhir;
      $('#tb').innerHTML = r.data.map((c, i) => `<tr style="animation-delay:${i * 25}ms">
        <td><b>${esc(c.nama)}</b><br><span class="mu sm">${esc(c.jabatan)} · ${esc(c.nik)}</span></td>
        <td>${esc(c.divisi)}</td>
        <td>${c.telat ? `<span class="badge y">Batas ${esc(c.jam_batas)}</span>` : '<span class="badge">Tanpa telat</span>'}${c.jam_tutup ? ` <span class="badge g">Tutup ${esc(c.jam_tutup)}</span>` : ''}</td>
        <td>${c.tipe === 'single' && !c.ikut_nik ? c.jumlah_titik + ' titik' : '-'}</td>
        <td>${c.ikut_nik ? `<span class="badge b">Ikut ${esc(c.ikut_nama)}</span> ${c.jumlah_titik} titik` : (c.tipe === 'multi' ? c.jumlah_titik + ' titik' : '-')}</td>
        <td><span class="sw ${c.aktif ? 'on' : ''}" data-tg="${esc(c.nik)}"></span></td>
        <td style="text-align:right"><button class="btn ghost sm" data-ed="${esc(c.nik)}">${ic('pencil', 14)} Edit</button> <button class="btn ghost sm" data-del="${esc(c.nik)}" title="Hapus">${ic('trash', 14)}</button></td></tr>`).join('')
        || '<tr><td colspan="7" class="mu">Belum ada data. Klik “Tambah Data” atau “Import Excel”.</td></tr>';
      $('#pinfo').textContent = `Hal ${r.meta.halaman}/${r.meta.terakhir} · ${r.meta.total} karyawan`;
      $('#prev').disabled = page <= 1; $('#next').disabled = page >= last;
    } catch (e) { toast(e.message, 1); }
  }
  $('#prev').onclick = () => { page--; load(); };
  $('#next').onclick = () => { page++; load(); };
  let tm; $('#cari').oninput = e => { clearTimeout(tm); tm = setTimeout(() => { q = e.target.value; page = 1; load(); }, 300); };
  $('#fdiv').onchange = e => { dv = e.target.value; page = 1; load(); };
  $('#tb').addEventListener('click', async e => {
    const t = e.target.closest('[data-tg],[data-ed],[data-del]'); if (!t) return;
    try {
      if (t.dataset.tg) { await api(`/admin/api/pengaturan/${t.dataset.tg}/aktif`, 'PATCH'); load(); }
      else if (t.dataset.ed) form(t.dataset.ed);
      else if (t.dataset.del && confirm('Hapus pengaturan karyawan ini?')) { await api(`/admin/api/pengaturan/${t.dataset.del}`, 'DELETE'); toast('Terhapus'); load(); }
    } catch (err) { toast(err.message, 1); }
  });
  $('#btnTambah').onclick = () => form();
  $('#btnImport').onclick = () => imp();
  api('/admin/api/induk').then(r => { $('#fdiv').innerHTML += r.divisi.map(d => `<option value="${d.id}">${esc(d.nama_divisi)}</option>`).join(''); }).catch(() => {});

  const rv = (m, n) => m.querySelector(`[name=${n}]:checked`)?.value;

  // ===== Form tambah / edit =====
  async function form(nik) {
    let cfg = null, ref;
    try { if (nik) cfg = await api(`/admin/api/pengaturan/${nik}`); ref = await api('/admin/api/induk'); } catch (e) { return toast(e.message, 1); }
    let pts = cfg && cfg.titik.length ? cfg.titik.map(t => ({ ...t })) : [{ nama: '', src: '', radius: 150 }];
    let tersedia = ref.tersedia;
    const m = modal(`<h3>${nik ? 'Edit' : 'Tambah'} Data Presensi</h3><div class="sub" style="margin-bottom:14px">Karyawan diambil dari db_indukk</div>
      <label>Karyawan</label>
      ${nik ? '' : '<input id="fq" placeholder="Cari nama / NIK…" style="margin-bottom:6px">'}
      <select id="fk" ${nik ? 'disabled' : ''}></select>
      <label>Perhitungan telat</label>
      <div class="radio"><label><input type="radio" name="tl" value="1" ${!cfg || cfg.telat ? 'checked' : ''}>Telat (hitung menit keterlambatan)</label>
      <label><input type="radio" name="tl" value="0" ${cfg && !cfg.telat ? 'checked' : ''}>Tanpa telat</label></div>
      <div id="jm"><label>Jam maksimal absen (batas telat)</label><input type="time" id="fjam" value="${cfg?.jam_batas || '08:00'}"></div>
      <label>Jam tutup absen <span class="mu" style="font-weight:400">(opsional)</span></label>
      <input type="time" id="ftutup" value="${cfg?.jam_tutup || ''}"><div class="hint">Setelah jam ini absen terkunci dan dihitung Alpa. Kosongkan jika tidak ada.</div>
      <div id="mg"></div>
      <div id="ptbox"><label>Tipe titik</label>
        <div class="radio"><label><input type="radio" name="md" value="single" ${!cfg || cfg.tipe === 'single' ? 'checked' : ''}>Single (1 titik)</label>
        <label><input type="radio" name="md" value="multi" ${cfg?.tipe === 'multi' ? 'checked' : ''}>Multi (tanpa batas titik)</label></div>
        <div class="ptwrap" id="pts"></div><button class="btn ghost sm" id="addp" type="button">＋ Tambah titik</button>
        <p class="hint" style="margin-top:10px">Isi dengan koordinat (<i>-8.2192, 114.3691</i>) atau link Google Maps panjang yang mengandung @lat,lng. Link pendek maps.app.goo.gl: buka dulu, lalu salin link panjangnya.</p></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="btn ghost" id="bx">Batal</button><button class="btn" id="bs">Simpan</button></div>`);
    const fk = m.querySelector('#fk');

    const optEmp = () => {
      fk.innerHTML = nik ? `<option value="${esc(cfg.nik)}" data-m="${cfg.is_manager ? 1 : 0}" data-j="${esc(cfg.jabatan)}">${esc(cfg.nama)} — ${esc(cfg.jabatan)} (${esc(cfg.nik)})</option>`
        : tersedia.map(x => `<option value="${esc(x.nik)}" data-m="${x.is_manager ? 1 : 0}" data-j="${esc(x.jabatan)}">${esc(x.nama)} — ${esc(x.jabatan)} (${esc(x.nik)})</option>`).join('') || '<option value="">Semua karyawan sudah terdaftar / tidak ditemukan</option>';
      refresh();
    };
    const isMgr = () => fk.selectedOptions[0]?.dataset.m === '1';
    function refresh() {
      const tl = rv(m, 'tl') === '1'; m.querySelector('#jm').style.display = tl ? '' : 'none';
      const md = rv(m, 'md'); m.querySelector('#addp').style.display = md === 'multi' ? '' : 'none';
      if (md === 'single') pts = pts.slice(0, 1);
      const cur = cfg?.ikut_nik || '', mg = m.querySelector('#mg'), had = rv(m, 'sm');
      if (isMgr()) {
        const pilih = had || (cur ? 'asmen' : 'baru');
        const jab = fk.selectedOptions[0].dataset.j.replace(/^manager/i, 'Asisten Manager').toLowerCase();
        const asm = [...ref.asmen].sort((a, b) => (b.jabatan.toLowerCase() === jab) - (a.jabatan.toLowerCase() === jab));
        mg.innerHTML = `<label>Sumber titik Manager</label><div class="radio">
          <label><input type="radio" name="sm" value="baru" ${pilih === 'baru' ? 'checked' : ''}>Pilih titik baru</label>
          <label><input type="radio" name="sm" value="asmen" ${pilih === 'asmen' ? 'checked' : ''}>Pilih titik asmen</label></div>
          <div id="as" style="display:${pilih === 'asmen' ? '' : 'none'}"><select id="fas">${asm.map(a => `<option value="${esc(a.nik)}" ${a.nik === cur ? 'selected' : ''}>${a.jabatan.toLowerCase() === jab ? '★ ' : ''}${esc(a.nama)} — ${esc(a.jabatan)} (${a.jumlah_titik} titik)</option>`).join('') || '<option value="">Belum ada asmen terdaftar</option>'}</select><div class="hint">★ = asmen satu area dengan manager ini</div></div>`;
        m.querySelector('#ptbox').style.display = pilih === 'asmen' ? 'none' : '';
      } else { mg.innerHTML = ''; m.querySelector('#ptbox').style.display = ''; }
      renderPts();
    }
    function renderPts() {
      m.querySelector('#pts').innerHTML = pts.map((p, i) => `<div class="pt">
        <input placeholder="Nama lahan" data-i="${i}" data-f="nama" value="${esc(p.nama)}">
        <input placeholder="Koordinat / link Maps" data-i="${i}" data-f="src" value="${esc(p.src)}">
        <input type="number" min="1" placeholder="Radius m" data-i="${i}" data-f="radius" value="${esc(p.radius)}">
        ${pts.length > 1 ? `<button class="btn ghost sm" type="button" data-rm="${i}">${ic('x', 14)}</button>` : '<span></span>'}</div>`).join('');
    }
    m.addEventListener('change', e => { if (['tl', 'md', 'sm'].includes(e.target.name) || e.target.id === 'fk') refresh(); });
    m.addEventListener('input', e => { const t = e.target; if (t.dataset.f) pts[+t.dataset.i][t.dataset.f] = t.dataset.f === 'radius' ? (+t.value || '') : t.value; });
    m.addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { pts.splice(+b.dataset.rm, 1); renderPts(); } });
    m.querySelector('#addp').onclick = () => { pts.push({ nama: '', src: '', radius: 150 }); renderPts(); const w = m.querySelector('#pts'); w.scrollTop = w.scrollHeight; };
    m.querySelector('#bx').onclick = () => m.remove();
    let tq; m.querySelector('#fq')?.addEventListener('input', e => { clearTimeout(tq); tq = setTimeout(async () => { try { tersedia = (await api('/admin/api/induk?q=' + encodeURIComponent(e.target.value))).tersedia; optEmp(); } catch (er) { toast(er.message, 1); } }, 300); });
    m.querySelector('#bs').onclick = async () => {
      const asmen = isMgr() && rv(m, 'sm') === 'asmen';
      const body = { nik: fk.value, telat: rv(m, 'tl') === '1', jam_batas: m.querySelector('#fjam').value || null, jam_tutup: m.querySelector('#ftutup').value || null,
        tipe: rv(m, 'md'), sumber: asmen ? 'asmen' : 'baru', ikut_nik: asmen ? m.querySelector('#fas').value : null, titik: asmen ? [] : pts };
      if (!body.nik) return toast('Pilih karyawan', 1);
      const b = m.querySelector('#bs'); b.disabled = true;
      try { await api('/admin/api/pengaturan', 'POST', body); m.remove(); toast('Data tersimpan'); load(); }
      catch (e) { toast(e.message, 1); b.disabled = false; }
    };
    optEmp();
  }

  // ===== Import Excel / CSV =====
  const TPL = [['NIK', 'Jam_Batas', 'Telat', 'Jam_Tutup', 'Nama_Titik', 'Koordinat_atau_Link', 'Radius_m', 'Ikut_NIK_Asmen'],
    ['99720014', '08:00', 'Ya', '17:00', 'Lahan A', '-8.2192,114.3691', '150', ''],
    ['99720014', '08:00', 'Ya', '17:00', 'Lahan B', 'https://www.google.com/maps/@-8.2301,114.3802,17z', '200', ''],
    ['02820009', '', 'Tidak', '', 'Lahan A', '-8.2105,114.3555', '100', ''],
    ['96700008', '', 'Tidak', '', '', '', '', '99720014']];
  function imp() {
    const m = modal(`<h3>Import Excel / CSV</h3>
      <p class="mu sm" style="margin:4px 0 12px">Satu baris = satu titik. NIK yang sama di beberapa baris otomatis menjadi <b>Multi Titik</b> (jumlah baris tanpa batas). Satu baris saja = Single. Data lama karyawan yang sama akan ditimpa.</p>
      <div class="tw"><table><tr>${TPL[0].map(h => `<th>${h}</th>`).join('')}</tr>${TPL.slice(1).map(r => `<tr>${r.map(v => `<td>${esc(v)}</td>`).join('')}</tr>`).join('')}</table></div>
      <ul class="mu sm" style="padding-left:18px"><li><b>NIK</b>: 8 digit sesuai db_indukk (nol di depan otomatis dilengkapi).</li>
      <li><b>Telat</b>: Ya / Tidak. Jika Tidak, Jam_Batas boleh kosong.</li>
      <li><b>Jam_Batas</b> dan <b>Jam_Tutup</b>: format HH:MM (24 jam). Jam_Tutup opsional.</li>
      <li><b>Koordinat_atau_Link</b>: “lat,lng” atau link Google Maps panjang.</li>
      <li><b>Radius_m</b>: meter (kosong = 150).</li>
      <li><b>Ikut_NIK_Asmen</b> (opsional, khusus Manager): NIK asmen yang titiknya dipakai; kolom titik dikosongkan. Asmen harus diimport lebih dulu.</li></ul>
      <div class="row-btn"><button class="btn ghost sm" id="tx">⬇ Template Excel (.xlsx)</button><a class="btn ghost sm" href="${window.HADIRKU.template}">⬇ Template CSV</a></div>
      <label style="margin-top:14px">Pilih file (.xlsx / .xls / .csv)</label>
      <input type="file" id="xf" accept=".xlsx,.xls,.csv"><div id="ir"></div>
      <div style="text-align:right"><button class="btn ghost" id="bx">Tutup</button></div>`);
    m.querySelector('#bx').onclick = () => m.remove();
    m.querySelector('#tx').onclick = () => {
      const ws = XLSX.utils.aoa_to_sheet(TPL); ws['!cols'] = [{ wch: 12 }, { wch: 11 }, { wch: 8 }, { wch: 11 }, { wch: 16 }, { wch: 50 }, { wch: 10 }, { wch: 16 }];
      const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Template'); XLSX.writeFile(wb, 'template_titik_presensi.xlsx');
    };
    m.querySelector('#xf').onchange = async e => {
      const f = e.target.files[0]; if (!f) return;
      const out = m.querySelector('#ir'); out.innerHTML = '<div class="info ok">Memproses…</div>';
      try {
        const wb = XLSX.read(await f.arrayBuffer(), { type: 'array', raw: true, codepage: 65001 });
        const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '', raw: true });
        if (!rows.length) throw new Error('File kosong');
        const r = await api('/admin/api/import', 'POST', { rows });
        out.innerHTML = `<div class="info ok">✅ ${r.berhasil} karyawan berhasil diimport${r.gagal ? `, ${r.gagal} dilewati` : ''}</div>`
          + (r.errors.length ? `<div class="info no" style="font-weight:500">${r.errors.map(esc).join('<br>')}${r.errors_lainnya ? `<br>…dan ${r.errors_lainnya} error lain` : ''}</div>` : '');
        load();
      } catch (er) { out.innerHTML = `<div class="info no">${esc(er.message)}</div>`; }
    };
  }
  load();
})();
