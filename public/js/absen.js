(() => {
  const { esc, modal, toast, ic } = H;
  const $ = s => document.querySelector(s);
  const tok = document.querySelector('meta[name=csrf-token]').content;
  const boot = JSON.parse($('#boot').textContent);
  const stage = $('#stage');

  // ===== Jam server (tidak terpengaruh jam HP) =====
  // Jam di halaman ini = jam server + waktu berjalan (monotonic). Disinkronkan ulang ke server saat halaman
  // dibuka, saat kembali ke tab, dan bila terdeteksi lompatan jam perangkat.
  let srv0 = boot.serverNow, perf0 = performance.now();
  const nowMs = () => srv0 + (performance.now() - perf0);
  const nowSrv = () => new Date(nowMs());
  const jam = d => d.toLocaleTimeString('id-ID', { timeZone: boot.tz, hour12: false }).replace(/\./g, ':');
  const tgl = d => d.toLocaleDateString('id-ID', { timeZone: boot.tz, day: '2-digit', month: '2-digit', year: 'numeric' });
  const tunda = ms => new Promise(r => setTimeout(r, ms));

  async function api(url, method, body) {
    const r = await fetch(url, {
      method, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': tok, ...(body ? { 'Content-Type': 'application/json' } : {}) },
      body: body ? JSON.stringify(body) : undefined,
    });
    let d = null; try { d = await r.json(); } catch (e) {}
    if (r.status === 401) { location.href = '/login'; throw new Error('Sesi berakhir'); }
    if (!r.ok) { const e = new Error(d?.errors ? Object.values(d.errors)[0][0] : (d?.message || 'Terjadi kesalahan')); e.status = r.status; e.data = d; throw e; }
    return d;
  }

  const clk = $('#clk');
  const tampilJam = () => { if (clk) clk.textContent = jam(nowSrv()); };
  tampilJam(); setInterval(tampilJam, 1000);

  let stream = null, watch = null, poll = null, pos = null, nonce = null, shot = null, sampel = [], kamera = false;
  let blokir = null; // { jenis: 'waktu', bukti } selama jam perangkat tidak sesuai
  const stop = () => {
    kamera = false;
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    if (watch !== null) { navigator.geolocation.clearWatch(watch); watch = null; }
    clearInterval(poll); poll = null;
  };
  const home = () => { stop(); location.reload(); };

  // ===== Popup hasil absen =====
  function hasil({ img, titik, jarak, radius, ok, jamTxt, status, menit, pesan }) {
    const m = modal(`<div class="res">
      ${ok ? '<svg class="chk" viewBox="0 0 60 60"><circle cx="30" cy="30" r="25"/><path d="M18 31l8 8 16-17"/></svg>' : `<div class="dlg-ic bad" style="margin:0 auto 12px">${ic('x', 30)}</div>`}
      <h3>${ok ? 'Absen Berhasil' : 'Absen Ditolak'}</h3>
      <p class="mu" style="margin:4px 0 12px">${ok ? `Tercatat pukul <b style="color:var(--tx)">${esc(jamTxt)}</b>` : esc(pesan)}</p>
      ${ok && status === 'telat' ? `<div class="info" style="background:#fef3c7;color:#92400e;margin:0 0 12px">Terlambat ${menit} menit</div>` : ''}
      ${img ? `<img src="${img}" alt="Foto absen">` : ''}
      ${jarak != null ? `<div class="res-row ${ok ? 'ok' : 'no'}"><span>${esc(titik)}</span><b>${jarak} m</b><small>dari titik · radius ${radius} m</small></div>` : ''}
      <button class="btn full ${ok ? '' : 'danger'}" style="margin-top:14px" id="mx">Tutup</button></div>`, { cls: 'res-card', dismiss: false });
    m.querySelector('#mx').onclick = () => { m.remove(); home(); };
  }

  // ===== Popup kecurangan (Fake GPS / waktu diubah) =====
  function kecurangan(jenis, bukti = {}) {
    if (document.querySelector('.fraud')) return;
    const gps = jenis === 'gps';
    const judul = gps ? 'Fake GPS Terdeteksi' : 'Perubahan Waktu Terdeteksi';
    const pesan = gps
      ? 'Lokasi pada perangkat Anda terindikasi dipalsukan (Mock Location). Matikan aplikasi Fake GPS dan opsi “Pilih aplikasi lokasi palsu” di Opsi Pengembang, lalu coba lagi.'
      : 'Jam pada perangkat Anda tidak sama dengan jam server. Aktifkan “Tanggal & waktu otomatis” di pengaturan HP, lalu muat ulang halaman ini.';
    const detail = gps
      ? `<ul class="fraud-ul">${(bukti.alasan || ['Pola data lokasi tidak wajar.']).map(a => `<li>${esc(a)}</li>`).join('')}</ul>`
      : `<div class="fraud-kv"><span>Jam perangkat Anda</span><b>${esc(bukti.waktu_perangkat || '-')}</b><span>Jam server</span><b>${esc(bukti.waktu_server || '-')}</b><span>Selisih</span><b class="bad">${esc(bukti.selisih_teks || '-')} ${esc(bukti.arah || '')}</b></div>`;
    const m = modal(`<div class="fraud">
        <div class="fraud-h"><span class="fraud-ic">${ic('shield', 38)}</span><small>PERINGATAN KEAMANAN</small><h3>Sistem Mendeteksi Kecurangan</h3><div class="fraud-t">${judul}</div></div>
        <div class="fraud-b"><p>${pesan}</p>${detail}<div class="fraud-note">${ic('lock', 16)}<span>Absen tidak dapat dilakukan selama kondisi ini berlangsung. Kejadian dicatat dan dapat dilihat oleh admin.</span></div></div>
        <div class="fraud-f"><button class="btn danger full" id="fx" type="button">Saya Mengerti</button></div></div>`, { cls: 'fraud-card', dismiss: false });
    m.querySelector('#fx').onclick = () => { m.remove(); if (gps || !blokir) home(); };
  }

  // Tangani respons kecurangan dari server (HTTP 422 + kode). true = sudah ditangani
  function curangServer(e) {
    const k = e?.data?.kode; if (!k) return false;
    stop();
    if (k === 'waktu_diubah') { setBlokirWaktu(e.data.bukti); }
    else if (k === 'fake_gps') kecurangan('gps', e.data.bukti);
    else return false;
    return true;
  }

  // ===== Anti manipulasi waktu =====
  async function sinkron() {
    const t0 = performance.now(), d0 = Date.now();
    const r = await fetch('/absen/waktu?d=' + d0, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (r.status === 401) { location.href = '/login'; return null; }
    if (!r.ok) return null;
    const j = await r.json(), t1 = performance.now();
    srv0 = j.ms + (t1 - t0) / 2; perf0 = t1; tampilJam();
    return j;
  }
  function setBlokirWaktu(bukti) {
    const baru = !blokir;
    blokir = { jenis: 'waktu', bukti };
    const b = $('#btnStart'); if (b) b.disabled = true;
    if (!$('#blk')) {
      const d = document.createElement('div'); d.id = 'blk'; d.className = 'info no blk';
      d.innerHTML = `${ic('shield', 18)}<div><b>Absen diblokir: jam perangkat tidak sesuai.</b><br><span class="sm" style="font-weight:500">Aktifkan tanggal &amp; waktu otomatis lalu <a href="#" id="blkRl">muat ulang</a>.</span></div>`;
      stage.querySelector('.rows')?.after(d);
      d.querySelector('#blkRl').onclick = e => { e.preventDefault(); location.reload(); };
    }
    if (baru) kecurangan('waktu', bukti);
  }
  function lepasBlokir() {
    if (!blokir) return; blokir = null;
    $('#blk')?.remove(); const b = $('#btnStart'); if (b) b.disabled = false;
    toast('Waktu perangkat sudah sesuai. Anda bisa absen.', 'ok');
  }
  async function cekWaktu() {
    try {
      const j = await sinkron(); if (!j) return true;
      if (j.curang) { setBlokirWaktu(j.bukti); return false; }
      lepasBlokir(); return true;
    } catch (e) { return true; } // jaringan gagal: server tetap memeriksa saat absen
  }
  // jam dinding vs jam monotonic: lompatan berarti jam HP diubah / HP tidur -> konfirmasi ke server
  let w0 = Date.now(), p0 = performance.now();
  setInterval(() => { const dw = Date.now() - w0, dp = performance.now() - p0; w0 = Date.now(); p0 = performance.now(); if (Math.abs(dw - dp) > 4000) cekWaktu(); }, 2500);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) cekWaktu(); });
  window.addEventListener('focus', () => cekWaktu());
  window.addEventListener('pageshow', e => { if (e.persisted) cekWaktu(); });
  cekWaktu();

  // ===== Anti Fake GPS / Mock Location =====
  // Browser tidak bisa membaca flag "mock" Android, jadi dipakai analisis pola data lokasi (server memeriksa ulang).
  const geoAsli = () => {
    try { const g = navigator.geolocation; return ['getCurrentPosition', 'watchPosition', 'clearWatch'].every(k => /\[native code\]/.test(Function.prototype.toString.call(g[k]))); }
    catch (e) { return false; }
  };
  const desimal = v => { const t = Math.abs(v).toFixed(12).replace(/0+$/, ''), p = t.indexOf('.'); return p < 0 ? 0 : t.length - p - 1; };
  const jarakM = (a, b, c, d) => { const R = 6371000, r = x => x * Math.PI / 180, dl = r(c - a), dg = r(d - b); const h = Math.sin(dl / 2) ** 2 + Math.cos(r(a)) * Math.cos(r(c)) * Math.sin(dg / 2) ** 2; return 2 * R * Math.asin(Math.min(1, Math.sqrt(h))); };
  function analisaGps(s, asli) {
    let skor = 0; const alasan = [];
    if (!asli) { skor += 4; alasan.push('Fungsi lokasi di browser dimodifikasi (bukan GPS asli perangkat).'); }
    for (let i = 1; i < s.length; i++) {
      const j = jarakM(s[i - 1].lat, s[i - 1].lng, s[i].lat, s[i].lng), dt = Math.max(0.5, (s[i].ts - s[i - 1].ts) / 1000 || 1);
      if (j > 300 + (s[i - 1].acc || 0) + (s[i].acc || 0) && j / dt > 100) { skor += 4; alasan.push(`Lokasi berpindah ${Math.round(j)} m dalam ${dt.toFixed(1)} detik (tidak wajar).`); break; }
    }
    if (s.length >= 5 && new Set(s.map(x => x.lat.toFixed(9) + ',' + x.lng.toFixed(9))).size === 1) { skor += 2; alasan.push(`Koordinat identik persis pada ${s.length} pembacaan berturut-turut.`); }
    if (s.length) { const mx = Math.max(...s.map(x => Math.max(desimal(x.lat), desimal(x.lng)))); if (mx <= 4) { skor += 2; alasan.push(`Presisi koordinat terlalu rendah (maks ${mx} desimal), pola lokasi buatan.`); } }
    const ac = s.map(x => x.acc).filter(v => v != null && !isNaN(v)); if (ac.length && Math.max(...ac) <= 1) { skor += 2; alasan.push(`Akurasi GPS ±${Math.max(...ac)} m (tidak mungkin pada GPS ponsel).`); }
    const al = s.map(x => x.alt).filter(v => v != null && !isNaN(v)); if (al.length >= 3 && al.every(v => v === 0)) { skor += 1; alasan.push('Ketinggian selalu tepat 0 m.'); }
    return { skor, alasan };
  }
  const rekam = p => {
    const x = { lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy, alt: p.coords.altitude, ts: p.timestamp }, l = sampel[sampel.length - 1];
    if (l && l.ts === x.ts && l.lat === x.lat && l.lng === x.lng) return;
    sampel.push(x); if (sampel.length > 12) sampel.shift();
  };
  const ambil1 = () => new Promise(res => navigator.geolocation.getCurrentPosition(p => { rekam(p); res(p); }, () => res(null), { enableHighAccuracy: true, maximumAge: 0, timeout: 7000 }));
  async function lengkapiSampel() { for (let n = 0; sampel.length < 5 && n < 8; n++) { await ambil1(); if (sampel.length < 5) await tunda(500); } }
  function curigaGps() {
    if (!boot.deteksiGps) return null;
    const h = analisaGps([...sampel].sort((a, b) => a.ts - b.ts), geoAsli());
    return h.skor >= boot.skorGps ? h : null;
  }
  function blokirGps(h) { stop(); kecurangan('gps', { alasan: h.alasan }); }

  $('#btnLihat')?.addEventListener('click', () => {
    const s = boot.sudah; if (!s) return;
    hasil({ img: s.foto_url, titik: s.titik, jarak: s.jarak, radius: s.radius, ok: true, jamTxt: s.jam, status: s.status, menit: s.menit_telat });
  });

  async function start() {
    if (blokir) return kecurangan('waktu', blokir.bukti);
    if (!navigator.mediaDevices?.getUserMedia || !navigator.geolocation) return toast('Browser tidak mendukung kamera/GPS. Gunakan Chrome/Safari terbaru lewat HTTPS.', 1);
    if (boot.deteksiGps && !geoAsli()) return blokirGps({ alasan: ['Fungsi lokasi di browser dimodifikasi (bukan GPS asli perangkat).'] });
    if (!(await cekWaktu())) return;

    stage.innerHTML = `<h3>Ambil Foto Selfie</h3><p class="mu sm" id="gps">Mencari lokasi GPS…</p>
      <div class="cam" id="cam"><video id="vd" autoplay playsinline muted></video></div>
      <div style="margin-top:14px;display:flex;gap:8px;justify-content:center">
        <button class="btn ghost" id="bBatal">Batal</button><button class="btn" id="bSnap" disabled>${ic('check', 16)} Ambil Foto</button></div>`;
    $('#bBatal').onclick = home;
    $('#bSnap').onclick = snap;
    pos = null; sampel = []; kamera = true;
    try { nonce = (await api('/absen/mulai', 'POST', { waktu_perangkat: Date.now() })).nonce; }
    catch (e) { if (curangServer(e)) return; toast(e.message, 1); return home(); }
    watch = navigator.geolocation.watchPosition(p => {
      rekam(p);
      if (!pos || p.coords.accuracy <= pos.acc + 5) pos = { lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy };
      $('#gps').textContent = `📍 ${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)} (±${Math.round(pos.acc)} m)`;
      ready();
    }, () => { const g = $('#gps'); if (g) g.textContent = '⚠️ Izinkan akses lokasi (GPS) untuk absen'; }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    // pembacaan lokasi berkala untuk menilai apakah GPS asli
    poll = setInterval(async () => { if (!kamera) return; await ambil1(); if (!kamera) return; const h = sampel.length >= 5 && curigaGps(); if (h) blokirGps(h); }, 1500);
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 } }, audio: false });
      $('#vd').srcObject = stream; ready();
    } catch (e) { $('#cam').innerHTML = '<p style="color:#fff;padding:30px">Kamera tidak bisa dibuka. Izinkan akses kamera di browser.</p>'; }
  }
  const ready = () => { const b = $('#bSnap'); if (b && pos && stream) b.disabled = false; };

  // Foto HANYA dari frame video kamera langsung (tidak ada input file/galeri)
  async function snap() {
    const v = $('#vd'); if (!v || !v.videoWidth || !pos) return;
    const b = $('#bSnap'); b.disabled = true; b.textContent = 'Memverifikasi lokasi…';
    await lengkapiSampel();
    if (!kamera) return; // sudah dihentikan oleh deteksi
    const h = curigaGps(); if (h) return blokirGps(h);
    if (!(await cekWaktu())) { stop(); return; }
    const W = 640, H2 = Math.round(W * v.videoHeight / v.videoWidth);
    const cv = document.createElement('canvas'); cv.width = W; cv.height = H2;
    const x = cv.getContext('2d'); x.drawImage(v, 0, 0, W, H2);
    const d = nowSrv(), t1 = tgl(d) + ' ' + jam(d), t2 = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
    x.font = 'bold 18px sans-serif';
    const w = Math.max(x.measureText(t1).width, x.measureText(t2).width) + 24;
    x.fillStyle = 'rgba(0,0,0,.6)'; x.fillRect(W - w - 10, 10, w, 56);
    x.fillStyle = '#fff'; x.textAlign = 'right'; x.fillText(t1, W - 22, 34); x.fillText(t2, W - 22, 58);
    shot = { img: cv.toDataURL('image/jpeg', 0.7), lat: pos.lat, lng: pos.lng, acc: pos.acc };
    clearInterval(poll); poll = null;
    stop();
    stage.innerHTML = `<h3>Pratinjau</h3><div class="cam"><img src="${shot.img}" alt=""></div>
      <div style="margin-top:14px;display:flex;gap:8px;justify-content:center">
        <button class="btn ghost" id="bUlang">Ulangi</button><button class="btn pulse" id="bSimpan">${ic('check', 16)} Simpan Absen</button></div>`;
    $('#bUlang').onclick = start;
    $('#bSimpan').onclick = simpan;
  }

  async function simpan() {
    const b = $('#bSimpan'); b.disabled = true; b.textContent = 'Menyimpan…';
    try {
      const s8 = [...sampel].sort((a, c) => a.ts - c.ts).slice(-8).map(s => ({ lat: s.lat, lng: s.lng, acc: s.acc, alt: s.alt, ts: s.ts }));
      const r = await api('/absen', 'POST', { foto: shot.img, lat: shot.lat, lng: shot.lng, akurasi: shot.acc, nonce, waktu_perangkat: Date.now(), geo_asli: geoAsli(), sampel: s8 });
      const s = r.data;
      hasil({ img: shot.img, titik: s.titik, jarak: s.jarak, radius: s.radius, ok: true, jamTxt: s.jam, status: s.status, menit: s.menit_telat });
    } catch (e) {
      if (curangServer(e)) return;
      const dt = e.data?.detail;
      if (dt) hasil({ img: shot.img, titik: dt.titik, jarak: dt.jarak, radius: dt.radius, ok: false, pesan: 'Di luar radius. Absen ditolak, dekati titik lahan lalu ulangi.' });
      else if (e.status === 409) hasil({ ok: false, pesan: e.message });
      else { toast(e.message, 1); b.disabled = false; b.innerHTML = `${ic('check', 16)} Simpan Absen`; if (e.status === 422) setTimeout(start, 1500); }
    }
  }

  $('#btnStart')?.addEventListener('click', start);
})();
