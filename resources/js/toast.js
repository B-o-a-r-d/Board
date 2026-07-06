/**
 * Toast helper. Exposes window.toast() and bridges Livewire's
 * $dispatch('toast', { message, type, description, position }) to the toast
 * container (resources/views/components/toasts.blade.php).
 *
 *   window.toast('Enregistré', { type: 'success' })
 *   // From Livewire: $this->dispatch('toast', message: 'Enregistré', type: 'success');
 */
window.toast = function (message, options = {}) {
    window.dispatchEvent(
        new CustomEvent('toast-show', {
            detail: {
                type: options.type ?? 'default',
                message: message ?? '',
                description: options.description ?? '',
                position: options.position ?? 'bottom-right',
                html: options.html ?? '',
            },
        })
    )
}

// Livewire dispatches arrive as a browser "toast" event; forward them.
window.addEventListener('toast', (event) => {
    const detail = event.detail?.params?.[0] ?? event.detail ?? {}
    window.toast(detail.message ?? '', detail)
})
