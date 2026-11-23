function normalized(value) {
  return value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function addTableControls(table) {
  const rows = [...table.tBodies].flatMap(body => [...body.rows]).filter(row => !row.querySelector('td[colspan]'));
  const headers = [...table.querySelectorAll('thead th')];
  if (!headers.length) return;
  const controls = document.createElement('div');
  controls.className = 'table-controls';
  const searchLabel = document.createElement('label');
  searchLabel.textContent = 'Buscar';
  const search = document.createElement('input');
  search.type = 'search';
  search.placeholder = 'Buscar en la tabla';
  searchLabel.append(search);
  const filterLabel = document.createElement('label');
  filterLabel.textContent = 'Filtrar por columna';
  const filter = document.createElement('select');
  const all = new Option('Todas las columnas', '');
  filter.add(all);
  headers.forEach((header, column) => {
    if (header.textContent.trim() && header.textContent.trim() !== 'Acción') {
      filter.add(new Option(header.textContent.trim(), String(column)));
    }
  });
  filterLabel.append(filter);
  const count = document.createElement('span');
  count.className = 'muted';
  count.setAttribute('aria-live', 'polite');
  const update = () => {
    const term = normalized(search.value.trim());
    let visible = 0;
    for (const row of rows) {
      const text = filter.value === '' ? row.textContent : row.cells[Number(filter.value)]?.textContent || '';
      row.hidden = !normalized(text).includes(term);
      if (!row.hidden) visible++;
    }
    count.textContent = `${visible} de ${rows.length} registros`;
  };
  search.addEventListener('input', update);
  filter.addEventListener('change', update);
  controls.append(searchLabel, filterLabel, count);
  table.closest('.table-wrap').before(controls);
  update();
}

function addContentTabs(container, panels) {
  if (panels.length < 2) return;
  const tabs = document.createElement('div');
  tabs.className = 'dialog-tabs';
  tabs.setAttribute('role', 'tablist');
  panels.forEach((panel, index) => {
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'secondary';
    tab.textContent = panel.querySelector('h2')?.textContent || 'Datos';
    tab.setAttribute('role', 'tab');
    tab.addEventListener('click', () => select(index));
    tabs.append(tab);
  });
  function select(active) {
    panels.forEach((panel, index) => {
      panel.hidden = active !== index;
      tabs.children[index].setAttribute('aria-selected', String(active === index));
    });
  }
  container.prepend(tabs);
  select(0);
  container.addEventListener('invalid', event => {
    const index = panels.findIndex(panel => panel.contains(event.target));
    if (index !== -1) select(index);
  }, true);
}

function makeDialog(title) {
  const dialog = document.createElement('dialog');
  dialog.className = 'action-dialog';
  dialog.setAttribute('aria-label', title);
  const header = document.createElement('div');
  header.className = 'dialog-header';
  const heading = document.createElement('h2');
  heading.textContent = title;
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'secondary small';
  close.textContent = 'Cerrar';
  close.addEventListener('click', () => dialog.close());
  header.append(heading, close);
  dialog.append(header);
  document.body.append(dialog);
  return dialog;
}

function groupActions(main, panels, title) {
  if (!panels.length) return;
  const dialog = makeDialog(title);
  const tabs = document.createElement('div');
  tabs.className = 'dialog-tabs';
  tabs.setAttribute('role', 'tablist');
  const content = document.createElement('div');
  content.className = 'dialog-content';
  panels.forEach((panel, index) => {
    const label = panel.querySelector('h2')?.textContent || 'Datos';
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'secondary';
    tab.textContent = label;
    tab.setAttribute('role', 'tab');
    const panelId = `action-panel-${index}`;
    panel.id = panelId;
    panel.setAttribute('role', 'tabpanel');
    tab.setAttribute('aria-controls', panelId);
    tab.addEventListener('click', () => select(index));
    tabs.append(tab);
    content.append(panel);
  });
  function select(active) {
    panels.forEach((panel, index) => {
      panel.hidden = active !== index;
      tabs.children[index].setAttribute('aria-selected', String(active === index));
    });
  }
  dialog.append(tabs, content);
  select(0);
  const open = document.createElement('button');
  open.type = 'button';
  open.textContent = title;
  open.addEventListener('click', () => dialog.showModal());
  main.querySelector('.page-header')?.append(open);
}

function openInDialog(link, module = false) {
  const title = link.querySelector('strong')?.textContent.trim() || link.textContent.trim();
  const dialog = makeDialog(title);
  dialog.classList.add('frame-dialog');
  const frame = document.createElement('iframe');
  frame.name = module ? 'fibro-module' : 'fibro-action';
  frame.title = title;
  const url = new URL(link.href);
  url.searchParams.set('modal', '1');
  frame.src = url;
  dialog.append(frame);
  dialog.addEventListener('close', () => {
    dialog.remove();
    if (!module) window.location.reload();
  });
  dialog.showModal();
}

export function setupWorkspace() {
  const main = document.querySelector('main[data-page]');
  if (!main) return;
  const params = new URLSearchParams(location.search);
  if (window.self !== window.top) {
    document.body.classList.add('modal-page');
  }
  if (window.name === 'fibro-action' && window.self !== window.top) {
    const currentPage = params.get('page');
    const isAction = params.has('new') || params.has('edit') || (['orders', 'sales'].includes(currentPage) && params.has('id'));
    if (!isAction) {
      window.parent.postMessage({ type: 'fibro-action-finished', page: currentPage }, location.origin);
    }
    if (currentPage === 'users' && params.has('edit')) {
      main.querySelector('section.panel:has(table)')?.remove();
    }
    const actionForm = main.querySelector('[data-product-form], [data-order-form]');
    if (actionForm) {
      addContentTabs(actionForm, [...actionForm.querySelectorAll(':scope > .columns > section.panel, :scope > section.panel')]);
    } else if (['orders', 'sales'].includes(currentPage) && params.has('id')) {
      addContentTabs(main, [...main.querySelectorAll(':scope > section.panel')]);
    }
    return;
  }
  document.querySelectorAll('main table:not([data-billing-table])').forEach(addTableControls);
  const page = main.dataset.page;
  const title = { materials: 'Gestionar materia prima', sellers: 'Configurar salario', users: 'Gestionar usuario' }[page];
  if (title) {
    const panels = [...main.querySelectorAll('section.panel')].filter(panel => {
      const form = panel.querySelector('form[method="post"]');
      return form && !panel.querySelector('table');
    });
    groupActions(main, panels, title);
  }
  main.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const target = new URL(link.href);
    const action = target.searchParams.has('new') || target.searchParams.has('edit')
      || (['orders', 'sales'].includes(target.searchParams.get('page')) && target.searchParams.has('id'));
    if (target.origin === location.origin && action) {
      event.preventDefault();
      openInDialog(link);
    }
  });
  document.querySelectorAll('[data-module]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      openInDialog(link, true);
    });
  });
  main.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('form:has(input[name="action"][value="user_status"])') || form.dataset.confirmed) return;
    event.preventDefault();
    const dialog = makeDialog('Confirmar cambio de estado');
    const message = document.createElement('p');
    message.textContent = form.querySelector('button').getAttribute('aria-label') + ' ¿Deseas continuar?';
    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.textContent = 'Confirmar';
    confirm.addEventListener('click', () => {
      form.dataset.confirmed = '1';
      form.requestSubmit();
    });
    dialog.append(message, confirm);
    dialog.addEventListener('close', () => dialog.remove());
    dialog.showModal();
  });
  window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.data?.type !== 'fibro-action-finished') return;
    const dialog = document.querySelector('.frame-dialog[open]');
    if (dialog?.querySelector('iframe')?.contentWindow === event.source) dialog.close();
  });
}
