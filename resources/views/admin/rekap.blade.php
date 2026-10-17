@extends('layouts.app')
@section('title', 'Rekap · Hadirku')
@section('content')
@include('admin._nav')
<div class="card">
  <div class="stats">
    <div><b>{{ $hadir }}</b><span class="mu">Hadir</span></div>
    <div><b>{{ max(0, $aktif - $hadir) }}</b><span class="mu">Belum absen</span></div>
    <div><b>{{ $aktif }}</b><span class="mu">Karyawan aktif</span></div>
  </div>
  <form class="row-btn" method="GET" style="margin:14px 0">
    <input type="date" name="tanggal" value="{{ $tgl }}" style="width:auto;margin:0">
    <input name="q" value="{{ $q }}" placeholder="Cari nama / NIK" style="width:200px;margin:0">
    <select name="status" style="width:auto;margin:0">
      <option value="">Semua</option>
      <option value="hadir" @selected($status === 'hadir')>Hadir</option>
      <option value="belum" @selected($status === 'belum')>Belum absen</option>
    </select>
    <button class="btn sm">Tampilkan</button>
  </form>
  <div class="tw"><table>
    <thead><tr><th>Foto</th><th>Karyawan</th><th>Jam</th><th>Lahan</th><th>Jarak</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($rows as $x)
      <tr>
        <td>@if($x->absen_id)<a href="{{ route('admin.foto', $x->absen_id) }}" target="_blank"><img class="thumb" loading="lazy" src="{{ route('admin.foto', $x->absen_id) }}" alt=""></a>@endif</td>
        <td><b>{{ $x->nama }}</b><br><span class="mu">{{ $x->jabatan }} · {{ $x->nik }}</span></td>
        <td>{{ $x->jam ? substr($x->jam, 0, 5) : '-' }}</td>
        <td>{{ $x->titik_nama ?? '-' }}</td>
        <td>{{ $x->jarak !== null ? $x->jarak.' m' : '-' }}</td>
        <td>@if(! $x->absen_id)<span class="badge r">Belum absen</span>@elseif($x->status === 'terlambat')<span class="badge y">Terlambat</span>@else<span class="badge">Hadir</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="6" class="mu">Tidak ada data</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="pager">
    @if($rows->previousPageUrl())<a class="btn ghost sm" href="{{ $rows->previousPageUrl() }}">‹ Sebelumnya</a>@else<span></span>@endif
    @if($rows->nextPageUrl())<a class="btn ghost sm" href="{{ $rows->nextPageUrl() }}">Berikutnya ›</a>@endif
  </div>
  <hr style="border:0;border-top:1px solid var(--bd);margin:18px 0">
  <form class="row-btn" method="GET" action="{{ route('admin.rekap.export') }}">
    <span class="mu">Ekspor CSV:</span>
    <input type="date" name="dari" value="{{ $tgl }}" style="width:auto;margin:0">
    <span class="mu">s/d</span>
    <input type="date" name="sampai" value="{{ $tgl }}" style="width:auto;margin:0">
    <button class="btn ghost sm">⬇ Unduh</button>
  </form>
</div>
@endsection
