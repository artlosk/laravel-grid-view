const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

const script = fs.readFileSync('resources/js/grid-view.js', 'utf8');

function createGrid(id) {
    const attributes = new Map();

    return {
        dataset: {
            gridViewId: id,
            gridViewHeader: 'X-Grid-View',
        },
        getAttribute(name) {
            return attributes.get(name) ?? null;
        },
        setAttribute(name, value) {
            attributes.set(name, value);
        },
        removeAttribute(name) {
            attributes.delete(name);
        },
        replaceWith(replacement) {
            this.replacedWith = replacement;
        },
    };
}

function createHarness(grids) {
    const documentListeners = new Map();
    const windowListeners = new Map();
    const fetchCalls = [];
    const dispatchedEvents = [];
    const assignedUrls = [];
    const pushedStates = [];
    const gridsById = new Map(grids.map((grid) => [grid.dataset.gridViewId, grid]));

    const document = {
        addEventListener(type, listener) {
            const listeners = documentListeners.get(type) ?? [];
            listeners.push(listener);
            documentListeners.set(type, listeners);
        },
        removeEventListener(type, listener) {
            documentListeners.set(type, (documentListeners.get(type) ?? []).filter((item) => item !== listener));
        },
        createRange() {
            return {
                createContextualFragment(html) {
                    const id = html.match(/data-grid-view-id="([^"]+)"/)?.[1];
                    const fragment = {
                        querySelectorAll() {
                            return id ? [replacement] : [];
                        },
                    };
                    const replacement = id ? createGrid(id) : null;

                    if (replacement) {
                        replacement.parentNode = fragment;
                    }

                    return fragment;
                },
            };
        },
        dispatchEvent(event) {
            dispatchedEvents.push(event);
        },
        querySelector(selector) {
            for (const [id, grid] of gridsById) {
                if (selector.includes(`"${id}"`)) {
                    return grid;
                }
            }

            return null;
        },
        querySelectorAll() {
            return grids;
        },
    };

    const window = {
        location: {
            href: 'https://example.test/records?page=1',
            assign(url) {
                assignedUrls.push(url);
            },
        },
        history: {
            state: { router: 'preserved' },
            pushState(state, title, url) {
                this.state = state;
                pushedStates.push({ state, title, url });
            },
        },
        addEventListener(type, listener) {
            const listeners = windowListeners.get(type) ?? [];
            listeners.push(listener);
            windowListeners.set(type, listeners);
        },
        confirm() {
            return true;
        },
        scrollY: 0,
        innerHeight: 600,
        scrollBy() {},
        requestAnimationFrame: () => 1,
        cancelAnimationFrame() {},
        setTimeout: (callback) => callback(),
    };

    const context = vm.createContext({
        AbortController,
        CSS: { escape: (value) => value },
        CustomEvent: class CustomEvent {
            constructor(type, options) {
                this.type = type;
                this.detail = options.detail;
            }
        },
        Error,
        FormData: function FormData() {
            const entries = [['search', 'alice']];
            entries.append = (name, value) => entries.push([name, value]);

            return entries;
        },
        Map,
        Promise,
        URL,
        URLSearchParams,
        document,
        fetch(url, options) {
            let completeRequest;
            let rejectRequest;
            const promise = new Promise((complete, reject) => {
                completeRequest = complete;
                rejectRequest = reject;
            });

            options.signal?.addEventListener('abort', () => {
                const error = new Error('Aborted');
                error.name = 'AbortError';
                rejectRequest(error);
            });

            fetchCalls.push({ url, options, promise, complete: completeRequest });

            return promise;
        },
        window,
    });

    vm.runInContext(script, context);

    return {
        assignedUrls,
        dispatchedEvents,
        fetchCalls,
        pushedStates,
        dispatchDocument(type, event) {
            for (const listener of documentListeners.get(type) ?? []) {
                listener(event);
            }
        },
        dispatchWindow(type) {
            for (const listener of windowListeners.get(type) ?? []) {
                listener();
            }
        },
        window,
    };
}

function createEvent(target) {
    return {
        target,
        button: 0,
        defaultPrevented: false,
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        altKey: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
        stopImmediatePropagation() {},
    };
}

function partialResponse(id, html = `<div data-grid-view-id="${id}"></div>`) {
    const headers = new Map([
        ['X-Grid-View', id],
        ['Content-Type', 'text/html; charset=UTF-8'],
    ]);

    return {
        ok: true,
        redirected: false,
        headers: {
            get(name) {
                return headers.get(name) ?? null;
            },
        },
        async text() {
            return html;
        },
    };
}

test('deduplicates the same pending URL per grid', () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);

    harness.dispatchWindow('popstate');
    harness.dispatchWindow('popstate');

    assert.equal(harness.fetchCalls.length, 1);
    assert.equal(grid.getAttribute('aria-busy'), 'true');
    assert.equal(
        harness.fetchCalls[0].options.headers['X-Grid-View'],
        'users-grid',
    );
});

test('keeps requests independent between grids and replaces a different URL', async () => {
    const usersGrid = createGrid('users-grid');
    const ordersGrid = createGrid('orders-grid');
    const harness = createHarness([usersGrid, ordersGrid]);

    harness.dispatchWindow('popstate');
    harness.dispatchWindow('popstate');

    assert.equal(harness.fetchCalls.length, 2);

    harness.window.location.href = 'https://example.test/records?page=2';
    harness.dispatchWindow('popstate');
    await 0;

    assert.equal(harness.fetchCalls.length, 4);
    assert.equal(harness.fetchCalls[0].options.signal.aborted, true);
    assert.equal(harness.fetchCalls[1].options.signal.aborted, true);
    assert.equal(usersGrid.getAttribute('aria-busy'), 'true');
    assert.equal(ordersGrid.getAttribute('aria-busy'), 'true');
});

test('ignores AJAX link clicks while the grid is busy', () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);
    grid.setAttribute('aria-busy', 'true');

    const link = {
        href: 'https://example.test/records?page=2',
        closest(selector) {
            if (selector === 'a[data-grid-view-link], [data-grid-view-pagination] a') {
                return this;
            }

            if (selector === '[data-grid-view-ajax]') {
                return grid;
            }

            return null;
        },
    };
    const event = createEvent(link);

    harness.dispatchDocument('click', event);

    assert.equal(event.defaultPrevented, true);
    assert.equal(harness.fetchCalls.length, 0);
});

test('keeps target and download links on native navigation', () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);
    const link = {
        href: 'https://example.test/records?page=2',
        target: '_blank',
        hasAttribute(name) {
            return name === 'download';
        },
        closest(selector) {
            return selector === 'a[data-grid-view-link], [data-grid-view-pagination] a'
                ? this
                : grid;
        },
    };
    const event = createEvent(link);

    harness.dispatchDocument('click', event);

    assert.equal(event.defaultPrevented, false);
    assert.equal(harness.fetchCalls.length, 0);
});

test('ignores repeated external filter submits while target grid is busy', () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);
    const form = {
        action: 'https://example.test/records',
        method: 'GET',
        dataset: { gridViewFilter: 'users-grid' },
        closest(selector) {
            return selector === 'form[data-grid-view-filter]' ? this : null;
        },
    };

    const firstEvent = createEvent(form);
    harness.dispatchDocument('submit', firstEvent);
    const secondEvent = createEvent(form);
    harness.dispatchDocument('submit', secondEvent);

    assert.equal(firstEvent.defaultPrevented, true);
    assert.equal(secondEvent.defaultPrevented, true);
    assert.equal(harness.fetchCalls.length, 1);
    assert.equal(
        harness.fetchCalls[0].url,
        'https://example.test/records?search=alice',
    );
});

test('includes the clicked filter submitter in the request URL', () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);
    const form = {
        action: 'https://example.test/records',
        method: 'GET',
        dataset: { gridViewFilter: 'users-grid' },
        closest(selector) {
            return selector === 'form[data-grid-view-filter]' ? this : null;
        },
    };
    const event = createEvent(form);
    event.submitter = { name: 'scope', value: 'archived' };

    harness.dispatchDocument('submit', event);

    assert.equal(
        harness.fetchCalls[0].url,
        'https://example.test/records?search=alice&scope=archived',
    );
});

test('replaces a valid partial, preserves history state, and dispatches render event', async () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);
    const link = {
        href: 'https://example.test/records?page=2',
        target: '',
        hasAttribute() {
            return false;
        },
        closest(selector) {
            if (selector === 'a[data-grid-view-link], [data-grid-view-pagination] a') {
                return this;
            }

            if (selector === '[data-grid-view-ajax]') {
                return grid;
            }

            return null;
        },
    };

    harness.dispatchDocument('click', createEvent(link));
    harness.fetchCalls[0].complete(partialResponse('users-grid'));
    await new Promise((done) => setImmediate(done));

    assert.equal(grid.replacedWith.dataset.gridViewId, 'users-grid');
    assert.equal(harness.dispatchedEvents[0].type, 'grid-view:rendered');
    assert.equal(harness.pushedStates.length, 1);
    assert.equal(harness.pushedStates[0].state.router, 'preserved');
    assert.equal(harness.pushedStates[0].state.laravelGridView.id, 'users-grid');
});

test('falls back to full navigation for an invalid partial response', async () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);

    harness.dispatchWindow('popstate');
    harness.fetchCalls[0].complete(partialResponse('orders-grid'));
    await new Promise((done) => setImmediate(done));

    assert.deepEqual(harness.assignedUrls, ['https://example.test/records?page=1']);
});

test('accepts a full page that contains the grid', async () => {
    const grid = createGrid('users-grid');
    const harness = createHarness([grid]);

    harness.dispatchWindow('popstate');
    harness.fetchCalls[0].complete(partialResponse('users-grid', '<html><body><h1>Page</h1><div data-grid-view-id="users-grid"></div></body></html>'));
    await new Promise((done) => setImmediate(done));

    assert.equal(grid.replacedWith.dataset.gridViewId, 'users-grid');
    assert.deepEqual(harness.assignedUrls, []);
});

function createReorderGrid(keys) {
    const grid = createGrid('records');
    const table = {
        records: [],
        get children() {
            return this.records;
        },
        insertBefore(node, reference) {
            this.records.splice(this.records.indexOf(node), 1);
            this.records.splice(reference ? this.records.indexOf(reference) : this.records.length, 0, node);
        },
    };

    grid.classList = { add() {}, remove() {} };
    grid.dataset.gridViewReorderUrl = '/records/order';
    grid.dataset.gridViewReorderMethod = 'PUT';
    grid.querySelectorAll = () => table.records;
    table.records = keys.map((key) => {
        const record = {
            dataset: { gridViewKey: key },
            parentNode: table,
            classList: { add() {}, remove() {} },
            style: {},
            // строки по 40px: первая с 0 до 40
            offsetHeight: 40,
            get offsetTop() {
                return table.records.indexOf(record) * 40;
            },
            getBoundingClientRect: () => ({ top: record.offsetTop, height: 40 }),
            matches: (selector) => selector === 'tbody[data-grid-view-key]',
            get previousElementSibling() {
                return table.records[table.records.indexOf(record) - 1] ?? null;
            },
            get nextElementSibling() {
                return table.records[table.records.indexOf(record) + 1] ?? null;
            },
            get nextSibling() {
                return this.nextElementSibling;
            },
        };
        const parents = new Map([
            ['[data-grid-view-handle]', null],
            ['[data-grid-view-reorder-url]', grid],
            ['tbody[data-grid-view-key]', record],
        ]);

        record.handle = {
            focus() {},
            closest: (selector) => (selector === '[data-grid-view-handle]' ? record.handle : parents.get(selector) ?? null),
        };

        return record;
    });

    return { grid, records: table.records, table };
}

function keyEvent(target, key) {
    return { ...createEvent(target), key };
}

async function settle() {
    for (let i = 0; i < 5; i += 1) {
        await Promise.resolve();
    }
}

test('arrow keys move a record and save the new order', async () => {
    const { grid, records, table } = createReorderGrid(['1', '2', '3']);
    const harness = createHarness([grid]);

    harness.dispatchDocument('keydown', keyEvent(records[0].handle, 'ArrowDown'));
    await settle();

    assert.deepEqual(table.records.map((record) => record.dataset.gridViewKey), ['2', '1', '3']);
    assert.equal(harness.fetchCalls.length, 1);
    assert.equal(harness.fetchCalls[0].url, '/records/order');
    assert.equal(harness.fetchCalls[0].options.method, 'PUT');
    assert.deepEqual(JSON.parse(harness.fetchCalls[0].options.body), { ids: ['2', '1', '3'] });

    harness.dispatchDocument('keydown', keyEvent(table.records[0].handle, 'ArrowUp'));
    await settle();

    assert.equal(harness.fetchCalls.length, 1, 'the first record cannot move up');
});

test('saves one order at a time and redraws the grid when saving fails', async () => {
    const { grid, records } = createReorderGrid(['1', '2', '3']);
    const [first] = records;
    const harness = createHarness([grid]);

    harness.dispatchDocument('keydown', keyEvent(first.handle, 'ArrowDown'));
    harness.dispatchDocument('keydown', keyEvent(first.handle, 'ArrowDown'));
    await settle();

    assert.equal(harness.fetchCalls.length, 1, 'the second save waits for the first');

    harness.fetchCalls[0].complete({ ok: true, status: 204 });
    await settle();

    assert.equal(harness.fetchCalls.length, 2);
    assert.deepEqual(JSON.parse(harness.fetchCalls[1].options.body), { ids: ['2', '3', '1'] });
    assert.ok(harness.dispatchedEvents.some((event) => event.type === 'grid-view:reordered'));

    harness.fetchCalls[1].complete({ ok: false, status: 422 });
    await settle();

    assert.ok(harness.dispatchedEvents.some((event) => event.type === 'grid-view:reorder-failed'));
    assert.equal(harness.fetchCalls[2].options.headers['X-Grid-View'], 'records');
});

test('the dragged record follows the pointer and takes the place of the record it passes', async () => {
    const { grid, records, table } = createReorderGrid(['1', '2', '3']);
    const [first] = records;
    const harness = createHarness([grid]);
    const pointer = (type, y, buttons = 1) => harness.dispatchDocument(type, { ...createEvent(first.handle), pointerId: 7, clientX: 10, clientY: y, buttons });

    pointer('pointerdown', 20);
    pointer('pointermove', 70);
    assert.deepEqual(table.records.map((record) => record.dataset.gridViewKey), ['2', '1', '3']);
    assert.equal(first.style.transform, 'translateY(10px)', 'the record stays under the pointer after taking a new place');
    pointer('pointermove', 110);
    harness.dispatchDocument('pointermove', { ...createEvent(first.handle), pointerId: 8, clientX: 10, clientY: 10, buttons: 1 });
    await settle();

    assert.deepEqual(table.records.map((record) => record.dataset.gridViewKey), ['2', '3', '1'], 'another pointer does not move the record');
    assert.equal(harness.fetchCalls.length, 0, 'saves only when the record is dropped');

    pointer('pointerup', 110, 0);
    await settle();

    assert.deepEqual(JSON.parse(harness.fetchCalls[0].options.body), { ids: ['2', '3', '1'] });
});
