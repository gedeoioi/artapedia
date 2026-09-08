@php
    $catalogTabs = [
        \App\Models\Product::TYPE_GAME => 'Top Up Game',
        \App\Models\Product::TYPE_PULSA => 'Pulsa',
        \App\Models\Product::TYPE_DATA => 'Paket Data',
        \App\Models\Product::TYPE_VOUCHER => 'Voucher',
    ];
@endphp

<nav class="catalog-tabs" aria-label="Filter kategori produk">
    @foreach($catalogTabs as $type => $label)
        <a
            href="{{ route($routeName, array_filter(['type' => $type, 'q' => $q ?: null])) }}"
            @class(['is-active' => $activeType === $type])
            @if($activeType === $type) aria-current="page" @endif
        >{{ $label }}</a>
    @endforeach
</nav>
