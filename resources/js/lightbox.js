/**
 * Global media lightbox (images + videos). Open from anywhere with a media
 * list + start index. Each item is either a plain image URL (string) or an
 * object { type: 'image' | 'video', url, mime }:
 *
 *   <img @click="$store.lightbox.open(@js($media), {{ $index }})">
 *
 * Viewer UI lives in resources/views/components/lightbox.blade.php.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('lightbox', {
        shown: false,
        items: [],
        index: 0,
        zoomed: false,

        open(items, index = 0) {
            const list = Array.isArray(items) ? items : [items]

            this.items = list.map((item) =>
                typeof item === 'string' ? { type: 'image', url: item } : item
            )
            this.index = index
            this.zoomed = false
            this.shown = this.items.length > 0
        },

        close() {
            this.shown = false
            this.zoomed = false
        },

        next() {
            if (this.items.length) {
                this.index = (this.index + 1) % this.items.length
                this.zoomed = false
            }
        },

        prev() {
            if (this.items.length) {
                this.index = (this.index - 1 + this.items.length) % this.items.length
                this.zoomed = false
            }
        },

        get current() {
            return this.items[this.index] ?? null
        },
    })
})
