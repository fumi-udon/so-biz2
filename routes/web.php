<?php

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
Route::post('/mypage', [MyPageController::class, 'store'])->name('mypage.store');
