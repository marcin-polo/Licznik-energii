// Panel konfiguracji: edycja ekranów, progów i kolorów, zmiana hasła.
const $ = (id) => document.getElementById(id);
const esc = MeterUI.escape;
const REG = BOOT.registers;
const LEVEL_NAMES = { normal: 'W normie', export: 'Oddawanie', warning: 'Ostrzeżenie', alarm: 'Alarm' };
const TYPE_NAMES = { value: 'Wartość', chart: 'Wykres', clock: 'Zegar' };
const WINDOWS = [[60, '1 min'], [300, '5 min'], [900, '15 min'], [1800, '30 min'], [3600, '1 h']].filter(([s]) => s <= BOOT.maxWindow);
const MAX_ROWS = 6, MAX_CELLS = 6;

let S = structuredClone(BOOT.settings);   // edytowane ustawienia
let savedJson = JSON.stringify(S);
let current = Object.keys(S.screens)[0] ?? null;
let live = null;                           // bieżący stan z api.php
const levelOf = MeterUI.levelTracker();

// ---------- pomocnicze ----------

const regLabel = (key) => REG[key] ? REG[key].label : `${key} (brak w liczniku)`;

function keyOptions(selected) {
  const groups = {};
  for (const [k, r] of Object.entries(REG)) (groups[r.group] ??= []).push(k);
  let html = Object.entries(groups).map(([g, keys]) => `<optgroup label="${esc(g)}">${keys.map((k) =>
    `<option value="${k}" ${k === selected ? 'selected' : ''}>${esc(REG[k].label)}${REG[k].unit ? ` [${esc(REG[k].unit)}]` : ''}</option>`).join('')}</optgroup>`).join('');
  if (selected && !REG[selected]) html = `<option value="${esc(selected)}" selected>${esc(selected)} (brak w liczniku)</option>` + html;
  return html;
}

function getPath(path) { return path.split('|').reduce((o, k) => o?.[k], S); }
function setPath(path, value) {
  const parts = path.split('|');
  const last = parts.pop();
  let obj = S;
  for (const p of parts) obj = obj[p] ??= {};
  // Klucze najwyższego poziomu zostają (null), żeby porównanie „niezapisane zmiany” nie zależało od kolejności kluczy.
  // Puste pole połączenia = null (wartość z .env); text '' też traktujemy jak puste.
  if (parts[0] === 'connection' && value === '') value = null;
  if ((value === null || value === undefined) && parts.length && parts[0] !== 'connection') delete obj[last]; else obj[last] = value ?? null;
}

function readInput(el) {
  if (el.type === 'checkbox') return el.checked;
  if (el.dataset.kind === 'num') return el.value.trim() === '' ? null : parseFloat(el.value.replace(',', '.'));
  return el.value;
}

function changed() {
  const dirty = JSON.stringify(S) !== savedJson;
  $('save').disabled = !dirty;
  $('revert').disabled = !dirty;
  const msg = $('save-msg');
  msg.className = 'msg';
  msg.textContent = dirty ? 'Niezapisane zmiany' : 'Brak zmian';
}

// ---------- zakładki ----------

document.querySelectorAll('[data-tab]').forEach((btn) => btn.addEventListener('click', () => {
  document.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('active', b === btn));
  document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== btn.dataset.tab; });
  try { localStorage.setItem('admin-tab', btn.dataset.tab); } catch (e) {}
}));
try { document.querySelector(`[data-tab="${localStorage.getItem('admin-tab')}"]`)?.click(); } catch (e) {}

// ---------- ekrany ----------

function renderScreenList() {
  $('screen-list').innerHTML = Object.entries(S.screens).map(([name, s]) =>
    `<button data-act="select" data-name="${esc(name)}" class="${name === current ? 'active' : ''}">${esc(s.title || name)}<small>/d/${esc(name)}</small></button>`).join('')
    + '<button data-act="new-screen">+ Nowy ekran</button>';
}

function renderEditor() {
  const ed = $('screen-editor');
  const s = S.screens[current];
  if (!s) { ed.innerHTML = '<p class="hint">Brak ekranów – dodaj nowy.</p>'; return; }
  const p = `screens|${current}`;

  ed.innerHTML = `
    <div class="fields">
      <label class="field">Nazwa w adresie<input type="text" id="screen-name" value="${esc(current)}" pattern="[a-z0-9_-]+" title="małe litery, cyfry, - i _"></label>
      <label class="field">Tytuł<input type="text" data-path="${p}|title" value="${esc(s.title)}" maxlength="100"></label>
      <span style="flex:1"></span>
      <a href="d/${esc(current)}" target="_blank" id="open-link">Otwórz ↗</a>
      <button data-act="dup-screen">Duplikuj</button>
      <button data-act="del-screen" class="danger">Usuń</button>
    </div>
    <div class="preview" id="preview"></div>
    ${s.rows.map((row, r) => rowHtml(row, r, `${p}|rows|${r}`, s.rows.length)).join('')}
    <button data-act="add-row" ${s.rows.length >= MAX_ROWS ? 'disabled' : ''}>+ Wiersz</button>
    <p class="hint">Wysokość wiersza i szerokość kolumny to wagi względne – np. wiersz 2 i 1 = podział 2/3 i 1/3 ekranu.
      Zmiany pojawią się na otwartych ekranach w ciągu sekundy po zapisaniu.</p>`;
  renderPreview();
}

function rowHtml(row, r, p, count) {
  return `<div class="row-card">
    <div class="row-head">
      <b>Wiersz ${r + 1}</b>
      <label class="field" style="flex-direction:row;align-items:center">wysokość <input type="number" data-path="${p}|height" data-kind="num" value="${row.height}" min="0.5" max="10" step="0.5" style="width:70px"></label>
      <span class="spacer"></span>
      <button class="icon" data-act="row-up" data-r="${r}" ${r === 0 ? 'disabled' : ''} title="W górę">↑</button>
      <button class="icon" data-act="row-down" data-r="${r}" ${r === count - 1 ? 'disabled' : ''} title="W dół">↓</button>
      <button class="icon danger" data-act="row-del" data-r="${r}" ${count === 1 ? 'disabled' : ''} title="Usuń wiersz">✕</button>
    </div>
    <div class="cells">
      ${row.cells.map((c, i) => cellHtml(c, r, i, `${p}|cells|${i}`, row.cells.length)).join('')}
      ${row.cells.length < MAX_CELLS ? `<button class="add-cell" data-act="add-cell" data-r="${r}">+ Kolumna</button>` : ''}
    </div>
  </div>`;
}

function cellHtml(c, r, i, p, count) {
  let body = '';
  if (c.type === 'value') {
    body = `
      <label class="field">Wartość<select data-path="${p}|key">${keyOptions(c.key)}</select></label>
      <label class="field">Podpis (puste = domyślny)<input type="text" data-path="${p}|label" value="${esc(c.label)}" maxlength="60" placeholder="${esc(REG[c.key]?.label || '')}"></label>
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <label><input type="checkbox" data-path="${p}|kw" ${c.kw ? 'checked' : ''}> kW / kvar / kVA</label>
        <label><input type="checkbox" data-path="${p}|bar" ${c.bar !== false ? 'checked' : ''}> pasek do progu</label>
      </div>`;
  } else if (c.type === 'chart') {
    const groups = {};
    for (const [k, reg] of Object.entries(REG)) (groups[reg.group] ??= []).push(k);
    body = `
      <div class="field">Serie (pierwsza = jednostka osi)
        <div class="checks">${Object.entries(groups).map(([g, keys]) => `<span class="grp">${esc(g)}</span>` + keys.map((k) =>
          `<label><input type="checkbox" data-act="chart-key" data-path="${p}|keys" data-key="${k}" ${c.keys.includes(k) ? 'checked' : ''}> ${esc(REG[k].label)}</label>`).join('')).join('')}
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <label class="field">Zakres<select data-path="${p}|window" data-kind="num">${WINDOWS.map(([s, l]) => `<option value="${s}" ${s === c.window ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
        <label><input type="checkbox" data-path="${p}|kw" ${c.kw ? 'checked' : ''}> kW</label>
      </div>
      <label class="field">Podpis<input type="text" data-path="${p}|label" value="${esc(c.label)}" maxlength="60"></label>`;
  } else {
    body = `<label class="field">Podpis<input type="text" data-path="${p}|label" value="${esc(c.label)}" maxlength="60"></label>`;
  }
  return `<div class="cell-card">
    <div class="top">
      <select data-act="cell-type" data-r="${r}" data-i="${i}">${Object.entries(TYPE_NAMES).map(([t, n]) => `<option value="${t}" ${t === c.type ? 'selected' : ''}>${n}</option>`).join('')}</select>
      <button class="icon" data-act="cell-left" data-r="${r}" data-i="${i}" ${i === 0 ? 'disabled' : ''} title="W lewo">←</button>
      <button class="icon" data-act="cell-right" data-r="${r}" data-i="${i}" ${i === count - 1 ? 'disabled' : ''} title="W prawo">→</button>
      <button class="icon danger" data-act="cell-del" data-r="${r}" data-i="${i}" ${count === 1 ? 'disabled' : ''} title="Usuń kolumnę">✕</button>
    </div>
    <label class="field">Szerokość<input type="number" data-path="${p}|width" data-kind="num" value="${c.width}" min="0.5" max="10" step="0.5" style="width:70px"></label>
    ${body}
  </div>`;
}

function renderPreview() {
  const s = S.screens[current];
  const el = $('preview');
  if (!s || !el) return;
  el.innerHTML = s.rows.map((row) => `<div class="prow" style="flex:${row.height || 1}">${row.cells.map((c) => {
    let text = '';
    if (c.type === 'value') {
      const v = REG[c.key] && live?.values ? MeterUI.scaled(live.values[c.key], REG[c.key], c.kw) : null;
      text = `<b>${esc(c.label || regLabel(c.key))}</b>${v ? esc(MeterUI.format(v.value, v.precision) + ' ' + v.unit) : '–'}`;
    }
    else if (c.type === 'chart') text = `<b>${esc(c.label || 'Wykres')}</b>📈 ${c.keys.length} seri${c.keys.length === 1 ? 'a' : 'e'}`;
    else text = `<b>${esc(c.label)}</b>🕒`;
    return `<div class="pcell" style="flex:${c.width || 1}">${text}</div>`;
  }).join('')}</div>`).join('');
}

function newCell(type, prev) {
  const key = prev?.key || prev?.keys?.[0] || 'power_active_total';
  if (type === 'chart') return { type, width: prev?.width ?? 1, label: '', keys: [key], window: 300, kw: !!prev?.kw };
  if (type === 'clock') return { type, width: prev?.width ?? 1, label: '' };
  return { type: 'value', width: prev?.width ?? 1, label: '', key, kw: !!prev?.kw, bar: true };
}

function uniqueName(base) {
  let name = base, n = 2;
  while (S.screens[name]) name = `${base}-${n++}`;
  return name;
}

const move = (arr, from, to) => arr.splice(to, 0, arr.splice(from, 1)[0]);

function act(btn) {
  const s = S.screens[current];
  const r = +btn.dataset.r, i = +btn.dataset.i;
  switch (btn.dataset.act) {
    case 'select': current = btn.dataset.name; break;
    case 'new-screen':
      current = uniqueName('ekran');
      S.screens[current] = { title: 'Nowy ekran', rows: [{ height: 1, cells: [newCell('value')] }] };
      break;
    case 'dup-screen': {
      const name = uniqueName(current + '-kopia');
      S.screens[name] = structuredClone(s);
      S.screens[name].title += ' (kopia)';
      current = name;
      break;
    }
    case 'del-screen':
      if (!confirm(`Usunąć ekran „${s.title || current}”?`)) return;
      delete S.screens[current];
      current = Object.keys(S.screens)[0] ?? null;
      break;
    case 'add-row': s.rows.push({ height: 1, cells: [newCell('value')] }); break;
    case 'row-up': move(s.rows, r, r - 1); break;
    case 'row-down': move(s.rows, r, r + 1); break;
    case 'row-del': s.rows.splice(r, 1); break;
    case 'add-cell': { const cells = s.rows[r].cells; cells.push(newCell(cells.at(-1)?.type === 'chart' ? 'value' : cells.at(-1)?.type || 'value', cells.at(-1))); break; }
    case 'cell-left': move(s.rows[r].cells, i, i - 1); break;
    case 'cell-right': move(s.rows[r].cells, i, i + 1); break;
    case 'cell-del': s.rows[r].cells.splice(i, 1); break;
    default: return;
  }
  renderScreenList();
  renderEditor();
  changed();
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-act]');
  if (btn) act(btn);
});

document.addEventListener('input', (e) => {
  const el = e.target;
  if (el.dataset.act === 'chart-key') {
    const keys = getPath(el.dataset.path);
    const idx = keys.indexOf(el.dataset.key);
    if (el.checked && idx === -1) keys.push(el.dataset.key);
    if (!el.checked && idx !== -1) {
      if (keys.length === 1) { el.checked = true; return; }   // wykres musi mieć co najmniej jedną serię
      keys.splice(idx, 1);
    }
  } else if (el.dataset.act === 'cell-type') {
    const cells = S.screens[current].rows[+el.dataset.r].cells;
    cells[+el.dataset.i] = newCell(el.value, cells[+el.dataset.i]);
    renderEditor();
  } else if (el.dataset.path) {
    setPath(el.dataset.path, readInput(el));
    if (el.dataset.path.startsWith('thresholds|')) cleanThreshold(el.dataset.path.split('|')[1]);
    if (el.dataset.path.startsWith('colors|')) renderSwatch();
    if (el.dataset.path.startsWith('notifications|')) renderNotifyState();
    if (el.dataset.path === 'connection|type') renderConnection();
    if (el.dataset.path === 'poll_interval') {
      document.querySelectorAll('[data-preset]').forEach((b) => b.classList.toggle('active', b.dataset.preset === String(S.poll_interval ?? '')));
      renderReadingLive();
    }
    if (el.dataset.path.endsWith('|title')) renderScreenList();
  } else {
    return;
  }
  renderPreview();
  renderLive();
  changed();
});

// Zmiana nazwy ekranu – dopiero po zatwierdzeniu pola.
document.addEventListener('change', (e) => {
  if (e.target.id !== 'screen-name') return;
  const el = e.target;
  const name = el.value.trim().toLowerCase();
  if (name === current) return;
  if (!/^[a-z0-9_-]{1,40}$/.test(name) || S.screens[name]) {
    el.classList.add('invalid');
    el.title = S.screens[name] ? 'Taka nazwa już istnieje' : 'Dozwolone: małe litery, cyfry, - i _';
    return;
  }
  // Zachowaj kolejność ekranów.
  S.screens = Object.fromEntries(Object.entries(S.screens).map(([k, v]) => [k === current ? name : k, v]));
  current = name;
  renderScreenList();
  renderEditor();
  changed();
});

// ---------- progi i kolory ----------

function renderColors() {
  $('colors').innerHTML = Object.entries(LEVEL_NAMES).map(([lvl, name]) =>
    `<label>${name}<input type="color" data-path="colors|${lvl}" value="${toHex(S.colors[lvl])}"></label>`).join('');
  renderSwatch();
}

function renderSwatch() {
  $('swatch').innerHTML = Object.entries(LEVEL_NAMES).map(([lvl, name]) => `<span style="color:${esc(S.colors[lvl])}" title="${name}">${name}</span>`).join('');
}

function toHex(color) {
  if (/^#[0-9a-f]{6}$/i.test(color)) return color;
  if (/^#[0-9a-f]{3}$/i.test(color)) return '#' + [...color.slice(1)].map((c) => c + c).join('');
  const ctx = document.createElement('canvas').getContext('2d');
  ctx.fillStyle = color;
  return ctx.fillStyle.startsWith('#') ? ctx.fillStyle : '#ffffff';
}

function cleanThreshold(key) {
  const t = S.thresholds[key];
  if (!t) return;
  if (t.export === false) delete t.export;
  if (t.notify === false) delete t.notify;
  if (!Object.keys(t).length) delete S.thresholds[key];
}

function renderThresholds() {
  const fields = [['alarm_low', 'Alarm ≤'], ['warning_low', 'Ostrzeżenie ≤'], ['warning', 'Ostrzeżenie ≥'], ['alarm', 'Alarm ≥']];
  let html = `<thead><tr><th>Wartość</th><th style="text-align:right">Teraz</th>${fields.map(([, n]) => `<th>${n}</th>`).join('')}<th title="O ile wartość musi się cofnąć poza próg, żeby zejść z poziomu – zapobiega miganiu">Histereza</th><th>Oddawanie</th><th>Powiadom</th></tr></thead><tbody>`;
  let group = null;
  for (const [key, reg] of Object.entries(REG)) {
    if (reg.group !== group) { group = reg.group; html += `<tr><td class="grp" colspan="9">${esc(group)}</td></tr>`; }
    const t = S.thresholds[key] || {};
    html += `<tr><td>${esc(reg.label)}${reg.unit ? ` <span class="hint">${esc(reg.unit)}</span>` : ''}</td>
      <td style="text-align:right"><span class="live" id="live-${key}">–</span></td>
      ${fields.map(([f]) => `<td><input type="number" step="any" data-kind="num" data-path="thresholds|${key}|${f}" value="${t[f] ?? ''}"></td>`).join('')}
      <td><input type="number" step="any" min="0" data-kind="num" data-path="thresholds|${key}|hysteresis" value="${t.hysteresis ?? ''}" style="width:70px"></td>
      <td><input type="checkbox" data-path="thresholds|${key}|export" ${t.export ? 'checked' : ''}></td>
      <td><input type="checkbox" data-path="thresholds|${key}|notify" ${t.notify ? 'checked' : ''}></td></tr>`;
  }
  $('thresholds').innerHTML = html + '</tbody>';
}

function renderLive() {
  if (!live?.values) return;
  for (const [key, value] of Object.entries(live.values)) {
    const el = $('live-' + key);
    if (!el || !REG[key]) continue;
    el.textContent = MeterUI.format(value, REG[key].precision);
    el.style.color = MeterUI.color(levelOf(key, value, S.thresholds[key]), S, key) || '#fff';
  }
}

async function pollLive() {
  try {
    live = await (await fetch('api.php?values', { cache: 'no-store' })).json();
    renderLive();
    renderReadingLive();
    if (!document.activeElement?.closest('#screen-editor')) renderPreview();
  } catch (e) {}
  setTimeout(pollLive, 2000);
}

// ---------- powiadomienia ----------

const CHANNELS = {
  ntfy: { name: 'ntfy', help: 'Aplikacja ntfy na telefon. Adres tematu, np. <code>https://ntfy.sh/moj-licznik-x7k2</code> (nazwa tematu = „hasło”, wymyśl trudną) albo własny serwer ntfy.',
    fields: [['url', 'Adres tematu', 'text', 'https://ntfy.sh/…'], ['token', 'Token (opcjonalnie)', 'password', '']] },
  telegram: { name: 'Telegram', help: 'Bot od @BotFather (token) i ID czatu – np. z @userinfobot; dla grupy ID zaczyna się od „-”. Najpierw napisz do bota /start.',
    fields: [['token', 'Token bota', 'password', '123456:ABC…'], ['chat_id', 'ID czatu', 'text', '123456789']] },
  email: { name: 'E-mail (SMTP)', help: 'Np. Gmail: smtp.gmail.com, 587, STARTTLS, hasło aplikacji. Kilku odbiorców – po przecinku.',
    fields: [['host', 'Serwer SMTP', 'text', 'smtp.example.com'], ['port', 'Port', 'num', '587'], ['security', 'Szyfrowanie', 'security', ''],
      ['user', 'Użytkownik', 'text', ''], ['password', 'Hasło', 'password', ''], ['from', 'Nadawca', 'text', 'licznik@example.com'], ['to', 'Odbiorcy', 'text', 'ja@example.com']] },
};

function renderNotifications() {
  const n = S.notifications;
  const p = 'notifications';
  $('notify-general').innerHTML = `
    <h2>Powiadomienia</h2>
    <div class="fields">
      <label><input type="checkbox" data-path="${p}|enabled" ${n.enabled ? 'checked' : ''}> <b>Wysyłaj powiadomienia</b></label>
    </div>
    <div class="fields">
      <label class="field">Od poziomu<select data-path="${p}|min_level">
        <option value="alarm" ${n.min_level === 'alarm' ? 'selected' : ''}>tylko alarm</option>
        <option value="warning" ${n.min_level === 'warning' ? 'selected' : ''}>ostrzeżenie i alarm</option></select></label>
      <label class="field">Opóźnienie [s]<input type="number" data-kind="num" data-path="${p}|delay" value="${n.delay}" min="0" max="3600"></label>
      <label class="field">Przypominaj co [min] (0 = nie)<input type="number" data-kind="num" data-path="${p}|repeat" value="${n.repeat}" min="0" max="1440"></label>
      <label><input type="checkbox" data-path="${p}|recovery" ${n.recovery ? 'checked' : ''}> powiadom o powrocie do normy</label>
    </div>
    <div class="fields">
      <label><input type="checkbox" data-path="${p}|comm_loss" ${n.comm_loss ? 'checked' : ''}> powiadom o braku komunikacji z licznikiem po</label>
      <label class="field" style="flex-direction:row;align-items:center"><input type="number" data-kind="num" data-path="${p}|comm_delay" value="${n.comm_delay}" min="10" max="3600"> s</label>
    </div>
    <p class="hint">Które wartości są pilnowane – zaznacz „Powiadom” w zakładce „Progi i kolory”. Opóźnienie: stan musi trwać co najmniej tyle sekund
      (krótkie skoki mocy, np. rozruch pompy, nie wywołają powiadomienia). Pilnuje tego usługa odczytu, więc działa bez otwartej przeglądarki.
      <span id="notify-watch"></span></p>`;

  $('notify-channels').innerHTML = Object.entries(CHANNELS).map(([ch, def]) => {
    const c = n[ch];
    return `<div class="card">
      <div class="channel-head"><h2 style="margin:0">${def.name}</h2>
        <label><input type="checkbox" data-path="${p}|${ch}|enabled" ${c.enabled ? 'checked' : ''}> włączony</label></div>
      <p class="hint" style="margin:0">${def.help}</p>
      ${def.fields.map(([f, label, type, ph]) => {
        const path = `${p}|${ch}|${f}`;
        if (type === 'security') return `<label class="field">${label}<select data-path="${path}">${[['starttls', 'STARTTLS (587)'], ['ssl', 'SSL/TLS (465)'], ['none', 'brak (25)']].map(([v, l]) => `<option value="${v}" ${c[f] === v ? 'selected' : ''}>${l}</option>`).join('')}</select></label>`;
        return `<label class="field">${label}<input type="${type === 'num' ? 'number' : type}" ${type === 'num' ? 'data-kind="num"' : ''} data-path="${path}" value="${esc(c[f] ?? '')}" placeholder="${esc(ph)}" autocomplete="off"></label>`;
      }).join('')}
      <div style="display:flex;gap:10px;align-items:center"><button data-test="${ch}">Wyślij test</button><span class="hint" id="test-${ch}"></span></div>
    </div>`;
  }).join('');
  renderNotifyState();
}

function renderNotifyState() {
  const watched = Object.entries(S.thresholds).filter(([, t]) => t.notify).map(([k]) => REG[k]?.label || k);
  const el = $('notify-watch');
  if (el) el.innerHTML = watched.length ? `<br>Pilnowane: <b>${watched.map(esc).join(', ')}</b>.` : '<br><b>Żadna wartość nie ma zaznaczonego „Powiadom”.</b>';
}

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-test]');
  if (!btn) return;
  const ch = btn.dataset.test, out = $('test-' + ch);
  out.className = 'hint'; out.textContent = 'wysyłanie…';
  btn.disabled = true;
  try {
    await post('test_notify', { channel: ch, notifications: S.notifications });
    out.className = 'hint'; out.style.color = 'var(--ok)'; out.textContent = 'Wysłano – sprawdź telefon / skrzynkę.';
  } catch (err) {
    out.className = 'error'; out.style.color = ''; out.textContent = err.message;
  }
  btn.disabled = false;
});

async function loadEvents() {
  try {
    const data = await (await fetch('api.php?events&limit=30', { cache: 'no-store' })).json();
    $('events').innerHTML = data.events.length ? data.events.map((ev) =>
      `<li class="lvl-${esc(ev.level)}"><time>${new Date(ev.ts * 1000).toLocaleString('pl-PL')}</time>${esc(ev.message)} <span class="hint">(${esc(ev.delivery)})</span></li>`).join('')
      : '<li class="hint">Brak zdarzeń.</li>';
  } catch (e) {}
}

// ---------- połączenie z licznikiem ----------

function renderConnection() {
  const c = S.connection ||= {};
  const env = BOOT.envConnection;
  const type = c.type || env.type;
  const m = BOOT.meters[type];
  $('connection').innerHTML = `
    <h2>Licznik i połączenie Modbus TCP</h2>
    <div class="fields" id="conn-fields">
      <label class="field">Typ licznika<select data-path="connection|type">
        <option value="" ${!c.type ? 'selected' : ''}>z pliku .env (${esc(BOOT.meters[env.type]?.name || env.type)})</option>
        ${Object.entries(BOOT.meters).map(([t, mm]) => `<option value="${esc(t)}" ${c.type === t ? 'selected' : ''}>${esc(mm.name)}${mm.verified ? ' ✓' : ''}</option>`).join('')}
      </select></label>
      <label class="field">Adres IP / host<input type="text" data-path="connection|host" value="${esc(c.host ?? '')}" placeholder="${esc(env.host)}"></label>
      <label class="field">Port<input type="number" data-kind="num" data-path="connection|port" value="${c.port ?? ''}" placeholder="${env.port}" min="1" max="65535"></label>
      <label class="field">Unit ID (slave)<input type="number" data-kind="num" data-path="connection|unit" value="${c.unit ?? ''}" placeholder="${env.unit}" min="0" max="255"></label>
      <label class="field">Timeout [s]<input type="number" data-kind="num" data-path="connection|timeout" value="${c.timeout ?? ''}" placeholder="${env.timeout}" min="0.2" max="10" step="0.1"></label>
    </div>
    <p class="hint" id="meter-desc">${m ? `${esc(m.description)} · ${m.registers} wartości · ${m.verified ? '<span class="badge ok">mapa sprawdzona na urządzeniu</span>' : '<span class="badge">mapa z dokumentacji – sprawdź „Testuj” przed użyciem</span>'}` : ''}</p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button id="meter-test-btn">Testuj (odczyt wszystkich wartości)</button>
      <span class="hint" id="meter-test-msg">Test nie zmienia konfiguracji. Zapisz, żeby przełączyć licznik – poller przełączy się w ciągu kilku sekund.</span>
    </div>
    <div class="wrap"><table id="meter-test"></table></div>
    <p class="hint">Puste pole = wartość z pliku <code>.env</code>. Przy RS485 (Eastron, ABB, Carlo Gavazzi, ORNO, DDS238…) podaj adres bramki RS485→TCP
      i adres licznika na magistrali jako Unit ID. Po zmianie licznika raporty liczą jego energię osobno – stany różnych liczników nie są ze sobą porównywane.
      Własny licznik: skopiuj plik z katalogu <code>meters/</code> i zmień adresy rejestrów.</p>`;
}

document.addEventListener('click', async (e) => {
  if (e.target.id !== 'meter-test-btn') return;
  const btn = e.target, msg = $('meter-test-msg');
  btn.disabled = true;
  msg.className = 'hint'; msg.textContent = 'odczyt…';
  $('meter-test').innerHTML = '';
  try {
    const r = await post('test_meter', { connection: S.connection });
    const entries = Object.entries(r.values);
    const nulls = entries.filter(([, v]) => v === null).length;
    msg.className = 'hint';
    msg.innerHTML = `<span style="color:var(--ok)">${esc(r.meter)}: odczytano ${entries.length} wartości w ${Math.round(r.ms)} ms.</span>`
      + (nulls ? ` <span class="warn">${nulls} bez wartości (NaN) – sprawdź mapę.</span>` : '')
      + ' Sprawdź, czy wartości są realne (napięcie ~230 V, częstotliwość ~50 Hz).';
    $('meter-test').innerHTML = '<thead><tr><th>Wartość</th><th>Odczyt</th><th></th></tr></thead><tbody>' + entries.map(([k, v]) => {
      const reg = r.registers[k];
      return `<tr><td>${esc(reg.label)}</td><td>${MeterUI.format(v, reg.precision)}</td><td class="hint">${esc(reg.unit)}</td></tr>`;
    }).join('') + '</tbody>';
  } catch (err) {
    msg.className = 'error'; msg.textContent = 'Błąd: ' + err.message;
  }
  btn.disabled = false;
});

// ---------- odczyt ----------

const PRESETS = [[0.1, '0,1 s'], [0.5, '0,5 s'], [1, '1 s'], [2, '2 s'], [5, '5 s'], [10, '10 s'], [30, '30 s'], [60, '1 min']];
const fmtSec = (v) => v.toLocaleString('pl-PL', { maximumFractionDigits: 1 }) + ' s';

function renderReading() {
  const [min, max] = BOOT.intervalRange;
  const v = S.poll_interval;
  $('reading').innerHTML = `
    <h2>Interwał odczytu</h2>
    <div class="fields">
      <label class="field">Co ile sekund (${fmtSec(min)} – ${max / 60} min)
        <input type="number" id="interval-input" data-kind="num" data-path="poll_interval" value="${v ?? ''}" min="${min}" max="${max}" step="0.1" placeholder="${BOOT.envInterval}"></label>
      <div class="presets">${PRESETS.map(([s, l]) => `<button data-preset="${s}" class="${v === s ? 'active' : ''}">${l}</button>`).join('')}
        <button data-preset="" class="${v === null || v === undefined ? 'active' : ''}" title="POLL_INTERVAL z pliku .env">domyślny (${fmtSec(BOOT.envInterval)})</button></div>
    </div>
    <p class="hint">Zmiana działa w ciągu kilku sekund od zapisania, bez restartu. Odczyt trwa w tle cały czas – także przy zamkniętej stronie.
      Szybszy odczyt nie zmienia raportów ani średniej ${BOOT.demandMinutes}-min (jest ważona czasem), daje tylko gęstsze dane na żywo i szybszą reakcję alarmów.
      Wykresy na żywo zapisują punkt najwyżej co 0,5 s.</p>
    <p id="reading-live" class="hint"></p>
    <dl class="info">
      <dt>Poller czyta teraz</dt><dd id="active-meter">${esc(BOOT.meter)}</dd>
      <dt>Okres uśredniania</dt><dd>${BOOT.demandMinutes} min (<code>DEMAND_MINUTES</code> w <code>.env</code>)</dd>
    </dl>`;
  renderReadingLive();
}

function renderReadingLive() {
  const el = $('reading-live');
  if (!el || !live) return;
  if (live.meter && $('active-meter')) $('active-meter').textContent = `${live.meter.name} @ ${live.meter.host}:${live.meter.port}, unit ${live.meter.unit}`;
  const want = S.poll_interval ?? BOOT.envInterval;
  if (!live.ok) { el.innerHTML = `<span class="error">Licznik nie odpowiada: ${esc(live.error || '')}</span>`; return; }
  const ms = live.read_ms ?? 0;
  el.innerHTML = `Ostatni odczyt trwał <b>${Math.round(ms)} ms</b>, aktualny interwał pollera: <b>${fmtSec(live.interval)}</b>.`
    + (ms > want * 1000 ? ` <span class="warn">Licznik nie nadąża za ${fmtSec(want)} – faktycznie odczyt będzie co ok. ${fmtSec(Math.ceil(ms / 100) / 10)}.</span>` : '');
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-preset]');
  if (!btn) return;
  S.poll_interval = btn.dataset.preset === '' ? null : +btn.dataset.preset;
  renderReading();
  changed();
});

// ---------- diagnostyka ----------

const diag = $('diag-form');
diag.host.value = BOOT.modbus.host; diag.port.value = BOOT.modbus.port; diag.unit.value = BOOT.modbus.unit;

diag.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('diag-msg');
  const address = diag.address.value.trim().toLowerCase().startsWith('0x') ? parseInt(diag.address.value, 16) : parseInt(diag.address.value, 10);
  if (!Number.isInteger(address) || address < 0 || address > 65535) { msg.className = 'error'; msg.textContent = 'Nieprawidłowy adres'; return; }
  msg.className = 'hint'; msg.textContent = 'odczyt…';
  try {
    const r = await post('modbus_read', { host: diag.host.value, port: +diag.port.value, unit: +diag.unit.value, function: +diag.function.value, address, count: +diag.count.value });
    msg.textContent = `Odczytano ${r.registers.length} rejestrów w ${Math.round(r.ms)} ms.`;
    renderDiag(address, r.registers);
  } catch (err) {
    msg.className = 'error'; msg.textContent = err.message;
    $('diag-table').innerHTML = '';
  }
});

// Każdy rejestr i interpretacje zaczynające się od niego (ABCD = big-endian, CDAB = zamienione słowa).
function renderDiag(start, regs) {
  const view = (words) => { const dv = new DataView(new ArrayBuffer(words.length * 2)); words.forEach((w, i) => dv.setUint16(i * 2, w)); return dv; };
  const f = (v, d = 3) => Number.isFinite(v) ? (Math.abs(v) >= 1e9 || (v !== 0 && Math.abs(v) < 1e-4) ? v.toExponential(3) : +v.toFixed(d)).toLocaleString('pl-PL', { maximumFractionDigits: d }) : '–';
  const rows = regs.map((w, i) => {
    const two = i + 1 < regs.length ? view([regs[i], regs[i + 1]]) : null;
    const swapped = two ? view([regs[i + 1], regs[i]]) : null;
    const four = i + 3 < regs.length ? view(regs.slice(i, i + 4)) : null;
    const a = start + i;
    return `<tr><td>${a} <span class="hint">0x${a.toString(16).toUpperCase().padStart(4, '0')}</span></td>
      <td>${w.toString(16).toUpperCase().padStart(4, '0')}</td><td>${w}</td><td>${w > 32767 ? w - 65536 : w}</td>
      <td>${two ? f(two.getFloat32(0)) : ''}</td><td>${swapped ? f(swapped.getFloat32(0)) : ''}</td>
      <td>${two ? two.getUint32(0) : ''}</td><td>${two ? two.getInt32(0) : ''}</td>
      <td>${four ? f(four.getFloat64(0)) : ''}</td></tr>`;
  }).join('');
  $('diag-table').innerHTML = `<thead><tr><th>Adres</th><th>hex</th><th>uint16</th><th>int16</th><th>float32 ABCD</th><th>float32 CDAB</th><th>uint32</th><th>int32</th><th>float64</th></tr></thead><tbody>${rows}</tbody>`;
}

// ---------- zapis ----------

async function post(action, body) {
  const res = await fetch('admin.php?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': BOOT.csrf },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({ error: 'Nieprawidłowa odpowiedź serwera' }));
  if (!res.ok) throw new Error(data.error || res.statusText);
  return data;
}

$('save').addEventListener('click', async () => {
  const msg = $('save-msg');
  $('save').disabled = true;
  try {
    const data = await post('save', { settings: S });
    S = data.settings;
    savedJson = JSON.stringify(S);
    if (!S.screens[current]) current = Object.keys(S.screens)[0] ?? null;
    renderAll();
    changed();
    msg.className = 'msg ok';
    msg.textContent = 'Zapisano ' + new Date().toLocaleTimeString('pl-PL');
  } catch (e) {
    $('save').disabled = false;
    msg.className = 'msg err';
    msg.textContent = 'Błąd zapisu: ' + e.message;
  }
});

$('revert').addEventListener('click', () => {
  S = JSON.parse(savedJson);
  if (!S.screens[current]) current = Object.keys(S.screens)[0] ?? null;
  renderAll();
  changed();
});

window.addEventListener('beforeunload', (e) => {
  if (JSON.stringify(S) !== savedJson) e.preventDefault();
});

$('pw-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.target, msg = $('pw-msg');
  if (f.new.value !== f.repeat.value) { msg.className = 'error'; msg.textContent = 'Nowe hasła nie są takie same'; return; }
  try {
    await post('password', { current: f.current.value, new: f.new.value });
    f.reset();
    msg.className = 'hint';
    msg.textContent = 'Hasło zmienione.';
    $('pw-banner').hidden = true;
  } catch (err) {
    msg.className = 'error';
    msg.textContent = err.message;
  }
});

function renderAll() {
  renderScreenList();
  renderEditor();
  renderColors();
  renderThresholds();
  renderNotifications();
  renderConnection();
  renderReading();
  renderLive();
}

renderAll();
pollLive();
loadEvents();
setInterval(loadEvents, 30000);
