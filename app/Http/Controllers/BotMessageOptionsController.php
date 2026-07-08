<?php

namespace App\Http\Controllers;

use App\Models\BotMessageOption;
use App\Services\BotMessageOptionsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $optionsKey = $options[$o['id']]->options_key ?? null;
            if (BotMessageOptionsRegistry::get($optionsKey) === null) {
                Log::warning('@BotMessageOptionsController-update: options_key no encontrado en registry', [
                    'option_id'   => $o['id'],
                    'options_key' => $optionsKey,
                ]);
                abort(422, 'Opción con options_key inválido.');
            }
        }

        try {
            DB::transaction(function () use ($data) {
                foreach ($data['options'] as $o) {
                    BotMessageOption::where('id', $o['id'])->update([
                        'label'  => $o['label'],
                        'orden'  => $o['orden'],
                        'activo' => $o['activo'],
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('@BotMessageOptionsController-update: error en transacción al guardar opciones', [
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'No se pudieron guardar las opciones. Intentá de nuevo.']);
        }

        return back()->with('success', 'Opciones actualizadas.');
    }
}
