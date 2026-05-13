<?php

namespace App\Http\Controllers;

use App\Models\BotMessage;
use App\Services\BotMessages;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BotMessagesAdminController extends Controller
{
    public function index()
    {
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
        $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $botMessage->update(['content' => $request->content]);

        return back()->with('success', 'Mensaje guardado.');
    }

    public function archive(BotMessage $botMessage)
    {
        $botMessage->update(['is_archived' => true]);
        return back()->with('success', 'Mensaje archivado.');
    }

    public function restore(BotMessage $botMessage)
    {
        $botMessage->update(['is_archived' => false]);
        return back()->with('success', 'Mensaje restaurado.');
    }

    public function resetDefault(BotMessage $botMessage)
    {
        $default = BotMessages::hardcodedDefault($botMessage->key);

        if ($default === null) {
            return back()->with('error', 'Este mensaje es dinámico y no tiene un valor por defecto fijo.');
        }

        $botMessage->update(['content' => $default]);
        return back()->with('success', 'Mensaje restaurado al valor por defecto.');
    }
}
