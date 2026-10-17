(() => {
  const c = JSON.parse(document.getElementById('boot').textContent);
  const $ = s => document.querySelector(s);

  // Jam & tanggal WIB
  const dt = $('#dt');
  const tick = () => {
    const d = new Date();
    const tgl = d.toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const jam = d.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false }).replace(/\./g, ':');
    dt.textContent = `${tgl} · ${jam} WIB`;
  };
  tick(); setInterval(tick, 1000);

  // Donut
  const tot = c.donut.reduce((a, b) => a + b.nilai, 0);
  const R = 78, C = 2 * Math.PI * R;
  let off = 0, segs = '';
  c.donut.forEach(s => {
    if (!s.nilai) return;
    const len = s.nilai / tot * C;
    segs += `<circle cx="110" cy="110" r="${R}" fill="none" stroke="${s.warna}" stroke-width="30" stroke-dasharray="${len} ${C - len}" stroke-dashoffset="${-off}" transform="rotate(-90 110 110)"><title>${H.esc(s.label)}: ${s.nilai}</title></circle>`;
    off += len;
  });
  $('#donut').innerHTML = `<svg viewBox="0 0 220 220" style="width:100%;max-width:260px;display:block;margin:0 auto">
    <circle cx="110" cy="110" r="${R}" fill="none" stroke="#eef4f0" stroke-width="30"/>${segs}
    <text x="110" y="108" text-anchor="middle" font-size="32" font-weight="800" fill="#0f1f17">${tot}</text>
    <text x="110" y="130" text-anchor="middle" font-size="12" fill="#64796d">karyawan</text></svg>
    <div class="legend">${c.donut.map(s => `<span><i style="background:${s.warna}"></i>${H.esc(s.label)} (${s.nilai})</span>`).join('')}</div>`;

  // Grafik garis (area) 14 hari
  const S = c.seri, W = 640, Ht = 260, L = 38, Rr = 14, T = 14, B = 34;
  const max = Math.max(4, ...S.map(x => x.hadir));
  const top = Math.ceil(max / 4) * 4;
  const x = i => L + i * (W - L - Rr) / (S.length - 1), y = v => T + (1 - v / top) * (Ht - T - B);
  const pts = S.map((p, i) => [x(i), y(p.hadir)]), pts2 = S.map((p, i) => [x(i), y(p.telat)]);
  const path = a => a.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
  let grid = '';
  for (let i = 0; i <= 4; i++) { const v = top / 4 * i, yy = y(v); grid += `<line x1="${L}" x2="${W - Rr}" y1="${yy}" y2="${yy}" stroke="#e7efe9"/><text x="${L - 8}" y="${yy + 4}" text-anchor="end" font-size="11" fill="#64796d">${v}</text>`; }
  const lbl = S.map((p, i) => i % 2 === 0 ? `<text x="${x(i)}" y="${Ht - 10}" text-anchor="middle" font-size="10.5" fill="#64796d">${p.tgl}</text>` : '').join('');
  $('#line').innerHTML = `<svg viewBox="0 0 ${W} ${Ht}" style="width:100%;height:auto;display:block">
    <defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#16a34a" stop-opacity=".28"/><stop offset="1" stop-color="#16a34a" stop-opacity="0"/></linearGradient></defs>
    ${grid}${lbl}
    <path d="${path(pts)} L${x(S.length - 1)} ${y(0)} L${x(0)} ${y(0)} Z" fill="url(#ag)"/>
    <path d="${path(pts)}" fill="none" stroke="#16a34a" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" style="stroke-dasharray:1400;stroke-dashoffset:1400;animation:dr 1.4s .2s forwards"/>
    <path d="${path(pts2)}" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="5 5"/>
    ${pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="4.5" fill="#fff" stroke="#16a34a" stroke-width="2.5"><title>${S[i].tgl}: ${S[i].hadir} absen, ${S[i].telat} telat</title></circle>`).join('')}</svg>
    <div class="legend"><span><i style="background:#16a34a"></i>Absen masuk</span><span><i style="background:#f59e0b"></i>Telat</span></div>`;
})();
