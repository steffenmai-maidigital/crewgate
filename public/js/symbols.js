/*
 * Einheitliche Symbole in der Oberfläche (gemeinsamer Baustein, Standard 03).
 * Emojis in Menüs, Knöpfen, Überschriften, Dialogen und Fuchs-Blasen werden
 * gegen die Twemoji-Grafiken unter /img/emoji ausgetauscht, damit sie auf jedem
 * Gerät gleich aussehen. Auch später eingefügte Inhalte werden erfasst.
 * Eingabefelder, Code und reine Textzeichen (→ ✓ ▶ ◐) bleiben, wie sie sind.
 * Fehlt eine Grafik, bleibt das Emoji des Geräts stehen.
 *
 * Einbau: <meta name="symbol-base" content="{{ rtrim(asset(''), '/') }}">
 *         <script src="{{ asset('js/symbols.js') }}" defer></script>
 * Grafiken sammeln die Werkzeuge der Betreuer ein.
 *
 * Uniform symbols in the interface (shared building block, Standard 03).
 * Emojis in menus, buttons, headings, dialogs and fox speech bubbles are
 * swapped for the Twemoji graphics under /img/emoji so that they look the
 * same on every device. Content inserted later is covered as well.
 * Input fields, code and plain text characters (→ ✓ ▶ ◐) stay as they are.
 * If a graphic is missing, the device's own emoji remains.
 *
 * Setup: <meta name="symbol-base" content="{{ rtrim(asset(''), '/') }}">
 *        <script src="{{ asset('js/symbols.js') }}" defer></script>
 * The graphics are collected by the maintainers' tools.
 */
(function () {
    'use strict';

    const PATTERN = /\p{Extended_Pictographic}(?:️|[\u{1F3FB}-\u{1F3FF}])?(?:‍\p{Extended_Pictographic}(?:️|[\u{1F3FB}-\u{1F3FF}])?)*|[0-9#*]️⃣/gu;
    // nur Zeichen, die als buntes Emoji gemeint sind, nicht ✓ → ▶ ©
    // only characters meant as colour emoji, not ✓ → ▶ ©
    const isEmoji = (s) => s.includes('️') || /^\p{Emoji_Presentation}/u.test(s) || s.codePointAt(0) >= 0x1F000;
    const SKIP = 'script, style, textarea, input, select, option, svg, code, pre, [contenteditable], '
        + '[data-emoji], .emoji-image, .device-emoji, .no-symbols';
    // „symbol-basis“/„bw-basis“: alte Namen (vor Standard 10) / old names (before standard 10)
    const meta = document.querySelector('meta[name="symbol-base"]') || document.querySelector('meta[name="symbol-basis"]')
        || document.querySelector('meta[name="bw-basis"]') || {};
    const base = meta.content || '';

    function code(character) {
        const cps = Array.from(character, (z) => z.codePointAt(0).toString(16));
        return (cps.includes('200d') ? cps : cps.filter((c) => c !== 'fe0f')).join('-');
    }

    function image(character) {
        const img = document.createElement('img');
        img.className = 'emoji-image emoji-symbol';
        img.alt = character;
        img.draggable = false;
        img.decoding = 'async';
        img.addEventListener('error', () => {
            // keine Grafik: Geräte-Emoji behalten (markiert, damit es nicht erneut ausgetauscht wird)
            // no graphic: keep the device emoji (marked so that it is not swapped again)
            const s = document.createElement('span');
            s.className = ['device-emoji'].concat([...img.classList].filter((c) => c.startsWith('space-'))).join(' ');
            s.textContent = character;
            img.replaceWith(s);
        });
        img.src = base + '/img/emoji/' + code(character) + '.svg';
        return img;
    }

    function textNode(node) {
        const text = node.nodeValue;
        if (!text || !PATTERN.test(text)) return;
        PATTERN.lastIndex = 0;
        const parts = document.createDocumentFragment();
        let rest = 0, swapped = false;
        for (const m of text.matchAll(PATTERN)) {
            if (!isEmoji(m[0])) continue;
            const img = image(m[0]);
            // Leerzeichen neben dem Symbol als fester Abstand: in Flex-Menüs
            // verschluckt der Browser sonst das Leerzeichen hinter einem Bild
            // Spaces next to the symbol become a fixed margin: in flex menus
            // the browser would otherwise swallow the space after an image
            let before = text.slice(rest, m.index);
            if (/[  ]$/.test(before) && before.trim()) { before = before.replace(/[  ]$/, ''); img.classList.add('space-left'); }
            if (before) parts.appendChild(document.createTextNode(before));
            parts.appendChild(img);
            rest = m.index + m[0].length;
            if (/^[  ]\S/.test(text.slice(rest))) { rest++; img.classList.add('space-right'); }
            swapped = true;
        }
        if (!swapped) return;
        if (rest < text.length) parts.appendChild(document.createTextNode(text.slice(rest)));
        node.replaceWith(parts);
    }

    function swap(root) {
        if (!root) return;
        if (root.nodeType === Node.TEXT_NODE) {
            const p = root.parentElement;
            if (p && !p.closest(SKIP)) textNode(root);
            return;
        }
        if (root.nodeType !== Node.ELEMENT_NODE || root.closest(SKIP)) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: (n) => (n.parentElement && n.parentElement.closest(SKIP) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        const list = [];
        while (walker.nextNode()) list.push(walker.currentNode);
        list.forEach(textNode);
    }

    function start() {
        swap(document.body);
        new MutationObserver((changes) => {
            for (const a of changes) {
                if (a.type === 'characterData') swap(a.target);
                else a.addedNodes.forEach(swap);
            }
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    window.Symbols = { swap };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
