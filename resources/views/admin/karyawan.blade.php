@extends('layouts.admin')
@section('title', 'Data Karyawan')
@section('subtitle', 'Atur batas jam dan titik lahan setiap karyawan')
@section('content')
<div class="card">
  <div class="head">
    <div><h3>Pengaturan Presensi Karyawan</h3><div class="sub">Karyawan diambil dari db_indukk. Titik lahan single atau multi tanpa batas jumlah.</div></div>
    <div class="row-btn">
      <select id="fdiv" style="width:190px"><option value="">Semua divisi</option></select>
      <input id="cari" placeholder="Cari nama / NIK" style="width:190px">
      <button class="btn ghost sm" id="btnImport"><x-icon n="upload" :size="16" /> Import Excel</button>
      <button class="btn sm" id="btnTambah"><x-icon n="plus" :size="16" /> Tambah Data</button>
    </div>
  </div>
  <div class="tw"><table>
    <thead><tr><th>Karyawan</th><th>Divisi</th><th>Kunci Jam</th><th>1 Titik</th><th>Multi Titik</th><th>Aktif</th><th></th></tr></thead>
    <tbody id="tb"><tr><td colspan="7" class="mu">Memuat…</td></tr></tbody>
  </table></div>
  <div class="pager"><button class="btn ghost sm" id="prev">‹ Sebelumnya</button><span class="mu sm" id="pinfo"></span><button class="btn ghost sm" id="next">Berikutnya ›</button></div>
</div>
<div id="modals"></div>
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>window.HADIRKU={template:@json(route('admin.template'))}</script>
<script src="{{ asset('js/karyawan.js') }}"></script>
@endpush
