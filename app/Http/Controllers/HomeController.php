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
            ->available()
            ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
            ->groupBy('game')
            ->orderBy('game')
            ->limit(24)
            ->get();

        $icons = collect();
        foreach ($games as $g) {
            $icon = GameIcon::where('game_name', $g->game)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($g->game)])->where('is_active', true)->first();
            if ($icon) {
                $icons[$g->game] = $icon;
            }
        }
        $popular = Product::available()->orderByDesc('id')->limit(8)->get();
        $gateways = \App\Models\PaymentGatewayConfig::activeOrdered();
        $totalProducts = Product::available()->count();
        $totalGames = Product::available()->distinct()->count('game');

        return view('home', compact('games', 'icons', 'popular', 'q', 'gateways', 'totalProducts', 'totalGames'));
    }

    public function game(string $game)
    {
        $products = Product::where('game', $game)->available()->orderBy('price_guest')->get();
        $icon = GameIcon::where('game_name', $game)->where('is_active', true)->first()
            ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($game)])->where('is_active', true)->first();

        return view('game', compact('products', 'game', 'icon'));
    }
}
