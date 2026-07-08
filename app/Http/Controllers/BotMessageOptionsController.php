<?php

namespace App\Http\Controllers;

use App\Models\BotMessageOption;
use App\Services\BotMessageOptionsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class BotMessageOptionsController extends Controller
{
    private const SESSION_KEY  = 'bot_messages_unlocked_at';
    private const LOCK_MINUTES = 120;

    private function isUnlocked(): bool
    {
        $ts = session(self::SESSION_KEY);
        return $ts && now()->diffInMinutes($ts, true) < self::LOCK_MINUTES;
    }

    /**
     * Solo acepta label/orden/activo — nunca `value` ni `options_key`. Eso es lo que
     * impide técnicamente que el admin invente una rama de negocio nueva desde acá:
     * el `value` (de lo que rutea el bot) queda siempre fijo, pase lo que pase.
     */
    public function update(Request $request, BotMessageOption $option): RedirectResponse
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $config = BotMessageOptionsRegistry::get($option->options_key);
        if ($config === null) {
            abort(404);
        }

        $data = $request->validate([
            'label'  => ['required', 'string', 'max:255'],
            'orden'  => ['required', 'integer', 'min:1'],
            'activo' => ['required', 'boolean'],
        ]);

        $option->update($data);

        return back()->with('success', 'Opción actualizada.');
    }
}
