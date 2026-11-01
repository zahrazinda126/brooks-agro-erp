/* Shared page behaviour. Plain JS, no build step.
   1. Tables that are not already inside a scrolling container get one, so
      wide tables scroll sideways on phones instead of breaking the page.
   2. Irreversible form actions ask for confirmation once. Buttons or forms
      that already call confirm() keep their own wording and are skipped.
      Any form or button can opt in with data-confirm="Message". */
(function () {
    var SCROLLERS = '.table-wrap, .table-card, .table-scroll, [data-no-table-scroll]';

    function wrapTables(root) {
        root.querySelectorAll('table').forEach(function (table) {
            if (table.closest(SCROLLERS)) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-scroll';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    }

    var DESTRUCTIVE_PATH = /\/(delete|destroy|revoke|close|close-day|end|checkout|cancel)(\/|\?|$)/i;

    function methodOf(form, submitter) {
        if (submitter && submitter.name === '_method') return submitter.value.toUpperCase();
        var field = form.querySelector('input[name="_method"]');
        return field ? field.value.toUpperCase() : (form.getAttribute('method') || 'GET').toUpperCase();
    }

    function hasOwnConfirm(el) {
        return !!el && /confirm\s*\(/.test((el.getAttribute('onclick') || '') + (el.getAttribute('onsubmit') || ''));
    }

    function messageFor(form, submitter) {
        var explicit = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (explicit) return explicit;
        if (hasOwnConfirm(form) || hasOwnConfirm(submitter)) return null;
        var decision = submitter && submitter.name === 'decision' ? submitter.value : '';
        if (/^reject/i.test(decision)) return 'Reject this request? The submitter will be notified.';
        if (methodOf(form, submitter) === 'DELETE') return 'Remove this record? This cannot be undone from this screen.';
        var action = (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || '';
        if (form.method.toUpperCase() === 'POST' && DESTRUCTIVE_PATH.test(action)) return 'Confirm this action? It closes or ends the record.';
        return null;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        var message = messageFor(form, event.submitter);
        if (message && !window.confirm(message)) event.preventDefault();
    }, true);

    /* 3. Keep the tab bar frozen directly under the top bar at any screen width,
          and scroll the active tab into view so the current page is always named. */
    function trackTopbar() {
        var topbar = document.querySelector('.topbar');
        if (!topbar) return;
        var tabs = document.getElementById('contextNav');
        // Heights of the frozen top bar and tab bar, so page buttons can freeze just below them.
        var set = function () {
            document.documentElement.style.setProperty('--topbar-h', topbar.offsetHeight + 'px');
            document.documentElement.style.setProperty('--contextnav-h', (tabs && !tabs.hidden ? tabs.offsetHeight : 0) + 'px');
        };
        set();
        if (window.ResizeObserver) { var ro = new ResizeObserver(set); ro.observe(topbar); if (tabs) ro.observe(tabs); }
        else window.addEventListener('resize', set);
    }

    function revealActiveTab() {
        var active = document.querySelector('#contextNavLinks [aria-current="page"]');
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    /* 4. Standard "Export to Excel" on every screen, always in the same place (the tab bar).
          Pages with their own detailed export (a link to /export...) use it, because it
          includes every record; otherwise the screen's figures, filters and tables are
          sent to /export/page and come back as the standard branded .xlsx. */
    function text(el) {
        var copy = el.cloneNode(true);
        copy.querySelectorAll('button, form, select, input, textarea, script, style, .no-export, [aria-hidden="true"], summary').forEach(function (n) { n.remove(); });
        /* Keep separate lines apart: "21 Sep 2026 · 16:43 · Solomon" rather than "21 Sep 202616:43Solomon". */
        copy.querySelectorAll('br').forEach(function (n) { n.replaceWith(' · '); });
        copy.querySelectorAll('small, em, .table-sub, .data-sub, p, div, li').forEach(function (n) { n.prepend(' · '); });
        return (copy.textContent || '').replace(/\s+/g, ' ').replace(/^(\s*·\s*)+/, '').replace(/(\s*·\s*){2,}/g, ' · ').replace(/\s*·\s*$/, '').trim();
    }

    function headingFor(table) {
        if (table.caption && text(table.caption)) return text(table.caption);
        var node = table;
        while (node && node !== document.body) {
            var caption = node.querySelector(':scope > figcaption, :scope > h2, :scope > h3, :scope > .section-heading h2, :scope > .section-heading h3, :scope > .card-label');
            if (caption && !caption.contains(table)) return text(caption);
            var prev = node.previousElementSibling;
            while (prev) {
                if (/^H[1-4]$/.test(prev.tagName) || prev.matches('.section-heading, .card-label, figcaption')) return text(prev);
                if (prev.querySelector && prev.querySelector('table')) break;
                prev = prev.previousElementSibling;
            }
            node = node.parentElement;
            if (node && node.matches('.content, main')) break;
        }
        return '';
    }

    function tableData(table) {
        var headerRows = table.tHead ? Array.from(table.tHead.rows) : [];
        var headers = [];
        if (headerRows.length) {
            Array.from(headerRows[headerRows.length - 1].cells).forEach(function (cell) {
                headers.push(text(cell));
                for (var i = 1; i < (cell.colSpan || 1); i++) headers.push('');
            });
        }
        var rows = [];
        Array.from(table.rows).forEach(function (row) {
            if (row.parentElement === table.tHead) return;
            var cells = [];
            Array.from(row.cells).forEach(function (cell) {
                cells.push(text(cell));
                for (var i = 1; i < (cell.colSpan || 1); i++) cells.push('');
            });
            if (!headers.length && !rows.length && row.cells.length && Array.from(row.cells).every(function (c) { return c.tagName === 'TH'; })) { headers = cells; return; }
            if (!cells.some(function (c) { return c !== ''; })) return;
            var cls = (row.className || '') + ' ' + (row.parentElement.tagName === 'TFOOT' ? 'total' : '');
            var style = /grand|(^|\s|-)total/.test(cls) ? 'total' : /subtotal|sub-total/.test(cls) ? 'subtotal'
                : (row.cells.length === 1 && (headers.length > 1) ? 'heading' : 'detail');
            rows.push({ cells: cells, style: style });
        });
        return { heading: headingFor(table), headers: headers, rows: rows };
    }

    function pageData() {
        var content = document.querySelector('main.content') || document.body;
        var titleEl = content.querySelector('.page-head h1, .page-head h2, .standard-report-head__main h1, h1, h2');
        var filters = [];
        content.querySelectorAll('form[method="GET"], form[method="get"], form:not([method])').forEach(function (form) {
            form.querySelectorAll('input[name], select[name]').forEach(function (field) {
                if (field.type === 'hidden' || field.type === 'checkbox' && !field.checked) return;
                var value = field.tagName === 'SELECT' ? (field.selectedOptions[0] ? field.selectedOptions[0].text : '') : field.value;
                if (!value || field.tagName === 'SELECT' && field.selectedIndex === 0) return;
                var label = field.closest('.field') ? text(field.closest('.field').querySelector('label') || field) : (field.getAttribute('aria-label') || field.name);
                filters.push({ label: label, value: value });
            });
        });
        var stats = [];
        content.querySelectorAll('.summary-card, .stat-card, .exec-kpi, [data-stat]').forEach(function (card) {
            var label = card.querySelector('[data-stat-label]') || card.querySelector('span, small, p');
            var value = card.querySelector('strong, .figure');
            if (label && value) stats.push({ label: text(label), value: text(value) });
        });
        var skip = function (el) { return el.closest('[data-no-export], .no-export, dialog, form, nav'); };
        var tables = Array.from(content.querySelectorAll('table')).filter(function (t) { return !skip(t); }).map(tableData)
            .filter(function (t) { return t.rows.length || t.headers.length; });
        content.querySelectorAll('.data-list').forEach(function (list) {
            if (skip(list)) return;
            var head = list.querySelector('.data-head');
            var rows = Array.from(list.querySelectorAll('.data-row')).map(function (row) {
                return { cells: Array.from(row.querySelectorAll('.data-cell')).map(text), style: 'detail' };
            }).filter(function (r) { return r.cells.length; });
            if (rows.length) tables.push({ heading: headingFor(list), headers: head ? Array.from(head.children).map(text) : [], rows: rows });
        });
        if (!tables.length) {
            var cards = cardRows(content, skip);
            if (cards.length) tables.push({ heading: '', headers: ['Item', 'Details'], rows: cards });
        }
        return { title: titleEl ? text(titleEl) : document.title, filters: filters, stats: stats, tables: tables };
    }

    /* Card pages: one row per card (its title, then everything else it says). */
    var CARD_SELECTOR = '.report-link, .directive-card, .communication-card, .approval-card, article, .exec-panel, section.card, div.card, .department-report';
    function cardRows(content, skip) {
        var picked = Array.from(content.querySelectorAll(CARD_SELECTOR)).filter(function (card) {
            if (skip(card) || card.matches('.filters, .page-head, .disclosure') || card.closest('details:not([open])')) return false;
            /* Use the innermost cards only, so a panel holding several cards gives one row per card. */
            return !card.querySelector(CARD_SELECTOR);
        });
        return picked.map(function (card) {
            var titleEl = card.querySelector('h2, h3, h4, strong, b');
            var title = titleEl ? text(titleEl) : '';
            var all = text(card);
            var rest = title && all.indexOf(title) === 0 ? all.slice(title.length).replace(/^\s*·\s*/, '') : all;
            return { cells: [title || rest.slice(0, 80), title ? rest : ''], style: 'detail' };
        }).filter(function (row) { return row.cells[0] || row.cells[1]; });
    }

    function exportPage() {
        var url = document.querySelector('meta[name="page-export-url"]');
        var token = document.querySelector('meta[name="csrf-token"]');
        if (!url || !token) return;
        var data = pageData();
        if (!data.tables.length && !data.stats.length) { window.alert('There is nothing on this page to export.'); return; }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url.content;
        form.hidden = true;
        [['_token', token.content], ['payload', JSON.stringify(data)]].forEach(function (pair) {
            var field = document.createElement('textarea');
            field.name = pair[0];
            field.value = pair[1];
            form.appendChild(field);
        });
        document.body.appendChild(form);
        form.submit();
        setTimeout(function () { form.remove(); }, 1000);
    }

    /* 6. A page's main action (marked data-bar-action) moves into the frozen bar, so it stays
          visible while scrolling, next to Export to Excel. */
    function moveBarActions() {
        var bar = document.getElementById('contextNav');
        var content = document.querySelector('main.content');
        if (!bar || !content) return;
        content.querySelectorAll('[data-bar-action]').forEach(function (action) {
            action.classList.remove('btn--inline');
            action.classList.add('context-nav__action');
            bar.appendChild(action);
        });
    }

    /* 7. Report pages (they carry a print-only report header) get a Print button in the bar. */
    function hasExportable(content) {
        if (content.querySelector('table, .data-list, .summary-card, .stat-card, .exec-kpi, [data-stat]')) return true;
        return cardRows(content, function (el) { return el.closest('[data-no-export], .no-export, dialog, form, nav'); }).length > 0;
    }

    function addPrintButton() {
        var bar = document.getElementById('contextNav');
        var content = document.querySelector('main.content');
        if (!bar || !content || !(content.querySelector('.standard-report-head') || hasExportable(content))) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'context-nav__print';
        button.textContent = 'Print / PDF';
        button.title = 'Print this page, or choose "Save as PDF" to download it';
        button.addEventListener('click', function () { window.print(); });
        bar.appendChild(button);
    }

    function addExportButton() {
        var bar = document.getElementById('contextNav');
        var content = document.querySelector('main.content');
        if (!bar || !content || !document.querySelector('meta[name="page-export-url"]')) return;
        if (!hasExportable(content)) return;
        var own = content.querySelector('a[href*="/export"]:not([href*="/export/page"])');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'context-nav__export';
        button.textContent = 'Export to Excel';
        button.title = own ? 'Download the full report in Excel' : 'Download what this page shows in Excel';
        button.addEventListener('click', function () { if (own) window.location.href = own.href; else exportPage(); });
        bar.appendChild(button);
    }

    /* 5. One way to get somewhere: links on the page that go where a tab in the bar (or the
          sidebar's Dashboard) already goes are hidden, and so is any menu left empty by that.
          Links inside forms (Clear, Today…), tables and exports are never touched. */
    function sameDestination(href) {
        try {
            var url = new URL(href, window.location.href);
            /* Date settings do not change which page it is, so they are ignored. */
            var params = Array.from(url.searchParams.entries()).filter(function (p) { return ['from', 'to', 'year', 'quarter', 'month', 'period'].indexOf(p[0]) === -1; })
                .sort().map(function (p) { return p.join('='); }).join('&');
            return url.origin + url.pathname.replace(/\/+$/, '') + (params ? '?' + params : '');
        } catch (e) { return null; }
    }

    function hideDuplicateLinks() {
        var content = document.querySelector('main.content');
        var tabs = document.querySelectorAll('#contextNavLinks a[href]');
        if (!content || !tabs.length) return;
        var taken = new Set();
        tabs.forEach(function (tab) { taken.add(sameDestination(tab.href)); });
        var dashboard = document.querySelector('.drawer a[href$="/dashboard"]');
        if (dashboard) taken.add(sameDestination(dashboard.href));
        var hidden = [];
        content.querySelectorAll('a[href]').forEach(function (link) {
            if (link.closest('form, table, #sectionScreens, #contextNav, .bottom-nav, [data-keep-link]') || /\/export/.test(link.getAttribute('href'))) return;
            if (link.getAttribute('href').charAt(0) === '#') return;
            if (!taken.has(sameDestination(link.href))) return;
            link.hidden = true;
            link.classList.add('is-duplicate-link');
            hidden.push(link);
        });
        /* Remove menus and card grids whose every link is now hidden. */
        hidden.forEach(function (link) {
            var group = link.parentElement;
            while (group && group !== content) {
                var links = group.querySelectorAll('a[href]');
                var allHidden = links.length && Array.from(links).every(function (a) { return a.hidden; });
                var onlyLinks = Array.from(group.children).every(function (child) { return child.tagName === 'A' || child.hidden; });
                if (allHidden && (onlyLinks || group.tagName === 'NAV')) { group.hidden = true; group = group.parentElement; continue; }
                break;
            }
        });
    }

    window.brooksPageData = pageData; // used by tests and support to see what an export will contain
    window.brooksExportPage = exportPage; // in-page "Export to Excel" buttons (e.g. the Finance dashboard)

    /* 8. Short data-entry forms get an icon tile beside each box (as on Customer Register).
          Only when the form has 1–8 boxes and every one has an obvious icon; long forms keep the plain look. */
    var FIELD_ICONS = {
        person: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        phone: '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        email: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        place: '<path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        date: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        time: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
        money: '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 9.5v5M17.5 9.5v5"/>',
        count: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        note: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        list: '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        file: '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>'
    };
    function iconForField(control, labelText) {
        var name = (control.name || '').toLowerCase(), type = (control.type || '').toLowerCase(), text = (labelText || '').toLowerCase();
        var both = name + ' ' + text;
        if (type === 'password') return 'lock';
        if (type === 'file') return 'file';
        if (type === 'email' || /e-?mail/.test(both)) return 'email';
        if (type === 'tel' || /phone|mobile|tel\b|contact/.test(both)) return 'phone';
        if (type === 'date' || type === 'datetime-local' || type === 'month' || /date|_on\b|\bon$|day\b/.test(both)) return 'date';
        if (type === 'time' || /time\b|_at\b/.test(both)) return 'time';
        if (/address|location|venue|place|district|village/.test(both)) return 'place';
        if (/amount|cost|price|ugx|salary|wage|fee|limit|budget|paid|balance|total|account|bank|wallet/.test(both)) return 'money';
        if (control.tagName === 'TEXTAREA' || /note|description|reason|purpose|comment|remark|details|agenda/.test(both)) return 'note';
        if (/quantity|qty|kg|bags|count|number|no\.|reference|invoice|receipt|code|tag|serial|weight|age|week/.test(both)) return 'count';
        if (/name|customer|supplier|person|owner|driver|by\b|employee|staff|worker|host|visitor/.test(both)) return 'person';
        if (control.tagName === 'SELECT') return 'list';
        return null;
    }
    function decorateForms() {
        if (document.querySelector('.shell--plain')) return;
        document.querySelectorAll('main.content form[method="POST"], main.content form[method="post"]').forEach(function (form) {
            if (form.closest('[data-no-field-icons], .cr, .fd, .rc') || form.querySelector('.ff-iconed')) return;
            var fields = Array.from(form.querySelectorAll('.field')).filter(function (field) {
                if (field.closest('table') || field.hidden || field.classList.contains('field--actions')) return false;
                var control = field.querySelector(':scope > input:not([type=hidden]):not([type=checkbox]):not([type=radio]), :scope > select, :scope > textarea, :scope > label > input:not([type=hidden]):not([type=checkbox]):not([type=radio]), :scope > label > select, :scope > label > textarea');
                return !!control;
            });
            if (!fields.length || fields.length > 8) return;
            var plan = fields.map(function (field) {
                var control = field.querySelector('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea');
                var label = field.querySelector('label');
                return { field: field, icon: iconForField(control, label ? label.textContent : '') };
            });
            if (plan.some(function (p) { return !p.icon; })) return;
            plan.forEach(function (p) {
                var body = document.createElement('div');
                body.className = 'ff-body';
                while (p.field.firstChild) body.appendChild(p.field.firstChild);
                var tile = document.createElement('span');
                tile.className = 'ff-tile';
                tile.setAttribute('aria-hidden', 'true');
                tile.innerHTML = '<svg viewBox="0 0 24 24">' + FIELD_ICONS[p.icon] + '</svg>';
                p.field.appendChild(tile);
                p.field.appendChild(body);
                p.field.classList.add('ff-iconed');
            });
        });
    }

    function init() { wrapTables(document); trackTopbar(); hideDuplicateLinks(); moveBarActions(); addPrintButton(); addExportButton(); decorateForms(); setTimeout(revealActiveTab, 0); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
