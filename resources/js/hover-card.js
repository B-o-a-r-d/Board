/**
 * Hover card for user avatars — a delayed, intent-based popover shown when the
 * pointer lingers over an avatar (name + biography). The delay avoids flashing
 * the card while the pointer merely crosses avatars; the leave delay lets the
 * pointer travel onto the card without it vanishing.
 *
 * The popover is teleported to <body> and fixed-positioned from the avatar's
 * bounding box so it escapes the `overflow-hidden` of card tiles and columns.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('hoverCard', (user = null) => ({
        open: false,
        // Present for JS-rendered avatars (e.g. the presence bar); Blade avatars
        // render their popover content server-side and leave this null.
        user,
        coords: { top: 0, left: 0 },
        enterDelay: 500,
        leaveDelay: 250,
        _enterTimer: null,
        _leaveTimer: null,

        enter() {
            clearTimeout(this._leaveTimer);

            if (this.open) {
                return;
            }

            clearTimeout(this._enterTimer);
            this._enterTimer = setTimeout(() => {
                this.position();
                this.open = true;
            }, this.enterDelay);
        },

        leave() {
            clearTimeout(this._enterTimer);

            if (! this.open) {
                return;
            }

            clearTimeout(this._leaveTimer);
            this._leaveTimer = setTimeout(() => {
                this.open = false;
            }, this.leaveDelay);
        },

        position() {
            const rect = this.$root.getBoundingClientRect();
            const width = 256; // matches w-64
            let left = rect.left + rect.width / 2 - width / 2;
            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

            this.coords = { top: Math.round(rect.bottom + 8), left: Math.round(left) };
        },
    }));
});
