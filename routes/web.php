<?php

use App\Http\Controllers\BotMessagesAdminController;
use App\Http\Controllers\BotSimulatorController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Bot Simulator
    Route::get('/bot', [BotSimulatorController::class, 'index'])->name('bot.simulator');
    Route::post('/bot/message', [BotSimulatorController::class, 'message'])->name('bot.message');
    Route::post('/bot/reset', [BotSimulatorController::class, 'reset'])->name('bot.reset');

    // Bot Messages Admin
    Route::get('/bot/messages', [BotMessagesAdminController::class, 'index'])->name('bot.messages');
    Route::put('/bot/messages/{botMessage}', [BotMessagesAdminController::class, 'update'])->name('bot.messages.update');
});

require __DIR__.'/settings.php';
