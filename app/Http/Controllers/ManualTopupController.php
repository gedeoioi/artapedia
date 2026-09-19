<?php

namespace App\Http\Controllers;

use App\Models\ManualTopup;
use App\Services\ManualTopupService;
use Illuminate\Http\Request;

class ManualTopupController extends Controller
{
    public function create(Request $request, ManualTopupService $service)
    {
        $user = $request->user();

        $topups = ManualTopup::with('reviewer')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('member.topup-manual', [
            'banks' => $service->banks(),
            'minimum' => $service->minimumAmount(),
            'enabled' => $service->isEnabled(),
            'topups' => $topups,
        ]);
    }

    public function store(Request $request, ManualTopupService $service)
    {
        // Bukti transfer divalidasi sebagai gambar/PDF dengan batas ukuran,
        // bukan `mimes:` saja — ekstensi bisa dipalsukan.
        $data = $request->validate([
            'amount' => 'required|integer|min:1',
            'bank_name' => 'required|string|max:64',
            'sender_name' => 'nullable|string|max:100',
            'proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ]);

        try {
            $topup = $service->submit(
                $request->user(),
                (int) $data['amount'],
                $data['bank_name'],
                $data['sender_name'] ?? null,
                $request->file('proof'),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['proof' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('manual-topup.create')
            ->with('ok', 'Konfirmasi topup '.$topup->code.' terkirim. Menunggu review admin.');
    }
}
