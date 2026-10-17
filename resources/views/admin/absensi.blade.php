@extends('layouts.admin')
@section('title', 'Absensi Harian')
@section('subtitle', 'Siapa yang sudah dan belum absen, lengkap dengan foto')
@section('content')
<div class="card">
  @if($libur)<div class="info" style="background:#fef3c7;color:#92400e">📅 Tanggal ini libur{{ $libur['jenis'] === 'rutin' ? ' rutin' : ': '.$libur['nama'] }}.</div>@endif
  <div class="stats">
    <div><b>{{ $hadir }}</b><span class="mu sm">Sudah absen</span></div>
    <div><b>{{ $telat }}</b><span class="mu sm">Terlambat</span></div>
    <div><b>{{ max(0, $aktif - $hadir) }}</b><span class="mu sm">Belum absen</span></div>
    <div><b>{{ $aktif }}</b><span class="mu sm">Karyawan aktif</span></div>
  </div>
  <form class="filters" method="GET">
    <div><label>Tanggal</label><input type="date" name="tanggal" value="{{ $tgl }}"></div>
    <div><label>Divisi</label><select name="divisi"><option value="">Semua</option>@foreach($daftarDivisi as $dv)<option value="{{ $dv->id }}" @selected($divisi === $dv->id)>{{ $dv->nama_divisi }}</option>@endforeach</select></div>
    <div><label>Status</label><select name="status"><option value="">Semua</option><option value="hadir" @selected($status === 'hadir')>Sudah absen</option><option value="telat" @selected($status === 'telat')>Terlambat</option><option value="belum" @selected($status === 'belum')>Belum absen</option></select></div>
    <div><label>Cari</label><input name="q" value="{{ $q }}" placeholder="Nama / NIK"></div>
    <button class="btn">Tampilkan</button>
  </form>
  <div class="tw"><table>
    <thead><tr><th>Foto</th><th>Karyawan</th><th>Divisi</th><th>Jam</th><th>Lahan</th><th>Jarak</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($rows as $x)
      <tr>
        <td>@if($x->absen_id)<a href="{{ route('admin.foto', $x->absen_id) }}" target="_blank"><img class="thumb" loading="lazy" src="{{ route('admin.foto', $x->absen_id) }}" alt=""></a>@endif</td>
        <td><b>{{ $x->nama }}</b><br><span class="mu sm">{{ $x->nama_jabatan ?? '-' }} · {{ $x->nik }}</span></td>
        <td>{{ $x->nama_divisi ?? '-' }}</td>
        <td>{{ $x->jam ? substr($x->jam, 0, 5) : '-' }}</td>
        <td>{{ $x->titik_nama ?? '-' }}</td>
        <td>{{ $x->jarak !== null ? $x->jarak.' m' : '-' }}</td>
        <td>@if(! $x->absen_id)<span class="badge r">Belum absen</span>@elseif(in_array($x->status, ['telat', 'terlambat']))<span class="badge y">Telat {{ $x->menit_telat }} mnt</span>@else<span class="badge">Hadir</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="7" class="mu">Tidak ada data</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="pager">
    @if($rows->previousPageUrl())<a class="btn ghost sm" href="{{ $rows->previousPageUrl() }}">‹ Sebelumnya</a>@else<span></span>@endif
    @if($rows->nextPageUrl())<a class="btn ghost sm" href="{{ $rows->nextPageUrl() }}">Berikutnya ›</a>@endif
  </div>
  <hr style="border:0;border-top:1px solid var(--bd);margin:18px 0">
  <form class="row-btn" method="GET" action="{{ route('admin.absensi.export') }}">
    <span class="mu sm">Ekspor CSV:</span>
    <input type="date" name="dari" value="{{ $tgl }}"><span class="mu sm">s/d</span><input type="date" name="sampai" value="{{ $tgl }}">
    <button class="btn ghost sm"><x-icon n="download" :size="16" /> Unduh</button>
  </form>
</div>
@endsection
