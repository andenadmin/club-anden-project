<?php

namespace App\Http\Controllers;

use App\Models\BotMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BotMessagesAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('bot-messages', [
            'messages' => BotMessage::where('is_archived', false)->orderBy('category')->orderBy('id')->get(),
            'archived' => BotMessage::where('is_archived', true)->orderBy('category')->orderBy('id')->get(),
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
}
