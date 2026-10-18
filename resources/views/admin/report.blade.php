@extends('layouts.admin')
@section('title', 'Report')
@section('subtitle', 'Rekap kehadiran per divisi pada rentang tanggal')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/report.css') }}">
@endpush
@section('content')
    <div class="card">
        <div class="filters noprint">
            <div><label>Dari tanggal</label><input type="date" id="dari" value="{{ $awal }}"></div>
            <div><label>Sampai tanggal</label><input type="date" id="sampai" value="{{ $akhir }}"></div>
            <div><label>Divisi</label><select id="divisi">
                    <option value="">Semua divisi</option>
                    @foreach ($divisi as $d)
                        <option value="{{ $d->id }}">{{ $d->nama_divisi }}</option>
                    @endforeach
                </select></div>
            <div><label>Cari karyawan</label><input id="q" placeholder="Nama / NIK"></div>
            <button class="btn" id="btnCari"><x-icon n="search" :size="16" /> Tampilkan</button>
        </div>
        <div class="row-btn noprint" style="margin-bottom:14px">
            <span class="mu sm">Cepat:</span>
            <button class="btn ghost sm" data-p="bulan">Bulan ini</button><button class="btn ghost sm" data-p="lalu">Bulan
                lalu</button><button class="btn ghost sm" data-p="7">7 hari</button><button class="btn ghost sm"
                data-p="30">30 hari</button>
            <span style="flex:1"></span>
            <button class="btn ghost sm" id="btnAll">Buka / tutup semua divisi</button>
            <button class="btn ghost sm" id="btnManual"><x-icon n="plus" :size="16" /> Input Keterangan</button>
            <button class="btn ghost sm" id="btnCsv"><x-icon n="download" :size="16" /> CSV</button>
            <button class="btn ghost sm" id="btnPrint"><x-icon n="print" :size="16" /> Cetak</button>
            <button class="btn sm" id="btnXlsx"><x-icon n="download" :size="16" /> Export Excel</button>
        </div>
        <div id="info" class="mu sm" style="margin-bottom:10px"></div>
        <div class="tw">
            <table class="rt" id="rt">
                <tbody>
                    <tr>
                        <td class="mu">Memuat…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="lg" id="lg"></div>
        <p class="mu sm" style="margin:12px 0 0">Alpa dihitung untuk hari kerja (bukan libur) yang tidak ada absen maupun
            keterangan manual. Hari ini baru dihitung alpa jika karyawan punya jam tutup yang sudah lewat.</p>
    </div>
    <div id="modals"></div>
@endsection
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="{{ asset('js/report.js') }}"></script>
@endpush
