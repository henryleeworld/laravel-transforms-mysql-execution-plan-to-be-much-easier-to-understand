<?php

use App\Http\Controllers\Api\V1\PropertySearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('search', PropertySearchController::class);
