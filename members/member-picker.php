<?php
/**
 * member-picker.php — shared "type a name, pick a member" typeahead.
 *
 * Suggestions come from registered accounts AND the imported roster
 * (member_invitations), because most people on the roster have not signed up
 * yet and would otherwise be unfindable. Role tables store a denormalized
 * email + name with no foreign key, so assigning an unregistered member is
 * safe on every page that uses this.
 *
 * A page opts in by rendering inputs with this ID convention, where {key}
 * identifies one picker instance on the page:
 *
 *   <form id="form-{key}" onsubmit="return confirmAssign(event, '{key}')"
 *         data-role-label="Treasurer">
 *     <input type="hidden" name="..."  id="sel-email-{key}">
 *     <input type="hidden" name="..."  id="sel-name-{key}">
 *     <input type="text" id="search-{key}"
 *            oninput="filterMembers('{key}')" onkeydown="handleKey(event,'{key}')">
 *     <ul class="suggestions" id="sugg-{key}"></ul>
 *     <input type="text" id="offname-{key}" style="display:none"
 *            oninput="offlistNameInput('{key}')">
 *     <button type="submit" id="save-{key}" class="save-btn">Save</button>
 *   </form>
 *
 * Call memberPickerStyles() inside <style>, and memberPickerScript() at the
 * bottom of <body> BEFORE any page script that depends on MEMBERS.
 *
 * Optional: set window.memberPickerOnEscape = fn(key) to hook the Escape key
 * (roles-overview uses it to close its inline row editor).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/invite-db.php';

/**
 * Registered members merged with the imported roster, deduped on lowercased
 * email with the registered record winning. Each entry: name, email, registered.
 *
 * Callers should only emit this for admins — it is the full membership list
 * with personal email addresses.
 */
function memberPickerData(): array {
    $picker = [];

    inviteEnsureTable();
    // COALESCE/NULLIF because member_invitations.name is nullable and the
    // client-side filter calls .toLowerCase() on it.
    $roster = getDB()->query(
        "SELECT COALESCE(NULLIF(TRIM(name), ''), email) AS name, email
         FROM member_invitations"
    )->fetchAll();
    foreach ($roster as $r) {
        $key = strtolower(trim($r['email']));
        if ($key === '') continue;
        $picker[$key] = ['name' => $r['name'], 'email' => $key, 'registered' => false];
    }

    $members = getDB()->query(
        "SELECT COALESCE(NULLIF(TRIM(name), ''), email) AS name, email FROM members"
    )->fetchAll();
    foreach ($members as $m) {
        $key = strtolower(trim($m['email']));
        if ($key === '') continue;
        $picker[$key] = ['name' => $m['name'], 'email' => $key, 'registered' => true];
    }

    $picker = array_values($picker);
    usort($picker, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return $picker;
}

function memberPickerJson(bool $allowed = true): string {
    return $allowed ? (json_encode(memberPickerData()) ?: '[]') : '[]';
}

function memberPickerStyles(): void {
    echo <<<'CSS'
    .typeahead-wrap { position: relative; flex: 1; min-width: 180px; max-width: 320px; }
    .offlist-name { flex: 0 1 190px; min-width: 150px; }
    .sugg-tag { display: inline-block; background: #e0e7ff; color: #3730a3; font-size: .62rem;
                font-weight: 800; border-radius: 100px; padding: .05rem .4rem; margin-left: .35rem;
                vertical-align: middle; text-transform: uppercase; letter-spacing: .03em; }
    .suggestions li.sugg-note { cursor: default; color: var(--gray-400); font-size: .78rem;
                                font-style: italic; }
    .suggestions li.sugg-note:hover { background: none; }
    .suggestions li.sugg-offlist { cursor: pointer; color: var(--primary); font-weight: 700;
                                   font-size: .8rem; font-style: normal; }
    .typeahead-input { width: 100%; border: 1.5px solid var(--primary); border-radius: 7px;
                       padding: .45rem .75rem; font-size: .88rem; font-family: inherit;
                       outline: none; box-shadow: 0 0 0 3px rgba(26,107,53,.1); box-sizing: border-box; }
    .suggestions { position: absolute; top: calc(100% + 3px); left: 0; right: 0; z-index: 100;
                   background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                   box-shadow: 0 4px 16px rgba(0,0,0,.1); list-style: none; padding: .3rem 0;
                   margin: 0; max-height: 200px; overflow-y: auto; display: none; }
    .suggestions li { padding: .45rem .85rem; cursor: pointer; font-size: .85rem; color: var(--gray-700); }
    .suggestions li:hover, .suggestions li.active { background: #f0fdf4; color: var(--primary); }
    .suggestions .sugg-name { font-weight: 700; }
    .suggestions .sugg-email { font-size: .75rem; color: var(--gray-400); }
    .save-btn { background: var(--primary); color: #fff; border: none; border-radius: 7px;
                padding: .45rem .9rem; font-size: .85rem; font-weight: 700; cursor: pointer;
                opacity: .45; pointer-events: none; transition: opacity .15s; }
    .save-btn.ready { opacity: 1; pointer-events: auto; }
CSS;
}

function memberPickerScript(bool $allowed = true): void {
    $json = memberPickerJson($allowed);
    echo "<script>\nconst MEMBERS = {$json};\nlet activeSuggIdx = {};\n\n";
    echo <<<'JS'
var SUGG_LIMIT = 12;

function looksLikeEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
}

function filterMembers(slug) {
    var raw  = document.getElementById('search-' + slug).value.trim();
    var q    = raw.toLowerCase();
    var list = document.getElementById('sugg-' + slug);
    activeSuggIdx[slug] = -1;

    // Clear selection when user edits
    document.getElementById('sel-email-' + slug).value = '';
    document.getElementById('sel-name-' + slug).value = '';
    document.getElementById('save-' + slug).classList.remove('ready');
    hideOfflist(slug);

    if (q.length < 2) { hideSuggestions(slug); return; }

    var all = MEMBERS.filter(function(m) {
        return m.name.toLowerCase().indexOf(q) !== -1 || m.email.toLowerCase().indexOf(q) !== -1;
    });
    var matches = all.slice(0, SUGG_LIMIT);

    list.innerHTML = '';

    if (!matches.length) {
        // Don't just hide — silence reads as a broken feature.
        var none = document.createElement('li');
        none.className = 'sugg-note';
        none.textContent = 'No matches on the member list.';
        list.appendChild(none);

        // Offer the typed address as a deliberate off-list assignment.
        if (looksLikeEmail(raw)) {
            var use = document.createElement('li');
            use.className = 'sugg-offlist';
            use.textContent = '\u2192 Use ' + raw + ' anyway';
            use.addEventListener('mousedown', function(e) {
                e.preventDefault();
                startOfflist(slug, raw.toLowerCase());
            });
            list.appendChild(use);
        }
        list.style.display = 'block';
        list._matches = [];
        return;
    }

    matches.forEach(function(m, i) {
        var li = document.createElement('li');
        li.setAttribute('data-idx', i);
        li.innerHTML = '<span class="sugg-name">' + escHtml(m.name) + '</span>'
                     + (m.registered ? '' : '<span class="sugg-tag">not registered</span>')
                     + '<br><span class="sugg-email">' + escHtml(m.email) + '</span>';
        li.addEventListener('mousedown', function(e) {
            e.preventDefault(); // don't blur input
            selectMember(slug, m);
        });
        list.appendChild(li);
    });

    if (all.length > matches.length) {
        var more = document.createElement('li');
        more.className = 'sugg-note';
        more.textContent = '\u2026showing first ' + matches.length + ' of ' + all.length
                         + ' \u2014 keep typing to narrow';
        list.appendChild(more);
    }

    list.style.display = 'block';
    list._matches = matches;
}

/* ── Off-list assignment ──────────────────────────────────────────────────── */

function startOfflist(slug, email) {
    document.getElementById('search-' + slug).value = email;
    document.getElementById('sel-email-' + slug).value = email;
    document.getElementById('sel-name-' + slug).value  = '';  // filled from the name box

    var nameBox = document.getElementById('offname-' + slug);
    nameBox.style.display = '';
    nameBox.value = '';
    var hint = document.getElementById('offhint-' + slug);   // optional
    if (hint) hint.style.display = '';
    // Save stays gated until a name is supplied — the server rejects a blank one,
    // and this value is what the directory displays.
    document.getElementById('save-' + slug).classList.remove('ready');
    hideSuggestions(slug);
    nameBox.focus();
}

function offlistNameInput(slug) {
    var name = document.getElementById('offname-' + slug).value.trim();
    document.getElementById('sel-name-' + slug).value = name;
    document.getElementById('save-' + slug).classList.toggle('ready', name.length > 0);
}

function hideOfflist(slug) {
    var nameBox = document.getElementById('offname-' + slug);
    if (nameBox) { nameBox.style.display = 'none'; nameBox.value = ''; }
    var hint = document.getElementById('offhint-' + slug);   // optional
    if (hint) hint.style.display = 'none';
}

/**
 * Block submits with nothing chosen, and confirm an address that isn't on the
 * member list. A typo here fails silently: role notifications are sent to this
 * stored address, so a wrong one simply never delivers.
 */
function confirmAssign(e, slug) {
    var email = document.getElementById('sel-email-' + slug).value.trim();
    var name  = document.getElementById('sel-name-' + slug).value.trim();
    if (!email || !name) return false;

    var known = MEMBERS.some(function(m) { return m.email === email.toLowerCase(); });
    if (known) return true;

    var form = document.getElementById('form-' + slug);
    var role = (form && form.getAttribute('data-role-label')) || 'this position';
    return confirm('Assign ' + role + ' to ' + email + '?\n\n'
                 + 'This address isn\'t on the member list — check it for typos, '
                 + 'since role notifications are sent to it.');
}

function handleKey(e, slug) {
    var list = document.getElementById('sugg-' + slug);
    if (!list || list.style.display === 'none') return;
    var items = list.querySelectorAll('li');
    var idx = activeSuggIdx[slug] || -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(idx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(idx - 1, -1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (idx >= 0 && list._matches && list._matches[idx]) {
            selectMember(slug, list._matches[idx]);
        }
        return;
    } else if (e.key === 'Escape') {
        // Pages with an inline editor (roles-overview) close it on Escape;
        // a plain form just dismisses the dropdown.
        if (typeof window.memberPickerOnEscape === 'function') {
            window.memberPickerOnEscape(slug);
        } else {
            hideSuggestions(slug);
        }
        return;
    } else {
        return;
    }

    activeSuggIdx[slug] = idx;
    items.forEach(function(li, i) { li.classList.toggle('active', i === idx); });
}

function selectMember(slug, m) {
    hideOfflist(slug);
    document.getElementById('search-' + slug).value = m.name;
    document.getElementById('sel-email-' + slug).value = m.email;
    document.getElementById('sel-name-' + slug).value = m.name;
    document.getElementById('save-' + slug).classList.add('ready');
    hideSuggestions(slug);
}

function hideSuggestions(slug) {
    var list = document.getElementById('sugg-' + slug);
    if (list) list.style.display = 'none';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close suggestions when clicking outside
JS;
    echo "\n</script>\n";
}
