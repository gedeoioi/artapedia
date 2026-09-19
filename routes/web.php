<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ManualTopupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierWebhookController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\WebhookController;
use App\Models\Product;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Support\AdminRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/review', [HomeController::class, 'reviews'])->name('reviews.index');
Route::get('/kategori', [HomeController::class, 'categories'])->name('categories.index');
Route::get('/game/{game}', [HomeController::class, 'game'])->name('game.show');
Route::get('/terms-and-conditions', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/refund-policy', [LegalController::class, 'refund'])->name('legal.refund');
Route::get('/faq', [LegalController::class, 'faq'])->name('support.faq');
Route::get('/kontak', [LegalController::class, 'contact'])->name('support.contact');

Route::get('/product/{product}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:checkout')->name('checkout.quote');
Route::post('/checkout/check-nickname', [CheckoutController::class, 'checkNickname'])->middleware('throttle:nickname-check')->name('checkout.nickname');

Route::get('/pay/{invoice}', [PaymentController::class, 'show'])->name('payment.show');
Route::get('/pay/{invoice}/status', [PaymentController::class, 'status'])->name('payment.status');

Route::get('/cek-invoice', [InvoiceController::class, 'index'])->name('invoice.index');
Route::get('/cek-invoice/hasil', [InvoiceController::class, 'show'])->middleware('throttle:invoice-lookup')->name('invoice.show');
Route::get('/api/invoice/{code}', [InvoiceController::class, 'api'])->middleware('throttle:invoice-lookup')->name('invoice.api');

Route::post('/webhook/payment/{gateway}', [WebhookController::class, 'gateway'])->middleware('throttle:webhook')->name('webhook.payment');
Route::post('/webhook/supplier/digiflazz', [SupplierWebhookController::class, 'digiflazz'])->middleware('throttle:webhook')->name('webhook.supplier.digiflazz');
Route::post('/webhook/supplier/vip-reseller', [SupplierWebhookController::class, 'vipReseller'])->middleware('throttle:webhook')->name('webhook.supplier.vip-reseller');
Route::post('/webhook/supplier/toko-voucher', [SupplierWebhookController::class, 'tokoVoucher'])->middleware('throttle:webhook')->name('webhook.supplier.toko-voucher');

Route::get('/sitemap.xml', function () {
    $products = Product::available()->orderBy('game')->limit(1000)->get();

    return response()->view('sitemap', compact('products'))->header('Content-Type', 'text/xml');
})->name('sitemap');

// Halaman cetak laporan. Sengaja di luar panel Filament supaya dokumennya
// berdiri sendiri (tanpa layout panel) dan bisa disimpan sebagai PDF.
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports/print', function (Request $request, ReportExportService $exports) {
        abort_unless($request->user()->hasAdminPermission(AdminRoles::PERM_REPORTS), 403);

        [$from, $to] = $exports->resolveRange($request->get('from'), $request->get('to'));
        $dimension = (string) $request->get('dimension', 'product');

        $tables = ReportService::dimensions();
        abort_unless(array_key_exists($dimension, $tables), 404);

        $table = app(ReportService::class)->exportRows($dimension, $from, $to);

        return $exports->pdf(
            $table,
            'laporan-'.$dimension.'.pdf',
            'Laporan '.$tables[$dimension],
            $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
        );
    })->name('admin.reports.print');
});

Route::middleware('auth')->group(function () {
    Route::get('/member', [MemberController::class, 'dashboard'])->name('member.dashboard');
    Route::get('/member/topup', [TopupController::class, 'create'])->name('topup.create');
    Route::post('/member/topup/quote', [TopupController::class, 'quote'])->name('topup.quote');
    Route::post('/member/topup', [TopupController::class, 'store'])->middleware('throttle:topup')->name('topup.store');

    Route::get('/member/topup-manual', [ManualTopupController::class, 'create'])->name('manual-topup.create');
    Route::post('/member/topup-manual', [ManualTopupController::class, 'store'])->middleware('throttle:manual-topup')->name('manual-topup.store');

    Route::get('/dashboard', [MemberController::class, 'dashboard'])->name('dashboard');
    Route::post('/member/rate/{transaction}', [MemberController::class, 'rate'])->middleware('throttle:rating')->name('member.rate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
