<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Marcky's static frontend prototype (browser-only demo data, no database).
if (! app()->isProduction()) {
    Route::view('/prototype', 'welcome')->name('prototype');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
    Route::get('/farmers/{member}', [FarmerController::class, 'show'])->name('farmers.show');

    Route::middleware('role:warehouse_assistant,accounting_clerk,cooperative_manager')->group(function () {
        Route::get('/receipts', [ReceivingController::class, 'index'])->name('receipts.index');
    });

    Route::middleware('role:warehouse_assistant')->group(function () {
        Route::get('/receipts/create', [ReceivingController::class, 'create'])->name('receive.create');
        Route::post('/receipts', [ReceivingController::class, 'store'])->middleware('throttle:60,1')->name('receipts.store');
        Route::post('/receipts/{receipt}/approve-storage', [ReceivingController::class, 'approveStorage'])->name('receipts.approve-storage');
    });

    Route::middleware('role:accounting_clerk,cooperative_manager')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });

    Route::middleware('role:accounting_clerk')->group(function () {
        Route::post('/receipts/{receipt}/payment', [PaymentController::class, 'store'])->name('payments.store');
    });

    Route::middleware('role:warehouse_assistant,cooperative_manager')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    });

    Route::middleware('role:warehouse_assistant')->group(function () {
        Route::post('/stock/release', [StockController::class, 'release'])->name('stock.release');
    });

    Route::middleware('role:cooperative_manager')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
    });
});
