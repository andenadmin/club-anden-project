<?php

namespace App\Http\Controllers;

use App\Models\BotMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BotMessagesAdminController extends Controller
{
    public function index()
    {
        $messages = BotMessage::orderBy('category')->orderBy('id')->get();

        return Inertia::render('bot-messages', [
            'messages' => $messages,
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
}
