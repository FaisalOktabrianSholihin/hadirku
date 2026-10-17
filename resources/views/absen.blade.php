<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Absen · Hadirku</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📍</text></svg>">
<link rel="stylesheet" href="{{ asset('css/hadirku.css') }}">
</head>
<body>
<div class="emp">
  <div class="etop">
    <div class="brand-t"><span class="bl"><x-icon n="pincheck" :size="22" /></span><div><b>HADIRKU</b><small>Presensi Lahan</small></div></div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost sm"><x-icon n="logout" :size="16" /> Keluar</button></form>
  </div>

  <section class="hero">
    <div class="dt">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</div>
    <div class="clock" id="clk">--:--:--</div>
    <h2>Halo, {{ $k->nama }} 👋</h2>
    <div class="pills"><span>{{ $k->namaJabatan() ?: 'Karyawan' }}</span><span>{{ $k->divisi->nama_divisi ?? '-' }}</span></div>
  </section>

  <div class="card" id="stage" style="margin-top:16px;text-align:center">
    <div class="rows">
      <div><small>Batas masuk</small><b>{{ $p->telat && $p->jam_batas ? $p->jamBatas() : 'Tanpa batas' }}</b></div>
      <div><small>Absen ditutup</small><b>{{ $p->jamTutup() ?? 'Tidak ada' }}</b></div>
      <div><small>Titik lahan</small><b>{{ $jumlahTitik }} titik</b></div>
      <div><small>NIK</small><b>{{ $k->nik }}</b></div>
    </div>
    @if($sudah)
      <div class="info ok">✅ Anda sudah absen hari ini pukul {{ substr($sudah->jam, 0, 5) }}@if(in_array($sudah->status, ['telat', 'terlambat'])) · terlambat {{ $sudah->menit_telat }} menit @endif</div>
      <button class="btn ghost" style="margin-top:8px" id="btnLihat">Lihat bukti absen</button>
    @elseif($libur)
      <div class="info" style="background:#fef3c7;color:#92400e">📅 Hari ini libur{{ $libur['jenis'] === 'rutin' ? ' rutin' : ': '.$libur['nama'] }}. Absen tidak diperlukan.</div>
    @elseif($ditutup)
      <div class="info no">🔒 Absen sudah ditutup pukul {{ $p->jamTutup() }}.</div>
    @else
      <button class="btn lg pulse" id="btnStart"><x-icon n="camera" :size="20" /> Mulai Absen</button>
      <p class="hint" style="margin:12px 0 0">Foto diambil langsung dari kamera. Izinkan akses kamera dan lokasi.</p>
    @endif
  </div>
  <script type="application/json" id="boot">@json($boot)</script>
</div>
<script src="{{ asset('js/absen.js') }}"></script>
</body>
</html>
