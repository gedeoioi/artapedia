<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
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

        $data = $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $transaction->rating()->updateOrCreate(['transaction_id' => $transaction->id], $data + ['user_id' => $request->user()->id]);

        return back()->with('ok', 'Terima kasih atas ratingnya.');
    }
}
