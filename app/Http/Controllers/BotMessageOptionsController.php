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

    public function store(Request $request): RedirectResponse
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $data = $request->validate([
            'options_key' => ['required', 'string'],
            'label'       => ['required', 'string', 'max:255'],
        ]);

        $config = BotMessageOptionsRegistry::get($data['options_key']);
        if (!$config || !($config['allowAddRemove'] ?? false)) {
            abort(422, 'No se pueden agregar opciones a este grupo.');
        }

        $maxOrden = BotMessageOption::where('options_key', $data['options_key'])->max('orden') ?? 0;

        BotMessageOption::create([
            'options_key' => $data['options_key'],
            'value'       => 'custom_' . uniqid(),
            'label'       => $data['label'],
            'orden'       => $maxOrden + 1,
            'activo'      => true,
        ]);

        return back()->with('success', 'Opción agregada.');
    }

    public function destroy(BotMessageOption $option): RedirectResponse
    {
        if (!$this->isUnlocked()) {
            return redirect()->route('bot.messages.unlock');
        }

        $config = BotMessageOptionsRegistry::get($option->options_key);
        if (!$config || !($config['allowAddRemove'] ?? false)) {
            abort(422, 'No se pueden eliminar opciones de este grupo.');
        }

        $option->delete();

        return back()->with('success', 'Opción eliminada.');
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
