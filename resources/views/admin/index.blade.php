@extends('layouts.admin')
@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan kehadiran dan aktivitas terbaru')
@section('content')
<section class="hero">
  <div>
    <div class="dt" id="dt">—</div>
    <h2>Halo, {{ auth()->user()->name }} 👋</h2>
    <div class="pills"><span>Super Admin</span><span>Semua Divisi</span>@if($libur)<span class="lib">Hari ini libur{{ $libur['jenis'] === 'rutin' ? ' rutin' : ': '.$libur['nama'] }}</span>@endif</div>
  </div>
  <div class="hs">
    <div><b>{{ $stat['karyawan'] }}</b><small>Karyawan</small></div>
    <div><b>{{ $stat['divisi'] }}</b><small>Divisi</small></div>
    <div><b>{{ $stat['titik'] }}</b><small>Titik Lahan</small></div>
  </div>
</section>

<div class="grid4">
  <div class="card sc" style="--c:#16a34a"><span class="ico"><x-icon n="users" :size="22" /></span><div class="l">Terdaftar Presensi</div><div class="n">{{ $stat['terdaftar'] }}</div><div class="d">Karyawan dengan pengaturan aktif</div></div>
  <div class="card sc" style="--c:#14b8a6"><span class="ico"><x-icon n="check" :size="22" /></span><div class="l">Hadir Hari Ini</div><div class="n">{{ $stat['hadir'] }}</div><div class="d">Sudah absen (termasuk telat)</div></div>
  <div class="card sc" style="--c:#f59e0b"><span class="ico"><x-icon n="clock" :size="22" /></span><div class="l">Terlambat</div><div class="n">{{ $stat['telat'] }}</div><div class="d">Absen lewat batas jam</div></div>
  <div class="card sc" style="--c:#e11d48"><span class="ico"><x-icon n="alert" :size="22" /></span><div class="l">Belum Absen</div><div class="n">{{ $stat['belum'] }}</div><div class="d">{{ $libur ? 'Hari libur, tidak dihitung' : 'Perlu tindak lanjut' }}</div></div>
</div>

<div class="grid2">
  <div class="card"><h3>Komposisi Kehadiran</h3><div class="sub">Status karyawan hari ini</div><div id="donut" style="margin-top:12px"></div></div>
  <div class="card"><h3>Absensi 14 Hari Terakhir</h3><div class="sub">Jumlah karyawan yang absen per hari</div><div id="line" style="margin-top:12px"></div></div>
</div>

<div class="card">
  <div class="head"><div><h3>Absen Terbaru Hari Ini</h3><div class="sub">8 absen terakhir yang masuk</div></div><a class="btn ghost sm" href="{{ route('admin.absensi') }}">Lihat semua</a></div>
  <div class="tw"><table>
    <thead><tr><th>Karyawan</th><th>Divisi</th><th>Jam</th><th>Lahan</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($terbaru as $x)
      <tr><td><b>{{ $x->nama }}</b></td><td>{{ $x->nama_divisi ?? '-' }}</td><td>{{ substr($x->jam, 0, 5) }}</td><td>{{ $x->titik_nama }}</td>
      <td>@if(in_array($x->status, ['telat', 'terlambat']))<span class="badge y">Telat {{ $x->menit_telat }} mnt</span>@else<span class="badge">Hadir</span>@endif</td></tr>
    @empty
      <tr><td colspan="5" class="mu">Belum ada absen hari ini.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>
<script type="application/json" id="boot">@json($chart)</script>
@endsection
@push('scripts')<script src="{{ asset('js/dashboard.js') }}"></script>@endpush
