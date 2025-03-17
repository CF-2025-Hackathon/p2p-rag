<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use App\Http\Controllers\Api\P2PController;

class ChatMessages extends Component
{
    public $messages = [];
    public $isLoading = false; // Variable to manage loading state
    public $loadingMessage = 'Your friend is thinking...'; // Default loading message


    protected $listeners = [
        'messageSent',
        'refreshChat',
    ];

    public function mount()
    {
        $this->messages = Message::latest()->take(20)->get()->reverse()->toArray();
    }

    public function refreshChat()
    {
        $this->messages = Message::latest()->take(20)->get()->reverse()->toArray();
    }

    public function messageSent($message)
    {
        $this->isLoading = true; // Set loading state to true

        $controller = new P2PController();
        $response = $controller->query(request()->merge([
            'question' => $message,
            'perPage' => 1,
        ]));

        $data = $response->getData();
        Message::create([
            'name' => 'AI',
            'message' => $data->response[0]->summary,
            'you' => false,
        ]);

        sleep(1); // Another simulated delay

        // Send the message
        $this->messages = Message::latest()->take(20)->get()->reverse()->toArray();

        $this->isLoading = false; // Turn off loading state

        // Emit event
        $this->dispatch('messageResponded');
    }

    public function render()
    {
        return view('livewire.chat-messages');
    }
}
