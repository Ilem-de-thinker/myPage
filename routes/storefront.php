<?php

use App\Domains\Billing\Http\Controllers\BuyerEscrowController;
use App\Domains\Booking\Http\Controllers\PublicBookingController;
use App\Domains\Seller\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'show'])->name('storefront.show');
Route::get('/about', [StorefrontController::class, 'about'])->name('storefront.about');
Route::get('/contact', [StorefrontController::class, 'contact'])->name('storefront.contact');
Route::get('/products', [StorefrontController::class, 'products'])->name('storefront.products');
Route::get('/products/{productSlug}', [StorefrontController::class, 'productShow'])->name('storefront.product');
Route::get('/services', [StorefrontController::class, 'services'])->name('storefront.services');
Route::get('/services/{serviceSlug}', [StorefrontController::class, 'serviceShow'])->name('storefront.service');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('storefront.cart');
Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
Route::get('/book', [StorefrontController::class, 'book'])->name('storefront.book');
Route::get('/book/confirmation', [PublicBookingController::class, 'confirmation'])->name('storefront.booking-confirmation');

/*
    Manage a booking you already made.

    Separate from the confirmation page, which is the destination straight after
    paying and is reached with a reference this platform just issued. This one is
    the door somebody comes back through a week later, so it is a lookup rather
    than a receipt — and it asks for the email or phone the booking was made
    with as well as the reference, because a reference alone is a string anybody
    can be handed.
*/
Route::get('/book/manage', [StorefrontController::class, 'manageBooking'])->name('storefront.manage-booking');
Route::post('/book', [PublicBookingController::class, 'store'])->name('storefront.booking.store');
Route::post('/book/whatsapp', [PublicBookingController::class, 'requestWhatsApp'])
    ->middleware('throttle:20,1')
    ->name('storefront.booking.whatsapp');
// Public, unauthenticated, and the one endpoint that runs repeated date-window
// queries, so it is throttled.
Route::get('/availability', [PublicBookingController::class, 'availability'])
    ->middleware('throttle:60,1')
    ->name('storefront.availability');
Route::get('/track-order', [StorefrontController::class, 'trackOrder'])->name('storefront.track-order');

// Buyer-facing escrow. Unauthenticated: the signed token in the URL is the
// capability, and it only ever opens one order. Throttled because a leaked link
// is a link anyone can hammer.
Route::get('/escrow/{token}', [BuyerEscrowController::class, 'show'])->name('storefront.escrow');
Route::post('/escrow/{token}/release', [BuyerEscrowController::class, 'release'])
    ->middleware('throttle:10,1')
    ->name('storefront.escrow.release');
Route::post('/escrow/{token}/dispute', [BuyerEscrowController::class, 'dispute'])
    ->middleware('throttle:5,1')
    ->name('storefront.escrow.dispute');
Route::post('/checkout/whatsapp', [StorefrontController::class, 'whatsappOrder'])->name('storefront.checkout.whatsapp');
/*
    Reviews. Public and unauthenticated, so throttled hard — this is the most
    abusable endpoint on a platform where buyers deliberately have no account.
    Anyone can post; nobody's review appears until the seller reads it.
*/
/*
    /reviews/business, not /reviews. The seller's inbox is GET /reviews in
    web.php, and the two route files are registered in order — so a POST /reviews
    matches that GET route first and comes back 405, with no explanation.

    All three public review endpoints live under /reviews/* so they read as one
    family and cannot collide with a dashboard screen again.
*/
Route::post('/reviews/business', [StorefrontController::class, 'reviewBusiness'])
    ->middleware('throttle:5,1')
    ->name('storefront.review');
Route::post('/reviews/product/{productSlug}', [StorefrontController::class, 'reviewProduct'])
    ->middleware('throttle:5,1')
    ->name('storefront.product.review');
Route::post('/reviews/service/{serviceSlug}', [StorefrontController::class, 'reviewService'])
    ->middleware('throttle:5,1')
    ->name('storefront.service.review');

// Public and unauthenticated, so it is throttled: without a limit anyone could
// fill a seller's dashboard with carts they never intend to buy.
Route::post('/checkout/capture', [StorefrontController::class, 'captureCart'])
    ->middleware('throttle:20,1')
    ->name('storefront.checkout.capture');

/*
    The link a buyer keeps.

    Both screens already exist and are reachable by reference lookup; these are
    the same screens reached through a signed URL instead. The signature *is* the
    authorisation — there is no login to check, because on this platform a buyer
    has none — and the `signed` middleware is what enforces it, including the
    expiry.

    Scoped to the studio in the slug as well as the reference, so a link cannot
    be replayed against a different seller's storefront even if somebody edits it.
*/
Route::get('/o/{reference}', [StorefrontController::class, 'orderByLink'])
    ->middleware('signed')
    ->name('storefront.order.show');

Route::get('/b/{reference}', [StorefrontController::class, 'bookingByLink'])
    ->middleware('signed')
    ->name('storefront.booking.show');
