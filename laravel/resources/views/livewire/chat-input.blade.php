<div>
    <div class="card">
        <div class="card-body">
            <form wire:submit.prevent="sendMessage" class="row align-items-center">
                <div class="col-10 ">
                    <input type="text" class="form-control" wire:model="message" placeholder="Type your message...">
                </div>
                <div class="col-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send"></i> Send</button>
                </div>
            </form>
            @error('message') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
