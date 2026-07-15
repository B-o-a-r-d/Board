import Sortable from 'sortablejs';

/**
 * Card drag & drop.
 *
 * Livewire's native `wire:sort` has no delay option, so long-press-to-drag
 * (essential on touch) is impossible with it. We drive card sorting with our
 * own SortableJS instance instead: press-and-hold starts the drag, a plain tap
 * falls through to the card's `wire:click` (open the card).
 *
 * Exposed as a global helper (not an `Alpine.data` component) so the cards
 * `<ul>` keeps its plain `x-ref="cards"` — the parent list scope relies on that
 * ref to count cards for the WIP limit, and a nested `x-data` would move the ref
 * boundary. The instance is tracked in a WeakMap keyed by the container so a
 * Livewire morph never double-initialises it; SortableJS delegates over the
 * container's children, so cards added/removed by a morph (or a realtime
 * broadcast) stay draggable without re-initialising.
 *
 * @param {HTMLElement} el   the cards `<ul>` (carries `data-list-id`)
 * @param {object} wire      the Livewire `$wire` proxy for the board component
 */
const instances = new WeakMap();

// A single drag runs at a time across all columns, so a module-level flag is
// enough to coordinate both the click guard and the cross-list handoff.
let dragging = false;
let clickGuardInstalled = false;

/**
 * Ending a drag with the pointer still over a card fires a synthetic `click`
 * (common on short cross-list drops), which would open the card. A single
 * capture-phase listener swallows that click before it reaches the card's
 * `wire:click`. It only bites while a drag is in flight, so normal taps and the
 * card's own controls are untouched.
 */
function installClickGuard() {
    if (clickGuardInstalled) {
        return;
    }

    clickGuardInstalled = true;

    document.addEventListener(
        'click',
        (event) => {
            if (dragging && event.target.closest?.('[data-card]')) {
                event.stopImmediatePropagation();
                event.preventDefault();
            }
        },
        true,
    );
}

window.initCardSortable = function (el, wire) {
    if (instances.has(el)) {
        return;
    }

    installClickGuard();

    const sortable = Sortable.create(el, {
        group: 'cards',
        draggable: '[data-card]',
        // Interactive controls (menu button, links, inputs) must not start a
        // drag; their own click still fires (preventOnFilter: false).
        filter: 'button, a, input, textarea, select, [data-no-drag]',
        preventOnFilter: false,
        // Long-press to drag. The delay only guards touch (delayOnTouchOnly), so
        // a mouse drags immediately and a finger swipe still scrolls the column.
        delay: 180,
        delayOnTouchOnly: true,
        touchStartThreshold: 5,
        // Fallback drag avoids the native HTML5 drag image the browser would
        // otherwise build from any <img> inside the card. Append the clone to
        // <body> so the drag preview follows the pointer everywhere — inside the
        // list the clone is clipped by the column's overflow + backdrop-filter
        // (which makes it the containing block for the fixed-positioned clone).
        forceFallback: true,
        fallbackOnBody: true,
        fallbackClass: 'board-drag-clone',
        animation: 150,
        ghostClass: 'opacity-40',
        onStart: () => {
            dragging = true;
            // Let a column's infinite-scroll loader stand down mid-drag, so it
            // never morphs the list (appending a page) while a card is in hand.
            window.boardCardDragging = true;
        },
        onEnd: (evt) => {
            const cardId = Number(evt.item.dataset.cardId);
            const toListId = Number(evt.to.dataset.listId);

            // Release the guard only after the synchronous post-drop click has
            // had its chance to fire (and be swallowed).
            setTimeout(() => {
                dragging = false;
                window.boardCardDragging = false;
            }, 0);

            if (!cardId || !toListId) {
                return;
            }

            // `position` is the 0-based target index (see resequence()).
            wire.moveCard(cardId, evt.newIndex, toListId);
        },
    });

    instances.set(el, sortable);
};
