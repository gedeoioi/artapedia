@extends('layouts.shop')

@section('title', 'Topup Saldo Manual')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-extrabold mb-1">Topup Saldo Manual</h1>
        <p class="muted text-sm mb-6">Transfer ke rekening di bawah, lalu unggah bukti transfer. Saldo masuk setelah admin menyetujui.</p>

        @if(session('ok'))
            <div class="card p-4 mb-5 text-sm" style="border-color:#22c55e">{{ session('ok') }}</div>
        @endif

        @if(! $enabled)
            <div class="card p-5 text-sm">
                <strong>Topup manual sedang tidak tersedia.</strong>
                <p class="muted mt-1">Silakan gunakan <a class="accent underline" href="{{ route('topup.create') }}">topup otomatis</a> yang masuk seketika.</p>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                <div class="card p-5">
                    <h2 class="font-bold mb-3">1. Rekening tujuan</h2>
                    @forelse($banks as $bank)
                        <div class="mb-3 pb-3" style="border-bottom:1px solid #26262b">
                            <div class="font-bold accent">{{ $bank['name'] }}</div>
                            <div class="text-lg font-extrabold tracking-wide">{{ $bank['account_number'] }}</div>
                            <div class="muted text-xs">a/n {{ $bank['account_name'] ?: '-' }}</div>
                        </div>
                    @empty
                        <p class="muted text-sm">Admin belum mengisi rekening tujuan. Hubungi dukungan.</p>
                    @endforelse
                    <p class="muted text-xs mt-2">Minimum topup: <strong>Rp {{ number_format($minimum, 0, ',', '.') }}</strong></p>
                </div>

                <div class="card p-5">
                    <h2 class="font-bold mb-3">2. Unggah bukti transfer</h2>

                    @if($errors->any())
                        <div class="card p-3 mb-3 text-sm" style="border-color:#ef4444">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('manual-topup.store') }}" enctype="multipart/form-data" class="grid gap-3">
                        @csrf
                        <label class="grid gap-1">
                            <span class="text-xs muted">Nominal transfer</span>
                            <input type="number" name="amount" min="{{ $minimum }}" value="{{ old('amount', $minimum) }}"
                                   class="rounded px-3 py-2" style="background:#1c1c21;border:1px solid #33333a;color:inherit" required>
                        </label>

                        <label class="grid gap-1">
                            <span class="text-xs muted">Bank tujuan</span>
                            <select name="bank_name" class="rounded px-3 py-2" style="background:#1c1c21;border:1px solid #33333a;color:inherit" required>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank['name'] }}" @selected(old('bank_name') === $bank['name'])>{{ $bank['name'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1">
                            <span class="text-xs muted">Nama pengirim (opsional)</span>
                            <input type="text" name="sender_name" value="{{ old('sender_name') }}" maxlength="100"
                                   class="rounded px-3 py-2" style="background:#1c1c21;border:1px solid #33333a;color:inherit">
                        </label>

                        <label class="grid gap-1">
                            <span class="text-xs muted">Bukti transfer (JPG/PNG/WebP/PDF, maks 4 MB)</span>
                            <input type="file" name="proof" accept="image/*,application/pdf" required>
                        </label>

                        <button type="submit" class="btn-primary px-4 py-2 mt-1">Kirim konfirmasi</button>
                    </form>
                </div>
            </div>

            <div class="card p-5 mt-6">
                <h2 class="font-bold mb-3">Riwayat topup manual</h2>
                @if($topups->isEmpty())
                    <p class="muted text-sm">Belum ada pengajuan.</p>
                @else
                    <table class="bordered text-sm">
                        <thead>
                            <tr><th>Kode</th><th>Nominal</th><th>Bank</th><th>Status</th><th>Catatan admin</th><th>Waktu</th></tr>
                        </thead>
                        <tbody>
                            @foreach($topups as $topup)
                                <tr>
                                    <td class="font-mono text-xs">{{ $topup->code }}</td>
                                    <td>Rp {{ number_format($topup->amount, 0, ',', '.') }}</td>
                                    <td>{{ $topup->bank_name }}</td>
                                    <td>
                                        <span class="px-2 py-1 rounded text-xs font-bold {{ $topup->status === 'approved' ? 'badge-ok' : ($topup->status === 'rejected' ? 'badge-fail' : 'badge-pending') }}">
                                            {{ $topup->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="text-xs muted">{{ $topup->review_note ?: '-' }}</td>
                                    <td class="text-xs muted">{{ $topup->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
@endsection
