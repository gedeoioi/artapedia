<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierWebhookController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/kategori', [HomeController::class, 'categories'])->name('categories.index');
Route::get('/game/{game}', [HomeController::class, 'game'])->name('game.show');

Route::get('/product/{product}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
Route::post('/checkout/check-nickname', [CheckoutController::class, 'checkNickname'])->name('checkout.nickname');

Route::get('/pay/{invoice}', [PaymentController::class, 'show'])->name('payment.show');
Route::get('/pay/{invoice}/status', [PaymentController::class, 'status'])->name('payment.status');

Route::get('/cek-invoice', [InvoiceController::class, 'index'])->name('invoice.index');
Route::get('/cek-invoice/hasil', [InvoiceController::class, 'show'])->name('invoice.show');
Route::get('/api/invoice/{code}', [InvoiceController::class, 'api'])->name('invoice.api');

Route::post('/webhook/payment/{gateway}', [WebhookController::class, 'gateway'])->name('webhook.payment');
Route::post('/webhook/supplier/digiflazz', [SupplierWebhookController::class, 'digiflazz'])->name('webhook.supplier.digiflazz');
Route::post('/webhook/supplier/vip-reseller', [SupplierWebhookController::class, 'vipReseller'])->name('webhook.supplier.vip-reseller');
Route::post('/webhook/supplier/toko-voucher', [SupplierWebhookController::class, 'tokoVoucher'])->name('webhook.supplier.toko-voucher');

Route::get('/sitemap.xml', function () {
    $products = \App\Models\Product::available()->orderBy('game')->limit(1000)->get();

    return response()->view('sitemap', compact('products'))->header('Content-Type', 'text/xml');
})->name('sitemap');

Route::middleware('auth')->group(function () {
    Route::get('/member', [MemberController::class, 'dashboard'])->name('member.dashboard');
    Route::get('/member/topup', [TopupController::class, 'create'])->name('topup.create');
    Route::post('/member/topup', [TopupController::class, 'store'])->name('topup.store');
    Route::get('/dashboard', [MemberController::class, 'dashboard'])->name('dashboard');
    Route::post('/member/rate/{transaction}', [MemberController::class, 'rate'])->name('member.rate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
