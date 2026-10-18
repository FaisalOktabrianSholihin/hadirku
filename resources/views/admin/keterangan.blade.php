@extends('layouts.admin')
@section('title', 'Keterangan Absen')
@section('subtitle', 'Kode dan jenis keterangan yang muncul di Report')
@section('content')
    <div class="card">
        <div class="head">
            <div>
                <h3>Master Keterangan</h3>
                <div class="sub">Tambah keterangan baru (Izin, Sakit, Cuti, Dinas, dst.). Aktifkan “Tampilkan waktu” bila
                    perlu kolom menit di Report.</div>
            </div>
            <button class="btn sm" id="btnAdd"><x-icon n="plus" :size="16" /> Tambah Keterangan</button>
        </div>
        <div class="tw">
            <table>
                <thead id="th">
                    <tr>
                        <th data-sort="kode">Kode<i class="so"></i></th>
                        <th data-sort="nama">Nama<i class="so"></i></th>
                        <th data-sort="jenis">Jenis<i class="so"></i></th>
                        <th data-sort="menit">Kolom Waktu<i class="so"></i></th>
                        <th data-sort="aktif">Aktif<i class="so"></i></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tb">
                    <tr>
                        <td colspan="6" class="mu">Memuat…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mu sm" style="margin-top:14px"><b>Bawaan sistem</b> (Hadir, Telat, Alpa, Libur) dipakai otomatis oleh
            mesin absen: nama, kode, dan warnanya boleh diubah, tetapi tidak bisa dihapus. Keterangan lain diberikan ke
            karyawan lewat tombol “Input Keterangan” di menu Report.</p>
    </div>
    <div id="modals"></div>
@endsection
@push('scripts')
    <script src="{{ asset('js/keterangan.js') }}"></script>
@endpush
