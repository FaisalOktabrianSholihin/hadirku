@extends('layouts.admin')
@section('title', 'Kalender Libur')
@section('subtitle', 'Libur nasional, cuti bersama, libur perusahaan, dan libur rutin mingguan')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/libur.css') }}">
@endpush
@section('content')
    <div class="libgrid">
        <div class="card">
            <div class="cal-h">
                <button class="btn ghost sm" id="bPrev">‹</button>
                <h3 id="judul">—</h3>
                <button class="btn ghost sm" id="bNext">›</button>
            </div>
            <div class="cal" id="cal"></div>
            <div class="leg">
                <span><i style="background:#ffe4e6"></i>Libur nasional</span><span><i style="background:#ffedd5"></i>Cuti
                    bersama</span>
                <span><i style="background:#dbeafe"></i>Libur perusahaan</span><span><i style="background:#f1f5f2"></i>Libur
                    rutin</span>
            </div>
        </div>
        <div class="card notes">
            <h3>Catatan</h3>
            <div class="sub">Ubah catatan di sini, kalender di kiri ikut berubah warna.</div>
            <div class="dirty" id="dirty">Ada perubahan yang belum disimpan.</div>
            <label style="margin-top:14px">Libur rutin setiap minggu</label>
            <div class="wk" id="wk"></div>
            <label>Catatan libur bulan ini</label>
            <div id="rows" style="margin-top:6px"></div>
            <div class="row-btn" style="margin-top:8px">
                <button class="btn ghost sm" id="bAdd"><x-icon n="plus" :size="16" /> Tambah catatan</button>
                <span style="flex:1"></span>
                <button class="btn" id="bSave">Simpan</button>
            </div>
            <p class="hint" style="margin-top:12px">Klik angka di kalender untuk menambah / memilih catatan pada tanggal
                itu. Hari libur tidak dihitung Alpa dan absen karyawan ditutup.</p>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/libur.js') }}"></script>
@endpush
