/**
 * Reusable accessible select dropdown, registered as `x-data="selectMenu({...})"`.
 * Emits a `select-change` event carrying the chosen value so a consumer can wire
 * it to Livewire however it likes:
 *   <x-select ... @select-change="$wire.set('prop', $event.detail)" />
 *   <x-select ... @select-change="$wire.method(id, $event.detail)" />
 *
 * With `multiple: true` the menu stays open, choose() toggles values and the
 * event detail is the array of selected values.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('selectMenu', (config = {}) => ({
        open: false,
        items: config.items ?? [],
        multiple: config.multiple ?? false,
        selected: null,
        values: [],
        active: null,

        init() {
            if (this.multiple) {
                const initial = Array.isArray(config.initial) ? config.initial.map(String) : [];
                this.values = this.items.filter((i) => initial.includes(String(i.value))).map((i) => String(i.value));
            } else {
                const initial = config.initial ?? '';
                this.selected = this.items.find((i) => String(i.value) === String(initial)) ?? null;
            }
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
            if (this.multiple) {
                const value = String(item.value);
                this.values = this.values.includes(value)
                    ? this.values.filter((v) => v !== value)
                    : [...this.values, value];
                this.$dispatch('select-change', this.values);
                return; // stays open for further picks
            }
            this.selected = item;
            this.open = false;
            this.$dispatch('select-change', item.value);
            this.$refs.button?.focus();
        },

        isPicked(item) {
            return this.multiple
                ? this.values.includes(String(item.value))
                : this.selected && this.selected.value === item.value;
        },

        buttonLabel() {
            if (!this.multiple) return this.selected ? this.selected.label : null;
            const labels = this.items.filter((i) => this.values.includes(String(i.value))).map((i) => i.label);
            return labels.length ? labels.join(', ') : null;
        },

        clear() {
            this.selected = null;
            this.values = [];
            this.open = false;
            this.$dispatch('select-change', this.multiple ? [] : '');
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
