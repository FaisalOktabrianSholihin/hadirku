(() => {
  const $ = s => document.querySelector(s);
  const tok = document.querySelector('meta[name=csrf-token]').content;
  const boot = JSON.parse($('#boot').textContent);
  const off = boot.serverNow - Date.now();
  const stage = $('#stage');
  const homeHtml = stage.innerHTML;
  const nowSrv = () => new Date(Date.now() + off);
  const jam = d => d.toLocaleTimeString('id-ID', { timeZone: boot.tz, hour12: false }).replace(/\./g, ':');
  const tgl = d => d.toLocaleDateString('id-ID', { timeZone: boot.tz, day: '2-digit', month: '2-digit', year: 'numeric' });
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

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

  setInterval(() => { const c = $('#clk'); if (c) c.textContent = jam(nowSrv()); }, 1000);
  $('#clk').textContent = jam(nowSrv());

  let stream = null, watch = null, pos = null, nonce = null, shot = null;
  const stop = () => {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    if (watch !== null) { navigator.geolocation.clearWatch(watch); watch = null; }
  };
  const toast = m => { const t = document.createElement('div'); t.className = 'toast'; t.textContent = m; document.body.append(t); setTimeout(() => t.remove(), 3200); };
  const home = () => { stop(); location.reload(); };

  function modal(html) {
    const m = document.createElement('div'); m.className = 'modal';
    m.innerHTML = `<div class="card res" style="text-align:center;max-width:400px">${html}</div>`;
    document.body.append(m); return m;
  }
  function hasil({ img, titik, jarak, radius, ok, jamTxt, status, menit, pesan }) {
    const m = modal(`${img ? `<img src="${img}" alt="Foto absen">` : ''}
      ${jarak != null ? `<div class="info ${ok ? 'ok' : 'no'}">📏 ${esc(titik)}: jarak ${jarak} m dari titik (radius ${radius} m)</div>` : ''}
      ${ok ? `<div class="info ok" style="animation-delay:.3s"><svg class="chk" viewBox="0 0 60 60"><circle cx="30" cy="30" r="25"/><path d="M18 31l8 8 16-17"/></svg>Absen berhasil · ${esc(jamTxt)}${status === 'telat' ? `<br><span style="color:#b45309">Terlambat ${menit} menit</span>` : ''}</div>`
              : `<div class="info no" style="animation-delay:.3s">❌ ${esc(pesan)}</div>`}
      <button class="btn full" style="margin-top:10px" id="mx">Tutup</button>`);
    m.querySelector('#mx').onclick = () => { m.remove(); home(); };
  }

  $('#btnLihat')?.addEventListener('click', () => {
    const s = boot.sudah; if (!s) return;
    hasil({ img: s.foto_url, titik: s.titik, jarak: s.jarak, radius: s.radius, ok: true, jamTxt: s.jam, status: s.status, menit: s.menit_telat });
  });

  async function start() {
    if (!navigator.mediaDevices?.getUserMedia || !navigator.geolocation) return toast('Browser tidak mendukung kamera/GPS. Gunakan Chrome/Safari terbaru lewat HTTPS.');
    stage.innerHTML = `<h3>Ambil Foto Selfie</h3><p class="mu sm" id="gps">Mencari lokasi GPS…</p>
      <div class="cam" id="cam"><video id="vd" autoplay playsinline muted></video></div>
      <div style="margin-top:14px;display:flex;gap:8px;justify-content:center">
        <button class="btn ghost" id="bBatal">Batal</button><button class="btn" id="bSnap" disabled>📸 Ambil Foto</button></div>`;
    $('#bBatal').onclick = home;
    $('#bSnap').onclick = snap;
    pos = null;
    try { nonce = (await api('/absen/mulai', 'POST')).nonce; } catch (e) { return toast(e.message); }
    watch = navigator.geolocation.watchPosition(p => {
      if (!pos || p.coords.accuracy <= pos.acc + 5) pos = { lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy };
      $('#gps').textContent = `📍 ${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)} (±${Math.round(pos.acc)} m)`;
      ready();
    }, () => { $('#gps').textContent = '⚠️ Izinkan akses lokasi (GPS) untuk absen'; }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 } }, audio: false });
      $('#vd').srcObject = stream; ready();
    } catch (e) { $('#cam').innerHTML = '<p style="color:#fff;padding:30px">Kamera tidak bisa dibuka. Izinkan akses kamera di browser.</p>'; }
  }
  const ready = () => { const b = $('#bSnap'); if (b && pos && stream) b.disabled = false; };

  // Foto HANYA dari frame video kamera langsung (tidak ada input file/galeri)
  function snap() {
    const v = $('#vd'); if (!v.videoWidth || !pos) return;
    const W = 640, H = Math.round(W * v.videoHeight / v.videoWidth);
    const cv = document.createElement('canvas'); cv.width = W; cv.height = H;
    const x = cv.getContext('2d'); x.drawImage(v, 0, 0, W, H);
    const d = nowSrv(), t1 = tgl(d) + ' ' + jam(d), t2 = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
    x.font = 'bold 18px sans-serif';
    const w = Math.max(x.measureText(t1).width, x.measureText(t2).width) + 24;
    x.fillStyle = 'rgba(0,0,0,.6)'; x.fillRect(W - w - 10, 10, w, 56);
    x.fillStyle = '#fff'; x.textAlign = 'right'; x.fillText(t1, W - 22, 34); x.fillText(t2, W - 22, 58);
    shot = { img: cv.toDataURL('image/jpeg', 0.7), lat: pos.lat, lng: pos.lng, acc: pos.acc };
    stop();
    stage.innerHTML = `<h3>Pratinjau</h3><div class="cam"><img src="${shot.img}" alt=""></div>
      <div style="margin-top:14px;display:flex;gap:8px;justify-content:center">
        <button class="btn ghost" id="bUlang">Ulangi</button><button class="btn pulse" id="bSimpan">💾 Simpan Absen</button></div>`;
    $('#bUlang').onclick = start;
    $('#bSimpan').onclick = simpan;
  }

  async function simpan() {
    const b = $('#bSimpan'); b.disabled = true; b.textContent = 'Menyimpan…';
    try {
      const r = await api('/absen', 'POST', { foto: shot.img, lat: shot.lat, lng: shot.lng, akurasi: shot.acc, nonce });
      const s = r.data;
      hasil({ img: shot.img, titik: s.titik, jarak: s.jarak, radius: s.radius, ok: true, jamTxt: s.jam, status: s.status, menit: s.menit_telat });
    } catch (e) {
      const dt = e.data?.detail;
      if (dt) hasil({ img: shot.img, titik: dt.titik, jarak: dt.jarak, radius: dt.radius, ok: false, pesan: 'Di luar radius. Absen ditolak, dekati titik lahan lalu ulangi.' });
      else if (e.status === 409) hasil({ ok: false, pesan: e.message });
      else { toast(e.message); b.disabled = false; b.textContent = '💾 Simpan Absen'; if (e.status === 422) setTimeout(start, 1500); }
    }
  }

  $('#btnStart')?.addEventListener('click', start);
})();
