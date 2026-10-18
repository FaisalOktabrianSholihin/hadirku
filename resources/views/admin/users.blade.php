@extends('layouts.admin')
@section('title', 'User')
@section('subtitle', 'Atur siapa saja yang boleh login ke panel admin')
@section('content')
<div class="card">
  <div class="head">
    <div><h3>Akun Panel Admin</h3><div class="sub">Nonaktifkan akun agar tidak bisa login. Hak akses menu diatur lewat Role. Login karyawan (NIK) diatur dengan saklar Aktif di Presensi › Data Karyawan.</div></div>
    <div class="row-btn">
      <input id="cari" placeholder="Cari nama / email" style="width:200px">
      <button class="btn sm" id="btnAdd"><x-icon n="plus" :size="16" /> Tambah User</button>
    </div>
  </div>
  <div class="tw"><table>
    <thead id="th"><tr>
      <th data-sort="nama">Nama<i class="so"></i></th><th data-sort="email">Email<i class="so"></i></th><th data-sort="role">Role<i class="so"></i></th>
      <th data-sort="login">Login Terakhir<i class="so"></i></th><th data-sort="aktif">Boleh Login<i class="so"></i></th><th></th>
    </tr></thead>
    <tbody id="tb"><tr><td colspan="6" class="mu">Memuat…</td></tr></tbody>
  </table></div>
  <div class="pager"><button class="btn ghost sm" id="prev">‹ Sebelumnya</button><span class="mu sm" id="pinfo"></span><button class="btn ghost sm" id="next">Berikutnya ›</button></div>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/users.js') }}"></script>@endpush
