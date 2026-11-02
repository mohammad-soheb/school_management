<?php

use App\Http\Controllers\School\Subscription\SubscriptionController;
use Illuminate\Support\Facades\Route;

// This school's ERP subscription (read-only). Stays reachable when the subscription is expired or suspended
// (see App\Services\Platform\Subscriptions\SubscriptionAccess). Name: school.subscription.show, URL /app/subscription.

Route::get('subscription', SubscriptionController::class)->middleware('can:billing.view')->name('subscription.show');
