/*
 * Fuchs-Helfer Rua (gemeinsamer Baustein). Begrüßt, lobt, gibt Tipps, hilft bei Fehlern und beim Warten.
 * Spricht von sich aus höchstens alle 3 Minuten (oder nach einem Erfolg),
 * sonst nur, wenn man ihn antippt. Schläft nach einer Weile Ruhe ein.
 * „Schlafen legen“ merkt sich der Browser (localStorage), bis man ihn antippt.
 *
 * Fox helper Rua (shared building block). Greets, praises, gives tips, helps with errors and waiting.
 * Speaks on its own at most every 3 minutes (or after a success),
 * otherwise only when tapped. Falls asleep after a while of quiet.
 * The browser remembers “Schlafen legen” (put to sleep) in localStorage until it is tapped.
 */
(function () {
    'use strict';
    const fox = document.getElementById('fox');
    if (!fox) return;
    const P = (fox.dataset.prefix || 'fox') + '-';
    // Schlüssel vor Standard 10: „fuchs-…“ statt „fox-…“, „tiefschlaf“/„zuletzt“ statt „sleep“/„last“
    // Keys before standard 10: "fuchs-…" instead of "fox-…", "tiefschlaf"/"zuletzt" instead of "sleep"/"last"
    const OLD_P = P.replace(/fox-$/, 'fuchs-');
    const OLD_KEYS = { sleep: 'tiefschlaf', last: 'zuletzt', position: 'lage' };
    const store = {
        get(k, s) {
            try {
                const st = s ? sessionStorage : localStorage;
                let v = st.getItem(P + k);
                // alten Schlüssel einmal übernehmen und löschen / take over the old key once and delete it
                const old = OLD_P + (OLD_KEYS[k] || k);
                if (v === null && st.getItem(old) !== null) {
                    v = st.getItem(old);
                    st.setItem(P + k, v);
                    st.removeItem(old);
                }
                return v;
            } catch (e) { return null; }
        },
        set(k, v, s) { try { (s ? sessionStorage : localStorage).setItem(P + k, v); } catch (e) { /* egal / ignore */ } },
        remove(k, s) { try { (s ? sessionStorage : localStorage).removeItem(P + k); } catch (e) { /* egal / ignore */ } },
    };
    const deepAsleep = () => fox.classList.contains('deep-sleep');

    const d = JSON.parse(fox.dataset.fox);
    const bubble = fox.querySelector('.fox-bubble');
    const text = fox.querySelector('.fox-text');
    const figure = fox.querySelector('.fox-figure');

    // Reihenfolge: erst Passendes zur Seite, dann gemischt Tipps und Sprüche
    // Order: first what fits the page, then tips and sayings shuffled
    const shuffle = (a) => a.map((x) => [Math.random(), x]).sort((x, y) => x[0] - y[0]).map((x) => x[1]);
    let queue = [];
    let said = 0;
    function nextTip() {
        if (!queue.length) queue = shuffle(d.tips.concat(d.sayings));
        return queue.shift();
    }

    let timer = null;
    function say(t, duration) {
        text.textContent = t;
        bubble.hidden = false;
        fox.classList.add('speaking');
        wake();
        clearTimeout(timer);
        timer = setTimeout(hush, duration || 14000);
        said++;
    }
    function hush() {
        bubble.hidden = true;
        fox.classList.remove('speaking', 'waving');
    }
    function hop() {
        fox.classList.remove('hopping');
        void fox.offsetWidth;   // Animation neu starten / restart the animation
        fox.classList.add('hopping');
    }

    // Einschlafen nach 45 s ohne Bewegung, aufwachen bei jeder Aktion
    // Fall asleep after 45 s without activity, wake up on any action
    let sleepTimer = null;
    function wake() {
        if (deepAsleep()) return;   // aus dem Tiefschlaf weckt nur ein Antippen / only a tap wakes it from deep sleep
        fox.classList.remove('asleep');
        clearTimeout(sleepTimer);
        sleepTimer = setTimeout(() => { if (bubble.hidden) fox.classList.add('asleep'); }, 45000);
    }
    ['pointerdown', 'keydown', 'scroll'].forEach((e) => window.addEventListener(e, wake, { passive: true }));

    function putToSleep() {
        store.set('sleep', '1');
        hush();
        fox.classList.remove('asleep');
        fox.classList.add('deep-sleep');
        figure.title = d.texts.wake;
        figure.setAttribute('aria-label', d.texts.wake);
    }
    function wakeUp() {
        store.remove('sleep');
        fox.classList.remove('deep-sleep');
        figure.title = d.texts.fox;
        figure.setAttribute('aria-label', d.texts.fox);
        hop();
        say(d.texts.woken, 6000);
    }

    /*
     * Verschieben: Mit Maus oder Finger lässt sich Rua an jede Stelle ziehen.
     * Erst ab 6 Pixeln Bewegung zählt es als Ziehen – ein kurzer Tipp befragt
     * ihn weiter. Die Lage merkt sich der Browser als Anteil des Fensters, damit
     * sie auch nach einer Größenänderung im Bild bleibt.
     *
     * Moving: Rua can be dragged anywhere with mouse or finger. Only movement of
     * 6 pixels or more counts as dragging – a short tap still asks him. The browser
     * remembers the position as a share of the window so that it stays in view
     * after a resize.
     */
    function place(l, t) {
        const w = fox.offsetWidth, h = fox.offsetHeight;
        l = Math.max(4, Math.min(window.innerWidth - w - 4, l));
        t = Math.max(4, Math.min(window.innerHeight - h - 4, t));
        fox.style.left = l + 'px';
        fox.style.top = t + 'px';
        fox.style.right = 'auto';
        fox.style.bottom = 'auto';
        fox.classList.toggle('at-left', l + w / 2 < window.innerWidth / 2);
        fox.classList.toggle('at-top', t < 200);
        return [l, t];
    }
    function loadPosition() {
        try {
            const p = JSON.parse(store.get('position') || 'null');
            if (!p) return;
            const w = fox.offsetWidth || 96, h = fox.offsetHeight || 96;
            place(p[0] * (window.innerWidth - w), p[1] * (window.innerHeight - h));
        } catch (e) { /* egal / ignore */ }
    }
    let drag = null, afterDrag = false;
    figure.addEventListener('pointerdown', (e) => {
        if (e.button) return;
        const r = fox.getBoundingClientRect();
        drag = { id: e.pointerId, x: e.clientX, y: e.clientY, l: r.left, t: r.top, moved: false };
    });
    window.addEventListener('pointermove', (e) => {
        if (!drag || e.pointerId !== drag.id) return;
        const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
        if (!drag.moved && Math.hypot(dx, dy) < 6) return;
        if (!drag.moved) {
            drag.moved = true;
            fox.classList.add('dragging');
            try { figure.setPointerCapture(e.pointerId); } catch (x) { /* egal / ignore */ }
        }
        place(drag.l + dx, drag.t + dy);
        e.preventDefault();
    }, { passive: false });
    const release = (e) => {
        if (!drag || e.pointerId !== drag.id) return;
        if (drag.moved) {
            fox.classList.remove('dragging');
            const r = fox.getBoundingClientRect();
            store.set('position', JSON.stringify([
                r.left / Math.max(1, window.innerWidth - r.width),
                r.top / Math.max(1, window.innerHeight - r.height),
            ]));
            afterDrag = true;
        }
        drag = null;
    };
    window.addEventListener('pointerup', release);
    window.addEventListener('pointercancel', release);
    // Nach dem Ziehen kein Klick hinterher / no click after dragging
    figure.addEventListener('click', (e) => {
        if (afterDrag) { afterDrag = false; e.stopImmediatePropagation(); }
    });
    window.addEventListener('resize', loadPosition);
    loadPosition();

    figure.addEventListener('click', () => {
        if (deepAsleep()) { wakeUp(); return; }
        hop();
        if (!bubble.hidden && said > 0) say(nextTip());
        else say(d.context.length && said === 0 ? d.context[0] : nextTip());
    });
    fox.querySelector('.fox-next').addEventListener('click', () => { hop(); say(nextTip()); });
    fox.querySelector('.fox-close').addEventListener('click', hush);
    fox.querySelector('.fox-sleep').addEventListener('click', putToSleep);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !bubble.hidden) hush(); });
    // Wer etwas eintippt (Anmeldung, Formular …), soll nichts verdeckt bekommen
    // Anyone typing (sign-in, form …) should not have anything covered up
    const isField = (e) => e.target.closest && e.target.closest('input, textarea, select, [contenteditable]');
    ['focusin', 'input'].forEach((type) => document.addEventListener(type, (e) => {
        if (!bubble.hidden && isField(e)) hush();
    }));

    /*
     * Fehler und Warten (Figur und Ton: docs/RUA.md).
     * Dauert ein abgeschicktes Formular länger als 0,7 s, sagt er einen Wartesatz
     * („Die Ruder sind im Wasser …“) – geht es schneller, ist die Seite schon weg.
     * Mit data-no-fox am Formular bleibt er still.
     *
     * Errors and waiting (character and tone: docs/RUA.md).
     * If a submitted form takes longer than 0.7 s, he says a waiting line
     * (“The oars are in the water …”) – if it is faster, the page is already gone.
     * With data-no-fox on the form he stays quiet.
     */
    const pick = (a) => (a && a.length ? a[Math.floor(Math.random() * a.length)] : null);
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented || !d.waiting || !d.waiting.length || deepAsleep()) return;
        if (e.target.hasAttribute && e.target.hasAttribute('data-no-fox')) return;
        setTimeout(() => { if (!deepAsleep()) say(pick(d.waiting), 20000); }, 700);
    });

    // Auftritt
    // Entrance
    fox.hidden = false;
    // nicht rAF: pausiert in verborgenen Tabs
    // not rAF: it pauses in hidden tabs
    setTimeout(() => fox.classList.add('shown'), 60);
    if (store.get('sleep')) {
        putToSleep();
        return;   // schläft: keine Begrüßung, keine Tipps / asleep: no greeting, no tips
    }
    wake();
    // Kam die Seite mit einer Fehlermeldung zurück, geht das vor Gruß und Lob
    // If the page came back with an error message, that comes before greeting and praise
    if (d.error) {
        setTimeout(() => { hop(); say(d.error, 12000); }, 900);
        return;
    }
    const last = parseInt(store.get('last') || '0', 10);
    // auf der Anmeldeseite darf er öfter erzählen (freie Software)
    // on the sign-in page it may talk more often (free software)
    const PAUSE = fox.classList.contains('on-login') ? 60 * 1000 : 3 * 60 * 1000;
    const longAgo = Date.now() - last > PAUSE;
    if (d.praise || longAgo || d.context.length && last === 0) {
        setTimeout(() => {
            if (d.praise) { fox.classList.add('waving'); say(d.praise, 6000); }
            else if (d.context.length) say(d.context.shift());
            else { fox.classList.add('waving'); say(d.greeting); }
            store.set('last', String(Date.now()));
        }, 1200);
    }
})();
