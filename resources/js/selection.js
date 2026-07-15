/**
 * Global multi-select store for the board's cards.
 *
 * The board is split into one <livewire:boards.list-column> per list, and each
 * Livewire component is an Alpine scope boundary — so the selection state can't
 * live in the parent Show's x-data (the cards, rendered inside the child
 * component, wouldn't see it). A global store is reachable from every component
 * (the parent's bulk-action bar and the cards inside each column alike), and is
 * also defined before Alpine walks the DOM, so there is no undefined-scope flash.
 *
 * Blade usage:
 *   $store.selection.mode            // select mode on/off
 *   $store.selection.has(id)         // is this card selected
 *   $store.selection.toggle(id)      // add / remove a card
 *   $store.selection.toggleMode()    // flip select mode (clears on exit)
 *   $store.selection.ids             // selected card ids (for bulk actions)
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('selection', {
        ids: [],
        mode: false,

        has(id) {
            return this.ids.includes(id);
        },

        toggle(id) {
            this.ids = this.has(id) ? this.ids.filter((i) => i !== id) : [...this.ids, id];
        },

        toggleMode() {
            this.mode = ! this.mode;

            if (! this.mode) {
                this.ids = [];
            }
        },

        clear() {
            this.ids = [];
        },

        reset() {
            this.ids = [];
            this.mode = false;
        },
    });
});
