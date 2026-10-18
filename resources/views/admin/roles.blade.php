@extends('layouts.admin')
@section('title', 'Roles')
@section('subtitle', 'Tentukan menu apa saja yang boleh dibuka setiap role')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/roles.css') }}">
@endpush
@section('content')
    <div class="card">
        <div class="head">
            <div>
                <h3>Daftar Role</h3>
                <div class="sub">Setiap user punya satu role. Role bawaan Super Admin selalu punya akses penuh dan tidak
                    bisa diubah.</div>
            </div>
            <button class="btn sm" id="btnAdd"><x-icon n="plus" :size="16" /> Tambah Role</button>
        </div>
        <div class="rgrid" id="rg">
            <p class="mu">Memuat…</p>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/roles.js') }}"></script>
@endpush
