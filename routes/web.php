<?php

use Illuminate\Support\Facades\Route;
use Als\Accounting\Http\Controllers\AdjustmentController;

Route::prefix('accounting')->group(function () {
    Route::get('/adjustments', [AdjustmentController::class, 'index']);
});
