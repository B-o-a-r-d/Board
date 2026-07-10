/**
 * Reusable accessible select dropdown, registered as `x-data="selectMenu({...})"`.
 * Emits a `select-change` event carrying the chosen value so a consumer can wire
 * it to Livewire however it likes:
 *   <x-select ... @select-change="$wire.set('prop', $event.detail)" />
 *   <x-select ... @select-change="$wire.method(id, $event.detail)" />
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('selectMenu', (config = {}) => ({
        open: false,
        items: config.items ?? [],
        selected: null,
        active: null,

        init() {
            const initial = config.initial ?? '';
            this.selected = this.items.find((i) => String(i.value) === String(initial)) ?? null;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.active = this.selected ?? this.items[0] ?? null;
                this.$nextTick(() => this.scrollToActive());
            }
        },

        choose(item) {
            if (!item) return;
            this.selected = item;
            this.open = false;
            this.$dispatch('select-change', item.value);
            this.$refs.button?.focus();
        },

        clear() {
            this.selected = null;
            this.open = false;
            this.$dispatch('select-change', '');
            this.$refs.button?.focus();
        },

        next() {
            const i = this.items.indexOf(this.active);
            this.active = this.items[Math.min(i + 1, this.items.length - 1)] ?? this.items[0] ?? null;
            this.scrollToActive();
        },

        prev() {
            const i = this.items.indexOf(this.active);
            this.active = this.items[Math.max(i - 1, 0)] ?? this.items[0] ?? null;
            this.scrollToActive();
        },

        scrollToActive() {
            const el = this.$refs.list?.querySelector('[data-active="true"]');
            el?.scrollIntoView({ block: 'nearest' });
        },
    }));
});
