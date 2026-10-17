import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { fitLabels } from '../../resources/js/components/ui/entitlement-labels/fitLabels.js';
import { labelsUrl, nameUrl } from './helpers/entitlementLabels.js';

const dom = new JSDOM('<div id="app"></div><button id="outside">Outside</button>');
for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node', 'getComputedStyle']) globalThis[key] = dom.window[key];
const { createApp, ref, nextTick } = await import('vue');
const { default: Labels } = await import(labelsUrl);
const { default: ItemName } = await import(nameUrl);
let phone = true, width = 210, disconnected = 0;
const widths = new Map([['A', 65], ['B', 65], ['C', 65], ['D', 65], ['Long', 500]]);
const resizeCallbacks = new Set();
window.matchMedia = () => ({ get matches() { return phone; }, addEventListener(_, callback) { resizeCallbacks.add(callback); }, removeEventListener(_, callback) { resizeCallbacks.delete(callback); } });
globalThis.ResizeObserver = class { constructor(callback) { this.callback = callback; } observe() {} disconnect() { disconnected++; } };
HTMLElement.prototype.getBoundingClientRect = function () {
    const size = this.hasAttribute('data-entitlement-labels') ? width
        : this.hasAttribute('data-label-measure') ? widths.get(this.textContent.trim()) ?? 65
        : this.hasAttribute('data-chip-measure') ? 20 + this.textContent.trim().length * 8
        : this.getAttribute('role') === 'dialog' ? 200 : 40;
    return { width: size, height: 100, top: 120, bottom: 220, left: 10, right: 10 + size };
};
HTMLElement.prototype.showPopover = function () { this.dataset.open = 'true'; };
HTMLElement.prototype.hidePopover = function () { delete this.dataset.open; };
const labels = ['A', 'B', 'C', 'D'].map((name, index) => ({ id: index + 1, name, color: index === 0 ? 'rose' : 'teal' }));
const tick = async () => { await nextTick(); await nextTick(); };
const mount = async (value = labels, component = Labels) => {
    const props = ref(value);
    const app = createApp({ setup: () => () => h(component, component === Labels ? { labels: props.value, itemName: 'Wristband' } : { labels: props.value, name: 'Wristband' }) });
    app.mount('#app'); await tick();
    return { app, props };
};
const { h } = await import('vue');
const visible = () => [...document.querySelectorAll('[data-entitlement-labels] > span:first-child > span[title]')].map((tag) => tag.title);

test('width fitting reserves the actual chip width, handles exact fits and never uses a fixed cap', () => {
    assert.equal(fitLabels([50, 50, 50], 162, [0, 30, 30], 6), 3);
    assert.equal(fitLabels([50, 50, 50], 136, [0, 30, 30], 6), 1);
    assert.equal(fitLabels([500, 20], 90, [0, 30], 6), 1);
    assert.equal(fitLabels(Array(12).fill(10), 186, Array(13).fill(20), 6), 12);
    assert.equal(fitLabels([], 10, [], 6), 0);
});

test('phone resizing shows an ordered prefix, exact hidden count and full hidden names; desktop wraps all labels', async () => {
    phone = true; width = 210;
    const { app } = await mount();
    try {
        assert.deepEqual(visible(), ['A', 'B']);
        assert.ok(document.querySelector('[data-entitlement-labels] > span:first-child > span[title]').classList.contains('text-label-rose'));
        const chip = document.querySelector('[aria-haspopup="dialog"]');
        assert.equal(chip.textContent, '+2');
        assert.equal(chip.getAttribute('aria-label'), '2 more labels: C, D');
        width = 280; window.dispatchEvent(new window.Event('resize')); await tick();
        assert.deepEqual(visible(), ['A', 'B', 'C', 'D']);
        assert.equal(document.querySelector('[aria-haspopup="dialog"]'), null);
        phone = false; width = 80; resizeCallbacks.forEach((callback) => callback()); await tick();
        assert.equal(visible().length, 4);
        assert.equal(document.querySelector('[aria-haspopup="dialog"]'), null);
    } finally { app.unmount(); }
});

test('popup lists all labels in order and restores focus after Close, Escape and outside tap without selecting a row', async () => {
    phone = true; width = 210;
    const { app } = await mount();
    let selected = 0;
    const parent = document.querySelector('#app');
    const onClick = () => selected++;
    parent.addEventListener('click', onClick);
    try {
        const chip = document.querySelector('[aria-haspopup="dialog"]');
        for (const dismissal of ['close', 'escape', 'outside']) {
            chip.focus();
            // Native buttons support Enter and Space; the click is the browser's activation event.
            chip.click(); await tick();
            const popup = document.querySelector('[role="dialog"]');
            assert.deepEqual([...popup.querySelectorAll('[title]')].map((tag) => tag.title), ['A', 'B', 'C', 'D']);
            assert.equal(document.activeElement, popup.querySelector('button'));
            assert.equal(selected, 0);
            if (dismissal === 'close') popup.querySelector('button').click();
            if (dismissal === 'escape') popup.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            if (dismissal === 'outside') document.querySelector('#outside').dispatchEvent(new window.Event('pointerdown', { bubbles: true }));
            await tick();
            assert.equal(document.querySelector('[role="dialog"]'), null);
            assert.equal(document.activeElement, chip);
            assert.equal(chip.getAttribute('aria-expanded'), 'false');
        }
    } finally { parent.removeEventListener('click', onClick); app.unmount(); }
});

test('long first label stays visible with the rest counted; one long label and unlabelled items have no chip', async () => {
    phone = true; width = 100;
    const { app, props } = await mount([{ id: 1, name: 'Long', color: 'rose' }, ...labels]);
    try {
        assert.deepEqual(visible(), ['Long']);
        assert.equal(document.querySelector('[aria-haspopup="dialog"]').textContent, '+4');
        props.value = [{ id: 1, name: 'Long', color: 'rose' }]; await tick();
        assert.deepEqual(visible(), ['Long']);
        assert.equal(document.querySelector('[aria-haspopup="dialog"]'), null);
        props.value = []; await tick();
        assert.equal(document.querySelector('[data-entitlement-labels]'), null);
        props.value = labels; await tick();
        assert.ok(document.querySelector('[aria-haspopup="dialog"]'));
    } finally { app.unmount(); }
    assert.ok(disconnected > 0);
    const empty = await mount([], ItemName);
    try { assert.equal(document.querySelector('#app').textContent, 'Wristband'); }
    finally { empty.app.unmount(); }
});
