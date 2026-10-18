@props(['size' => 40])
<img src="{{ asset('logo.svg') }}" width="{{ $size }}" height="{{ $size }}" alt="Hadirku" {{ $attributes->merge(['class' => 'logo']) }}>
