<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Transaction;
use App\Support\ProfanityFilter;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $transactions = Transaction::with('product')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('member.dashboard', compact('user', 'transactions'));
    }

    public function rate(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        abort_unless($transaction->status === Transaction::STATUS_SUCCESS, 422);

        // Satu rating per invoice, selamanya. Cek di sini menutup pesan yang
        // lebih ramah; unique index di DB adalah penjaga sebenarnya untuk
        // request yang benar-benar bersamaan.
        if ($transaction->rating()->exists()) {
            return back()->withErrors(['stars' => 'Invoice ini sudah pernah dinilai.']);
        }

        $data = $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $comment = trim((string) ($data['comment'] ?? ''));
        $flagged = $comment !== '' ? ProfanityFilter::matches($comment) : [];

        // Semua rating masuk antrean moderasi; komentar dengan kata kasar tetap
        // disimpan (tidak dibuang) dan diberi catatan supaya admin yang
        // memutuskan, bukan filter otomatis.
        $transaction->rating()->create([
            'user_id' => $request->user()->id,
            'stars' => $data['stars'],
            'comment' => $comment ?: null,
            'status' => Rating::STATUS_PENDING,
            'moderation_note' => $flagged === []
                ? null
                : 'Terdeteksi kata tidak layak: '.implode(', ', $flagged),
        ]);

        return back()->with('ok', 'Terima kasih atas ratingnya. Rating akan tampil setelah dimoderasi.');
    }
}
