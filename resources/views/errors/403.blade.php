@extends('layouts.app')
@section('title', 'Tidak ada akses')
@section('content')
<div class="card" style="max-width:460px;margin:12vh auto 0;text-align:center">
  <div class="dlg-ic bad" style="margin:0 auto 14px"><x-icon n="shield" :size="30" /></div>
  <h3>Anda tidak punya akses</h3>
  <p class="mu" style="margin:8px 0 18px">Role akun Anda belum diizinkan membuka halaman ini. Hubungi Super Admin bila perlu akses tambahan.</p>
  @if(!empty($awal))<a class="btn" href="{{ $awal }}">Kembali ke halaman yang boleh dibuka</a>@endif
</div>
@endsection
