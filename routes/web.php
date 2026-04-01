<?php

use App\Http\Controllers\ClientInventoryController;
use App\Http\Controllers\CloseCheckController;
use App\Http\Controllers\MyPageController;
use App\Livewire\TimecardForm;
use App\Livewire\ClientOrderForm;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $request->session()->forget('mypage_staff_id');

    return view('welcome');
})->name('home');

Route::get('/order/{table_number}', ClientOrderForm::class)->name('order.table');


Route::get('/timecard', TimecardForm::class)->name('timecard.index');

Route::get('/close-check', [CloseCheckController::class, 'index'])->name('close-check.index');
Route::post('/close-check/process', [CloseCheckController::class, 'process'])->name('close-check.process');
Route::get('/close-check/success', [CloseCheckController::class, 'success'])->name('close-check.success');

Route::get('/mypage', [MyPageController::class, 'index'])->name('mypage.index');
Route::get('/mypage/reauth', [MyPageController::class, 'reauthenticate'])->name('mypage.reauth');
Route::post('/mypage/open', [MyPageController::class, 'openByPin'])->name('mypage.open');
Route::post('/mypage', [MyPageController::class, 'store'])->name('mypage.store');
Route::post('/mypage/auto-logout', [MyPageController::class, 'autoLogout'])->name('mypage.auto-logout');
Route::get('/mypage/attendance', [MyPageController::class, 'attendance'])->name('mypage.attendance');
Route::post('/mypage/attendance', [MyPageController::class, 'updateAttendance'])->name('mypage.attendance.update');

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [ClientInventoryController::class, 'index'])->name('index');
    Route::get('/input/{timing}/{staff_id}', [ClientInventoryController::class, 'input'])->name('input');
    Route::post('/store', [ClientInventoryController::class, 'store'])->name('store');
});
