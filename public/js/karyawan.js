(() => {
  const { api, esc, toast, modal, ic } = H;
  const $ = s => document.querySelector(s);
  let page = 1, q = '', dv = '', last = 1;
  const st = { sort: '', dir: 'asc' };

  async function load() {
    try {
      const r = await api(`/admin/api/pengaturan?page=${page}&q=${encodeURIComponent(q)}&divisi=${dv}&sort=${st.sort}&dir=${st.dir}`);
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
  H.sortable($('#th'), st, () => { page = 1; load(); });
  $('#prev').onclick = () => { page--; load(); };
  $('#next').onclick = () => { page++; load(); };
  let tm; $('#cari').oninput = e => { clearTimeout(tm); tm = setTimeout(() => { q = e.target.value; page = 1; load(); }, 300); };
  $('#fdiv').onchange = e => { dv = e.target.value; page = 1; load(); };
  $('#tb').addEventListener('click', async e => {
    const t = e.target.closest('[data-tg],[data-ed],[data-del]'); if (!t) return;
    try {
      if (t.dataset.tg) { await api(`/admin/api/pengaturan/${t.dataset.tg}/aktif`, 'PATCH'); load(); }
      else if (t.dataset.ed) form(t.dataset.ed);
      else if (t.dataset.del) {
        const nama = t.closest('tr').querySelector('b')?.textContent || t.dataset.del;
        if (await H.confirm({ judul: 'Hapus pengaturan presensi?', pesan: `Pengaturan presensi dan titik lahan milik ${nama} akan dihapus. Data karyawan di db_indukk tidak terpengaruh.`, ok: 'Ya, hapus', tipe: 'bad' })) { await api(`/admin/api/pengaturan/${t.dataset.del}`, 'DELETE'); toast('Pengaturan dihapus'); load(); }
      }
    } catch (err) { toast(err.message, 1); }
  });
  $('#btnTambah').onclick = () => form();
  $('#btnImport').onclick = () => imp();
  api('/admin/api/induk').then(r => { $('#fdiv').innerHTML += r.divisi.map(d => `<option value="${d.id}">${esc(d.nama_divisi)}</option>`).join(''); }).catch(() => { });

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
      const body = {
        nik: fk.value, telat: rv(m, 'tl') === '1', jam_batas: m.querySelector('#fjam').value || null, jam_tutup: m.querySelector('#ftutup').value || null,
        tipe: rv(m, 'md'), sumber: asmen ? 'asmen' : 'baru', ikut_nik: asmen ? m.querySelector('#fas').value : null, titik: asmen ? [] : pts
      };
      if (!body.nik) return toast('Pilih karyawan', 1);
      const b = m.querySelector('#bs'); b.disabled = true;
      try { await api('/admin/api/pengaturan', 'POST', body); m.remove(); toast('Data tersimpan'); load(); }
      catch (e) { toast(e.message, 1); b.disabled = false; }
    };
    optEmp();
  }

  // ===== Import Excel (berjalan di latar belakang) =====
  const aktifImp = j => j && (j.status === 'menunggu' || j.status === 'berjalan');
  const norm = k => String(k).toLowerCase().replace(/[^a-z]/g, '');

  function imp() {
    const m = modal(`<div class="imp-title"><h3>Import Data Karyawan</h3></div>
      <div class="imp-dl"><span>${ic('file', 18)} Download Template Import</span>
        <a class="btn sm" href="${window.HADIRKU.template}">${ic('download', 16)} Download Template</a></div>
      <div class="imp-box">
        <div class="imp-bh">${ic('upload', 18)} Upload File Excel</div>
        <div class="imp-bb" id="ib">
          <div class="imp-warn"><b>${ic('alert', 15)} Perhatian:</b>
            <ol>
              <li>Download template Excel di atas terlebih dahulu</li>
              <li>Isi data mulai dari <b>baris ke-2</b> (baris header jangan diubah)</li>
              <li>Satu baris = satu titik lahan. NIK yang sama di beberapa baris otomatis menjadi <b>Multi Titik</b></li>
              <li>Simpan file dalam format <b>.xlsx</b> (nama file bebas)</li>
              <li>Upload file tersebut melalui form di bawah</li>
              <li>Kolom <b>NIK</b> harus 8 digit berformat <b>Teks</b>; <b>Telat</b> diisi <b>Ya</b> atau <b>Tidak</b>; <b>Jam_Batas</b> dan <b>Jam_Tutup</b> ditulis <b>HH:MM</b>. Penjelasan tiap kolom ada di sheet kedua <b>Petunjuk</b></li>
            </ol>
          </div>
          <label for="xf">Pilih File Excel</label>
          <input type="file" id="xf" class="imp-file" accept=".xlsx,.xls,.csv">
          <div id="ie"></div>
          <div class="imp-act">
            <button class="btn" id="go" type="button" disabled>${ic('upload', 16)} Import Excel</button>
            <button class="btn ghost" id="bx" type="button">${ic('back', 16)} Kembali</button>
          </div>
        </div>
      </div>`, { cls: 'imp-card' });
    m.setAttribute('data-imp-modal', '');
    const $m = s => m.querySelector(s);
    const bye = () => m.remove();
    $m('#bx').onclick = bye;

    // ---- tampilan progres di dalam popup ----
    const tampilProgres = () => {
      $m('#ib').innerHTML = `<div class="imp-prog">
          <div class="imp-pt"><span id="ipt">Menunggu antrian…</span><b id="ipp">0%</b></div>
          <div class="ipb"><i id="ipf" style="width:0%"></i></div>
          <p class="mu sm" id="ipn">Proses berjalan di latar belakang. Anda boleh menutup popup atau pindah menu, progres tetap tampil di pojok kanan bawah.</p>
          <div id="ires"></div>
        </div>
        <div class="imp-act"><button class="btn ghost" id="bx2" type="button">Tutup</button></div>`;
      $m('#bx2').onclick = bye;
    };
    const update = j => {
      if (!$m('#ipf')) return;
      $m('#ipf').style.width = j.persen + '%'; $m('#ipp').textContent = j.persen + '%';
      if (j.status === 'menunggu') $m('#ipt').textContent = j.menunggu_detik > 15 ? 'Menunggu antrian… pastikan queue worker berjalan (php artisan queue:work)' : 'Menunggu antrian…';
      else if (j.status === 'berjalan') $m('#ipt').textContent = `Memproses ${j.diproses} dari ${j.total} karyawan`;
      else {
        $m('#ipt').textContent = j.status === 'selesai' ? 'Selesai' : 'Gagal';
        $m('#ipn').remove();
        const err = (j.errors || []).map(esc).join('<br>') + (j.errors_lainnya ? `<br>…dan ${j.errors_lainnya} error lain` : '');
        $m('#ires').innerHTML = j.status === 'selesai'
          ? `<div class="info ok">✅ ${j.berhasil} karyawan berhasil diimport${j.gagal ? `, ${j.gagal} dilewati` : ''}</div>${err ? `<div class="info no" style="font-weight:500">${err}</div>` : ''}`
          : `<div class="info no">${esc(j.pesan || 'Import gagal diproses.')}</div>${err ? `<div class="info no" style="font-weight:500">${err}</div>` : ''}`;
      }
    };
    const onUpd = e => update(e.detail);
    document.addEventListener('import:update', onUpd);
    m.addEventListener('close', () => {
      document.removeEventListener('import:update', onUpd);
      setTimeout(() => H.imp?.segarkan(), 250); // popup tertutup: widget pojok kanan bawah mengambil alih
    });

    // popup dibuka saat ada import yang masih jalan: langsung tampilkan progresnya
    if (aktifImp(H.imp?.job)) { tampilProgres(); update(H.imp.job); return; }

    // ---- pilih file & kirim ----
    const out = $m('#ie');
    $m('#xf').onchange = e => { out.innerHTML = ''; $m('#go').disabled = !e.target.files[0]; };
    $m('#go').onclick = async () => {
      const f = $m('#xf').files[0]; if (!f) return;
      const go = $m('#go'); go.disabled = true; out.innerHTML = '<div class="info ok">Membaca file…</div>';
      try {
        const wb = XLSX.read(await f.arrayBuffer(), { type: 'array', raw: true, codepage: 65001 });
        const ws = wb.Sheets['Data Presensi'] || wb.Sheets[wb.SheetNames[0]];
        const aoa = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: true, blankrows: true });
        if (aoa.length < 2) throw new Error('File kosong. Isi data mulai dari baris ke-2.');
        const head = aoa[0].map(norm);
        if (!head.includes('nik')) throw new Error('Header tidak sesuai. Gunakan template dari tombol “Download Template” dan jangan ubah baris header.');
        const rows = [];
        aoa.slice(1).forEach((r, i) => {
          if (!r.some(v => String(v).trim() !== '')) return; // lewati baris kosong, nomor baris asli tetap dikirim
          const o = { __baris: i + 2 };
          aoa[0].forEach((h, c) => { if (String(h).trim() !== '') o[h] = r[c] ?? ''; });
          rows.push(o);
        });
        if (!rows.length) throw new Error('File kosong. Isi data mulai dari baris ke-2.');
        if (rows.length > 5000) throw new Error('Maksimal 5.000 baris per file (file ini ' + rows.length + ' baris).');
        const j = await api('/admin/api/import', 'POST', { rows, nama_file: f.name });
        tampilProgres();
        H.imp.mulai(j);
      } catch (er) { out.innerHTML = `<div class="info no">${esc(er.message)}</div>`; go.disabled = false; }
    };
  }
  document.addEventListener('import:selesai', () => load()); // muat ulang tabel saat import selesai (popup tertutup / pindah menu)
  load();
})();
