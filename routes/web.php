<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VideoShowController;
use App\Http\Controllers\VideoViewController;
use App\Http\Controllers\VideoIndexController;
use App\Http\Controllers\VideoStoreController;
use App\Http\Controllers\VideoCreateController;
use App\Http\Controllers\VideoUpdateController;
use App\Http\Controllers\VideoDestroyController;
use App\Http\Controllers\VideoCaptureStoreController;
use App\Http\Controllers\VideoCaptureFileStoreController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/view/{video:uuid}', VideoViewController::class)->name('videos.view');

Route::middleware('auth')->group(function () {
    Route::get('/videos', VideoIndexController::class)->name('videos.index');
    Route::post('/videos/capture/{video:uuid}/file/store', VideoCaptureFileStoreController::class)->name('videos.capture.file');
    Route::get('/videos/capture', VideoCreateController::class)->name('videos.capture');
    Route::post('/videos', VideoStoreController::class)->name('videos.store');
    Route::post('/videos/capture/store', VideoCaptureStoreController::class)->name('videos.capture.store');
    Route::get('/videos/{video:uuid}', VideoShowController::class)->name('videos.show');
    Route::patch('/videos/{video:uuid}', VideoUpdateController::class)->name('videos.update');
    Route::delete('/videos/{video:uuid}', VideoDestroyController::class)->name('videos.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
