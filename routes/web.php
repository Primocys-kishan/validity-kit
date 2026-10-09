
<?php

use Illuminate\Support\Facades\Route;

Route::get('/license/validate', function () {
    return view('license-validator::validate');
})->name('license-validator.page');