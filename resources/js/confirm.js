/**
 * Global confirmation modal, replacing native window.confirm() / wire:confirm.
 *
 * Usage in Blade (inside a Livewire component so $wire resolves):
 *   <button @click="$store.confirm.open({ message: '…', danger: true })
 *       .then(ok => ok && $wire.deleteBoard())">Supprimer</button>
 *
 * The modal UI lives in resources/views/components/confirm-modal.blade.php,
 * included once in the app layout and bound to this store.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('confirm', {
        shown: false,
        title: 'Confirmation',
        message: '',
        confirmLabel: 'Confirmer',
        cancelLabel: 'Annuler',
        danger: false,
        resolver: null,

        open(options = {}) {
            this.title = options.title ?? 'Confirmation'
            this.message = options.message ?? 'Êtes-vous sûr ?'
            this.confirmLabel = options.confirmLabel ?? 'Confirmer'
            this.cancelLabel = options.cancelLabel ?? 'Annuler'
            this.danger = options.danger ?? false
            this.shown = true

            return new Promise((resolve) => {
                this.resolver = resolve
            })
        },

        accept() {
            this.shown = false
            this.resolver?.(true)
            this.resolver = null
        },

        cancel() {
            this.shown = false
            this.resolver?.(false)
            this.resolver = null
        },
    })
})
