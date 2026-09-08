<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\GameIcon;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $gamesQuery = Product::query()
            ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
            ->available()
            ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
            ->groupBy('game')
            ->orderBy('game');

        $totalCategories = (clone $gamesQuery)->get()->count();
        // Grid 5 kolom x 4 baris = 20 kategori. Sisanya via tombol Lihat Selengkapnya.
        $games = (clone $gamesQuery)->limit(20)->get();
        $hasMoreCategories = $totalCategories > 20;

        $icons = collect();
        foreach ($games as $g) {
            $icon = GameIcon::where('game_name', $g->game)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($g->game)])->where('is_active', true)->first();
            if ($icon) {
                $icons[$g->game] = $icon;
            }
        }
        $popular = Product::available()->orderByDesc('id')->limit(8)->get();
        $gateways = PaymentGatewayConfig::activeOrdered();
        $totalProducts = Product::available()->count();
        $totalGames = Product::available()->distinct()->count('game');
        // Kategori terfavorit: 8 game dengan transaksi sukses terbanyak (fallback: produk terbanyak).
        $favGames = Transaction::query()
            ->selectRaw('products.game as game, COUNT(*) as total')
            ->join('products', 'products.id', '=', 'transactions.product_id')
            ->where('transactions.status', Transaction::STATUS_SUCCESS)
            ->groupBy('products.game')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
        if ($favGames->isEmpty()) {
            $favGames = Product::query()
                ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
                ->available()
                ->groupBy('game')
                ->orderByDesc('total')
                ->limit(8)
                ->get();
        }
        $favorites = $favGames;
        foreach ($favorites as $f) {
            $icon = GameIcon::where('game_name', $f->game)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($f->game)])->where('is_active', true)->first();
            if ($icon) {
                $icons[$f->game] = $icon;
            }
        }

        $banners = Banner::activeOrdered();

        return view('home', compact('games', 'icons', 'popular', 'q', 'gateways', 'totalProducts', 'totalGames', 'favorites', 'banners', 'hasMoreCategories'));
    }

    public function categories(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $games = Product::query()
            ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
            ->available()
            ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
            ->groupBy('game')
            ->orderBy('game')
            ->paginate(50);

        $icons = collect();
        foreach ($games as $g) {
            $icon = GameIcon::where('game_name', $g->game)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($g->game)])->where('is_active', true)->first();
            if ($icon) {
                $icons[$g->game] = $icon;
            }
        }

        return view('categories', compact('games', 'icons', 'q'));
    }

    public function game(string $game)
    {
        $products = Product::where('game', $game)->available()->orderBy('price_guest')->get();
        $icon = GameIcon::where('game_name', $game)->where('is_active', true)->first()
            ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($game)])->where('is_active', true)->first();

        return view('game', compact('products', 'game', 'icon'));
    }
}
