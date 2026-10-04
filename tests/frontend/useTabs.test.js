import test from 'node:test';
import assert from 'node:assert/strict';
import { createRenderer, h, nextTick } from 'vue';
import { useTabs } from '../../resources/js/Composables/useTabs.ts';

// Exercise Vue mount/unmount and the real Inertia event subscription without a browser.
class ObservedEvents extends EventTarget {
    listeners = new Map();
    addEventListener(type, listener, options) {
        const entries = this.listeners.get(type) ?? new Set();
        entries.add(listener);
        this.listeners.set(type, entries);
        super.addEventListener(type, listener, options);
    }
    removeEventListener(type, listener, options) {
        this.listeners.get(type)?.delete(listener);
        super.removeEventListener(type, listener, options);
    }
    count(type) { return this.listeners.get(type)?.size ?? 0; }
}
const renderer = createRenderer({
    createElement: () => ({}), createText: () => ({}), createComment: () => ({}),
    insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {},
    parentNode: () => null, nextSibling: () => null,
});
function environment(t, initialUrl) {
    const priorWindow = globalThis.window;
    const priorDocument = globalThis.document;
    const windowEvents = new ObservedEvents();
    const documentEvents = new ObservedEvents();
    const stored = new Map();
    const disposers = [];
    let location = new URL(initialUrl);
    let replacements = 0;
    let storageWrites = 0;
    Object.assign(windowEvents, {
        history: { state: { marker: 'preserve' }, replaceState(state, unused, url) {
            assert.deepEqual(state, { marker: 'preserve' });
            location = new URL(url, location);
            replacements++;
        } },
        localStorage: { getItem: key => stored.get(key) ?? null, setItem: (key, value) => { stored.set(key, value); storageWrites++; } },
    });
    Object.defineProperty(windowEvents, 'location', { get: () => location });
    globalThis.window = windowEvents;
    globalThis.document = documentEvents;
    t.after(() => {
        for (const dispose of disposers.reverse()) dispose();
        globalThis.window = priorWindow;
        globalThis.document = priorDocument;
    });
    return {
        windowEvents, documentEvents, stored,
        get location() { return location; },
        get replacements() { return replacements; },
        get storageWrites() { return storageWrites; },
        navigate(url) { location = new URL(url, location); },
        keep(instance) { disposers.push(instance.unmount); return instance; },
    };
}
function mount(key, preferred) {
    let tabs;
    const app = renderer.createApp({ setup() {
        tabs = useTabs(key, 'resumen', ['resumen', 'productos', 'documentos'], preferred);
        return () => h('div', tabs.activeTab.value);
    } });
    app.mount({});
    let mounted = true;
    return { tabs, unmount: () => { if (mounted) { app.unmount(); mounted = false; } } };
}

test('tabs preserve deep links and query state, reject unknown sections, and recover hash changes', async t => {
    const env = environment(t, 'http://localhost/trips/3?tab=resumen#documentos');
    env.stored.set('trip-3-tab', 'productos');
    const instance = env.keep(mount('trip-3-tab', 'resumen'));
    await nextTick();
    assert.equal(instance.tabs.activeTab.value, 'documentos');
    instance.tabs.setTab('unknown');
    await nextTick();
    assert.equal(instance.tabs.activeTab.value, 'documentos');
    instance.tabs.setTab('productos');
    await nextTick();
    assert.equal(env.location.pathname + env.location.search + env.location.hash, '/trips/3?tab=resumen#productos');
    assert.equal(env.stored.get('trip-3-tab'), 'productos');
    env.navigate('#invalid');
    env.windowEvents.dispatchEvent(new Event('hashchange'));
    await nextTick();
    assert.equal(instance.tabs.activeTab.value, 'resumen');
    assert.equal(env.location.hash, '#resumen');
});

test('repeated Vue mounts release both hash and Inertia listeners; unmounted tabs cannot rewrite navigation', async t => {
    const env = environment(t, 'http://localhost/trips/3#productos');
    for (let i = 0; i < 20; i++) {
        const instance = env.keep(mount(`trip-${i}-tab`));
        await nextTick();
        assert.equal(env.windowEvents.count('hashchange'), 1);
        assert.equal(env.documentEvents.count('inertia:finish'), 1);
        instance.unmount();
        assert.equal(env.windowEvents.count('hashchange'), 0);
        assert.equal(env.documentEvents.count('inertia:finish'), 0);
    }
    const writes = [env.replacements, env.storageWrites];
    env.navigate('http://localhost/companies?search=keep#other');
    env.windowEvents.dispatchEvent(new Event('hashchange'));
    env.documentEvents.dispatchEvent(new Event('inertia:finish'));
    await nextTick();
    assert.deepEqual([env.replacements, env.storageWrites], writes);
    assert.equal(env.location.pathname + env.location.search + env.location.hash, '/companies?search=keep#other');
});

test('Inertia finish restores the active section only on its importation and accepts valid navigation hashes', async t => {
    const env = environment(t, 'http://localhost/trips/3#productos');
    const instance = env.keep(mount('trip-3-tab'));
    await nextTick();
    env.navigate('/trips/4?keep=yes');
    env.documentEvents.dispatchEvent(new Event('inertia:finish'));
    await nextTick();
    assert.equal(env.location.pathname + env.location.search + env.location.hash, '/trips/4?keep=yes');
    env.navigate('/trips/3?keep=yes');
    env.documentEvents.dispatchEvent(new Event('inertia:finish'));
    await nextTick();
    assert.equal(env.location.hash, '#productos');
    env.navigate('/trips/3?keep=yes#documentos');
    env.documentEvents.dispatchEvent(new Event('inertia:finish'));
    await nextTick();
    assert.equal(instance.tabs.activeTab.value, 'documentos');
    assert.equal(env.location.search, '?keep=yes');
});
