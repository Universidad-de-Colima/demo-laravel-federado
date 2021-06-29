<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/login',  ['uses' => 'App\Http\Controllers\Auth\AuthController@login'])->name('login');
Route::get('/logout', ['uses' => 'App\Http\Controllers\Auth\AuthController@logout'])->name('logout');


Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    echo("Bienvenido " . \Auth::user()->first_name);
});
