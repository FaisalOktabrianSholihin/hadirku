<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/hadirku.css') }}">
</head>

<body>
    @php
        $u = auth()->user();
        $in = strtoupper(mb_substr($u->name, 0, 1));
        $ok = fn(string ...$k) => $u->bolehAkses(...$k);
        $grpMaster = request()->routeIs('admin.master.*') || request()->routeIs('admin.users');
    @endphp
    <div class="app">
        <aside class="sb" id="sb">
            <div class="brand-t"><span class="bl"><x-logo :size="44" /></span>
                <div><b>HADIRKU</b><small>Sistem Presensi Lahan</small></div>
            </div>
            <nav>
                @if ($ok('dashboard'))
                    <a class="ni {{ request()->routeIs('admin.index') ? 'on' : '' }}"
                        href="{{ route('admin.index') }}"><x-icon n="grid" /> Dashboard</a>
                @endif

                @if ($ok('presensi.karyawan', 'presensi.absensi'))
                    <div class="ns">Presensi</div>
                @endif
                @if ($ok('presensi.karyawan'))
                    <a class="ni {{ request()->routeIs('admin.karyawan') ? 'on' : '' }}"
                        href="{{ route('admin.karyawan') }}"><x-icon n="users" /> Data Karyawan</a>
                @endif
                @if ($ok('presensi.absensi'))
                    <a class="ni {{ request()->routeIs('admin.absensi') ? 'on' : '' }}"
                        href="{{ route('admin.absensi') }}"><x-icon n="history" /> Absensi Harian</a>
                @endif

                @if ($ok('laporan.report'))
                    <div class="ns">Laporan</div>
                    <a class="ni {{ request()->routeIs('admin.report') ? 'on' : '' }}"
                        href="{{ route('admin.report') }}"><x-icon n="file" /> Report</a>
                @endif

                @if ($ok('master.karyawan', 'master.divisi', 'master.user'))
                    <div class="ns">Data</div>
                    <div class="ng {{ $grpMaster ? 'open' : '' }}">
                        <button class="ni ngt {{ $grpMaster ? 'has' : '' }}" type="button" data-grp
                            aria-expanded="{{ $grpMaster ? 'true' : 'false' }}"><x-icon n="db" /> Data Master <x-icon
                                n="down" :size="16" class="chv" /></button>
                        <div class="ngc">
                            <div>
                                @if ($ok('master.karyawan'))
                                    <a class="ni sub {{ request()->routeIs('admin.master.karyawan') ? 'on' : '' }}"
                                        href="{{ route('admin.master.karyawan') }}">Karyawan</a>
                                @endif
                                @if ($ok('master.divisi'))
                                    <a class="ni sub {{ request()->routeIs('admin.master.divisi') ? 'on' : '' }}"
                                        href="{{ route('admin.master.divisi') }}">Divisi</a>
                                @endif
                                @if ($ok('master.user'))
                                    <a class="ni sub {{ request()->routeIs('admin.users') ? 'on' : '' }}"
                                        href="{{ route('admin.users') }}">User</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($ok('pengaturan.keterangan', 'pengaturan.libur'))
                    <div class="ns">Pengaturan</div>
                @endif
                @if ($ok('pengaturan.keterangan'))
                    <a class="ni {{ request()->routeIs('admin.keterangan') ? 'on' : '' }}"
                        href="{{ route('admin.keterangan') }}"><x-icon n="clip" /> Keterangan Absen</a>
                @endif
                @if ($ok('pengaturan.libur'))
                    <a class="ni {{ request()->routeIs('admin.libur') ? 'on' : '' }}"
                        href="{{ route('admin.libur') }}"><x-icon n="cal" /> Kalender Libur</a>
                @endif

                @if ($ok('sistem.role', 'sistem.log'))
                    <div class="ns">Sistem</div>
                @endif
                @if ($ok('sistem.role'))
                    <a class="ni {{ request()->routeIs('admin.roles') ? 'on' : '' }}"
                        href="{{ route('admin.roles') }}"><x-icon n="shield" /> Roles</a>
                @endif
                @if ($ok('sistem.log'))
                    <a class="ni {{ request()->routeIs('admin.log') ? 'on' : '' }}"
                        href="{{ route('admin.log') }}"><x-icon n="activity" /> Log Aktivitas</a>
                @endif
            </nav>
            <div class="sbf">
                <div class="ucard"><span class="av">{{ $in }}</span>
                    <div><b>{{ $u->name }}</b><small>{{ $u->namaRole() }}</small></div>
                </div>
            </div>
        </aside>
        <div class="main">
            <header class="tb">
                <button class="hb" type="button"
                    onclick="document.getElementById('sb').classList.toggle('open')"><x-icon n="menu" /></button>
                <div>
                    <h1>@yield('title')</h1>
                    <div class="sub">@yield('subtitle')</div>
                </div>
                <div class="um" id="um">
                    <button type="button" onclick="document.getElementById('um').classList.toggle('open')"><span
                            class="av">{{ $in }}</span><span><b>{{ $u->name }}</b><small>{{ $u->namaRole() }}</small></span><x-icon
                            n="down" :size="16" /></button>
                    <div class="dd">
                        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button><x-icon n="logout" />
                                Keluar</button></form>
                    </div>
                </div>
            </header>
            <main class="ct">@yield('content')</main>
        </div>
    </div>
    <script>
        document.addEventListener('click', e => {
            if (!e.target.closest('#um')) document.getElementById('um').classList.remove('open');
            if (window.innerWidth <= 960 && !e.target.closest('#sb') && !e.target.closest('.hb')) document
                .getElementById('sb').classList.remove('open');
            const g = e.target.closest('[data-grp]');
            if (g) {
                const w = g.closest('.ng');
                const o = w.classList.toggle('open');
                g.setAttribute('aria-expanded', o)
            }
        });
    </script>
    <script src="{{ asset('js/common.js') }}"></script>
    <script src="{{ asset('js/combo.js') }}"></script>
    @stack('scripts')
</body>

</html>
