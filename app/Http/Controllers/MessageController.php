<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Message::class);

        $messages = Message::with('produit:id,nom')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Messages/Index', [
            'messages' => $messages,
        ]);
    }

    public function marquerLu(Message $message): RedirectResponse
    {
        $this->authorize('update', $message);

        $message->update(['lu' => true]);

        return back();
    }

    public function destroy(Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $message->delete();

        return back()->with('flash_success', 'Message supprimé.');
    }
}
