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
    private const CATALOG_TYPES = [
        Product::TYPE_GAME,
        Product::TYPE_PULSA,
        Product::TYPE_DATA,
        Product::TYPE_VOUCHER,
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $activeType = $this->activeType($request);

        $gamesByType = collect();
        $hasMoreCategoriesByType = collect();

        foreach (self::CATALOG_TYPES as $type) {
            $gamesQuery = Product::query()
                ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
                ->available()
                ->where('product_type', $type)
                ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
                ->groupBy('game')
                ->orderBy('game');

            // Grid 5 kolom x 4 baris = 20 kategori per tipe.
            $categories = (clone $gamesQuery)->limit(21)->get();
            $hasMoreCategoriesByType[$type] = $categories->count() > 20;
            $gamesByType[$type] = $categories->take(20)->values();
        }

        // Dipertahankan untuk kompatibilitas view/test yang membaca tipe aktif.
        $games = $gamesByType[$activeType];
        $hasMoreCategories = $hasMoreCategoriesByType[$activeType];

        $icons = collect();
        foreach ($gamesByType->flatten(1) as $g) {
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
        $favGames = $this->configuredFavorites();

        // Jika admin belum memilih favorit, gunakan kategori dengan transaksi sukses
        // terbanyak dan terakhir jumlah produk sebagai fallback.
        if ($favGames->isEmpty()) {
            $favGames = Transaction::query()
                ->selectRaw('products.game as game, COUNT(*) as total, COUNT(DISTINCT products.id) as product_count, MIN(products.price_guest) as min_price')
                ->join('products', 'products.id', '=', 'transactions.product_id')
                ->where('transactions.status', Transaction::STATUS_SUCCESS)
                ->groupBy('products.game')
                ->orderByDesc('total')
                ->limit(8)
                ->get();
        }
        if ($favGames->isEmpty()) {
            $favGames = Product::query()
                ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total, COUNT(*) as product_count')
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

        return view('home', compact('games', 'gamesByType', 'icons', 'popular', 'q', 'activeType', 'gateways', 'totalProducts', 'totalGames', 'favorites', 'banners', 'hasMoreCategories', 'hasMoreCategoriesByType'));
    }

    public function categories(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $activeType = $this->activeType($request);

        $games = Product::query()
            ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
            ->available()
            ->where('product_type', $activeType)
            ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
            ->groupBy('game')
            ->orderBy('game')
            ->paginate(50)
            ->withQueryString();

        $icons = collect();
        foreach ($games as $g) {
            $icon = GameIcon::where('game_name', $g->game)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($g->game)])->where('is_active', true)->first();
            if ($icon) {
                $icons[$g->game] = $icon;
            }
        }

        return view('categories', compact('games', 'icons', 'q', 'activeType'));
    }

    public function game(string $game)
    {
        $products = Product::where('game', $game)->available()->orderBy('price_guest')->get();
        $icon = GameIcon::where('game_name', $game)->where('is_active', true)->first()
            ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($game)])->where('is_active', true)->first();

        return view('game', compact('products', 'game', 'icon'));
    }

    private function activeType(Request $request): string
    {
        $type = (string) $request->get('type', Product::TYPE_GAME);

        return in_array($type, self::CATALOG_TYPES, true) ? $type : Product::TYPE_GAME;
    }

    private function configuredFavorites()
    {
        return GameIcon::query()
            ->where('is_active', true)
            ->where('is_favorite', true)
            ->orderBy('favorite_order')
            ->orderBy('game_name')
            ->limit(8)
            ->get()
            ->map(function (GameIcon $category) {
                $stats = Product::query()
                    ->available()
                    ->where('game', $category->game_name)
                    ->selectRaw('MIN(price_guest) as min_price, COUNT(*) as total')
                    ->first();

                if (! $stats || (int) $stats->total === 0) {
                    return null;
                }

                return (object) [
                    'game' => $category->game_name,
                    'min_price' => (int) $stats->min_price,
                    'total' => (int) $stats->total,
                    'product_count' => (int) $stats->total,
                ];
            })
            ->filter()
            ->values();
    }
}
