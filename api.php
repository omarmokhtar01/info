<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactusController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\SubcategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CouponsUserController;
use App\Http\Controllers\Api\TermsAndConditionsApi;


use App\Http\Controllers\PaymobController;
use App\Http\Middleware\LanguageTranslation;
use App\Http\Middleware\StartSessionMiddleware;

Route::middleware(LanguageTranslation::class)->group(function () {
    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Protected Auth Routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', [AuthController::class, 'getUser']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::put('update-profile', [AuthController::class, 'updateProfile']);
            Route::put('change-password', [AuthController::class, 'changePassword']);
        });
    });


    Route::middleware('auth:sanctum')->group(function () {

        // Terms And Conditions
        Route::get('terms-and-conditions', [TermsAndConditionsApi::class, 'index']);

        // organizations
        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::get('organizations/{id}', [OrganizationController::class, 'show']);
        Route::put('/organizations/{id}/favourite', [OrganizationController::class, 'toggleFavourite']);
        Route::put('/organizations/{id}/rate', [OrganizationController::class, 'rateOrganization']);

        // Contact us
        Route::get('contact-us', [ContactusController::class, 'index']);

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);

        // Subcategories
        Route::get('subcategories', [SubcategoryController::class, 'index']);
        Route::get('subcategories/{id}', [SubcategoryController::class, 'show']);

        // Offers
        Route::get('offers', [OfferController::class, 'index']);
        Route::get('offers/{id}', [OfferController::class, 'show']);

        // Coupons
        Route::get('coupons/{id}', [CouponController::class, 'show']);
        Route::get('coupons', [CouponController::class, 'index']);
        Route::get('recommendations', [CouponController::class, 'recommendationHomeCoupons']);
        Route::get('new-coupons', [CouponController::class, 'newCoupons']);

        // Cart
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::put('/cart', [CartController::class, 'update']);
        Route::delete('/cart', [CartController::class, 'destroy']);

        // Orders
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::post('/order_paymob', [OrderPaymobController::class, 'store']);

        // User
        Route::get('/user/favourites', [FavouriteController::class, 'getUserFavourites']);
        Route::get('/user-coupons', [CouponsUserController::class, 'getUserCoupons']);
        Route::get('/coupon/find', [CouponsUserController::class, 'findByCode']);
        Route::post('/use-coupon', [CouponsUserController::class, 'useCoupon']);

        // Stripe
        Route::post('/create-checkout-session', [PaymentController::class, 'createCheckoutSession'])->middleware('auth:sanctum');
        Route::get('/payment/success/{order_id}', [PaymentController::class, 'success'])->name('payment.success');
        Route::get('/payment/cancel/{order_id}', [PaymentController::class, 'cancel'])->name('payment.cancel');


        // Paymob
        Route::post('/payment/process', [PaymobController::class, 'paymentProcess']);
        Route::get('/payment/callback', [PaymobController::class, 'callBack']);
        Route::post('/webhook', [StripeWebhookController::class, 'handleWebhook']);
    });
});

Route::get('/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');


// Social login
Route::middleware(StartSessionMiddleware::class)->group(function () {
    // Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback']);

    Route::get('/auth/facebook', [SocialiteController::class, 'redirectToFacebook']);
    Route::get('/auth/facebook/callback', [SocialiteController::class, 'handleFacebookCallback']);
});


Route::get('/paymob/success', [PaymobController::class, 'success'])->name('paymob.success');
Route::get('/paymob/cancel', [PaymobController::class, 'failed'])->name('paymob.cancel');
Route::any('/callback', [OrderPaymobController::class, 'callBack']);

Route::post('/auth/google', [SocialiteController::class, 'socialRegister']);
