import { formatMoney, setFieldset } from './fields.js';

const namespace = 'http://www.w3.org/2000/svg';
const number = new Intl.NumberFormat('es-PY', { maximumFractionDigits: 6 });

function svgElement(type, attributes) {
  const element = document.createElementNS(namespace, type);
  for (const [name, value] of Object.entries(attributes)) {
    element.setAttribute(name, value);
  }
  return element;
}

function pathData(points, closed) {
  const positions = points.map(([x, y]) => `${x},${-y}`);
  return 'M' + positions.join(' L') + (closed ? ' Z' : '');
}

function createDrawing(drawing) {
  const points = drawing.pieces.flatMap(piece => piece.outline).concat(drawing.open.flat());
  const xs = points.map(([x]) => x);
  const ys = points.map(([, y]) => -y);
  const left = Math.min(...xs);
  const top = Math.min(...ys);
  const width = Math.max(...xs) - left;
  const height = Math.max(...ys) - top;
  const margin = Math.max(width, height) * .05;
  const svg = svgElement('svg', {
    viewBox: [left - margin, top - margin, width + margin * 2, height + margin * 2].join(' '),
    role: 'group',
    'aria-label': 'Vista previa de piezas seleccionables'
  });
  for (const trail of drawing.open) {
    svg.append(svgElement('path', { d: pathData(trail, false), class: 'open-path' }));
  }
  return svg;
}

function renderPieces(drawing, preview, list, selected, update) {
  const svg = createDrawing(drawing);
  const labels = [];
  drawing.pieces.forEach((piece, id) => {
    const path = svgElement('path', {
      d: [piece.outline, ...piece.holes].map(ring => pathData(ring, true)).join(' '),
      class: 'cut-piece',
      'fill-rule': 'evenodd',
      'aria-hidden': 'true'
    });
    const label = document.createElement('label');
    label.className = 'check';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = selected.has(id);
    path.classList.toggle('selected', input.checked);
    input.addEventListener('change', () => {
      if (input.checked) selected.add(id);
      else selected.delete(id);
      path.classList.toggle('selected', input.checked);
      update();
    });
    path.addEventListener('click', () => input.click());
    label.append(input, `Pieza ${id + 1} · ${number.format(piece.area)} cm² · ${number.format(piece.width)} × ${number.format(piece.height)} mm`);
    labels.push(label);
    svg.append(path);
  });
  preview.replaceChildren(svg);
  list.replaceChildren(...labels);
}

export function setupProduct(form) {
  const fields = form.elements;
  const list = form.querySelector('[data-pieces]');
  const preview = form.querySelector('[data-preview]');
  const message = form.querySelector('[data-plot-message]');
  const glueRate = form.querySelector('[data-glue-rate]').dataset.glueRate;
  const save = form.querySelector('[data-save-product]');
  const selected = new Set(JSON.parse(fields.selection.value));
  let drawing = JSON.parse(form.querySelector('[data-drawing]').content.textContent);
  const initialDrawing = drawing;
  const initialSelection = [...selected];
  let revision = 0;

  function update() {
    setFieldset(form.querySelector('[data-assembly]'), fields.assembly.checked);
    setFieldset(form.querySelector('[data-wholesale]'), fields.enable_wholesale.checked);
    fields.selection.value = JSON.stringify([...selected]);
    const mdfRate = fields.mdf.selectedOptions[0].dataset.rate;
    const area = drawing ? [...selected].reduce((sum, id) => sum + drawing.pieces[id].area, 0) : 0;
    const grams = fields.assembly.checked ? Number(fields.glue.value) : 0;
    const labor = fields.assembly.checked ? Number(fields.labor.value) : 0;
    const valid = area > 0 && mdfRate !== '' && (grams === 0 || glueRate !== '');
    const values = {
      area: area ? number.format(area) + ' cm²' : '—',
      mdf: area && mdfRate !== '' ? formatMoney(area * Number(mdfRate)) : '—',
      glue: grams && glueRate === '' ? 'Sin costo registrado' : formatMoney(grams * Number(glueRate)),
      labor: formatMoney(labor),
      total: valid ? formatMoney(area * Number(mdfRate) + grams * Number(glueRate) + labor) : '—'
    };
    for (const [key, value] of Object.entries(values)) {
      form.querySelector(`[data-cost-${key}]`).textContent = value;
    }
    save.disabled = !valid;
  }

  function display() {
    if (drawing) {
      renderPieces(drawing, preview, list, selected, update);
      const open = drawing.open.length;
      message.textContent = open ? `Selecciona las piezas. ${open} trazos abiertos se muestran sin sumar área.` : 'Selecciona las piezas en el dibujo o en la lista.';
    }
    update();
  }

  fields.plot.addEventListener('change', async () => {
    const request = ++revision;
    drawing = null;
    selected.clear();
    preview.replaceChildren();
    list.replaceChildren();
    update();
    const file = fields.plot.files[0];
    if (!file) {
      drawing = initialDrawing;
      initialSelection.forEach(id => selected.add(id));
      message.textContent = 'Carga un archivo PLT para ver sus piezas.';
      display();
      return;
    }
    message.textContent = 'Leyendo el PLT…';
    const body = new FormData();
    body.set('action', 'plot_preview');
    body.set('csrf', fields.csrf.value);
    body.set('plot', file);
    try {
      const response = await fetch(window.location.href, { method: 'POST', body });
      const result = await response.json();
      if (request !== revision) return;
      if (!response.ok) throw new Error(result.error || 'No se pudo leer el PLT.');
      drawing = result;
      display();
    } catch (error) {
      if (request === revision) message.textContent = error instanceof SyntaxError ? 'No se pudo leer el PLT.' : error.message;
    }
  });
  form.addEventListener('input', update);
  display();
}
