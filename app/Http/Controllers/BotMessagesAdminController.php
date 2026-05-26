<?php

namespace App\Http\Controllers;

use App\Models\BotMessage;
use App\Services\BotMessages;
use Illuminate\Http\Request;
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

        $decorate = fn ($msg) => array_merge($msg->toArray(), [
            'default_content' => BotMessages::hardcodedDefault($msg->key),
        ]);

        return Inertia::render('bot-messages', [
            'messages' => BotMessage::where('is_archived', false)->orderBy('category')->orderBy('id')->get()->map($decorate),
            'archived' => BotMessage::where('is_archived', true)->orderBy('category')->orderBy('id')->get()->map($decorate),
        ]);
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

        return back()->with('success', 'Mensaje guardado.');
    }

    public function archive(BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $botMessage->update(['is_archived' => true]);
        return back()->with('success', 'Mensaje archivado.');
    }

    public function restore(BotMessage $botMessage)
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $botMessage->update(['is_archived' => false]);
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
