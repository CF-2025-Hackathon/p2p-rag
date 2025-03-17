<div>
    <div class="card position-relative">
        <div class="card-header text-dark d-flex justify-content-between align-items-center">
            <span><i class="bi bi-robot"></i> AI Responses</span>
            <a wire:click="clearMessages"
               href="javascript:void(0)"
               class="link-secondary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">
                Clear Chat
            </a>
        </div>
        <div id="chat-messages" class="card-body" style="height: 300px; overflow-y: auto;">
            @if(count($messages) > 0)
                @foreach($messages as $msg)
                    <div class="mb-2">
                        <strong>{{ $msg['name'] }}:</strong>
                        {{ $msg['message'] }}
                        <small class="text-muted d-block" style="font-size: 12px">{{ $msg['created_at'] }}</small>
                    </div>
                @endforeach
            @else
                <div class="mb-2">
                    <div class="alert alert-primary" role="alert">
                        Currently, no questions have been asked to the AI. Please feel free to ask anything, and I'll be
                        happy to assist you!
                    </div>
                </div>
            @endif
            <div class="loading-indicator" id="loadingIndicator"
                 style="{{ !$isLoading ? 'display:none;' : 'display:block;' }}">
                <div class="spinner-border text-primary spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-1 text-light-emphasis opacity-50">{{ $loadingMessage }}</span>
            </div>
        </div>
    </div>

    <style>
        .loading-indicator {
            position: absolute;
            right: 14px;
            display: flex;
            align-items: center;
            font-size: 14px;
            bottom: 10px;
        }
    </style>
</div>
