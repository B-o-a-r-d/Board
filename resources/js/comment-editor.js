/**
 * Alpine component powering the comment composer: a TipTap editor with markdown
 * I/O and an @mention autocomplete (board members, with avatars), plus the
 * existing "X is typing…" presence whisper.
 *
 * Mentions serialise to "@<slug>" in the markdown body, which the server matches
 * back to a member (see CardDetail::mentionedUsers/renderCommentBody).
 *
 * TipTap/ProseMirror is dynamically imported the first time a composer is built
 * (i.e. when a card modal opens) — it is NOT in the main bundle, so the board
 * list page never pays for it. See {@link loadTiptap}.
 *
 * As in markdown-editor.js, the TipTap instance lives in a closure variable —
 * never on the reactive Alpine object — to avoid ProseMirror identity issues.
 */

let tiptap = null

/** Load TipTap (+ Mention) once, on demand, as separate chunks. */
async function loadTiptap() {
    if (!tiptap) {
        const [core, starter, md, mention] = await Promise.all([
            import('@tiptap/core'),
            import('@tiptap/starter-kit'),
            import('tiptap-markdown'),
            import('@tiptap/extension-mention'),
        ])
        tiptap = {
            Editor: core.Editor,
            StarterKit: starter.default,
            Markdown: md.Markdown,
            Mention: mention.default,
        }
    }

    return tiptap
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('commentEditor', (config = {}) => {
        let editor = null

        return {
            members: config.members || [],
            boardId: config.boardId,
            cardId: config.cardId,
            userId: config.userId,
            userName: config.userName || '',

            empty: true,

            // "X is typing…" presence
            typers: {},
            timers: {},
            channel: null,
            lastPing: 0,

            // @mention popup state, driven by TipTap's suggestion utility
            mention: { open: false, items: [], index: 0, top: 0, left: 0, command: null },

            init() {
                this.setupTyping()
                this.$nextTick(() => this.build())
            },

            setupTyping() {
                if (!window.Echo) return
                this.channel = window.Echo.private('board.' + this.boardId)
                this.channel.listenForWhisper('typing', (e) => {
                    if (e.cardId !== this.cardId || e.id === this.userId) return
                    this.typers = { ...this.typers, [e.id]: e.name }
                    clearTimeout(this.timers[e.id])
                    this.timers[e.id] = setTimeout(() => {
                        const t = { ...this.typers }
                        delete t[e.id]
                        this.typers = t
                    }, 2500)
                })
            },

            ping() {
                const now = Date.now()
                if (now - this.lastPing < 800) return
                this.lastPing = now
                this.channel?.whisper('typing', { id: this.userId, name: this.userName, cardId: this.cardId })
            },

            async build() {
                const mount = this.$root.querySelector('.js-comment-mount')

                if (!mount) return

                const { Editor, StarterKit, Markdown, Mention } = await loadTiptap()

                if (editor) {
                    editor.destroy()
                    editor = null
                }

                mount.innerHTML = ''

                const self = this

                // A Mention that serialises to plain "@<id>" markdown text.
                const MarkdownMention = Mention.extend({
                    addStorage() {
                        return {
                            ...(this.parent?.() || {}),
                            markdown: {
                                serialize: (state, node) => state.write('@' + node.attrs.id),
                                parse: {},
                            },
                        }
                    },
                })

                editor = new Editor({
                    element: mount,
                    extensions: [
                        StarterKit.configure({ heading: { levels: [2, 3] } }),
                        Markdown.configure({ html: false, linkify: true, breaks: true, transformPastedText: true }),
                        MarkdownMention.configure({
                            HTMLAttributes: { class: 'rounded bg-indigo-100 px-1 font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' },
                            renderText: ({ node }) => '@' + node.attrs.id,
                            deleteTriggerWithBackspace: true,
                            suggestion: {
                                char: '@',
                                items: ({ query }) => self.members
                                    .filter((m) => m.name.toLowerCase().includes(query.toLowerCase()) || m.slug.includes(query.toLowerCase()))
                                    .slice(0, 8),
                                render: () => ({
                                    onStart: (props) => self.openMention(props),
                                    onUpdate: (props) => self.openMention(props),
                                    onKeyDown: (props) => self.mentionKeyDown(props),
                                    onExit: () => { self.mention.open = false },
                                }),
                            },
                        }),
                    ],
                    content: '',
                    editorProps: {
                        attributes: {
                            class: 'tiptap markdown min-h-[4.5rem] px-3 py-2 text-sm focus:outline-none',
                        },
                    },
                    onUpdate: () => {
                        self.empty = editor.isEmpty
                        self.ping()
                    },
                })
            },

            openMention(props) {
                this.mention.items = props.items || []
                this.mention.command = props.command
                this.mention.index = 0

                const rect = props.clientRect?.()

                if (rect) {
                    this.mention.top = rect.bottom + 4
                    this.mention.left = rect.left
                }

                this.mention.open = this.mention.items.length > 0
            },

            mentionKeyDown({ event }) {
                if (!this.mention.open) return false

                const n = this.mention.items.length

                if (!n) return false

                if (event.key === 'ArrowDown') { this.mention.index = (this.mention.index + 1) % n; return true }
                if (event.key === 'ArrowUp') { this.mention.index = (this.mention.index - 1 + n) % n; return true }
                if (event.key === 'Enter' || event.key === 'Tab') { this.pickMention(this.mention.index); return true }
                if (event.key === 'Escape') { this.mention.open = false; return true }

                return false
            },

            pickMention(i) {
                const m = this.mention.items[i]

                if (m && this.mention.command) {
                    this.mention.command({ id: m.slug, label: m.name })
                }

                this.mention.open = false
            },

            run(command, ...args) {
                editor?.chain().focus()[command](...args).run()
            },

            isActive(name, attrs = {}) {
                return editor ? editor.isActive(name, attrs) : false
            },

            toggleLink() {
                if (!editor) return

                if (editor.isActive('link')) {
                    editor.chain().focus().unsetLink().run()

                    return
                }

                const url = window.prompt('URL du lien :')

                if (url) {
                    editor.chain().focus().setLink({ href: url }).run()
                }
            },

            async submit() {
                const markdown = editor ? editor.storage.markdown.getMarkdown().trim() : ''

                if (!markdown) return

                await this.$wire.addComment(markdown)

                editor.commands.clearContent()
                this.empty = true
                editor.commands.focus()
            },

            get typingText() {
                const names = Object.values(this.typers)

                if (!names.length) return ''

                return names.length === 1 ? names[0] + ' écrit…' : names.length + ' personnes écrivent…'
            },

            destroy() {
                editor?.destroy()
                editor = null
            },
        }
    })
})
