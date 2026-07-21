/**
 * Alpine component powering a TipTap WYSIWYG editor with markdown I/O.
 *
 * Usage (see cards/card-detail.blade.php):
 *   <div x-data="markdownEditor(@js($card->description))"> ... </div>
 *
 * Read mode shows the server-rendered markdown; clicking it switches to the
 * editor. Saving serialises the document back to markdown and hands it to
 * Livewire via $wire.saveDescription().
 *
 * TipTap/ProseMirror (~200 KB) is dynamically imported the first time an editor
 * is actually built — it is NOT in the main bundle, so the board list page never
 * pays for it. See {@link loadTiptap}.
 *
 * IMPORTANT: the TipTap instance is kept in a closure variable — NOT on the
 * reactive object. If it lived on `this`, Alpine's reactive Proxy would wrap
 * it and ProseMirror's identity checks (transaction start-state vs view state)
 * would fail with "Applying a mismatched transaction".
 */

let tiptap = null

/** Load TipTap once, on demand, as a separate chunk. */
async function loadTiptap() {
    if (!tiptap) {
        const [core, starter, md] = await Promise.all([
            import('@tiptap/core'),
            import('@tiptap/starter-kit'),
            import('tiptap-markdown'),
        ])
        tiptap = { Editor: core.Editor, StarterKit: starter.default, Markdown: md.Markdown }
    }

    return tiptap
}

// Public seam for runtime plugins (Power-Ups): they render Blade inside the
// host and cannot ship their own bundle, so this is how a plugin page (e.g.
// Shelf notes) builds a TipTap editor while reusing the host's lazy chunk.
window.boardTiptapLoader = loadTiptap

document.addEventListener('alpine:init', () => {
    window.Alpine.data('markdownEditor', (initial = '') => {
        let editor = null

        return {
            editing: false,
            source: initial || '',

            async buildEditor() {
                // The mount node is x-ignore so Alpine's mutation observer
                // leaves TipTap's contenteditable alone.
                const mount = this.$root.querySelector('.js-editor-mount')

                if (!mount) {
                    return
                }

                const { Editor, StarterKit, Markdown } = await loadTiptap()

                if (editor) {
                    editor.destroy()
                    editor = null
                }

                mount.innerHTML = ''

                editor = new Editor({
                    element: mount,
                    extensions: [
                        StarterKit.configure({
                            heading: { levels: [1, 2, 3] },
                        }),
                        Markdown.configure({
                            html: false,
                            linkify: true,
                            breaks: true,
                            transformPastedText: true,
                        }),
                    ],
                    content: this.source,
                    editorProps: {
                        attributes: {
                            class: 'tiptap markdown min-h-[8rem] rounded-b-lg px-3 py-2 text-sm focus:outline-none',
                        },
                    },
                })
            },

            edit() {
                this.editing = true
                this.$nextTick(async () => {
                    if (!editor || editor.isDestroyed) {
                        await this.buildEditor()
                    }
                    editor?.commands.focus('end')
                })
            },

            cancel() {
                this.editing = false
                editor?.commands.setContent(this.source)
            },

            save() {
                const markdown = editor
                    ? editor.storage.markdown.getMarkdown()
                    : this.source
                this.source = markdown

                // The wrapper is wire:ignore, so refresh the read view ourselves.
                if (this.$refs.readview) {
                    this.$refs.readview.innerHTML = markdown.trim()
                        ? editor.getHTML()
                        : '<span class="text-neutral-400">Ajoutez une description…</span>'
                }

                this.$wire.saveDescription(markdown)
                this.editing = false
            },

            run(command, ...args) {
                editor?.chain().focus()[command](...args).run()
            },

            toggleLink() {
                if (!editor) {
                    return
                }

                if (editor.isActive('link')) {
                    editor.chain().focus().unsetLink().run()

                    return
                }

                const url = window.prompt('URL du lien :')

                if (url) {
                    editor.chain().focus().setLink({ href: url }).run()
                }
            },

            isActive(name, attrs = {}) {
                return editor ? editor.isActive(name, attrs) : false
            },

            destroy() {
                editor?.destroy()
                editor = null
            },
        }
    })
})
