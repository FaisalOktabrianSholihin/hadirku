<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Admin') · Hadirku</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📍</text></svg>">
<link rel="stylesheet" href="{{ asset('css/hadirku.css') }}">
</head>
<body>
@php $u = auth()->user(); $in = strtoupper(mb_substr($u->name, 0, 1)); @endphp
<div class="app">
  <aside class="sb" id="sb">
    <div class="brand-t"><span class="bl"><x-icon n="pincheck" :size="24" /></span><div><b>HADIRKU</b><small>Sistem Presensi Lahan</small></div><span class="ver">v2.0</span></div>
    <nav>
      <a class="ni {{ request()->routeIs('admin.index') ? 'on' : '' }}" href="{{ route('admin.index') }}"><x-icon n="grid" /> Dashboard</a>
      <div class="ns">Presensi</div>
      <a class="ni {{ request()->routeIs('admin.karyawan') ? 'on' : '' }}" href="{{ route('admin.karyawan') }}"><x-icon n="users" /> Data Karyawan</a>
      <a class="ni {{ request()->routeIs('admin.absensi') ? 'on' : '' }}" href="{{ route('admin.absensi') }}"><x-icon n="history" /> Absensi Harian</a>
      <div class="ns">Laporan</div>
      <a class="ni {{ request()->routeIs('admin.report') ? 'on' : '' }}" href="{{ route('admin.report') }}"><x-icon n="file" /> Report</a>
      <div class="ns">Pengaturan</div>
      <a class="ni {{ request()->routeIs('admin.keterangan') ? 'on' : '' }}" href="{{ route('admin.keterangan') }}"><x-icon n="clip" /> Keterangan Absen</a>
      <a class="ni {{ request()->routeIs('admin.libur') ? 'on' : '' }}" href="{{ route('admin.libur') }}"><x-icon n="cal" /> Kalender Libur</a>
    </nav>
    <div class="sbf"><div class="ucard"><span class="av">{{ $in }}</span><div><b>{{ $u->name }}</b><small>Super Admin</small></div></div></div>
  </aside>
  <div class="main">
    <header class="tb">
      <button class="hb" type="button" onclick="document.getElementById('sb').classList.toggle('open')"><x-icon n="menu" /></button>
      <div><h1>@yield('title')</h1><div class="sub">@yield('subtitle')</div></div>
      <div class="um" id="um">
        <button type="button" onclick="document.getElementById('um').classList.toggle('open')"><span class="av">{{ $in }}</span><span><b>{{ $u->name }}</b><small>Semua Divisi</small></span><x-icon n="down" :size="16" /></button>
        <div class="dd"><form method="POST" action="{{ route('admin.logout') }}">@csrf<button><x-icon n="logout" /> Keluar</button></form></div>
      </div>
    </header>
    <main class="ct">@yield('content')</main>
  </div>
</div>
<script>
document.addEventListener('click',e=>{if(!e.target.closest('#um'))document.getElementById('um').classList.remove('open');if(window.innerWidth<=960&&!e.target.closest('#sb')&&!e.target.closest('.hb'))document.getElementById('sb').classList.remove('open')});
</script>
<script src="{{ asset('js/common.js') }}"></script>
@stack('scripts')
</body>
</html>
