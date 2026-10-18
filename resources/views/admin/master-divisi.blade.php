@extends('layouts.admin')
@section('title', 'Master Divisi')
@section('subtitle', 'Data divisi dari db_indukk (hanya lihat)')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/master.css') }}">
@endpush
@section('content')
    <div class="card">
        <div class="head">
            <div>
                <h3>Divisi</h3>
                <div class="sub">Sumber: db_indukk. Jumlah karyawan dihitung otomatis.</div>
            </div>
            <div class="row-btn"><input id="cari" placeholder="Cari divisi" style="width:220px"></div>
        </div>
        <div class="tw">
            <table>
                <thead id="th">
                    <tr>
                        <th data-sort="nama">Divisi<i class="so"></i></th>
                        <th data-sort="total">Total Karyawan<i class="so"></i></th>
                        <th data-sort="aktif">Karyawan Aktif<i class="so"></i></th>
                        <th data-sort="terdaftar">Terdaftar Presensi<i class="so"></i></th>
                    </tr>
                </thead>
                <tbody id="tb">
                    <tr>
                        <td colspan="4" class="mu">Memuat…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pager"><span class="mu sm" id="pinfo"></span></div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/master-divisi.js') }}"></script>
@endpush
