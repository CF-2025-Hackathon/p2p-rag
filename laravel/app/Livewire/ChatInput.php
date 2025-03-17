<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class ChatInput extends Component
{
    public $message;

    protected $rules = [
        'message' => 'required|string|max:255',
    ];

    public function sendMessage()
    {
        $this->validate();

        Message::create([
            'name' => 'You',
            'message' => $this->message,
            'you' => true,
        ]);

        // Emit event to refresh messages
        $this->dispatch('messageSent', message: $this->message);

        $this->message = '';
    }

    public function render()
    {
        return view('livewire.chat-input');
    }
}