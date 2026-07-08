/**
 * Timeline (Gantt) drag & resize.
 *
 * Bars carry `data-tl-bar` + `data-card-id` + `data-start`/`data-due` (Y-m-d).
 * Three grab zones inside each bar declare `data-tl-handle`:
 *   - "move"  → drag the whole bar to shift both dates by the day delta
 *   - "start" → drag the left edge to change the start date
 *   - "end"   → drag the right edge to change the due date
 *
 * A drag below the movement threshold is treated as a click (opens the card);
 * a real drag is committed via $wire.setCardSchedule and its post-drop click is
 * swallowed. Day math is done on the Y-m-d parts to avoid UTC drift.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('timeline', () => ({
        dayWidth: 40,

        init() {
            const root = this.$el;
            const wire = this.$wire;
            const dayWidth = this.dayWidth;
            const threshold = 4;
            let drag = null;
            let suppressClick = false;

            const shiftDate = (iso, days) => {
                if (! iso) {
                    return null;
                }
                const [y, m, d] = iso.split('-').map(Number);
                const dt = new Date(y, m - 1, d + days);
                const mm = String(dt.getMonth() + 1).padStart(2, '0');
                const dd = String(dt.getDate()).padStart(2, '0');

                return `${dt.getFullYear()}-${mm}-${dd}`;
            };

            const onMove = (event) => {
                if (! drag) {
                    return;
                }

                const dx = event.clientX - drag.startX;

                if (! drag.active) {
                    if (Math.abs(dx) < threshold) {
                        return;
                    }
                    drag.active = true;
                    drag.bar.classList.add('opacity-80', 'z-20', 'shadow-lg');
                }

                if (drag.mode === 'move') {
                    drag.bar.style.left = `${drag.origLeft + dx}px`;
                } else if (drag.mode === 'start') {
                    const width = Math.max(dayWidth, drag.origWidth - dx);
                    drag.bar.style.left = `${drag.origLeft + (drag.origWidth - width)}px`;
                    drag.bar.style.width = `${width}px`;
                } else {
                    drag.bar.style.width = `${Math.max(dayWidth, drag.origWidth + dx)}px`;
                }
            };

            const onUp = (event) => {
                window.removeEventListener('pointermove', onMove);

                if (! drag) {
                    return;
                }

                const d = drag;
                drag = null;

                if (! d.active) {
                    return; // a plain click — let it open the card
                }

                suppressClick = true;
                setTimeout(() => { suppressClick = false; }, 0);
                d.bar.classList.remove('opacity-80', 'z-20', 'shadow-lg');

                const deltaDays = Math.round((event.clientX - d.startX) / dayWidth);

                if (deltaDays === 0) {
                    d.bar.style.left = `${d.origLeft}px`;
                    d.bar.style.width = `${d.origWidth}px`;

                    return;
                }

                let newStart = d.cardStart;
                let newDue = d.cardDue;

                if (d.mode === 'move') {
                    newStart = shiftDate(d.cardStart, deltaDays);
                    newDue = shiftDate(d.cardDue, deltaDays);
                } else if (d.mode === 'start') {
                    newStart = shiftDate(d.cardStart || d.cardDue, deltaDays);
                } else {
                    newDue = shiftDate(d.cardDue || d.cardStart, deltaDays);
                }

                wire.setCardSchedule(d.cardId, newStart, newDue);
            };

            root.addEventListener('pointerdown', (event) => {
                const handle = event.target.closest('[data-tl-handle]');
                const bar = handle?.closest('[data-tl-bar]');

                if (! handle || ! bar) {
                    return;
                }

                drag = {
                    bar,
                    mode: handle.dataset.tlHandle,
                    active: false,
                    startX: event.clientX,
                    cardId: Number(bar.dataset.cardId),
                    cardStart: bar.dataset.start || null,
                    cardDue: bar.dataset.due || null,
                    origLeft: parseFloat(bar.style.left) || 0,
                    origWidth: parseFloat(bar.style.width) || dayWidth,
                };

                window.addEventListener('pointermove', onMove);
                window.addEventListener('pointerup', onUp, { once: true });
            });

            // Swallow the click that follows a real drag so it doesn't open the card.
            root.addEventListener('click', (event) => {
                if (suppressClick && event.target.closest('[data-tl-bar]')) {
                    event.stopImmediatePropagation();
                    event.preventDefault();
                }
            }, true);
        },
    }));
});
