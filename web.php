<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\OfferController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\CouponController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\ContactUsController;
use App\Http\Controllers\Dashboard\SubCategoryController;
use App\Http\Controllers\Dashboard\OrganizationController;
use App\Http\Controllers\Dashboard\TermsAndConditions;
use App\Http\Controllers\Dashboard\UserCouponsController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;



Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]
    ], function(){

Route::get('/', function () {
    return redirect()->route('login');
})->middleware('guest');
Route::get('/login', [AuthController::class, 'login'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'loginAction'])->name('loginAction')->middleware('guest');

Route::group(['middleware'=>['auth','admin'],'prefix' => 'admin', 'as' => 'Admin.'], function () {
    // home
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    //Role
    Route::resource('roles',RoleController::class);
    //Category
    Route::resource('category',CategoryController::class);
    //sub_category
    Route::resource('sub_category',SubCategoryController::class);
    //organization
    Route::resource('organization',OrganizationController::class);
    //offers
    Route::resource('offers',OfferController::class);
    //coupon
    Route::resource('coupons',CouponController::class);
    //order
    Route::resource('orders',OrderController::class);
    //contact us
    Route::resource('contact-us',ContactUsController::class);
        //user-coupons
        Route::resource('user-coupons',UserCouponsController::class);

// TermsAndConditions
        Route::resource('terms',TermsAndConditions::class);



 // logout
 Route::get('logout', [AuthController::class, 'logout'])->name('logout');
});

});
