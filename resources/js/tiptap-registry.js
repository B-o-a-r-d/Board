/**
 * Shared TipTap surface for runtime plugins (Power-Ups).
 *
 * ProseMirror breaks the moment two copies of its packages coexist in one
 * page (schema / transaction identity checks), so a plugin must NEVER bundle
 * its own TipTap: everything prosemirror-related has to come from the host's
 * single lazy chunk. This module is that seam:
 *
 *   window.boardTiptap.load()
 *       The host's TipTap modules — Editor, StarterKit, Markdown, the
 *       Node/Mark/Extension cores (for hand-written extensions) and heavier
 *       opt-in packs (tables, image). Lazy, one chunk, loaded once.
 *
 *   window.boardTiptap.register(pluginKey, factory)
 *       A plugin declares a factory that receives the host modules and
 *       returns TipTap extensions. The registry is NAMESPACED by plugin key
 *       and registering mutates nothing global — no live editor is affected.
 *
 *   window.boardTiptap.extensionsFor(...keys)
 *       The extensions of the requested plugin sets only. Each editor site
 *       opts in explicitly, so plugin A's extensions can never leak into
 *       plugin B's editor, nor into the host's own editors.
 */

const factories = new Map()

let modules = null

export async function loadTiptap() {
    if (!modules) {
        const [core, starter, md, table, image, pmState, pmView, yjs, awareness, collaboration, caret] = await Promise.all([
            import('@tiptap/core'),
            import('@tiptap/starter-kit'),
            import('tiptap-markdown'),
            import('@tiptap/extension-table'),
            import('@tiptap/extension-image'),
            import('@tiptap/pm/state'),
            import('@tiptap/pm/view'),
            import('yjs'),
            import('y-protocols/awareness.js'),
            import('@tiptap/extension-collaboration'),
            import('@tiptap/extension-collaboration-caret'),
        ])

        modules = {
            Editor: core.Editor,
            Node: core.Node,
            Mark: core.Mark,
            Extension: core.Extension,
            StarterKit: starter.default,
            Markdown: md.Markdown,
            tables: {
                Table: table.Table,
                TableRow: table.TableRow,
                TableHeader: table.TableHeader,
                TableCell: table.TableCell,
            },
            // Inline images (Shelf embeds uploaded images as markdown ![](url)).
            image: {
                Image: image.Image ?? image.default,
            },
            // ProseMirror primitives for view-only overlays (Shelf highlights
            // commented text ranges with decorations — never stored marks, so
            // the markdown/CRDT stay untouched).
            pm: {
                Plugin: pmState.Plugin,
                PluginKey: pmState.PluginKey,
                Decoration: pmView.Decoration,
                DecorationSet: pmView.DecorationSet,
            },
            // Client-side CRDT collaboration. The transport is the caller's
            // concern (Shelf syncs Yjs updates over Reverb presence whispers) —
            // the host ships NO dedicated collaboration server on purpose.
            collab: {
                Y: yjs,
                Awareness: awareness.Awareness,
                encodeAwarenessUpdate: awareness.encodeAwarenessUpdate,
                applyAwarenessUpdate: awareness.applyAwarenessUpdate,
                removeAwarenessStates: awareness.removeAwarenessStates,
                Collaboration: collaboration.Collaboration,
                CollaborationCaret: caret.CollaborationCaret,
            },
        }
    }

    return modules
}

window.boardTiptap = {
    load: loadTiptap,

    register(pluginKey, factory) {
        factories.set(pluginKey, factory)
    },

    async extensionsFor(...keys) {
        const mods = await loadTiptap()
        const extensions = []

        for (const key of keys.flat()) {
            const factory = factories.get(key)

            if (factory) {
                extensions.push(...((await factory(mods)) ?? []))
            }
        }

        return extensions
    },
}
