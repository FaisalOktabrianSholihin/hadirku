<div class="top">
  <div><h2>Panel Admin Hadirku</h2><span class="mu">{{ auth()->user()->name }}</span></div>
  <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn ghost sm">Keluar</button></form>
</div>
<div class="tabs">
  <a class="tab {{ request()->routeIs('admin.index') ? 'on' : '' }}" href="{{ route('admin.index') }}">Data Karyawan</a>
  <a class="tab {{ request()->routeIs('admin.rekap') ? 'on' : '' }}" href="{{ route('admin.rekap') }}">Rekap Absen</a>
</div>
