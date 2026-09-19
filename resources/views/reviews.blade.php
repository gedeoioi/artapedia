@extends('layouts.shop')

@section('title', 'Review Pembeli')
@section('meta_description', 'Review dan rating pembeli ArtaPedia dari transaksi yang sudah berhasil.')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-extrabold mb-1">Review Pembeli</h1>
        <p class="muted text-sm mb-6">
            @if($total > 0)
                <strong class="accent">{{ number_format($average, 1, ',', '.') }}/5</strong>
                dari {{ $total }} ulasan pembeli yang sudah terverifikasi.
            @else
                Belum ada ulasan yang tayang.
            @endif
        </p>

        @if($reviews->isEmpty())
            <div class="card p-6 text-sm muted">
                Belum ada review. Review hanya bisa dikirim oleh pembeli yang transaksinya sudah berhasil, dan tayang setelah dimoderasi.
            </div>
        @else
            <div class="grid gap-4">
                @foreach($reviews as $review)
                    <article class="card p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold" style="color:#facc15">{{ str_repeat('★', $review->stars) }}<span class="muted">{{ str_repeat('☆', 5 - $review->stars) }}</span></div>
                                <div class="text-xs muted mt-1">
                                    {{ $review->transaction?->product?->name ?? 'Topup saldo' }}
                                    @if($review->transaction?->nickname)
                                        &middot; {{ $review->transaction->nickname }}
                                    @endif
                                </div>
                            </div>
                            <time class="text-xs muted whitespace-nowrap">{{ $review->created_at->format('d/m/Y') }}</time>
                        </div>
                        @if($review->comment)
                            <p class="mt-3 text-sm leading-relaxed">{{ $review->comment }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    </div>
@endsection
