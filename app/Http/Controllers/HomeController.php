<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\GameIcon;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\Rating;
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

    /**
     * Jumlah kategori per baris di grid (grid-cols-5 di desktop).
     *
     * Tombol "Tampilkan Lainnya" menambah tepat satu baris sebanyak angka ini,
     * jadi 5x2 = 10 kategori tampil setelah satu klik.
     */
    private const VISIBLE_CATEGORIES = 5;

    /** @var array<string, int>|null Peta urutan kategori, dibaca sekali per request. */
    private ?array $categoryOrderMap = null;

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $activeType = $this->activeType($request);

        $categoryPagesByType = collect();
        $gamesByType = collect();
        $totalKategoriPerTipe = [];

        foreach (self::CATALOG_TYPES as $type) {
            $gamesQuery = Product::query()
                ->selectRaw('game, MIN(price_guest) as min_price, COUNT(*) as total')
                ->available()
                ->where('product_type', $type)
                ->when($q, fn ($w) => $w->where('game', 'like', "%{$q}%"))
                ->groupBy('game');

            // Urutan kategori diatur admin lewat "Urutan tampil" di menu
            // Kategori. Angka lebih kecil tampil lebih dahulu; kategori yang
            // belum diatur (0) diletakkan setelahnya, diurutkan menurut abjad.
            // Peta urutannya dibaca sekali per request supaya tidak ada query
            // per kategori.
            $gamesQuery = $this->applyCategoryOrder($gamesQuery);

            $semuaKategori = $gamesQuery->get();

            // Daftar tetap disimpan sebagai halaman (untuk kompatibilitas dan
            // untuk penghitung "tampilkan lainnya"), tapi view merender satu
            // grid dan menyembunyikan kategori di luar batas tampil.
            $pages = $semuaKategori->chunk(self::VISIBLE_CATEGORIES)->map->values()->values();
            if ($pages->isEmpty()) {
                $pages = collect([collect()]);
            }

            $categoryPagesByType[$type] = $pages;
            $gamesByType[$type] = $pages->first();
            $totalKategoriPerTipe[$type] = $semuaKategori->count();
        }

        // Dipertahankan untuk kompatibilitas view/test yang membaca tipe aktif.
        $games = $gamesByType[$activeType];

        $activeIconsByName = GameIcon::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (GameIcon $icon) => mb_strtolower($icon->game_name));

        $icons = collect();
        foreach ($categoryPagesByType->flatten(2) as $g) {
            $icon = $activeIconsByName->get(mb_strtolower($g->game));
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
            $icon = $activeIconsByName->get(mb_strtolower($f->game));
            if ($icon) {
                $icons[$f->game] = $icon;
            }
        }

        $banners = Banner::activeOrdered();

        // Hanya rating hasil moderasi yang tampil di beranda.
        $reviews = Rating::approved()
            ->with('transaction:id,invoice_code,product_id,nickname,target_user_id')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $reviewSummary = Rating::approved()
            ->selectRaw('COUNT(*) as total, AVG(stars) as average')
            ->first();
        $reviewSummary = [
            'average' => round((float) ($reviewSummary->average ?? 0), 1),
            'total' => (int) ($reviewSummary->total ?? 0),
        ];

        return view('home', compact('games', 'gamesByType', 'categoryPagesByType', 'icons', 'popular', 'q', 'activeType', 'gateways', 'totalProducts', 'totalGames', 'favorites', 'banners', 'reviews', 'reviewSummary', 'totalKategoriPerTipe'))
            ->with('visibleCategories', self::VISIBLE_CATEGORIES);
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
            ->groupBy('game');

        // Jangan tambahkan orderBy('game') sebelum ini: klausa ORDER BY pertama
        // yang menang, sehingga CASE urutan dari admin tidak akan berpengaruh.
        // applyCategoryOrder() sudah memakai nama kategori sebagai pengurut
        // kedua, jadi kategori tanpa urutan tetap abjad.
        $games = $this->applyCategoryOrder($games)
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

        // Hanya rating yang sudah dimoderasi admin yang boleh tampil publik.
        $reviews = Rating::approved()
            ->with('transaction:id,invoice_code,product_id,nickname,target_user_id')
            ->whereHas('transaction.product', fn ($query) => $query->where('game', $game))
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $ratingSummary = $this->ratingSummary($game);

        return view('game', compact('products', 'game', 'icon', 'reviews', 'ratingSummary'));
    }

    public function reviews()
    {
        $reviews = Rating::approved()
            ->with('transaction:id,invoice_code,product_id,nickname,target_user_id')
            ->orderByDesc('id')
            ->paginate(20);

        $summary = Rating::approved()
            ->selectRaw('COUNT(*) as total, AVG(stars) as average')
            ->first();

        return view('reviews', [
            'reviews' => $reviews,
            'average' => round((float) ($summary->average ?? 0), 1),
            'total' => (int) ($summary->total ?? 0),
        ]);
    }

    /**
     * @return array{average: float, total: int}
     */
    private function ratingSummary(?string $game = null): array
    {
        $query = Rating::approved();

        if ($game !== null) {
            $query->whereHas('transaction.product', fn ($q) => $q->where('game', $game));
        }

        $row = $query->selectRaw('COUNT(*) as total, AVG(stars) as average')->first();

        return [
            'average' => round((float) ($row->average ?? 0), 1),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    private function activeType(Request $request): string
    {
        $type = (string) $request->get('type', Product::TYPE_GAME);

        return in_array($type, self::CATALOG_TYPES, true) ? $type : Product::TYPE_GAME;
    }

    /**
     * Peta urutan tampil kategori: nama kategori (huruf kecil) => angka urutan.
     *
     * Dibaca sekali per request. Kategori yang urutannya 0 tidak masuk peta —
     * artinya "belum diatur" dan akan diletakkan setelah kategori yang diatur.
     *
     * @return array<string, int>
     */
    private function categoryOrderMap(): array
    {
        if ($this->categoryOrderMap === null) {
            $this->categoryOrderMap = GameIcon::query()
                ->where('display_order', '>', 0)
                ->pluck('display_order', 'game_name')
                ->mapWithKeys(fn ($order, $name) => [mb_strtolower((string) $name) => (int) $order])
                ->all();
        }

        return $this->categoryOrderMap;
    }

    /**
     * Terapkan urutan kategori yang diatur admin ke sebuah query kategori.
     *
     * Nama kategori dikirim sebagai binding, bukan dirangkai ke dalam SQL, supaya
     * nama yang mengandung tanda kutip tidak merusak query. Kategori yang belum
     * diatur (tidak ada di peta) jatuh ke ELSE dan tetap urut abjad.
     */
    private function applyCategoryOrder($query)
    {
        $map = $this->categoryOrderMap();

        if ($map === []) {
            return $query->orderBy('game');
        }

        $cases = [];
        $bindings = [];

        foreach ($map as $name => $order) {
            $cases[] = 'WHEN LOWER(game) = ? THEN ?';
            $bindings[] = $name;
            $bindings[] = $order;
        }

        $expression = 'CASE '.implode(' ', $cases).' ELSE 999999 END ASC, game ASC';

        return $query->orderByRaw($expression, $bindings);
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
