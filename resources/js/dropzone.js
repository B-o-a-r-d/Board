/**
 * Alpine component enhancing a Livewire file input with a styled drag-and-drop
 * zone, a live client-side preview (image / video / generic file) and an upload
 * progress bar. Pairs with resources/views/components/dropzone.blade.php.
 *
 * The file input keeps its wire:model binding, so dropping or selecting a file
 * uploads it to Livewire's temp storage; the blade wrapper then triggers the
 * component action (e.g. saveAttachment) on livewire-upload-finish.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('dropzone', () => ({
        dragging: false,
        uploading: false,
        progress: 0,
        error: null,
        preview: null,

        browse() {
            this.$refs.input?.click()
        },

        onDrop(event) {
            this.dragging = false

            const files = event.dataTransfer?.files

            if (! files || ! files.length) {
                return
            }

            this.$refs.input.files = files
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }))
            this.setPreview(files[0])
        },

        onSelect() {
            const file = this.$refs.input?.files?.[0]

            if (file) {
                this.setPreview(file)
            }
        },

        setPreview(file) {
            this.error = null
            this.revoke()

            const type = file.type.startsWith('image/')
                ? 'image'
                : file.type.startsWith('video/')
                    ? 'video'
                    : 'file'

            this.preview = {
                url: type === 'file' ? null : URL.createObjectURL(file),
                type,
                name: file.name,
            }
        },

        clearPreview() {
            this.revoke()
            this.preview = null
            this.progress = 0
        },

        revoke() {
            if (this.preview?.url) {
                URL.revokeObjectURL(this.preview.url)
            }
        },

        destroy() {
            this.revoke()
        },
    }))
})
