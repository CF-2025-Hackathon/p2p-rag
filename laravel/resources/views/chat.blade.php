@extends('app')

@section('content')
    <div class="mb-3">
        @livewire('chat-messages')
    </div>

    <div>
        @livewire('chat-input')
    </div>

    <script>
        document.addEventListener('livewire:init', function () {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        Livewire.on('messageSent', () => {
            document.getElementById('loadingIndicator').style.display = 'block';
        });

        Livewire.on('messageResponded', () => {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
            document.getElementById('loadingIndicator').style.display = 'none';
        });
    </script>
@endsection