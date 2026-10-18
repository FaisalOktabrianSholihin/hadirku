@extends('layouts.admin')
@section('title', 'Log Aktivitas')
@section('subtitle', 'Jejak aktivitas admin, karyawan, dan peringatan kecurangan')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/log.css') }}">
@endpush
@section('content')
    <div class="card">
        <div class="filters">
            <div><label>Dari tanggal</label><input type="date" id="dari"></div>
            <div><label>Sampai tanggal</label><input type="date" id="sampai"></div>
            <div><label>Modul</label><select id="fmodul" data-placeholder="Semua modul">
                    <option value="">Semua modul</option>
                </select></div>
            <div><label>Level</label><select id="flevel">
                    <option value="">Semua level</option>
                    <option value="info">Info</option>
                    <option value="warning">Peringatan</option>
                    <option value="danger">Kecurangan / Bahaya</option>
                </select></div>
            <div><label>Cari</label><input id="cari" placeholder="Pelaku, deskripsi, IP"></div>
            <button class="btn" id="btnCari"><x-icon n="search" :size="16" /> Tampilkan</button>
        </div>
        <div class="tw">
            <table>
                <thead id="th">
                    <tr>
                        <th data-sort="waktu">Waktu<i class="so"></i></th>
                        <th data-sort="pelaku">Pelaku<i class="so"></i></th>
                        <th data-sort="modul">Modul<i class="so"></i></th>
                        <th data-sort="aksi">Aksi<i class="so"></i></th>
                        <th data-sort="level">Level<i class="so"></i></th>
                        <th>Deskripsi</th>
                        <th data-sort="ip">IP<i class="so"></i></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tb">
                    <tr>
                        <td colspan="8" class="mu">Memuat…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pager"><button class="btn ghost sm" id="prev">‹ Sebelumnya</button><span class="mu sm"
                id="pinfo"></span><button class="btn ghost sm" id="next">Berikutnya ›</button></div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/log.js') }}"></script>
@endpush
