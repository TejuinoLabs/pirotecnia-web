import { formatInventoryQuantity } from './inventory-quantity.js';

const root = document.getElementById('inventory-panel');
if (root) {
    const config = JSON.parse(document.getElementById('inventory-config').textContent);
    const permissions = new Set(config.permissions);
    const base = root.dataset.base;
    const content = document.getElementById('inventory-content');
    const toolbar = document.getElementById('inventory-toolbar');
    const title = document.getElementById('inventory-title');
    const feedback = document.getElementById('inventory-feedback');
    const dialog = document.getElementById('inventory-dialog');
    const dialogContent = document.getElementById('inventory-dialog-content');
    const section = root.dataset.section;
    const isSettings = ['settings', 'commerce'].includes(root.dataset.area);
    const headerActions = document.getElementById('inventory-actions');
    const eventId = Number(root.dataset.event);
    const selectedCatalog = root.dataset.catalog;
    const selectedPackage = Number(root.dataset.package);
    const options = (key) => (config.catalogs[key] || []).map(x => [x.id, x.name]);
    const statuses = { recommended: 'Recomendado', compatible: 'Compatible', protected: 'Protegido', exception: 'Excepción', incompatible: 'No compatible', out_of_stock: 'Sin stock', specific_product: 'Producto específico', specification: 'Especificación', profile: 'Perfil', material: 'Material', equipment: 'Equipo', pending: 'Pendiente', reserved: 'Asignado', preparing: 'En preparación', ready: 'Listo', dispatched: 'Despachado', in_operation: 'En operación', awaiting_return: 'Por devolver', closed: 'Cerrado', critical: 'Déficit', warning: 'Cobertura ajustada', covered: 'Cubierto', draft: 'Borrador', ordered: 'Ordenada', partial: 'Parcial', received: 'Recibida', confirmed: 'Confirmado', completed: 'Completado', cancelled: 'Cancelado', picked: 'Surtido', allocated: 'Asignado', partially_covered: 'Asignación parcial', inactive: 'Inactivo', in_progress: 'En progreso' };
    const pretty = (value, key = '') => /^(quantity(?:_|$)|.*_quantity$|.*_stock$|future_demand$|balance$|minimum_stock$)/.test(key) && value != null ? formatInventoryQuantity(value) : value == null ? '—' : typeof value === 'object' ? JSON.stringify(value) : statuses[value] || String(value);
    const el = (tag, text, cls = '') => { const element = document.createElement(tag); if (text != null) element.textContent = text; element.className = cls; return element; };
    const msg = (text, error = false) => { feedback.textContent = text; feedback.classList.remove('hidden'); feedback.style.borderColor = error ? '#ef4444' : '#22c55e'; };
    const button = (label, action, parent = toolbar) => {
        const isPrimary = isSettings && parent === headerActions;
        const b = el('button', label, isPrimary
            ? 'inline-flex items-center justify-center gap-2 rounded-xl bg-secondary px-6 py-3 font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-colors hover:bg-secondary-600 disabled:opacity-50'
            : 'inline-flex items-center justify-center gap-2 rounded-lg border border-primary-600/50 px-4 py-2 text-sm font-semibold text-primary-200 transition-colors hover:bg-primary-700/70 hover:text-secondary disabled:opacity-50');
        b.type = 'button';
        if (isSettings) {
            const icon = isPrimary ? 'add_circle' : ({ Detalle: 'visibility', Editar: 'edit', Desactivar: 'block', 'Importar saldo inicial': 'move_to_inbox', Anterior: 'chevron_left', Siguiente: 'chevron_right' }[label]);
            if (icon) { const symbol = el('span', icon, 'material-symbols-outlined text-[20px]'); symbol.setAttribute('aria-hidden', 'true'); b.prepend(symbol); }
        }
        b.addEventListener('click', async () => { b.disabled = true; try { await action(); } catch (e) { msg(e.message, true); } finally { b.disabled = false; } }); parent.append(b); return b;
    };
    const canButton = (permission, ...args) => permissions.has(permission) && button(...args);
    const createButton = (permission, label, action, parent = toolbar) => canButton(permission, isSettings ? root.dataset.createLabel || label : label, action, isSettings ? headerActions : parent);
    async function api(path, method = 'GET', payload, key) {
        const response = await fetch(`${base}/${path}`, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.getElementById('inventory-csrf').value, ...(key ? { 'Idempotency-Key': key } : {}) }, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error([data.message || 'No fue posible completar la operación.', ...Object.values(data.errors || {}).flat()].join(' '));
        return data;
    }
    function field(name, label, type = 'text', extra = {}) { return { name, label, type, ...extra }; }
    const select = (name, label, key, extra = {}) => field(name, label, 'select', { options: options(key), ...extra });
    function inputField(spec, values = {}) {
        const wrapper = el('label', null, 'block space-y-2'); wrapper.append(el('span', spec.label, 'text-sm text-primary-200'));
        if (spec.type === 'attributes') {
            const children = (config.catalogs.attribute_definitions || []).map(def => {
                const type = def.data_type === 'number' ? 'number' : def.data_type === 'json' ? 'json' : def.data_type === 'boolean' ? 'select' : 'text';
                const fs = field(def.code, def.name, type, { optional: true, string: true, ...(type === 'select' ? { options: [['true', 'Sí'], ['false', 'No']] } : {}) });
                const child = inputField(fs, values[spec.name] || {}); child.definition = def; wrapper.append(child.wrapper); return child;
            });
            if (!children.length) wrapper.append(el('p', 'Define atributos en Catálogos para capturar sus valores.', 'text-sm text-primary-200'));
            return { wrapper, spec, children };
        }
        let input;
        if (spec.type === 'select' || spec.type === 'multiple') { input = el('select'); if (spec.type === 'multiple') input.multiple = true; if (spec.optional && spec.type !== 'multiple') { const op = el('option', 'Sin seleccionar'); op.value = ''; input.append(op); } for (const [value, label] of spec.options) { const op = el('option', label); op.value = value; input.append(op); } }
        else if (spec.type === 'json' || spec.type === 'textarea') { input = el('textarea'); input.rows = spec.type === 'json' ? 5 : 3; }
        else { input = el('input'); input.type = spec.type; if (spec.type === 'number') { input.step = spec.step || '0.001'; input.min = spec.min ?? '0'; } }
        if (spec.readonly) input.readOnly = true;
        input.name = spec.name; input.required = !spec.optional && spec.type !== 'checkbox';
        const current = values[spec.name] ?? spec.value;
        if (spec.type === 'multiple') { const selected = Array.isArray(current) ? current : JSON.parse(current || '[]'); [...input.options].forEach(op => { op.selected = selected.map(String).includes(op.value); }); }
        else if (spec.type === 'checkbox') input.checked = Boolean(current);
        else if (current != null) input.value = spec.type === 'json' ? (typeof current === 'string' ? current : JSON.stringify(current, null, 2)) : spec.type === 'number' ? formatInventoryQuantity(current) : current;
        input.className = spec.type === 'checkbox' ? 'ml-3 h-5 w-5 rounded border-primary-600 text-secondary focus:ring-secondary' : 'w-full rounded-xl border border-primary-600/50 bg-primary-700/70 px-4 py-3 text-sm text-on-primary outline-none transition focus:border-secondary focus:ring-1 focus:ring-secondary/30';
        wrapper.append(input); return { wrapper, input, spec };
    }
    function readFields(items) {
        const result = {};
        for (const item of items) {
            const { input, spec } = item;
            if (spec.type === 'attributes') {
                result[spec.name] = {};
                for (const child of item.children) {
                    const values = readFields([child]);
                    if (Object.hasOwn(values, child.spec.name)) result[spec.name][child.spec.name] = child.definition.data_type === 'boolean' ? values[child.spec.name] === 'true' : values[child.spec.name];
                }
            }
            else if (spec.type === 'multiple') result[spec.name] = [...input.selectedOptions].map(op => Number(op.value));
            else if (spec.type === 'checkbox') result[spec.name] = input.checked;
            else if (input.value === '' && spec.optional) continue;
            else if (spec.type === 'json') { try { result[spec.name] = JSON.parse(input.value); } catch { throw new Error(`Revisa el formato de ${spec.label}.`); } }
            else if (spec.type === 'number' || spec.type === 'select' && !spec.string) result[spec.name] = Number(input.value);
            else result[spec.name] = input.value;
        }
        return result;
    }
    function form(label, specs, submit, values = {}, rows) {
        document.getElementById('inventory-dialog-title').textContent = label; dialogContent.replaceChildren();
        const f = el('form', null, 'space-y-5'); const grid = el('div', null, 'grid gap-4 sm:grid-cols-2'); const fields = specs.map(s => inputField(s, values)); fields.forEach(x => grid.append(x.wrapper)); f.append(grid);
        let collectRows = () => undefined;
        if (rows) {
            const group = el('div', null, 'space-y-4'); const rowFields = [];
            const addRow = (initial = {}) => { const card = el('div', null, 'rounded-xl border border-primary-600 p-4'); const rg = el('div', null, 'grid gap-3 sm:grid-cols-2'); const fs = rows.fields.map(s => inputField(s, initial)); fs.forEach(x => rg.append(x.wrapper));
                const productField = fs.find(x => x.spec.name === 'product_id');
                const priceField = fs.find(x => ['unit_price', 'unit_cost'].includes(x.spec.name));
                if (productField && priceField) {
                    const price = () => { const product = (config.catalogs.products || []).find(product => product.id === Number(productField.input.value)); priceField.input.value = formatInventoryQuantity(product?.[priceField.spec.name === 'unit_price' ? 'sale_price' : 'purchase_cost'] ?? 0); };
                    productField.input.addEventListener('change', price);
                    if (initial[priceField.spec.name] == null) price();
                }
                card.append(rg); const row = { card, fs }; rowFields.push(row); if (!rows.fixed) button('Quitar línea', () => { card.remove(); rowFields.splice(rowFields.indexOf(row), 1); }, card); group.append(card); };
            f.append(el('h3', rows.label || 'Conceptos', 'font-bold'), group);
            (rows.initial || [{}]).forEach(addRow); if (!rows.fixed) button('Agregar línea', () => addRow(), f);
            collectRows = () => rowFields.map(x => readFields(x.fs));
        }
        const errors = el('p', '', 'text-warning'); errors.setAttribute('role', 'alert'); f.append(errors);
        const save = el('button', 'Guardar', 'rounded-xl bg-secondary px-5 py-3 font-bold text-primary-900 disabled:opacity-50'); save.type = 'submit'; f.append(save);
        const key = crypto.randomUUID();
        f.addEventListener('submit', async e => { e.preventDefault(); save.disabled = true; try { const result = await submit(readFields(fields), collectRows(), key); dialog.close(); msg('Operación guardada.'); const record = result?.sale || result?.purchase; if (root.dataset.area === 'commerce' && record?.id) window.location.assign(`/panel/${section}/${record.id}`); else window.location.reload(); } catch (error) { errors.textContent = error.message; } finally { save.disabled = false; } });
        dialogContent.append(f); dialog.showModal(); return f;
    }
    document.getElementById('inventory-dialog-close').addEventListener('click', () => dialog.close());
    function table(rows, columns, parent = content, actions, paginate = true) {
        if (paginate) {
            const area = el('div', null, 'space-y-4'); parent.append(area);
            const lastPage = Math.max(1, Math.ceil(rows.length / 15));
            const show = page => {
                area.replaceChildren(); table(rows.slice((page - 1) * 15, page * 15), columns, area, actions, false);
                const nav = el('nav', null, 'flex items-center gap-3'); nav.setAttribute('aria-label', 'Paginación del listado'); area.append(nav);
                const previous = button('Anterior', () => show(page - 1), nav); previous.disabled = page === 1;
                nav.append(el('span', `Página ${page} de ${lastPage} · ${rows.length} registros`, 'text-sm'));
                const next = button('Siguiente', () => show(page + 1), nav); next.disabled = page === lastPage;
            };
            show(1); return;
        }
        const wrap = el('div', null, 'overflow-x-auto');
        const t = el('table', null, isSettings ? 'responsive-stack-table responsive-stack-table-dark w-full border-collapse text-left text-sm' : 'w-full text-left text-sm');
        const head = el('tr', null, isSettings ? 'bg-primary-700/70' : '');
        const headerClass = isSettings ? 'px-6 py-4 text-xs font-extrabold uppercase tracking-widest text-primary-200' : 'whitespace-nowrap border-b border-primary-600 p-3 text-primary-200';
        columns.forEach(([, label]) => head.append(el('th', label, headerClass)));
        if (actions) head.append(el('th', 'Acciones', headerClass));
        const thead = el('thead'); thead.append(head); t.append(thead);
        const body = el('tbody', null, isSettings ? 'divide-y divide-primary-700/50' : '');
        for (const row of rows) {
            const tr = el('tr', null, isSettings ? 'transition-colors hover:bg-primary-700/35' : '');
            for (const [key, label] of columns) {
                const value = typeof key === 'function' ? key(row) : key === 'name' ? row.display_name || row.name : row[key];
                const td = el('td', null, isSettings ? 'px-6 py-5 text-primary-200' : 'border-b border-primary-700 p-3');
                td.dataset.label = label;
                if (isSettings && key === 'active') {
                    const active = value === true || Number(value) === 1;
                    td.append(el('span', active ? 'Activo' : 'Inactivo', `inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold ${active ? 'border-secondary/20 bg-secondary/10 text-secondary' : 'border-warning/20 bg-warning/10 text-warning'}`));
                } else {
                    td.textContent = pretty(value, typeof key === 'string' ? key : '');
                    if (isSettings && key === 'name') td.classList.add('font-semibold', 'text-on-primary');
                }
                tr.append(td);
            }
            if (actions) {
                const td = el('td', null, isSettings ? 'px-6 py-4' : 'border-b border-primary-700 p-3'); td.dataset.label = 'Acciones';
                const group = el('div', null, 'flex flex-wrap items-center gap-2'); actions(row, group); td.append(group); tr.append(td);
            }
            body.append(tr);
        }
        t.append(body); wrap.append(t); parent.append(wrap);
        if (!rows.length) {
            const empty = el('div', null, isSettings ? 'm-6 rounded-2xl border border-primary-700/60 bg-primary-700/50 p-8 text-center' : 'py-4 text-primary-200');
            if (isSettings) { const icon = el('span', 'inventory_2', 'material-symbols-outlined text-5xl text-primary-200'); icon.setAttribute('aria-hidden', 'true'); empty.append(icon); }
            empty.append(el('p', 'Sin registros para esta consulta.', isSettings ? 'mt-4 text-lg font-bold text-on-primary' : ''));
            parent.append(empty);
        }
    }
    function paged(data, columns, parent, actions, path) {
        const area = el('div', null, 'space-y-4'); parent.append(area);
        const show = page => {
            area.replaceChildren(); table(page.data || [], columns, area, actions, false);
            const nav = el('nav', null, isSettings ? 'flex flex-wrap items-center justify-between gap-3 bg-primary-700/40 px-6 py-5' : 'mt-4 flex items-center gap-3'); nav.setAttribute('aria-label', 'Paginación del listado'); area.append(nav);
            const go = async number => {
                const [endpoint, query = ''] = path.split('?');
                const params = new URLSearchParams(query); params.set('page', number);
                show(await api(`${endpoint}?${params}`));
            };
            const previous = button('Anterior', () => go(page.current_page - 1), nav); previous.disabled = !page.prev_page_url;
            nav.append(el('span', `Página ${page.current_page || 1} de ${page.last_page || 1} · ${page.total || 0} registros`, 'px-3 py-2 text-sm'));
            const next = button('Siguiente', () => go(page.current_page + 1), nav); next.disabled = !page.next_page_url;
        };
        show(data);
    }
    const areaLabels = { polvorin: 'Polvorín', stand: 'Puestos', raw_material: 'Materia prima', external: 'Fuera de almacén' };
    const locationLabel = l => `${areaLabels[l.inventory_area] || areaLabels[l.type === 'stand' ? 'stand' : 'external']} · ${l.name}`;
    const storage = () => field('warehouse_location_id', 'Destino / origen del inventario', 'select', { options: (config.catalogs.inventory_locations || []).filter(l => ['warehouse', 'stand'].includes(l.type)).map(l => [l.id, locationLabel(l)]) });
    const warehouse = () => field('warehouse_location_id', 'Almacén', 'select', { options: (config.catalogs.inventory_locations || []).filter(x => x.type === 'warehouse').map(x => [x.id, x.name]) });
    const notes = () => field('notes', 'Notas', 'textarea', { optional: true });
    const productFields = () => [field('name', 'Nombre'), field('sku', 'SKU', 'text', { optional: true }), field('caliber', 'Calibre (pulgadas)', 'text', { optional: true }), select('product_role_id', 'Tipo de artículo', 'product_roles'), select('category_id', 'Categoría', 'categories', { optional: true }), field('unit', 'Unidad', 'text', { value: 'pz' }), field('reference_value', 'Valor de referencia', 'number', { optional: true }), field('purchase_cost', 'Costo', 'number', { optional: true }), field('sale_price', 'Precio de venta', 'number', { optional: true }), field('minimum_stock', 'Mínimo', 'number', { value: 0 }), select('legacy_equipment_id', 'Equipo del catálogo anterior', 'equipments', { optional: true }), field('description', 'Descripción', 'textarea', { optional: true }), field('attributes', 'Atributos', 'attributes'), field('effects', 'Efectos', 'multiple', { options: options('effects'), optional: true }), field('colors', 'Colores', 'multiple', { options: options('colors'), optional: true }), ...['active', 'tracks_inventory', 'is_consumable', 'is_returnable', 'is_sellable', 'is_event_usable'].map((name, i) => field(name, ['Activo', 'Controla inventario', 'Consumible', 'Retornable', 'Vendible', 'Usable en eventos'][i], 'checkbox', { value: name !== 'is_returnable' }))];
    async function inventoryCount(productId, locationId) {
        if (!(config.catalogs.products || []).some(p => p.tracks_inventory)) { msg('Agrega primero un producto o material que controle inventario en Configuración → Productos.', true); return; }
        if (!(config.catalogs.inventory_locations || []).some(l => ['warehouse', 'stand'].includes(l.type))) { msg('Crea primero la ubicación de polvorín, puesto o materia prima en Configuración → Ubicaciones.', true); return; }
        const f = form('Carga inicial o corrección de inventario', [field('product_id', 'Producto o material', 'select', { options: (config.catalogs.products || []).filter(p => p.tracks_inventory).map(p => [p.id, p.name]) }), field('location_id', 'Área y ubicación', 'select', { options: (config.catalogs.inventory_locations || []).filter(l => ['warehouse', 'stand'].includes(l.type)).map(l => [l.id, locationLabel(l)]) }), field('purpose', 'Motivo del conteo', 'select', { string: true, options: [['correction', 'Corrección por conteo físico'], ['initial', 'Carga inicial (sin movimientos anteriores)']] }), field('expected_quantity', 'Existencia actual en el sistema', 'number', { readonly: true }), field('quantity', 'Existencia real contada (cantidad total)', 'number', { min: 0 }), field('notes', 'Motivo u observaciones', 'textarea')], (data, _, key) => api('inventory/counts', 'POST', data, key), { product_id: productId, location_id: locationId });
        dialogContent.prepend(el('p', 'Captura la cantidad total que existe físicamente, incluida cero. Se registra únicamente la diferencia como ajuste en el Kardex, sin crear una compra.', 'mb-5 text-sm text-primary-200'));
        let revision = 0;
        const refresh = async () => {
            const current = ++revision; const save = f.querySelector('[type=submit]'); save.disabled = true;
            try {
                const data = await api(`inventory/balances/${f.elements.product_id.value}?location_id=${f.elements.location_id.value}`);
                if (current !== revision) return;
                f.elements.expected_quantity.value = formatInventoryQuantity(data.quantity); f.elements.quantity.value = formatInventoryQuantity(data.quantity); save.disabled = false;
            } catch (e) { if (current === revision) f.querySelector('[role=alert]').textContent = e.message; }
        };
        f.elements.product_id.addEventListener('change', refresh); f.elements.location_id.addEventListener('change', refresh); await refresh();
    }
    async function inventory() {
        title.textContent = 'Inventario operativo';
        canButton('inventory.adjust', 'Carga inicial / Corregir existencia', () => inventoryCount());
        canButton('inventory.adjust', 'Registrar ajuste', () => form('Ajuste de inventario', [select('product_id', 'Producto', 'products'), select('location_id', 'Ubicación', 'inventory_locations'), field('direction', 'Tipo de ajuste', 'select', { string: true, options: [['in', 'Entrada'], ['out', 'Salida']] }), field('quantity', 'Cantidad', 'number', { min: 0.001 }), field('notes', 'Motivo', 'textarea')], d => api('inventory/adjustments', 'POST', d)));
        const area = inputField(field('inventory_area', 'Área de inventario', 'select', { string: true, options: [['polvorin', 'Polvorín'], ['stand', 'Puestos'], ['raw_material', 'Materia prima']] }));
        const filter = inputField(field('location_id', 'Ubicación en el área', 'select', { optional: true, options: [] }));
        const query = async () => { content.replaceChildren(); title.textContent = `Inventario · ${areaLabels[area.input.value]}`; const params = new URLSearchParams(readFields([area, filter])); await showStock(`inventory?${params}`); };
        const locations = () => { filter.input.replaceChildren(); const all = el('option', 'Todas las ubicaciones del área'); all.value = ''; filter.input.append(all); for (const l of config.catalogs.inventory_locations || []) { if (l.inventory_area === area.input.value && ['warehouse', 'stand'].includes(l.type)) { const option = el('option', l.name); option.value = l.id; filter.input.append(option); } } };
        toolbar.append(area.wrapper, filter.wrapper); area.input.addEventListener('change', () => { locations(); query().catch(e => msg(e.message, true)); }); button('Consultar', query); locations(); await query();
    }
    async function showStock(path) {
        paged(await api(path), [['name', 'Producto'], ['sku', 'SKU'], [r => options('categories').find(x => x[0] === r.category_id)?.[1], 'Categoría'], ['physical_stock', 'Existencia'], ['reserved_stock', 'Reservado'], ['protected_stock', 'Protegido'], ['available_stock', 'Disponible'], ['future_demand', 'Demanda futura'], ['incoming_stock', 'En compra'], ['projected_stock', 'Proyección']], content, (row, td) => {
            button('Detalle', () => productDetail(row.id), td);
            canButton('inventory.adjust', 'Actualizar existencia', () => inventoryCount(row.id, new URLSearchParams(path.split('?')[1] || '').get('location_id') || undefined), td);
        }, path);
    }
    async function productDetail(id) {
        const product = await api(`products/${id}`);
        document.getElementById('inventory-dialog-title').textContent = product.display_name || product.name; dialogContent.replaceChildren();
        table([product], [['sku', 'SKU'], ['reference_value', 'Valor'], ['purchase_cost', 'Costo'], ['attributes', 'Atributos'], [r => r.effects.map(id => options('effects').find(x => x[0] === id)?.[1] || id).join(', '), 'Efectos'], [r => r.colors.map(id => options('colors').find(x => x[0] === id)?.[1] || id).join(', '), 'Colores']], dialogContent);
        if (section === 'products') canButton('packages.manage', 'Editar artículo', () => { dialog.close(); form('Editar artículo', productFields(), d => api(`products/${id}`, 'PUT', d), product); }, dialogContent);
        button('Kardex', async () => { const ledger = await api(`inventory/movements?product_id=${id}`); paged(ledger, [['occurred_at', 'Fecha'], ['movement_type', 'Tipo'], ['origin', 'Origen'], ['destination', 'Destino'], ['quantity', 'Cantidad'], ['balance', 'Saldo total']], dialogContent, null, `inventory/movements?product_id=${id}`); }, dialogContent);
        dialog.showModal();
    }
    async function products() {
        title.textContent = isSettings ? 'Listado de productos' : 'Productos';
        createButton('packages.manage', 'Nuevo artículo', () => form('Artículo', productFields(), d => api('products', 'POST', d)));
        paged(await api('products'), [['name', 'Producto'], ['sku', 'SKU'], [r => options('categories').find(x => x[0] === r.category_id)?.[1], 'Categoría'], ['unit', 'Unidad'], ['active', 'Estado']], content, (r, td) => {
            button('Detalle', () => productDetail(r.id), td);
            canButton('packages.manage', 'Editar', async () => form('Editar artículo', productFields(), d => api(`products/${r.id}`, 'PUT', d), await api(`products/${r.id}`)), td);
        }, 'products');
    }
    async function kardex() {
        title.textContent = 'Kardex';
        const specs = [select('product_id', 'Producto', 'products', { optional: true }), select('category_id', 'Categoría', 'categories', { optional: true }), select('location_id', 'Ubicación', 'inventory_locations', { optional: true }), field('date_from', 'Desde', 'date', { optional: true }), field('date_to', 'Hasta', 'date', { optional: true }), field('movement_type', 'Movimiento', 'select', { optional: true, string: true, options: ['purchase_receipt', 'event_dispatch', 'event_return', 'adjustment_in', 'adjustment_out', 'correction', 'damage', 'loss', 'consumption', 'transfer', 'sale', 'stand_transfer', 'stand_return'].map(x => [x, x]) }), field('event_id', 'Evento', 'number', { optional: true }), field('purchase_id', 'Compra', 'number', { optional: true }), field('created_by', 'ID de usuario', 'number', { optional: true })];
        const fs = specs.map(s => inputField(s)); fs.forEach(f => toolbar.append(f.wrapper));
        const query = async () => { content.replaceChildren(); const params = new URLSearchParams(readFields(fs)); const path = `inventory/movements?${params}`; paged(await api(path), [['occurred_at', 'Fecha'], ['product_name', 'Producto'], ['movement_type', 'Movimiento'], ['origin', 'Origen'], ['destination', 'Destino'], ['quantity', 'Cantidad'], ['balance', 'Saldo'], ['reference', 'Referencia'], ['actor', 'Usuario']], content, (r, td) => canButton('inventory.adjust', 'Corregir', () => form('Movimiento inverso', [field('reason', 'Motivo', 'textarea')], d => api(`inventory/movements/${r.id}/reverse`, 'POST', d)), td), path); };
        button('Filtrar', query); await query();
    }
    const currency = value => `$${Number(value || 0).toFixed(2)}`;
    const productName = id => options('products').find(([value]) => value === Number(id))?.[1] || `Producto #${id}`;
    const saleFields = () => [field('date', 'Fecha', 'date', { value: root.dataset.today }), field('client_name', 'Cliente'), field('client_phone', 'Teléfono', 'text', { optional: true }), field('client_email', 'Correo', 'email', { optional: true }), field('client_address', 'Dirección', 'text', { optional: true }), select('client_type_id', 'Tipo de cliente', 'client_types', { optional: true }), { ...storage(), optional: true }, field('status', 'Estado', 'select', { string: true, value: (config.catalogs.inventory_locations || []).some(l => ['warehouse', 'stand'].includes(l.type)) ? 'completed' : 'draft', options: [['draft', 'Borrador'], ['completed', 'Finalizada']] }), field('discount', 'Descuento ($)', 'number', { value: 0, step: '0.01' }), field('tax', 'Impuesto ($)', 'number', { value: 0, step: '0.01' }), notes()];
    const saleItemFields = () => [field('product_id', 'Producto o material', 'select', { options: (config.catalogs.products || []).filter(product => product.active && product.is_sellable).map(product => [product.id, product.name]) }), field('quantity', 'Cantidad', 'number', { min: 0.001, value: 1 }), field('unit_price', 'Precio unitario ($)', 'number', { step: '0.01' })];
    async function saleForm(id) {
        if (!id && !(config.catalogs.products || []).some(p => p.is_sellable)) { msg('Agrega primero un artículo vendible en Configuración → Productos.', true); return; }
        const data = id ? await api(`sales/${id}`) : null;
        const fields = saleFields();
        if (data?.sale.status === 'completed') fields.find(field => field.name === 'status').options = [['completed', 'Finalizada']];
        form(id ? `Editar venta #${id}` : 'Nueva venta', fields, (values, items, key) => api(id ? `sales/${id}` : 'sales', id ? 'PUT' : 'POST', { ...values, items }, key), data?.sale || {}, { fields: saleItemFields(), initial: data?.items.map(item => ({ product_id: item.product_id, quantity: item.quantity, unit_price: item.unit_price })) });
    }
    async function saleDetail(id) {
        const data = await api(`sales/${id}`);
        document.getElementById('inventory-dialog-title').textContent = `Venta #${id}`; dialogContent.replaceChildren();
        table([data.sale], [['client_name', 'Cliente'], ['date', 'Fecha'], ['status', 'Estado'], [row => currency(row.total), 'Total']], dialogContent);
        table(data.items, [['product_name', 'Producto o material'], ['quantity', 'Cantidad'], ['unit', 'Unidad'], [row => currency(row.unit_price), 'Precio'], [row => currency(row.subtotal), 'Subtotal']], dialogContent);
        dialogContent.append(el('p', `Subtotal: ${currency(data.sale.subtotal)} · Descuento: ${currency(data.sale.discount)} · Impuesto: ${currency(data.sale.tax)} · Total: ${currency(data.sale.total)}`, 'py-4 font-semibold text-on-primary'));
        if (data.sale.notes) dialogContent.append(el('p', data.sale.notes, 'text-sm text-primary-200'));
        dialog.showModal();
    }
    async function sales() {
        title.textContent = 'Listado de ventas';
        createButton('sales.manage', 'Nueva venta', () => saleForm());
        const filters = [inputField(field('q', 'Cliente', 'text', { optional: true })), inputField(field('status', 'Estado', 'select', { optional: true, string: true, options: [['draft', 'Borrador'], ['completed', 'Finalizada'], ['cancelled', 'Cancelada'], ['legacy', 'Registro anterior']] }))];
        filters.forEach(filter => toolbar.append(filter.wrapper));
        const show = async () => { content.replaceChildren(); const path = `sales?${new URLSearchParams(readFields(filters))}`;
            paged(await api(path), [['id', 'Folio'], ['client_name', 'Cliente'], ['date', 'Fecha'], ['status', 'Estado'], [row => currency(row.total), 'Total']], content, (row, actions) => {
                button('Detalle', () => saleDetail(row.id), actions);
                if (row.uses_operational_inventory && ['draft', 'completed'].includes(row.status)) {
                    canButton('sales.manage', 'Editar', () => saleForm(row.id), actions);
                    canButton('sales.manage', 'Cancelar', () => { form(`Cancelar venta #${row.id}`, [], (_, __, key) => api(`sales/${row.id}`, 'DELETE', {}, key)); dialogContent.prepend(el('p', 'Al cancelar una venta finalizada se reincorporan sus cantidades al almacén.', 'mb-5 text-sm text-primary-200')); }, actions);
                }
            }, path);
        };
        button('Filtrar', show); await show();
    }
    const purchaseFields = () => [field('purchase_date', 'Fecha', 'date', { value: root.dataset.today }), field('supplier_name', 'Proveedor', 'text', { optional: true }), field('expected_date', 'Fecha esperada', 'date', { optional: true }), field('status', 'Estado', 'select', { string: true, value: (config.catalogs.inventory_locations || []).some(l => ['warehouse', 'stand'].includes(l.type)) ? 'received' : 'ordered', options: [['received', 'Recibir ahora'], ['ordered', 'Pendiente de recibir'], ['draft', 'Borrador']] }), field('warehouse_location_id', 'Destino de la compra (polvorín, puesto o materia prima)', 'select', { options: (config.catalogs.inventory_locations || []).filter(location => ['warehouse', 'stand'].includes(location.type)).map(location => [location.id, locationLabel(location)]), optional: true }), field('tax', 'Impuesto ($)', 'number', { value: 0, step: '0.01' }), notes()];
    const purchaseItemFields = () => [select('product_id', 'Producto o material', 'products'), field('quantity', 'Cantidad', 'number', { min: 0.001, value: 1 }), field('unit_cost', 'Costo unitario ($)', 'number', { step: '0.01' })];
    async function purchaseForm(id) {
        if (!id && !(config.catalogs.products || []).length) { msg('Agrega primero un producto o material en Configuración → Productos.', true); return; }
        const data = id ? await api(`purchases/${id}`) : null;
        const received = data?.receipts.length > 0;
        let fields = purchaseFields();
        if (id) {
            fields = fields.filter(field => !received || !['warehouse_location_id', 'status'].includes(field.name));
            const status = fields.find(field => field.name === 'status'); if (status) status.options = [['ordered', 'Pendiente de recibir'], ['draft', 'Borrador']];
        }
        form(id ? `Editar compra #${id}` : 'Nueva compra', fields, (values, items, key) => api(id ? `purchases/${id}` : 'purchases', id ? 'PUT' : 'POST', { ...values, ...(items ? { items } : {}) }, key), data ? { ...data.purchase, purchase_date: data.purchase.date, notes: data.purchase.observations, warehouse_location_id: data.purchase.destination_location_id } : {}, received ? undefined : { fields: purchaseItemFields(), initial: data?.items.map(item => ({ product_id: item.product_id, quantity: item.quantity, unit_cost: item.unit_cost })) });
        if (received) dialogContent.prepend(el('p', 'Esta compra ya tiene recepciones. Los conceptos y cantidades se conservan; puedes editar sus datos generales.', 'mb-5 text-sm text-primary-200'));
    }
    async function purchaseDetail(id) {
        const data = await api(`purchases/${id}`);
        document.getElementById('inventory-dialog-title').textContent = `Compra #${id}`; dialogContent.replaceChildren();
        table([data.purchase], [['supplier_name', 'Proveedor'], ['date', 'Fecha'], ['status', 'Estado'], [row => currency(row.total), 'Total']], dialogContent);
        table(data.items, [[row => row.product_name || productName(row.product_id), 'Producto o material'], ['quantity', 'Cantidad comprada'], ['received_quantity', 'Recibido'], [row => currency(row.unit_cost), 'Costo'], [row => currency(Number(row.quantity) * Number(row.unit_cost)), 'Subtotal']], dialogContent);
        dialogContent.append(el('p', `Subtotal: ${currency(data.purchase.subtotal)} · Impuesto: ${currency(data.purchase.tax)} · Total: ${currency(data.purchase.total)}`, 'py-4 font-semibold text-on-primary'));
        if (data.purchase.observations) dialogContent.append(el('p', data.purchase.observations, 'text-sm text-primary-200'));
        dialog.showModal();
    }
    async function purchaseReceive(id) {
        const data = await api(`purchases/${id}`);
        form(`Recepción de compra #${id}`, [storage(), notes()], (values, items, key) => api(`purchases/${id}/receive`, 'POST', { ...values, items: items.filter(item => item.quantity > 0).map(item => ({ purchase_item_id: item.purchase_item_id, quantity: item.quantity })) }, key), {}, { fixed: true, initial: data.items.filter(item => Number(item.quantity) > Number(item.received_quantity)).map(item => ({ purchase_item_id: item.id, product_name: item.product_name || productName(item.product_id), quantity: Number(item.quantity) - Number(item.received_quantity) })), fields: [field('purchase_item_id', 'Línea', 'number', { readonly: true }), field('product_name', 'Producto o material', 'text', { readonly: true }), field('quantity', 'Cantidad recibida', 'number')] });
    }
    async function purchases() {
        title.textContent = 'Listado de compras';
        createButton('purchases.create', 'Nueva compra', () => purchaseForm());
        const filters = [inputField(field('q', 'Proveedor', 'text', { optional: true })), inputField(field('status', 'Estado', 'select', { optional: true, string: true, options: [['draft', 'Borrador'], ['ordered', 'Pendiente'], ['partial', 'Recepción parcial'], ['received', 'Recibida'], ['cancelled', 'Cancelada']] }))];
        filters.forEach(filter => toolbar.append(filter.wrapper));
        const show = async () => { content.replaceChildren(); const path = `purchases?${new URLSearchParams(readFields(filters))}`;
            paged(await api(path), [['id', 'Folio'], ['supplier_name', 'Proveedor'], ['date', 'Fecha'], ['status', 'Estado'], [row => currency(row.total), 'Total']], content, (row, actions) => {
                button('Detalle', () => purchaseDetail(row.id), actions);
                if (!row.uses_operational_inventory) { actions.append(el('span', 'Compra anterior', 'text-xs text-primary-200')); return; }
                if (['draft', 'ordered', 'partial', 'received'].includes(row.status)) {
                    canButton('purchases.create', 'Editar', () => purchaseForm(row.id), actions);
                    canButton('purchases.create', 'Cancelar', () => { form(`Cancelar compra #${row.id}`, [], () => api(`purchases/${row.id}`, 'DELETE')); dialogContent.prepend(el('p', 'Las cantidades recibidas se conservan en inventario. La cancelación anula únicamente el saldo pendiente.', 'mb-5 text-sm text-primary-200')); }, actions);
                }
                if (['ordered', 'partial'].includes(row.status)) canButton('purchases.receive', 'Recibir', () => purchaseReceive(row.id), actions);
            }, path);
        };
        button('Filtrar', show); await show();
    }
    const definitions = {
        categories: ['Categorías', [field('name', 'Nombre'), field('code', 'Código')]],
        attributes: ['Atributos', [field('name', 'Nombre'), field('code', 'Código'), field('data_type', 'Tipo de dato', 'select', { string: true, options: [['number', 'Número'], ['string', 'Texto'], ['boolean', 'Sí/No'], ['json', 'Lista u objeto']] }), field('unit', 'Unidad', 'text', { optional: true })]],
        effects: ['Efectos', [field('name', 'Nombre'), field('code', 'Código')]], colors: ['Colores', [field('name', 'Nombre'), field('code', 'Código')]],
        locations: ['Ubicaciones', [field('name', 'Nombre'), field('code', 'Código'), field('type', 'Tipo', 'select', { string: true, options: [['warehouse', 'Almacén'], ['stand', 'Puesto'], ['technician', 'Técnico'], ['event', 'Evento'], ['damaged', 'Daños'], ['quarantine', 'Cuarentena'], ['returns', 'Devoluciones']] }), field('inventory_area', 'Área de inventario', 'select', { string: true, optional: true, options: [['polvorin', 'Polvorín'], ['stand', 'Puestos'], ['raw_material', 'Materia prima'], ['external', 'Ubicación externa (evento, técnico, daños)']] }), select('parent_id', 'Ubicación superior', 'inventory_locations', { optional: true }), select('legacy_inventory_id', 'Inventario anterior a importar', 'inventories', { optional: true })]],
        profiles: ['Perfiles', [field('name', 'Nombre'), select('category_id', 'Categoría', 'categories'), field('constraints', 'Restricciones', 'json', { value: [] })]],
        'inventory-protection-rules': ['Protección', [field('name', 'Nombre'), select('category_id', 'Categoría', 'categories', { optional: true }), select('product_id', 'Producto', 'products', { optional: true }), field('priority', 'Prioridad', 'number', { value: 0 }), field('action', 'Acción', 'select', { string: true, options: [['protect_stock', 'Proteger cantidad'], ['penalize', 'Penalizar prioridad'], ['exclude', 'Excluir'], ['prefer', 'Preferir']] }), field('attribute_filters', 'Filtros por atributos', 'json', { value: {} }), field('protected_quantity', 'Cantidad protegida', 'number', { optional: true }), field('protected_percentage', 'Porcentaje protegido', 'number', { optional: true }), field('applicable_event_types', 'Aplica a eventos', 'multiple', { options: options('event_types'), optional: true }), field('excluded_event_types', 'Eventos exceptuados', 'multiple', { options: options('event_types'), optional: true }), field('message', 'Mensaje para almacén', 'textarea', { optional: true })]],
        'allocation-rules': ['Asignación', [field('name', 'Nombre'), field('priority', 'Prioridad', 'number', { value: 0 }), field('scope', 'Ámbito', 'select', { string: true, options: ['global', 'category', 'event_type', 'package', 'product_profile'].map(x => [x, x]) }), field('condition_json', 'Condiciones', 'json', { value: {} }), field('action_json', 'Acciones', 'json', { value: { score_delta: 20 } })]],
    };
    async function catalogs(rulesOnly = false) {
        title.textContent = isSettings ? `Listado de ${(rulesOnly ? 'reglas de inventario' : definitions[selectedCatalog]?.[0] || 'catálogos').toLowerCase()}` : rulesOnly ? 'Reglas de inventario' : definitions[selectedCatalog]?.[0] || 'Catálogos';
        const picker = inputField(field('catalog', 'Catálogo', 'select', { string: true, options: Object.entries(definitions).filter(([k]) => rulesOnly ? k.endsWith('rules') : !k.endsWith('rules')).map(([k, v]) => [k, v[0]]) })); if (rulesOnly) toolbar.append(picker.wrapper);
        const show = async () => {
            content.replaceChildren(); headerActions?.replaceChildren(); const name = selectedCatalog || picker.input.value; const [label, specs] = definitions[name]; const path = name.endsWith('rules') ? name : `inventory/catalogs/${name}`;
            createButton('rules.manage', `Agregar ${label.toLowerCase()}`, () => form(label, specs, d => api(path, 'POST', d)), content);
            const catalogColumns = [['name', 'Nombre']];
            if (['categories', 'attributes', 'effects', 'colors', 'locations'].includes(name)) catalogColumns.push(['code', 'Código']);
            if (name === 'attributes') catalogColumns.push([r => ({ number: 'Número', string: 'Texto', boolean: 'Sí / No', json: 'Lista u objeto' }[r.data_type]), 'Tipo'], ['unit', 'Unidad']);
            if (name === 'locations') catalogColumns.push([r => areaLabels[r.inventory_area], 'Área'], [r => ({ warehouse: 'Almacén', stand: 'Puesto', technician: 'Técnico', event: 'Evento', damaged: 'Daños', quarantine: 'Cuarentena', returns: 'Devoluciones' }[r.type]), 'Tipo']);
            if (name === 'profiles') catalogColumns.push([r => options('categories').find(x => x[0] === r.category_id)?.[1], 'Categoría']);
            if (name.endsWith('rules')) catalogColumns.push(['priority', 'Prioridad']);
            catalogColumns.push(['active', 'Estado']);
            paged(await api(path), catalogColumns, content, (r, td) => {
                canButton('rules.manage', 'Editar', async () => { const record = await api(`${path}/${r.id}`); form(label, [...specs, field('active', 'Activo', 'checkbox')], d => api(`${path}/${r.id}`, 'PUT', d), record); }, td);
                canButton('rules.manage', 'Desactivar', () => form('Desactivar registro', [], () => api(`${path}/${r.id}`, 'DELETE')), td);
                if (name === 'locations' && r.legacy_inventory_id && !r.adopted_at) canButton('inventory.adjust', 'Importar saldo inicial', () => form('Importar existencias anteriores', [], () => api(`inventory/locations/${r.id}/adopt`, 'POST', {})), td);
            }, path);
        }; if (rulesOnly) button('Consultar reglas', show); await show();
    }
    async function requirements() {
        title.textContent = isSettings ? 'Listado de requerimientos' : 'Requerimientos de paquetes';
        const picker = inputField(select('package_id', 'Paquete', 'packages'), { package_id: selectedPackage || undefined }); if (!selectedPackage) toolbar.append(picker.wrapper);
        const show = async () => { content.replaceChildren(); headerActions?.replaceChildren(); const id = picker.input.value; if (!id) return; const data = await api(`packages/${id}`);
            createButton('packages.manage', 'Agregar requerimiento', () => form('Requerimiento de paquete', [field('requirement_type', 'Tipo', 'select', { string: true, options: [['specification', 'Especificación'], ['specific_product', 'Producto específico'], ['profile', 'Perfil'], ['material', 'Material'], ['equipment', 'Equipo']] }), select('product_id', 'Producto o equipo inventariable', 'products', { optional: true }), select('category_id', 'Categoría', 'categories', { optional: true }), select('profile_id', 'Perfil', 'product_profiles', { optional: true }), field('quantity', 'Cantidad', 'number', { min: 0.001 }), field('reference_value', 'Valor de referencia', 'number', { optional: true }), field('value_tolerance', 'Tolerancia de valor', 'number', { optional: true }), field('allow_substitution', 'Permite sustitución', 'checkbox', { value: true }), field('metadata', 'Preferencias de variedad y valor', 'json', { value: {} })], (d, constraints) => api(`packages/${id}/requirements`, 'POST', { ...d, constraints }), {}, { label: 'Restricciones', initial: [], fields: [field('attribute_code', 'Código de atributo'), field('operator', 'Operador', 'select', { string: true, options: ['=', '!=', '>', '>=', '<', '<=', 'between', 'in', 'not_in', 'contains'].map(x => [x, x]) }), field('value', 'Valor (texto entre comillas, número o lista)', 'json', { value: 100 }), field('is_hard_constraint', 'Obligatoria', 'checkbox', { value: true }), field('weight', 'Peso de preferencia', 'number', { optional: true })] }), content);
            table(data.requirements, [['id', 'ID'], ['requirement_type', 'Tipo'], ['quantity', 'Cantidad'], ['reference_value', 'Valor'], ['value_tolerance', 'Tolerancia'], ['constraints', 'Restricciones']]);
        }; if (!selectedPackage) button('Abrir paquete', show); await show();
    }
    async function events() {
        title.textContent = 'Preparación de eventos';
        paged(await api('event-preparations'), [['id', 'Evento'], ['client_name', 'Cliente'], ['event_date', 'Fecha'], ['logistics_status', 'Logística']], content, (r, td) => { const link = el('a', 'Preparar', 'text-secondary underline'); link.href = `/events/${r.id}/preparation`; td.append(link); }, 'event-preparations');
    }
    async function preparation() {
        const path = `events/${eventId}`; const data = await api(`${path}/preparation`); title.textContent = `Evento #${eventId} · ${data.event.client_name} · ${pretty(data.event.logistics_status)}`;
        content.append(el('p', `${data.percent_completed}% asignado · ${pretty(data.coverage.status)}`, 'text-primary-200'));
        if (!data.requirements.length) canButton('events.prepare', 'Importar requerimientos del evento', async () => { await api(`${path}/requirements/snapshot`, 'POST', {}); await load(); });
        canButton('events.prepare', 'Iniciar preparación', async () => { await api(`${path}/preparation/start`, 'POST', {}); await load(); });
        canButton('events.prepare', 'Crear picking', () => form('Picking', [warehouse()], d => api(`${path}/pickings`, 'POST', d)));
        canButton('events.prepare', 'Marcar preparación lista', async () => { await api(`${path}/preparation/complete`, 'POST', {}); await load(); });
        table(data.requirements, [[r => r.snapshot.product_name || r.snapshot.profile_name || r.snapshot.category_name || `#${r.id}`, 'Requerimiento'], ['requirement_type', 'Tipo'], ['quantity_required', 'Requerido'], ['quantity_allocated', 'Asignado'], ['status', 'Estado'], ['constraints', 'Restricciones']], content, (r, td) => {
            button('Candidatos y asignación', () => candidates(r, path), td);
        });
        content.append(el('h3', 'Asignaciones', 'text-lg font-bold'));
        table(data.allocations, [['id', 'ID'], ['product_id', 'Producto'], ['quantity', 'Cantidad'], ['status', 'Estado']], content, (r, td) => { if (r.status === 'reserved') canButton('events.prepare', 'Sustituir', () => form('Sustitución', [select('product_id', 'Nuevo producto', 'products'), field('reason', 'Motivo', 'textarea'), field('override_reason', 'Motivo de uso protegido', 'textarea', { optional: true })], d => api(`requirement-allocations/${r.id}/replace`, 'POST', d)), td); });
        for (const picking of data.pickings) {
            const result = await api(`${path}/pickings/${picking.id}`); const box = el('div', null, 'space-y-3 rounded-xl border border-primary-600 p-4'); content.append(box); box.append(el('h3', `Picking #${picking.id} · ${pretty(picking.status)}`, 'font-bold'));
            table(result.items, [['id', 'Línea'], ['product_id', 'Producto'], ['quantity_required', 'Requerido'], ['quantity_picked', 'Surtido'], ['status', 'Estado']], box, (r, td) => { if (r.status !== 'confirmed' && picking.status === 'in_progress') canButton('events.prepare', 'Confirmar surtido', async () => { await api(`${path}/pickings/${picking.id}/items/${r.id}/confirm`, 'POST', { quantity_picked: Number(r.quantity_required) }); await load(); }, td); });
            if (picking.status === 'in_progress') canButton('events.prepare', 'Completar picking', async () => { await api(`${path}/pickings/${picking.id}/complete`, 'POST', {}); await load(); }, box);
            if (picking.status === 'completed' && !data.dispatches.some(d => d.picking_id === picking.id)) canButton('inventory.dispatch', 'Crear salida', () => form('Entrega a técnico', [select('technician_id', 'Técnico asignado al evento', 'technicians'), notes()], d => api(`${path}/dispatches`, 'POST', { ...d, picking_id: picking.id })), box);
        }
        for (const dispatch of data.dispatches) {
            const result = await api(`event-dispatches/${dispatch.id}`); const box = el('div', null, 'space-y-3 rounded-xl border border-primary-600 p-4'); content.append(box); box.append(el('h3', `Salida #${dispatch.id} · ${pretty(dispatch.status)}`, 'font-bold'));
            table(result.items, [['id', 'Línea'], ['product_id', 'Producto'], ['quantity', 'Cantidad entregada'], ['is_returnable', 'Retornable']], box);
            if (dispatch.status === 'draft') canButton('inventory.dispatch', 'Confirmar salida física', () => form('Confirmar salida de almacén', [], (_, __, key) => api(`event-dispatches/${dispatch.id}/confirm`, 'POST', {}, key)), box);
            if (['confirmed', 'received'].includes(dispatch.status) && !data.returns.some(r => r.event_dispatch_id === dispatch.id)) canButton('inventory.return', 'Registrar conciliación', () => form('Devolución y consumo', [warehouse(), select('damaged_location_id', 'Ubicación de daños', 'inventory_locations', { optional: true }), notes()], (d, items, key) => api(`${path}/returns`, 'POST', { ...d, event_dispatch_id: dispatch.id, items }, key), {}, { fixed: true, initial: result.items.map(i => ({ dispatch_item_id: i.id, quantity_returned: Number(i.quantity), quantity_consumed: 0, quantity_damaged: 0, quantity_lost: 0 })), fields: [field('dispatch_item_id', 'Línea despachada', 'number'), ...['returned', 'consumed', 'damaged', 'lost'].map((f, i) => field(`quantity_${f}`, ['Devuelto', 'Consumido', 'Dañado', 'Perdido'][i], 'number', { value: 0 }))] }), box);
        }
        for (const ret of data.returns) if (ret.status === 'draft') canButton('inventory.return', `Confirmar devolución #${ret.id}`, () => form('Confirmar devolución', [], (_, __, key) => api(`event-returns/${ret.id}/confirm`, 'POST', {}, key)), content);
    }
    async function candidates(requirement, eventPath) {
        const location = (config.catalogs.inventory_locations || []).find(x => x.type === 'warehouse')?.id;
        document.getElementById('inventory-dialog-title').textContent = `Candidatos para requerimiento #${requirement.id}`; dialogContent.replaceChildren();
        const picker = inputField(warehouse(), { warehouse_location_id: location }); dialogContent.append(picker.wrapper);
        const area = el('div', null, 'mt-4 space-y-4'); dialogContent.append(area);
        const show = async () => { area.replaceChildren(); const result = await api(`${eventPath}/requirements/${requirement.id}/candidates?location_id=${picker.input.value}&include_protected=true`);
            table(result.candidates, [['name', 'Producto'], ['sku', 'SKU'], ['physical_stock', 'Físico'], ['reserved_stock', 'Reservado'], ['protected_stock', 'Protegido'], ['available_stock', 'Disponible'], ['reference_value', 'Valor'], ['score', 'Score'], ['status', 'Estado'], ['reasons', 'Razones']], area, (r, td) => {
                if (r.compatible && r.physical_stock > 0) canButton('events.prepare', 'Asignar', () => { dialog.close(); form('Asignación física', [field('quantity', 'Cantidad', 'number', { min: 0.001 }), field('override_reason', 'Motivo para usar protegido (requiere permiso)', 'textarea', { optional: true })], d => api(`${eventPath}/requirements/${requirement.id}/allocations`, 'POST', { warehouse_location_id: Number(picker.input.value), allocations: [{ product_id: r.product_id, quantity: d.quantity }], ...(d.override_reason ? { override_reason: d.override_reason } : {}) })); }, td);
            });
        }; button('Consultar almacén', show, dialogContent); dialog.showModal(); if (location) await show();
    }
    async function audit() { title.textContent = 'Auditoría'; paged(await api('inventory/audit'), [['created_at', 'Fecha'], ['actor_id', 'Actor'], ['action', 'Acción'], ['entity_type', 'Entidad'], ['entity_id', 'ID'], ['old_values', 'Antes'], ['new_values', 'Después']], content, null, 'inventory/audit'); }
    async function load() {
        content.replaceChildren(); toolbar.replaceChildren(); content.setAttribute('aria-busy', 'true');
        try { await ({ inventory, products, kardex, purchases, sales, catalogs, requirements, events, preparation, rules: () => catalogs(true), audit }[section] || inventory)(); }
        catch (e) { title.textContent = 'No fue posible cargar el módulo'; msg(e.message, true); }
        finally { content.setAttribute('aria-busy', 'false'); }
        if (['sales', 'purchases'].includes(section) && root.dataset.action) {
            const action = root.dataset.action; root.dataset.action = '';
            const id = Number(root.dataset.record);
            try {
                if (action === 'show') await (section === 'sales' ? saleDetail(id) : purchaseDetail(id));
                else if (permissions.has(section === 'sales' ? 'sales.manage' : 'purchases.create')) await (section === 'sales' ? saleForm(action === 'edit' ? id : undefined) : purchaseForm(action === 'edit' ? id : undefined));
                else msg('No tienes permiso para esta operación.', true);
            } catch (error) { msg(error.message, true); }
        }
    }
    load();
}
