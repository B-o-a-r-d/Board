import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * Anonymous viewer presence for public (read-only) board pages — "à la Google
 * Docs". Uses a dedicated Echo instance whose authorizer signs the presence
 * channel through the guest endpoint (/share/{token}/presence-auth), so it
 * stays fully isolated from the authenticated app's window.Echo.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('publicPresence', (token, boardId) => ({
        viewers: [],
        echo: null,

        init() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? ''
            const channel = `board-presence.${boardId}`

            this.echo = new Echo({
                broadcaster: 'reverb',
                Pusher,
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
                authorizer: (ch) => ({
                    authorize: (socketId, callback) => {
                        fetch(`/share/${token}/presence-auth`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ socket_id: socketId, channel_name: ch.name }),
                        })
                            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                            .then((data) => callback(null, data))
                            .catch((error) => callback(true, error))
                    },
                }),
            })

            this.echo.join(channel)
                .here((users) => { this.viewers = users })
                .joining((user) => { this.viewers = [...this.viewers, user] })
                .leaving((user) => { this.viewers = this.viewers.filter((v) => v.id !== user.id) })

            document.addEventListener('livewire:navigating', () => this.teardown(channel), { once: true })
        },

        teardown(channel) {
            this.echo?.leave(channel)
            this.echo = null
        },

        destroy() {
            this.teardown(`board-presence.${boardId}`)
        },
    }))
})
