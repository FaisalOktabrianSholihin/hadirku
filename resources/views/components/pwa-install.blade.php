{{-- Tombol "Download Aplikasi" + script pemasangan PWA. Taruh di body (sekali per halaman). --}}
<button type="button" class="pwa-btn" data-pwa-install hidden>
    <x-icon n="download" :size="20" />
    <span class="pwa-t">Download Aplikasi<small>Pasang di layar utama, buka tanpa browser</small></span>
</button>
<script>
    document.documentElement.dataset.sw = @json(asset('sw.js'));
    document.documentElement.dataset.icon = @json(asset('icons/icon-192.png'));
</script>
<script src="{{ asset('js/pwa.js') }}" defer></script>
