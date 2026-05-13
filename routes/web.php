<?php

use App\Http\Controllers\BotMessagesAdminController;
use App\Http\Controllers\BotPreciosController;
use App\Http\Controllers\BotSimulatorController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Bot Simulator
    Route::get('/bot', [BotSimulatorController::class, 'index'])->name('bot.simulator');
    Route::post('/bot/message', [BotSimulatorController::class, 'message'])->name('bot.message');
    Route::post('/bot/reset', [BotSimulatorController::class, 'reset'])->name('bot.reset');

    // Bot Precios Admin
    Route::get('/bot/precios', [BotPreciosController::class, 'index'])->name('bot.precios');
    Route::get('/bot/precios/template', [BotPreciosController::class, 'template'])->name('bot.precios.template');
    Route::post('/bot/precios/import', [BotPreciosController::class, 'import'])->name('bot.precios.import');
    Route::patch('/bot/precios/{costoEvento}', [BotPreciosController::class, 'update'])->name('bot.precios.update');

    // Bot Messages Admin
    Route::get('/bot/messages', [BotMessagesAdminController::class, 'index'])->name('bot.messages');
    Route::put('/bot/messages/{botMessage}', [BotMessagesAdminController::class, 'update'])->name('bot.messages.update');
    Route::patch('/bot/messages/{botMessage}/archive', [BotMessagesAdminController::class, 'archive'])->name('bot.messages.archive');
    Route::patch('/bot/messages/{botMessage}/restore', [BotMessagesAdminController::class, 'restore'])->name('bot.messages.restore');
    Route::patch('/bot/messages/{botMessage}/reset-default', [BotMessagesAdminController::class, 'resetDefault'])->name('bot.messages.reset-default');

    // CRM — solo super admins
    Route::middleware('super_admin')->group(function () {
        Route::get('/crm', [CrmController::class, 'index'])->name('crm.index');
        Route::get('/crm/export', [CrmController::class, 'export'])->name('crm.export');
    });

    // Inbox
    Route::get('/inbox',          [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/poll',          [InboxController::class, 'poll'])->name('inbox.poll');
    Route::get('/inbox/{numero}/poll', [InboxController::class, 'pollChat'])->name('inbox.chat.poll')->where('numero', '[0-9]+');
    Route::get('/inbox/{numero}',      [InboxController::class, 'show'])->name('inbox.show')->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/pause',     [InboxController::class, 'pause'])->name('inbox.pause')->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/resume',    [InboxController::class, 'resume'])->name('inbox.resume')->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/snooze',    [InboxController::class, 'snooze'])->name('inbox.snooze')->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/restart',   [InboxController::class, 'restart'])->name('inbox.restart')->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/reply',     [InboxController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('inbox.reply')
        ->where('numero', '[0-9]+');
    Route::post('/inbox/{numero}/read',      [InboxController::class, 'markRead'])->name('inbox.read')->where('numero', '[0-9]+');
});

require __DIR__.'/settings.php';
