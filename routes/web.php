<?php

use App\Http\Controllers\ManifestEditorController;
use App\Http\Controllers\PrivateFilesController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', ActiveUser::class])->prefix('private')->name('private.')->group(function () {
    Route::get('manifest-editor/fonts/{font}', [ManifestEditorController::class, 'font'])->name('manifest-editor.font');
    Route::get('cases/{case}/manifest-editor', [ManifestEditorController::class, 'show'])->name('manifest-editor.show');
    Route::post('cases/{case}/manifest-editor', [ManifestEditorController::class, 'save'])->name('manifest-editor.save');
    Route::delete('cases/{case}/manifest-editor', [ManifestEditorController::class, 'reset'])->name('manifest-editor.reset');
    Route::get('cases/{case}/preview/{kind}', [PrivateFilesController::class, 'preview'])->name('preview');
    Route::get('documents/{document}', [PrivateFilesController::class, 'document'])->name('document');
    Route::get('cases/{case}/photo', [PrivateFilesController::class, 'photo'])->name('photo');
    Route::get('cases/{case}/attachments/{kind}', [PrivateFilesController::class, 'attachment'])->whereIn('kind', ['death-certificate', 'istat'])->name('attachment');
    Route::get('cases/{case}/identity/{side}', [PrivateFilesController::class, 'identity'])->whereIn('side', ['front', 'back'])->name('identity');
    Route::get('templates/{template}', [PrivateFilesController::class, 'template'])->name('template');
    Route::get('company/logo', [PrivateFilesController::class, 'logo'])->name('logo');
});
