<?php

namespace App\Http\Controllers;

use App\Models\GameIcon;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $games = Product::query()
            ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
            ->where('is_active', true)
            ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
            ->groupBy('game')
            ->orderBy('game')
            ->limit(24)
            ->get();

        $icons = GameIcon::whereIn('game_name', $games->pluck('game'))->get()->keyBy('game_name');
        // Fallback case-insensitive: "MOBILE LEGENDS" (produk) vs "Mobile Legends" (icon).
        if ($icons->count() < $games->count()) {
            $extra = GameIcon::whereNotIn('game_name', $games->pluck('game'))->get();
            foreach ($extra as $icon) {
                foreach ($games as $g) {
                    if (! isset($icons[$g->game]) && mb_strtolower($g->game) === mb_strtolower($icon->game_name)) {
                        $icons[$g->game] = $icon;
                    }
                }
            }
        }
        $popular = Product::where('is_active', true)->orderByDesc('id')->limit(8)->get();
        $gateways = \App\Models\PaymentGatewayConfig::activeOrdered();
        $totalProducts = Product::where('is_active', true)->count();
        $totalGames = Product::where('is_active', true)->distinct()->count('game');

        return view('home', compact('games', 'icons', 'popular', 'q', 'gateways', 'totalProducts', 'totalGames'));
    }

    public function game(string $game)
    {
        $products = Product::where('game', $game)->where('is_active', true)->orderBy('price_guest')->get();
        $icon = GameIcon::where('game_name', $game)->first();

        return view('game', compact('products', 'game', 'icon'));
    }
}
