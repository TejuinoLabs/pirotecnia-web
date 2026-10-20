const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim().replace(/\s+/g, ' ');

export function catalogMatches(products, query) {
    const terms = normalize(query).split(' ').filter(Boolean);
    return products.filter(product => product.active && terms.every(term => normalize(product.name).includes(term)))
        .sort((a, b) => Number(normalize(b.name) === normalize(query)) - Number(normalize(a.name) === normalize(query)) || a.name.localeCompare(b.name, 'es'));
}

export function catalogAutocomplete({ label, name, description, selectedId, products, roles, createProduct }) {
    const make = (tag, text, className = '') => {
        const node = document.createElement(tag); node.className = className;
        if (text != null) node.textContent = text;
        return node;
    };
    const inputClass = 'w-full rounded-xl border border-primary-600/50 bg-primary-700/70 px-4 py-3 text-sm text-on-primary outline-none focus:border-secondary focus:ring-1 focus:ring-secondary/30';
    const wrapper = make('div', null, 'space-y-2');
    const id = `catalog-${crypto.randomUUID()}`;
    const caption = make('label', label, 'block text-sm text-primary-200'); caption.htmlFor = id;
    const search = make('input', null, inputClass); search.id = id; search.type = 'text'; search.value = description; search.required = true; search.autocomplete = 'off';
    search.setAttribute('role', 'combobox'); search.setAttribute('aria-autocomplete', 'list'); search.setAttribute('aria-controls', `${id}-list`);
    const input = make('input'); input.type = 'hidden'; input.name = name;
    const selected = products().find(product => product.active && Number(product.id) === Number(selectedId));
    input.value = selected?.id ?? '';
    const list = make('div', null, 'max-h-48 overflow-y-auto rounded-xl border border-primary-600/50 bg-primary-800'); list.id = `${id}-list`; list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', 'Coincidencias del catálogo');
    const status = make('p', '', 'text-xs text-primary-200'); status.setAttribute('role', 'status');
    const creation = make('div', null, 'space-y-3');
    let matches = []; let active = -1; let saving = false;
    function validate() { search.setCustomValidity(input.value ? '' : 'Selecciona una coincidencia o crea el producto para continuar.'); }
    function close() { list.hidden = true; search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant'); active = -1; }
    function choose(product) {
        input.value = product.id; search.value = product.name; validate(); close(); creation.replaceChildren();
        status.textContent = `Seleccionado: ${product.name}`;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function highlight(index) {
        active = index;
        [...list.children].forEach((option, i) => { option.setAttribute('aria-selected', String(i === active)); option.classList.toggle('bg-primary-700', i === active); });
        if (active >= 0) { search.setAttribute('aria-activedescendant', list.children[active].id); list.children[active].scrollIntoView({ block: 'nearest' }); }
    }
    function offerCreation() {
        const open = make('button', `Crear «${search.value.trim()}»`, 'rounded-lg border border-secondary px-4 py-2 text-sm font-semibold text-secondary'); open.type = 'button';
        open.addEventListener('click', () => {
            creation.replaceChildren(make('p', `Nuevo producto: ${search.value.trim()}`, 'text-sm font-semibold'));
            const roleLabel = make('label', 'Tipo de artículo', 'block space-y-2 text-sm');
            const role = make('select', null, inputClass);
            for (const item of roles) { const option = make('option', item.name); option.value = item.id; role.append(option); }
            const defaultRole = roles.find(item => normalize(item.name) === 'producto'); if (defaultRole) role.value = defaultRole.id;
            roleLabel.append(role);
            const unitLabel = make('label', 'Unidad', 'block space-y-2 text-sm');
            const unit = make('input', null, inputClass); unit.value = 'pz'; unit.maxLength = 100; unitLabel.append(unit);
            const error = make('p', '', 'text-sm text-warning'); error.setAttribute('role', 'alert');
            const save = make('button', 'Crear y seleccionar', 'rounded-lg bg-secondary px-4 py-2 text-sm font-bold text-primary-900 disabled:opacity-50'); save.type = 'button';
            save.addEventListener('click', async () => {
                if (saving) return;
                if (!role.value || !unit.value.trim()) { error.textContent = 'Selecciona el tipo de artículo y escribe su unidad.'; return; }
                saving = true; save.disabled = true; search.disabled = true; error.textContent = '';
                try {
                    const product = await createProduct({ name: search.value.trim(), product_role_id: Number(role.value), unit: unit.value.trim() });
                    choose(product);
                } catch (e) { error.textContent = e.message; }
                finally { saving = false; save.disabled = false; search.disabled = false; }
            });
            const cancel = make('button', 'Cancelar', 'px-4 py-2 text-sm text-primary-200'); cancel.type = 'button'; cancel.addEventListener('click', render);
            creation.append(roleLabel, unitLabel, error, save, cancel);
        });
        creation.append(open);
    }
    function render() {
        if (saving) return;
        matches = catalogMatches(products(), search.value); active = -1;
        list.replaceChildren(); creation.replaceChildren(); search.removeAttribute('aria-activedescendant');
        matches.forEach((product, index) => {
            const option = make('button', product.name, 'block w-full px-4 py-3 text-left text-sm text-on-primary hover:bg-primary-700');
            option.type = 'button'; option.tabIndex = -1; option.id = `${id}-option-${index}`; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
            option.addEventListener('pointerdown', event => event.preventDefault()); option.addEventListener('click', () => choose(product)); list.append(option);
        });
        list.hidden = matches.length === 0; search.setAttribute('aria-expanded', String(!list.hidden));
        status.textContent = input.value ? `Seleccionado: ${products().find(product => Number(product.id) === Number(input.value))?.name ?? search.value}` : `${matches.length} coincidencias. Selecciona un producto.`;
        if (!matches.length) { status.textContent = 'Sin coincidencias.'; if (search.value.trim()) offerCreation(); }
    }
    search.addEventListener('input', () => { input.value = ''; validate(); render(); });
    search.addEventListener('focus', render);
    search.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && matches.length) {
            event.preventDefault(); list.hidden = false; search.setAttribute('aria-expanded', 'true');
            highlight(event.key === 'ArrowDown' ? (active + 1) % matches.length : (active <= 0 ? matches.length : active) - 1);
        } else if (event.key === 'Enter') {
            event.preventDefault(); if (!list.hidden && active >= 0) choose(matches[active]); else search.reportValidity();
        } else if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); event.stopPropagation(); close(); }
    });
    wrapper.addEventListener('focusout', event => { if (!wrapper.contains(event.relatedTarget)) close(); });
    wrapper.append(caption, search, input, list, status, creation); validate(); render();
    return { wrapper, input };
}
