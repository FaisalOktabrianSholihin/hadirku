@extends('layouts.admin')
@section('title', 'Master Karyawan')
@section('subtitle', 'Data karyawan dari db_indukk (hanya lihat)')
@section('content')
<div class="card">
  <div class="head">
    <div><h3>Karyawan</h3><div class="sub">Sumber: db_indukk. Data ini hanya bisa dilihat, perubahan dilakukan di sistem induk.</div></div>
    <div class="row-btn">
      <select id="fdiv" style="width:200px" data-placeholder="Semua divisi"><option value="">Semua divisi</option></select>
      <select id="fst" style="width:150px"><option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select>
      <input id="cari" placeholder="Cari nama / NIK" style="width:190px">
    </div>
  </div>
  <div class="tw"><table>
    <thead id="th"><tr>
      <th data-sort="nik">NIK<i class="so"></i></th><th data-sort="nama">Nama<i class="so"></i></th><th data-sort="jabatan">Jabatan<i class="so"></i></th>
      <th data-sort="divisi">Divisi<i class="so"></i></th><th data-sort="status">Status<i class="so"></i></th><th data-sort="presensi">Presensi<i class="so"></i></th>
    </tr></thead>
    <tbody id="tb"><tr><td colspan="6" class="mu">Memuat…</td></tr></tbody>
  </table></div>
  <div class="pager"><button class="btn ghost sm" id="prev">‹ Sebelumnya</button><span class="mu sm" id="pinfo"></span><button class="btn ghost sm" id="next">Berikutnya ›</button></div>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/master-karyawan.js') }}"></script>@endpush
