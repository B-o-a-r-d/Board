/**
 * Reusable context menu behaviour (right-click on desktop, long-press on mobile).
 * Registered as an Alpine component: `x-data="contextMenu"`.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('contextMenu', () => ({
        shown: false,
        x: 0,
        y: 0,
        longPressTimer: null,

        openAt(clientX, clientY) {
            this.shown = true;
            this.$nextTick(() => {
                const menu = this.$refs.menu;
                if (! menu) return;
                const { offsetWidth: w, offsetHeight: h } = menu;
                this.x = clientX + w > window.innerWidth ? Math.max(4, clientX - w) : clientX;
                this.y = clientY + h > window.innerHeight ? Math.max(4, window.innerHeight - h - 4) : clientY;
            });
        },

        onContextMenu(event) {
            event.preventDefault();
            this.openAt(event.clientX, event.clientY);
        },

        startLongPress(event) {
            if (! event.touches || event.touches.length !== 1) return;
            const touch = event.touches[0];
            this.longPressTimer = setTimeout(() => {
                this.openAt(touch.clientX, touch.clientY);
            }, 450);
        },

        cancelLongPress() {
            clearTimeout(this.longPressTimer);
        },
    }));
});
