// Wspólne funkcje strony głównej, ekranów pełnoekranowych i panelu admina.
const MeterUI = {
  // Poziom wartości wg progów: alarm | warning | export | normal (odpowiednik src/Levels.php).
  // Histereza: wyjście z poziomu wymaga cofnięcia się o t.hysteresis poza próg, stąd poprzedni poziom.
  level(value, t, previous = 'normal') {
    if (value === null || value === undefined || !t) return 'normal';
    const h = t.hysteresis || 0;
    const hit = (lvl, m) => (t[lvl] !== undefined && value >= t[lvl] - m) || (t[lvl + '_low'] !== undefined && value <= t[lvl + '_low'] + m);
    if (hit('alarm', previous === 'alarm' ? h : 0)) return 'alarm';
    if (hit('warning', previous === 'alarm' || previous === 'warning' ? h : 0)) return 'warning';
    if (t.export && value < 0) return 'export';
    return 'normal';
  },

  // Pamięta poprzednie poziomy (dla histerezy) – jeden obiekt na stronę.
  levelTracker() {
    const prev = {};
    return (key, value, t) => (prev[key] = MeterUI.level(value, t, prev[key]));
  },

  color(level, settings, key) {
    const colors = { ...(settings?.colors || {}), ...(settings?.thresholds?.[key]?.colors || {}) };
    return colors[level] || null;
  },

  format(value, precision) {
    if (value === null || value === undefined) return '–';
    return value.toLocaleString('pl-PL', { minimumFractionDigits: precision, maximumFractionDigits: precision });
  },

  // Przeliczenie W/var/VA na kW/kvar/kVA.
  scaled(value, reg, kw) {
    if (kw && ['W', 'var', 'VA'].includes(reg.unit)) {
      return { value: value === null || value === undefined ? value : value / 1000, unit: 'k' + reg.unit, precision: 2 };
    }
    return { value, unit: reg.unit, precision: reg.precision };
  },

  isStale(state) {
    return !state.updated_at || (state.server_time - state.updated_at) / 1000 > state.interval * 3 + 3;
  },

  escape(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  },

  // Kolor serii na wykresie: Σ niebieski, fazy stałe, pozostałe z palety.
  seriesColor(key, index) {
    if (/_total$|_avg$/.test(key)) return '#3b82f6';
    const phase = key.match(/_l([123])$/);
    if (phase) return ['#f59e0b', '#a78bfa', '#2dd4bf'][phase[1] - 1];
    return ['#3b82f6', '#f59e0b', '#a78bfa', '#2dd4bf', '#f472b6', '#84cc16', '#fb7185', '#94a3b8'][index % 8];
  },
};

// Historia wartości: ładowana z serwera, potem dopisywana z bieżących odczytów.
class MeterHistory {
  constructor() { this.t = []; this.v = {}; this.maxMs = 0; this.last = null; }

  async load(seconds) {
    this.maxMs = Math.max(this.maxMs, seconds * 1000);
    try {
      const data = await (await fetch(MeterHistory.base + 'api.php?history&seconds=' + seconds, { cache: 'no-store' })).json();
      // Scal: dane z serwera + to, co już przyszło na żywo później.
      const tail = this.t.length ? this.t[0] : Infinity;
      const keep = data.t.findIndex((t) => t >= tail);
      const n = keep === -1 ? data.t.length : keep;
      this.t = data.t.slice(0, n).concat(this.t);
      for (const [k, arr] of Object.entries(data.v)) this.v[k] = arr.slice(0, n).concat(this.v[k] || new Array(this.t.length - n).fill(null));
      for (const k of Object.keys(this.v)) if (!(k in data.v)) this.v[k] = new Array(n).fill(null).concat(this.v[k]);
      this.last = this.t[this.t.length - 1] ?? null;
    } catch (e) { /* bez historii – wykres zacznie się od teraz */ }
  }

  push(t, values) {
    if (t === this.last) return false;
    this.last = t;
    this.t.push(t);
    const len = this.t.length;
    for (const [k, v] of Object.entries(values)) {
      (this.v[k] ??= new Array(len - 1).fill(null)).push(v);
    }
    for (const arr of Object.values(this.v)) while (arr.length < len) arr.push(null);
    let drop = 0;
    while (drop < this.t.length && this.t[drop] < t - this.maxMs) drop++;
    if (drop) { this.t.splice(0, drop); for (const arr of Object.values(this.v)) arr.splice(0, drop); }
    return true;
  }
}
MeterHistory.base = '';

/**
 * Wykres liniowy na canvasie.
 * opts: { keys, windowMs, registers, kw, gapMs, theme: { grid, text } }
 */
function drawMeterChart(canvas, history, opts) {
  const dpr = window.devicePixelRatio || 1;
  const w = canvas.clientWidth, h = canvas.clientHeight;
  if (!w || !h) return;
  canvas.width = w * dpr; canvas.height = h * dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);

  const now = history.t.length ? history.t[history.t.length - 1] : Date.now();
  const from = now - opts.windowMs;
  let start = history.t.findIndex((t) => t >= from);
  if (start === -1) start = history.t.length;

  const first = opts.registers[opts.keys[0]] || { unit: '' };
  const div = opts.kw && ['W', 'var', 'VA'].includes(first.unit) ? 1000 : 1;
  const unit = (div === 1000 ? 'k' : '') + first.unit;

  let min = Infinity, max = -Infinity;
  for (const k of opts.keys) for (let i = start; i < history.t.length; i++) {
    const v = history.v[k]?.[i];
    if (v !== null && v !== undefined) { min = Math.min(min, v / div); max = Math.max(max, v / div); }
  }
  if (min === Infinity) { min = 0; max = 1; }
  // Moce zawsze od zera; napięcia itp. – dopasowane do zakresu.
  if (['W', 'var', 'VA', 'A'].includes(first.unit)) { min = Math.min(0, min); max = Math.max(0, max); }
  const step = niceStep((max - min) / 4 || Math.abs(max) / 10 || 1);
  min = Math.floor(min / step) * step; max = Math.ceil(max / step) * step;
  if (max === min) max = min + step;

  const fontPx = Math.max(10, Math.min(16, h / 18));
  ctx.font = `${fontPx}px system-ui, sans-serif`;
  const decimals = step < 1 ? Math.min(3, Math.ceil(-Math.log10(step))) : 0;
  const yLabel = (v) => v.toLocaleString('pl-PL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + unit;
  const labelW = Math.max(ctx.measureText(yLabel(min)).width, ctx.measureText(yLabel(max)).width);

  const pad = { l: labelW + 10, r: 8, t: 6, b: fontPx + 8 };
  const x = (t) => pad.l + (t - from) / opts.windowMs * (w - pad.l - pad.r);
  const y = (v) => pad.t + (max - v) / (max - min) * (h - pad.t - pad.b);

  ctx.fillStyle = opts.theme.text; ctx.strokeStyle = opts.theme.grid; ctx.lineWidth = 1;
  ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
  for (let v = min; v <= max + step / 2; v += step) {
    ctx.beginPath(); ctx.moveTo(pad.l, Math.round(y(v)) + .5); ctx.lineTo(w - pad.r, Math.round(y(v)) + .5); ctx.stroke();
    ctx.fillText(yLabel(v), pad.l - 6, y(v));
  }
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';
  ctx.fillText(new Date(from).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' }), pad.l, h - 3);
  ctx.textAlign = 'right';
  ctx.fillText(new Date(now).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' }), w - pad.r, h - 3);

  const gap = opts.gapMs || 5000;
  [...opts.keys].reverse().forEach((k, ri) => {
    const i0 = opts.keys.length - 1 - ri;
    const arr = history.v[k];
    if (!arr) return;
    ctx.strokeStyle = MeterUI.seriesColor(k, i0);
    ctx.lineWidth = i0 === 0 ? 2.2 : 1.5;
    ctx.lineJoin = 'round';
    ctx.beginPath();
    let prevT = null;
    for (let i = start; i < history.t.length; i++) {
      const v = arr[i];
      if (v === null || v === undefined) { prevT = null; continue; }
      const t = history.t[i];
      if (prevT === null || t - prevT > gap) ctx.moveTo(x(t), y(v / div)); else ctx.lineTo(x(t), y(v / div));
      prevT = t;
    }
    ctx.stroke();
  });
}

function niceStep(raw) {
  const mag = 10 ** Math.floor(Math.log10(raw));
  return [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
}
