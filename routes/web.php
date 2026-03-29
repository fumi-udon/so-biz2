<?php

use App\Http\Controllers\ClientInventoryController;
use App\Http\Controllers\CloseCheckController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\TimecardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/timecard', [TimecardController::class, 'index'])->name('timecard.index');
Route::post('/timecard/process', [TimecardController::class, 'process'])->name('timecard.process');

Route::get('/close-check', [CloseCheckController::class, 'index'])->name('close-check.index');
Route::post('/close-check/process', [CloseCheckController::class, 'process'])->name('close-check.process');
Route::get('/close-check/success', [CloseCheckController::class, 'success'])->name('close-check.success');

Route::get('/mypage', [MyPageController::class, 'index'])->name('mypage.index');
Route::post('/mypage/open', [MyPageController::class, 'openByPin'])->name('mypage.open');
Route::post('/mypage', [MyPageController::class, 'store'])->name('mypage.store');
Route::get('/mypage/attendance', [MyPageController::class, 'attendance'])->name('mypage.attendance');
Route::post('/mypage/attendance', [MyPageController::class, 'updateAttendance'])->name('mypage.attendance.update');

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [ClientInventoryController::class, 'index'])->name('index');
    Route::get('/input/{timing}/{staff_id}', [ClientInventoryController::class, 'input'])->name('input');
    Route::post('/store', [ClientInventoryController::class, 'store'])->name('store');
});
