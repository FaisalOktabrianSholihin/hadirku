(() => {
  const { api, esc, toast, modal, pad, ymd, tglIndo, download } = H;
  const $ = s => document.querySelector(s);
  let D = null, openAll = false;
  const open = new Set();

  const cell = v => v ? `<b>${v}</b>` : '<span class="z">–</span>';

  async function load() {
    const p = new URLSearchParams({ dari: $('#dari').value, sampai: $('#sampai').value, divisi: $('#divisi').value, q: $('#q').value.trim() });
    $('#rt').innerHTML = '<tbody><tr><td class="mu">Memuat…</td></tr></tbody>';
    try { D = await api('/admin/api/report?' + p); render(); } catch (e) { toast(e.message, 1); $('#rt').innerHTML = `<tbody><tr><td class="mu">${esc(e.message)}</td></tr></tbody>`; }
  }

  function render() {
    const k = D.kolom, w = k.filter(c => c.menit);
    $('#info').innerHTML = `Periode <b>${tglIndo(D.dari)}</b> s/d <b>${tglIndo(D.sampai)}</b> · ${D.hari_kerja} hari kerja · ${D.hari_libur} hari libur · ${D.jumlah_karyawan} karyawan`;
    const chip = c => `<span class="chip" style="background:${esc(c.warna)}" title="${esc(c.nama)}">${esc(c.kode)}</span>`;
    let h = `<thead><tr class="grp"><th rowspan="2" style="text-align:left;background:#f7faf8;color:var(--mu)">Karyawan</th><th colspan="${k.length}">Keterangan (jumlah hari)</th>${w.length ? `<th colspan="${w.length}">Waktu</th>` : ''}</tr>
      <tr>${k.map(c => `<th class="c">${chip(c)}</th>`).join('')}${w.map(c => `<th class="c">${esc(c.nama)} (mnt)</th>`).join('')}</tr></thead><tbody>`;
    const tot = {}, totM = {};
    D.divisi.forEach(dv => {
      const op = openAll || open.has(dv.id);
      h += `<tr class="dv ${op ? 'open' : ''}" data-d="${dv.id}"><td><svg class="ic chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>${esc(dv.nama)} <span class="badge g">${dv.jumlah} orang</span></td>
        ${k.map(c => `<td class="c">${cell(dv.hitung[c.id])}</td>`).join('')}${w.map(c => `<td class="c">${cell(dv.menit[c.id])}</td>`).join('')}</tr>`;
      dv.karyawan.forEach(u => {
        h += `<tr class="us" data-p="${dv.id}" style="display:${op ? 'table-row' : 'none'}"><td><b>${esc(u.nama)}</b><br><span class="mu sm">${esc(u.jabatan)} · ${esc(u.nik)}</span></td>
          ${k.map(c => `<td class="c">${cell(u.hitung[c.id])}</td>`).join('')}${w.map(c => `<td class="c">${cell(u.menit[c.id])}</td>`).join('')}</tr>`;
      });
      k.forEach(c => { tot[c.id] = (tot[c.id] || 0) + (dv.hitung[c.id] || 0); totM[c.id] = (totM[c.id] || 0) + (dv.menit[c.id] || 0); });
    });
    if (!D.divisi.length) h += `<tr><td colspan="${1 + k.length + w.length}" class="mu">Tidak ada data pada filter ini.</td></tr>`;
    else h += `<tr class="tot"><td>TOTAL SEMUA DIVISI</td>${k.map(c => `<td class="c">${tot[c.id] || 0}</td>`).join('')}${w.map(c => `<td class="c">${totM[c.id] || 0}</td>`).join('')}</tr>`;
    $('#rt').innerHTML = h + '</tbody>';
    $('#lg').innerHTML = '<b>Keterangan:</b> ' + k.map(c => `<span>${chip(c)} = ${esc(c.nama)}${c.menit ? ' (waktu dalam menit)' : ''}</span>`).join('');
  }

  $('#rt').addEventListener('click', e => {
    const tr = e.target.closest('tr.dv'); if (!tr) return;
    const id = +tr.dataset.d, on = !tr.classList.contains('open');
    tr.classList.toggle('open', on); on ? open.add(id) : open.delete(id);
    document.querySelectorAll(`tr.us[data-p="${id}"]`).forEach(r => r.style.display = on ? 'table-row' : 'none');
  });
  $('#btnAll').onclick = () => { openAll = !openAll; if (!openAll) open.clear(); render(); };
  $('#btnCari').onclick = load;
  $('#q').addEventListener('keydown', e => { if (e.key === 'Enter') load(); });
  document.querySelectorAll('[data-p]').forEach(b => { if (b.tagName !== 'BUTTON') return; b.onclick = () => {
    const n = new Date(), p = b.dataset.p;
    if (p === 'bulan') { $('#dari').value = ymd(new Date(n.getFullYear(), n.getMonth(), 1)); $('#sampai').value = ymd(n); }
    else if (p === 'lalu') { $('#dari').value = ymd(new Date(n.getFullYear(), n.getMonth() - 1, 1)); $('#sampai').value = ymd(new Date(n.getFullYear(), n.getMonth(), 0)); }
    else { $('#dari').value = ymd(new Date(n.getTime() - (+p - 1) * 864e5)); $('#sampai').value = ymd(n); }
    load();
  }; });
  $('#btnPrint').onclick = () => { openAll = true; render(); window.print(); };

  // ===== Export =====
  function sheets() {
    const k = D.kolom, w = k.filter(c => c.menit);
    const head = [...k.map(c => `${c.kode} (${c.nama})`), ...w.map(c => `${c.nama} (menit)`)];
    const vals = o => [...k.map(c => o.hitung[c.id] || 0), ...w.map(c => o.menit[c.id] || 0)];
    const rekap = [['Divisi', 'Jumlah Karyawan', ...head], ...D.divisi.map(d => [d.nama, d.jumlah, ...vals(d)])];
    const detail = [['Divisi', 'NIK', 'Nama', 'Jabatan', ...head]];
    D.divisi.forEach(d => d.karyawan.forEach(u => detail.push([d.nama, String(u.nik), u.nama, u.jabatan, ...vals(u)])));
    const ket = [['Kode', 'Keterangan', 'Satuan Waktu'], ...k.map(c => [c.kode, c.nama, c.menit ? 'menit' : '-'])];
    return { rekap, detail, ket, jml: k.length + w.length };
  }
  function wb() {
    const s = sheets(), b = XLSX.utils.book_new();
    const mk = (aoa, cols) => { const ws = XLSX.utils.aoa_to_sheet(aoa); ws['!cols'] = cols; return ws; };
    const w = Array(s.jml).fill({ wch: 13 });
    XLSX.utils.book_append_sheet(b, mk([[`Report Kehadiran ${D.dari} s/d ${D.sampai}`], [], ...s.rekap], [{ wch: 34 }, { wch: 16 }, ...w]), 'Rekap Divisi');
    XLSX.utils.book_append_sheet(b, mk(s.detail, [{ wch: 34 }, { wch: 12 }, { wch: 30 }, { wch: 30 }, ...w]), 'Detail Karyawan');
    XLSX.utils.book_append_sheet(b, mk(s.ket, [{ wch: 8 }, { wch: 20 }, { wch: 14 }]), 'Keterangan');
    return b;
  }
  const guard = () => { if (!D || !D.divisi.length) { toast('Tidak ada data untuk diexport', 1); return false; } return true; };
  $('#btnXlsx').onclick = () => { if (guard()) XLSX.writeFile(wb(), `report_kehadiran_${D.dari}_${D.sampai}.xlsx`); };
  $('#btnCsv').onclick = () => {
    if (!guard()) return;
    const csv = XLSX.utils.sheet_to_csv(XLSX.utils.aoa_to_sheet(sheets().detail));
    download(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }), `report_kehadiran_${D.dari}_${D.sampai}.csv`);
  };

  // ===== Input keterangan manual =====
  $('#btnManual').onclick = async () => {
    let ket;
    try { ket = (await api('/admin/api/keterangan')).filter(x => x.aktif && x.sistem !== 'libur'); } catch (e) { return toast(e.message, 1); }
    const pilih = new Map();
    const m = modal(`<h3>Input Keterangan Manual</h3><div class="sub" style="margin-bottom:14px">Untuk Izin, Sakit, Cuti, Dinas, atau koreksi kehadiran. Hari libur otomatis dilewati.</div>
      <label>Karyawan</label><input id="mq" placeholder="Cari nama / NIK lalu klik hasilnya…"><div id="mr" class="tw" style="max-height:150px;overflow:auto"></div>
      <div id="mc" class="row-btn" style="margin:8px 0 12px"></div>
      <div class="row-btn" style="align-items:flex-start"><div style="flex:1"><label>Dari tanggal</label><input type="date" id="md1" value="${$('#dari').value}" style="width:100%"></div><div style="flex:1"><label>Sampai tanggal</label><input type="date" id="md2" value="${$('#dari').value}" style="width:100%"></div></div>
      <label>Keterangan</label><select id="mk">${ket.map(x => `<option value="${x.id}" data-m="${x.pakai_menit ? 1 : 0}">${esc(x.kode)} — ${esc(x.nama)}</option>`).join('')}<option value="0">— Hapus keterangan manual pada rentang ini —</option></select>
      <div id="mm" style="display:none"><label>Lama (menit)</label><input type="number" id="mmn" min="0" max="1440" placeholder="contoh: 30"></div>
      <label>Catatan (opsional)</label><input id="mt" maxlength="255" placeholder="mis. surat dokter, SPD no. …">
      <div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn ghost" id="bx">Batal</button><button class="btn" id="bs">Simpan</button></div>`);
    const draw = () => { m.querySelector('#mc').innerHTML = [...pilih].map(([n, x]) => `<span class="badge">${esc(x)} <a href="#" data-r="${esc(n)}" style="color:inherit;text-decoration:none">✕</a></span>`).join('') || '<span class="mu sm">Belum ada karyawan dipilih</span>'; };
    draw();
    let tq; m.querySelector('#mq').oninput = e => { clearTimeout(tq); tq = setTimeout(async () => {
      const v = e.target.value.trim(); if (!v) return m.querySelector('#mr').innerHTML = '';
      try { const r = await api('/admin/api/cari-karyawan?q=' + encodeURIComponent(v));
        m.querySelector('#mr').innerHTML = r.map(x => `<div class="badge g" style="display:flex;margin:3px 0;padding:8px 10px;cursor:pointer;border-radius:10px" data-a="${esc(x.nik)}" data-n="${esc(x.nama)}">${esc(x.nama)} · ${esc(x.divisi || '-')} · ${esc(x.nik)}</div>`).join('') || '<span class="mu sm">Tidak ditemukan</span>'; } catch (er) { toast(er.message, 1); } }, 250); };
    m.addEventListener('click', e => {
      const a = e.target.closest('[data-a]'); if (a) { pilih.set(a.dataset.a, a.dataset.n); draw(); }
      const r = e.target.closest('[data-r]'); if (r) { e.preventDefault(); pilih.delete(r.dataset.r); draw(); }
    });
    const mk = m.querySelector('#mk'), sync = () => m.querySelector('#mm').style.display = mk.selectedOptions[0].dataset.m === '1' ? '' : 'none'; mk.onchange = sync; sync();
    m.querySelector('#bx').onclick = () => m.remove();
    m.querySelector('#bs').onclick = async () => {
      if (!pilih.size) return toast('Pilih minimal 1 karyawan', 1);
      const b = m.querySelector('#bs'); b.disabled = true;
      try {
        const r = await api('/admin/api/manual', 'POST', { niks: [...pilih.keys()], dari: m.querySelector('#md1').value, sampai: m.querySelector('#md2').value,
          keterangan_id: +mk.value, menit: m.querySelector('#mmn').value || null, catatan: m.querySelector('#mt').value || null });
        toast(r.pesan); m.remove(); load();
      } catch (e) { toast(e.message, 1); b.disabled = false; }
    };
  };
  load();
})();
