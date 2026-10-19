<?php

use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Client\ClientConversationController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientInvoiceController;
use App\Http\Controllers\Client\ClientMessageController;
use App\Http\Controllers\Client\ClientPaymentController;
use App\Http\Controllers\Client\ClientProductsController;
use App\Http\Controllers\Client\ClientPasswordController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\RepresentativeController;
use App\Http\Controllers\Client\AccessControlController;
use App\Http\Controllers\Client\ClientReceivingController;
use App\Http\Controllers\Client\ClientSupportController;
use App\Http\Controllers\Client\ClientTrackingController;
use App\Http\Controllers\Client\OrdersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| B2B Client Gateway Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest:client')->group(function () {
    Route::get('client/register', [ClientAuthController::class, 'create'])->name('client.register');
    Route::post('client/register', [ClientAuthController::class, 'store'])->middleware('throttle:6,1')->name('client.register.store');
    Route::get('client/login', [ClientAuthController::class, 'showLogin'])->name('client.login');
    Route::post('client/login', [ClientAuthController::class, 'login'])->middleware('throttle:10,1')->name('client.login.store');
    Route::post('client/password/recover', [PasswordRecoveryController::class, 'client'])->middleware('throttle:6,1')->name('client.password.recover');
});

Route::post('client/logout', [ClientAuthController::class, 'logout'])
    ->middleware('auth:client')
    ->name('client.logout');

// Forced password change (first login / after a recovery password). Kept
// outside the client.access group so restricted sessions are never locked out
// of changing a system-issued password.
Route::post('client/password/force', [ForcedPasswordController::class, 'client'])
    ->middleware('auth:client')
    ->name('client.password.force');

/*
|--------------------------------------------------------------------------
| Protected Client B2B Portal Routes (Restructured)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:client', 'client.access'])->prefix('partner')->name('client.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    // Products & Inquiries
    Route::get('/products', [ClientProductsController::class, 'index'])->name('products');
    Route::post('/products/{product}/inquire', [ClientProductsController::class, 'inquire'])->name('products.inquire');
    Route::post('/products/bulk-inquire', [ClientProductsController::class, 'bulkInquire'])->name('products.bulk-inquire');
    // Conversations
    Route::get('/conversations', [ClientConversationController::class, 'index'])->name('conversations');
    Route::get('/conversations/{inquiry}', [ClientConversationController::class, 'show'])->name('conversation.show');
    Route::post('/conversations/{inquiry}/message', [ClientConversationController::class, 'sendMessage'])->name('conversation.message');
    // Real-time: polling feed + typing heartbeat (no page reload needed)
    Route::get('/conversations/{inquiry}/feed', [ClientConversationController::class, 'feed'])->name('conversation.feed');
    Route::post('/conversations/{inquiry}/typing', [ClientConversationController::class, 'typing'])->name('conversation.typing');
    Route::post('/quotations/{quotation}/accept', [ClientConversationController::class, 'acceptQuotation'])->name('client.quotation.accept');
    Route::post('/quotations/{quotation}/reject', [ClientConversationController::class, 'rejectQuotation'])->name('client.quotation.reject');
    Route::get('quotations/{quotation}/download', [ClientConversationController::class, 'downloadQuotation'])
        ->name('client.quotation.download');
    // CORRECTED: send-po route name
    Route::post('/conversation/{inquiry}/send-po', [ClientConversationController::class, 'sendPO'])->name('conversation.send-po');

    // NEW: Attachment approval by client
    Route::post('/conversation/attachment/{attachment}/approve', [ClientConversationController::class, 'approveAttachment'])
        ->name('conversation.attachment.approve');

    // Fabric sample loop: approve the forwarded sample or request a color adjustment.
    Route::post('/sample-requests/{sampleRequest}/approve', [ClientConversationController::class, 'approveSample'])
        ->name('sample.approve');
    Route::post('/sample-requests/{sampleRequest}/adjust', [ClientConversationController::class, 'adjustSample'])
        ->name('sample.adjust');

    // Direct line to Monti Textiles (no inquiry needed)
    Route::get('/messages', [ClientMessageController::class, 'index'])->name('messages');
    Route::get('/messages/incoming-calls', [ClientMessageController::class, 'incomingCalls'])->name('messages.incoming-calls');
    Route::get('/messages/feed', [ClientMessageController::class, 'feed'])->name('messages.feed');
    Route::post('/messages', [ClientMessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{message}/unsend-for-me', [ClientMessageController::class, 'unsendForMe'])->name('messages.unsend-for-me');
    Route::post('/messages/{message}/unsend-for-everyone', [ClientMessageController::class, 'unsendForEveryone'])->name('messages.unsend-for-everyone');
    Route::post('/messages/{message}/react', [ClientMessageController::class, 'toggleReaction'])->name('messages.react');
    Route::post('/messages/calls/start', [ClientMessageController::class, 'startCall'])->name('messages.calls.start');
    Route::post('/messages/calls/{message}/end', [ClientMessageController::class, 'endCall'])->name('messages.calls.end');
    Route::post('/messages/calls/{message}/answer', [ClientMessageController::class, 'answerCall'])->name('messages.calls.answer');
    Route::post('/messages/calls/{message}/signal', [ClientMessageController::class, 'signalCall'])->name('messages.calls.signal');
    Route::get('/messages/calls/{message}/signals', [ClientMessageController::class, 'callSignals'])->name('messages.calls.signals');

    // Orders & Invoices (legacy support)
    Route::get('/orders', [OrdersController::class, 'orders'])->name('orders');
    Route::post('/orders/{order}/accept', [OrdersController::class, 'acceptPurchaseOrder'])->name('orders.accept');
    // Order Tracking (B2B tracking UI — mirrors PRO/ORD tracking)
    Route::get('/tracking', [ClientTrackingController::class, 'index'])->name('tracking');
    Route::get('/tracking/{type}/{id}', [ClientTrackingController::class, 'show'])
        ->whereIn('type', ['po', 'so', 'PO', 'SO'])
        ->name('tracking.show');
    Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices');
    // Detailed invoice: client reviews lines, then accepts and/or sends PO
    Route::get('/invoices/{order}', [ClientInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{order}/accept', [ClientInvoiceController::class, 'accept'])->name('invoices.accept');
    Route::post('/invoices/{order}/send-po', [ClientInvoiceController::class, 'sendPO'])->name('invoices.send-po');
    // Payments — the business client settles its receivable invoices through
    // the shared PayMongo hosted checkout; confirmed charges are written to
    // the FIN receivables ledger (see ClientPaymentController).
    Route::get('/payments', [ClientPaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{invoice}/checkout', [ClientPaymentController::class, 'startCheckout'])->name('payments.checkout');
    Route::get('/payments/checkout/return', [ClientPaymentController::class, 'checkoutReturn'])->name('payments.checkout.return');
    // Receiving (delivery confirmation)
    Route::get('/receiving', [ClientReceivingController::class, 'index'])->name('receiving');
    Route::post('/receiving/{order}/mark', [ClientReceivingController::class, 'markReceived'])->name('receiving.mark');
    // Change password (available to every sign-in, even when the Profile page
    // has been removed from a representative's granted pages).
    Route::post('/password', [ClientPasswordController::class, 'update'])->name('password.update');

    // Profile
    Route::get('/profile', [ClientProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/logo', [ClientProfileController::class, 'destroyLogo'])->name('profile.logo.destroy');
    Route::post('/profile/logo', [ClientProfileController::class, 'storeLogo'])->name('profile.logo.store');

    // Representatives (primary-only): the business partner registers its
    // delegated officers; CRM approves/creates their accounts.
    Route::get('/representatives', [RepresentativeController::class, 'index'])->name('representatives');
    Route::post('/representatives', [RepresentativeController::class, 'store'])->name('representatives.store');
    Route::delete('/representatives/{contact}', [RepresentativeController::class, 'destroy'])->name('representatives.destroy');

    // Access Control (primary-only): grant each representative their pages.
    Route::get('/access-control', [AccessControlController::class, 'index'])->name('access-control');
    Route::post('/access-control', [AccessControlController::class, 'update'])->name('access-control.update');
    // Support
    Route::get('/support', [ClientSupportController::class, 'index'])->name('support');
    Route::post('/support/complaint', [ClientSupportController::class, 'storeComplaint'])->name('support.complaint');

    // Legacy routes (preserved for backward compatibility)
    Route::post('/purchase-order', [ClientDashboardController::class, 'placeOrder'])->name('purchase-order.store');
    Route::post('/quotations/{quotation}/accept', [ClientConversationController::class, 'acceptQuotation'])->name('quotation.accept');
    Route::post('/quotations/{quotation}/reject', [ClientConversationController::class, 'rejectQuotation'])->name('quotation.reject');
    Route::post('/quotations/{quotation}/trash', [ClientConversationController::class, 'trashQuotation'])->name('quotation.trash');
});