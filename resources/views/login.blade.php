<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · Hadirku</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/hadirku.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pages/login.css') }}">
</head>

<body>
    <div class="auth">
        <section class="auth-l">
            <div class="auth-f">
                <x-logo :size="56" class="auth-logo" />
                <span class="pill">Hadirku · Presensi Lahan</span>
                <h1>Selamat datang 👋</h1>
                <p class="mu" style="margin:0">Masuk untuk melakukan presensi atau mengelola kehadiran.</p>

                <div class="seg" id="seg">
                    <button type="button" data-t="karyawan"
                        class="{{ $tab === 'karyawan' ? 'on' : '' }}">Karyawan</button>
                    <button type="button" data-t="admin" class="{{ $tab === 'admin' ? 'on' : '' }}">Admin</button>
                </div>

                <form id="f-karyawan" method="POST" action="{{ route('login.post') }}"
                    style="display:{{ $tab === 'karyawan' ? 'block' : 'none' }}">
                    @csrf
                    @if ($tab === 'karyawan' && $errors->any())
                        <div class="info no">{{ $errors->first() }}</div>
                    @endif
                    <label for="nik">NIK</label>
                    <div class="fld"><x-icon n="user" /><input id="nik" name="nik" inputmode="numeric"
                            maxlength="8" pattern="\d{8}" autocomplete="username" placeholder="8 digit NIK"
                            value="{{ old('nik') }}" required>
                    </div>
                    <label for="pw">Password</label>
                    <div class="fld"><x-icon n="lock" /><input id="pw" name="password" type="password"
                            inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="current-password"
                            placeholder="6 digit depan No. KTP" required><span class="eye"
                            data-eye="pw">Lihat</span></div>
                    <button class="btn full pulse" type="submit">Masuk &amp; Absen <x-icon n="arrow" /></button>
                </form>

                <form id="f-admin" method="POST" action="{{ route('admin.login.post') }}"
                    style="display:{{ $tab === 'admin' ? 'block' : 'none' }}">
                    @csrf
                    @if ($tab === 'admin' && $errors->any())
                        <div class="info no">{{ $errors->first() }}</div>
                    @endif
                    <label for="email">Email</label>
                    <div class="fld"><x-icon n="mail" /><input id="email" name="email" type="email"
                            placeholder="nama@perusahaan.co.id" value="{{ old('email') }}" required></div>
                    <label for="pw2">Password</label>
                    <div class="fld"><x-icon n="lock" /><input id="pw2" name="password" type="password"
                            placeholder="••••••••" required><span class="eye" data-eye="pw2">Lihat</span></div>
                    <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin-bottom:14px"><input
                            type="checkbox" name="ingat" value="1"> Ingat saya di perangkat ini</label>
                    <button class="btn full" type="submit">Masuk ke Panel Admin <x-icon n="arrow" /></button>
                </form>

                <p class="mu sm" style="text-align:center;margin-top:26px">Hadirku · © {{ date('Y') }} · Akses
                    diatur oleh administrator</p>
            </div>
        </section>

        <section class="auth-r">
            <div class="brand-t"><span class="bl"><x-logo :size="52" /></span>
                <div><b>HADIRKU</b><small>Sistem Presensi Lahan</small></div>
            </div>
            <div class="ill">
                <div class="float f1"><x-icon n="camera" :size="16" /> Selfie langsung</div>
                <div class="float f2"><x-icon n="target" :size="16" /> Radius 150 m</div>
                <div class="float f3"><x-icon n="clock" :size="16" /> Telat 5 menit</div>
                <div class="float f4"><x-icon n="check" :size="16" /> Hadir</div>
                <div class="phone">
                    <div class="ph-cam">
                        <div class="ph-stamp">16/10/2026 07:52:10<br>-8.219200, 114.369100</div>
                    </div>
                    <div class="ph-row" style="margin-top:14px"></div>
                    <div class="ph-row g"></div>
                    <div class="ph-row" style="width:80%"></div>
                    <div class="ph-ok">✓ Absen berhasil · 07:52</div>
                </div>
            </div>
            <div>
                <h2>Presensi lahan,<br>akurat dan terpercaya.</h2>
                <p>Absen dengan foto selfie langsung dan validasi radius lokasi. Rekap kehadiran per divisi, keterangan
                    fleksibel, dan kalender libur dalam satu tempat.</p>
            </div>
        </section>
    </div>
    <script src="{{ asset('js/common.js') }}"></script>
    <script>
        document.querySelectorAll('#seg button').forEach(b => b.onclick = () => {
            document.querySelectorAll('#seg button').forEach(x => x.classList.toggle('on', x === b));
            document.getElementById('f-karyawan').style.display = b.dataset.t === 'karyawan' ? 'block' : 'none';
            document.getElementById('f-admin').style.display = b.dataset.t === 'admin' ? 'block' : 'none';
        });
        // NIK maksimal 8 digit, password 6 digit: hanya angka (termasuk saat paste)
        [
            ['nik', 8],
            ['pw', 6]
        ].forEach(([id, n]) => {
            const i = document.getElementById(id);
            i.addEventListener('input', () => {
                const v = i.value.replace(/\D/g, '').slice(0, n);
                if (v !== i.value) i.value = v;
            });
        });
        document.querySelectorAll('[data-eye]').forEach(e => e.onclick = () => {
            const i = document.getElementById(e.dataset.eye);
            const s = i.type === 'password';
            i.type = s ? 'text' : 'password';
            e.textContent = s ? 'Sembunyi' : 'Lihat'
        });
    </script>
</body>

</html>
