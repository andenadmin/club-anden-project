<?php

namespace App\Http\Controllers;

use App\Models\BotMessageOption;
use App\Services\BotMessageOptionsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * Guarda todas las opciones editadas de un mensaje de una — un solo botón en el
     * panel, un solo request. Solo acepta label/orden/activo por opción — nunca
     * `value` ni `options_key`. Eso es lo que impide técnicamente que el admin invente
     * una rama de negocio nueva desde acá: el `value` (de lo que rutea el bot) queda
     * siempre fijo, pase lo que pase.
     */
    public function update(Request $request): RedirectResponse
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $data = $request->validate([
            'options'            => ['required', 'array', 'min:1'],
            'options.*.id'       => ['required', 'integer', Rule::exists('bot_message_options', 'id')],
            'options.*.label'    => ['required', 'string', 'max:255'],
            'options.*.orden'    => ['required', 'integer', 'min:1'],
            'options.*.activo'   => ['required', 'boolean'],
        ]);

        $options = BotMessageOption::whereIn('id', collect($data['options'])->pluck('id'))->get()->keyBy('id');

        foreach ($data['options'] as $o) {
            if (BotMessageOptionsRegistry::get($options[$o['id']]->options_key) === null) {
                abort(404);
            }
        }

        DB::transaction(function () use ($data) {
            foreach ($data['options'] as $o) {
                BotMessageOption::where('id', $o['id'])->update([
                    'label'  => $o['label'],
                    'orden'  => $o['orden'],
                    'activo' => $o['activo'],
                ]);
            }
        });

        return back()->with('success', 'Opciones actualizadas.');
    }
}
