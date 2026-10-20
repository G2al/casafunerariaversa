<?php

use App\Http\Controllers\PrivateFilesController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', ActiveUser::class])->prefix('private')->name('private.')->group(function () {
    Route::get('cases/{case}/preview/{kind}', [PrivateFilesController::class, 'preview'])->name('preview');
    Route::get('documents/{document}', [PrivateFilesController::class, 'document'])->name('document');
    Route::get('cases/{case}/photo', [PrivateFilesController::class, 'photo'])->name('photo');
    Route::get('templates/{template}', [PrivateFilesController::class, 'template'])->name('template');
    Route::get('company/logo', [PrivateFilesController::class, 'logo'])->name('logo');
});
