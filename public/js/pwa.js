/* Tombol "Download Aplikasi": memasang Hadirku ke layar utama HP/komputer (PWA).
   Setelah terpasang, dibuka dari ikon di layar seperti aplikasi biasa (tanpa bar alamat browser).
   Berdiri sendiri (tidak butuh common.js) supaya bisa dipakai di halaman login juga. */
(() => {
  const standalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const btns = [...document.querySelectorAll('[data-pwa-install]')];
  const ua = navigator.userAgent;
  const iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  let prompt = null;

  // daftarkan service worker (wajib agar browser menawarkan instal)
  if ('serviceWorker' in navigator) {
    addEventListener('load', () => navigator.serviceWorker.register(document.documentElement.dataset.sw || 'sw.js').catch(() => { }));
  }

  const tampil = on => btns.forEach(b => { b.hidden = !on; });
  if (standalone()) { tampil(false); return; } // sudah dibuka sebagai aplikasi
  tampil(true);

  addEventListener('beforeinstallprompt', e => { e.preventDefault(); prompt = e; });
  addEventListener('appinstalled', () => { prompt = null; tampil(false); toastPwa('Hadirku berhasil dipasang. Buka dari ikon di layar utama.'); });

  function toastPwa(t) {
    const d = document.createElement('div'); d.className = 'pwa-toast'; d.textContent = t; document.body.append(d);
    setTimeout(() => d.remove(), 4500);
  }

  function panduan() {
    const android = /Android/i.test(ua);
    const langkah = iOS
      ? ['Buka halaman ini di <b>Safari</b>.', 'Ketuk tombol <b>Bagikan</b> (ikon kotak dengan panah ke atas).', 'Pilih <b>Tambah ke Layar Utama</b>, lalu ketuk <b>Tambah</b>.']
      : android
        ? ['Ketuk menu <b>⋮</b> di pojok kanan atas Chrome.', 'Pilih <b>Instal aplikasi</b> atau <b>Tambahkan ke layar utama</b>.', 'Ketuk <b>Instal</b>.']
        : ['Klik ikon <b>Instal</b> di kanan bar alamat (atau menu <b>⋮</b> › <b>Instal Hadirku</b>).', 'Klik <b>Instal</b>.'];
    const m = document.createElement('div'); m.className = 'pwa-modal';
    m.innerHTML = `<div class="pwa-card" role="dialog" aria-modal="true" aria-label="Cara memasang aplikasi">
        <img src="${document.documentElement.dataset.icon || 'icons/icon-192.png'}" alt="" width="56" height="56">
        <h3>Pasang aplikasi Hadirku</h3>
        <p>Setelah terpasang, cukup ketuk ikon Hadirku di layar utama untuk membuka.</p>
        <ol>${langkah.map(l => `<li>${l}</li>`).join('')}</ol>
        <button type="button" class="pwa-ok">Mengerti</button></div>`;
    const tutup = () => m.remove();
    m.addEventListener('click', e => { if (e.target === m || e.target.closest('.pwa-ok')) tutup(); });
    document.body.append(m);
  }

  btns.forEach(b => b.addEventListener('click', async () => {
    if (prompt) {
      prompt.prompt();
      const r = await prompt.userChoice.catch(() => null);
      prompt = null;
      if (r && r.outcome === 'accepted') tampil(false);
    } else {
      panduan(); // iOS / browser yang tidak menyediakan dialog instal otomatis
    }
  }));
})();
