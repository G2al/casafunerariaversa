<?php

use App\Http\Controllers\FilePresentationController;
use App\Http\Controllers\ManifestEditorController;
use App\Http\Controllers\ManifestRecordController;
use App\Http\Controllers\PrivateFilesController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', ActiveUser::class])->prefix('private')->name('private.')->group(function () {
    Route::get('file/{type}/{record}/{part?}', [FilePresentationController::class, 'show'])->whereNumber('record')->name('file-present');
    Route::get('manifests/exports/{export}', [ManifestRecordController::class, 'export'])->name('manifests.export');
    Route::get('manifests/{manifest}/editor', [ManifestRecordController::class, 'show'])->name('manifests.show');
    Route::post('manifests/{manifest}/editor', [ManifestRecordController::class, 'save'])->name('manifests.save');
    Route::delete('manifests/{manifest}/editor', [ManifestRecordController::class, 'reset'])->name('manifests.reset');
    Route::get('manifests/{manifest}/preview', [ManifestRecordController::class, 'preview'])->name('manifests.preview');
    Route::get('manifests/{manifest}/photo', [ManifestRecordController::class, 'photo'])->name('manifests.photo');
    Route::post('manifests/{manifest}/upload', [ManifestRecordController::class, 'upload'])->name('manifests.upload');
    Route::get('manifests/{manifest}/attachments/{attachment}', [ManifestRecordController::class, 'attachment'])->name('manifests.attachment');
    Route::get('vehicles/{vehicle}/photos/{index}', [PrivateFilesController::class, 'vehiclePhoto'])->whereNumber('index')->name('vehicle-photo');
    Route::get('vehicles/{vehicle}/attachments/{index}', [PrivateFilesController::class, 'vehicleAttachment'])->whereNumber('index')->name('vehicle-attachment');
    Route::get('symbols/{symbol}', [ManifestEditorController::class, 'symbol'])->name('symbol');
    Route::post('cases/{case}/editor-upload', [ManifestEditorController::class, 'upload'])->name('manifest-editor.upload');
    Route::get('cases/{case}/editor-attachments/{attachment}', [ManifestEditorController::class, 'attachment'])->name('manifest-editor.attachment');
    Route::get('manifest-editor/fonts/{font}', [ManifestEditorController::class, 'font'])->name('manifest-editor.font');
    Route::get('cases/{case}/manifest-editor', [ManifestEditorController::class, 'show'])->name('manifest-editor.show');
    Route::post('cases/{case}/manifest-editor', [ManifestEditorController::class, 'save'])->name('manifest-editor.save');
    Route::delete('cases/{case}/manifest-editor', [ManifestEditorController::class, 'reset'])->name('manifest-editor.reset');
    Route::get('cases/{case}/preview/{kind}', [PrivateFilesController::class, 'preview'])->name('preview');
    Route::get('documents/{document}', [PrivateFilesController::class, 'document'])->name('document');
    Route::get('cases/{case}/photo', [PrivateFilesController::class, 'photo'])->name('photo');
    Route::get('cases/{case}/attachments/{kind}', [PrivateFilesController::class, 'attachment'])->whereIn('kind', ['death-certificate', 'istat'])->name('attachment');
    Route::get('cases/{case}/delegate-identity/{side}', [PrivateFilesController::class, 'delegateIdentity'])->whereIn('side', ['front', 'back'])->name('delegate-identity');
    Route::get('cases/{case}/identity/{side}', [PrivateFilesController::class, 'identity'])->whereIn('side', ['front', 'back'])->name('identity');
    Route::get('templates/{template}', [PrivateFilesController::class, 'template'])->name('template');
    Route::get('company/logo', [PrivateFilesController::class, 'logo'])->name('logo');
});
