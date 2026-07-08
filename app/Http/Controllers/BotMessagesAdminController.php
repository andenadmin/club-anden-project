<?php

namespace App\Http\Controllers;

use App\Models\BotMessage;
use App\Models\BotMessageOption;
use App\Models\RestaurantSector;
use App\Services\BotMessageOptionsRegistry;
use App\Services\BotMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class BotMessagesAdminController extends Controller
{
    private const SESSION_KEY    = 'bot_messages_unlocked_at';
    private const LOCK_MINUTES   = 120; // 2 horas

    private function isUnlocked(): bool
    {
        $ts = session(self::SESSION_KEY);
        return $ts && now()->diffInMinutes($ts, true) < self::LOCK_MINUTES;
    }

    public function showUnlock()
    {
        if ($this->isUnlocked()) {
            return redirect()->route('bot.messages');
        }

        return Inertia::render('bot-messages-unlock');
    }

    public function unlock(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (trim($request->password) !== trim(config('bot.messages_password', ''))) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.']);
        }

        session([self::SESSION_KEY => now()]);

        return redirect()->route('bot.messages');
    }

    public function index()
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $decorate = function ($msg) {
            $optionsKey = BotMessageOptionsRegistry::optionsKeyForMessage($msg->key);
            $config     = $optionsKey ? BotMessageOptionsRegistry::get($optionsKey) : null;

            return array_merge($msg->toArray(), [
                'default_content' => BotMessages::hardcodedDefault($msg->key),
                'options_key'      => $optionsKey,
                'options_config'   => $config,
                'options'          => $optionsKey
                    ? BotMessageOption::where('options_key', $optionsKey)->orderBy('orden')->get()
                    : null,
            ]);
        };

        return Inertia::render('bot-messages', [
            'messages' => BotMessage::where('is_archived', false)->orderBy('category')->orderBy('id')->get()->map($decorate),
            'archived' => BotMessage::where('is_archived', true)->orderBy('category')->orderBy('id')->get()->map($decorate),
            'sectores' => RestaurantSector::orderBy('orden')->get(),
        ]);
    }

    public function updateSector(Request $request, RestaurantSector $sector)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $data = $request->validate([
            'label'  => ['required', 'string', 'max:255'],
            'orden'  => ['required', 'integer', 'min:1'],
            'activo' => ['required', 'boolean'],
        ]);

        $sector->update($data);

        return back()->with('success', 'Sector actualizado.');
    }

    public function update(Request $request, BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $botMessage->update(['content' => $request->content]);
        BotMessages::clearCache();
        Artisan::call('queue:restart');

        return back()->with('success', 'Mensaje guardado.');
    }

    public function archive(BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $botMessage->update(['is_archived' => true]);
        BotMessages::clearCache();
        return back()->with('success', 'Mensaje archivado.');
    }

    public function restore(BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $botMessage->update(['is_archived' => false]);
        BotMessages::clearCache();
        return back()->with('success', 'Mensaje restaurado.');
    }

    public function resetDefault(BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $default = BotMessages::hardcodedDefault($botMessage->key);

        if ($default === null) {
            return back()->with('error', 'Este mensaje es dinámico y no tiene un valor por defecto fijo.');
        }

        $botMessage->update(['content' => $default]);
        return back()->with('success', 'Mensaje restaurado al valor por defecto.');
    }
}
