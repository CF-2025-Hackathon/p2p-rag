import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

var reverbPort = import.meta.env.VITE_REVERB_PORT;
if (window.location.port == 81) {
    reverbPort = 8081;
} else if (window.location.port == 82) {
    reverbPort = 8082;
}

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: reverbPort ?? 80,
    wssPort: reverbPort ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
