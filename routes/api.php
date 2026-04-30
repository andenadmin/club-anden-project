<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// WhatsApp Webhook — sin auth, sin CSRF
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('api.whatsapp.webhook.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->name('api.whatsapp.webhook');
