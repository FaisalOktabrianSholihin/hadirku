/* Widget progres import di semua menu admin.
   Import berjalan di server (queue), jadi tetap lanjut walau user pindah menu / menutup popup.
   Halaman mana pun yang dibuka akan menampilkan progresnya lagi lewat /admin/api/import/aktif. */
(() => {
  if (!window.H || window.H.imp) return;
  const { api, esc, ic } = H;
  const POLL = 1200;
  let job = null, timer = null, box = null, buka = false;
  const selesaiDilapor = new Set();

  const emit = (n, d) => document.dispatchEvent(new CustomEvent(n, { detail: d }));
  const aktif = j => j && (j.status === 'menunggu' || j.status === 'berjalan');
  const modalTerbuka = () => !!document.querySelector('[data-imp-modal]');

  function teks(j) {
    if (j.status === 'menunggu') return j.menunggu_detik > 15 ? 'Menunggu antrian… pastikan queue worker berjalan (php artisan queue:work).' : 'Menunggu antrian…';
    if (j.status === 'berjalan') return `Memproses ${j.diproses} dari ${j.total} karyawan`;
    if (j.status === 'selesai') return `${j.berhasil} karyawan berhasil${j.gagal ? `, ${j.gagal} dilewati` : ''}`;
    return j.pesan || 'Import gagal diproses.';
  }

  function render() {
    if (!job || modalTerbuka()) { box?.remove(); box = null; return; }
    if (!box) { box = document.createElement('div'); box.className = 'impw'; document.body.append(box); }
    const s = job.status, ok = s === 'selesai', bad = s === 'gagal';
    const err = (job.errors || []).map(esc).join('<br>') + (job.errors_lainnya ? `<br>…dan ${job.errors_lainnya} error lain` : '');
    box.className = 'impw ' + (ok ? 'ok' : bad ? 'bad' : '');
    box.innerHTML = `<div class="impw-h"><span class="impw-ic">${ic(ok ? 'check' : bad ? 'alert' : 'upload', 16)}</span>
        <div><b>${ok ? 'Import selesai' : bad ? 'Import gagal' : 'Import Data Karyawan'}</b><small>${esc(job.nama_file || 'File Excel')}</small></div>
        ${aktif(job) ? '' : `<button type="button" class="impw-x" data-x aria-label="Tutup">${ic('x', 16)}</button>`}</div>
      <div class="ipb"><i style="width:${job.persen}%"></i></div>
      <div class="impw-r"><span>${esc(teks(job))}</span><b>${job.persen}%</b></div>
      ${aktif(job) ? '<div class="impw-n">Berjalan di latar belakang, Anda bebas pindah menu.</div>' : ''}
      ${!aktif(job) && err ? `<button type="button" class="impw-d" data-d>${buka ? 'Sembunyikan' : 'Lihat'} detail (${(job.errors || []).length + job.errors_lainnya})</button>${buka ? `<div class="impw-e">${err}</div>` : ''}` : ''}`;
  }

  function terima(j) {
    job = j;
    emit('import:update', j);
    if (!aktif(j) && !selesaiDilapor.has(j.id)) { selesaiDilapor.add(j.id); emit('import:selesai', j); }
    render();
    clearTimeout(timer);
    if (aktif(j)) timer = setTimeout(tick, POLL);
  }

  async function tick() {
    try {
      const r = job ? await api('/admin/api/import/' + job.id) : (await api('/admin/api/import/aktif')).job;
      if (r) terima(r); else { job = null; render(); }
    } catch (e) { timer = setTimeout(tick, POLL * 3); } // koneksi putus sebentar: coba lagi
  }

  async function tutup() {
    if (!job) return;
    const id = job.id; job = null; buka = false; render();
    try { await api(`/admin/api/import/${id}/tutup`, 'POST'); } catch (e) { }
  }

  document.addEventListener('click', e => {
    if (e.target.closest('.impw [data-x]')) tutup();
    if (e.target.closest('.impw [data-d]')) { buka = !buka; render(); }
  });

  window.H.imp = {
    /** Dipanggil setelah upload berhasil: mulai memantau job */
    mulai: j => { buka = false; terima(j); },
    tutup,
    segarkan: render,
    get job() { return job; },
  };

  tick(); // saat halaman dibuka: tampilkan import yang masih berjalan / belum ditutup
})();
