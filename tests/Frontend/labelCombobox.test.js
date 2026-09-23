import assert from 'node:assert/strict';
import test from 'node:test';
import {
    canCreateLabel,
    resolveLabelEnterAction,
} from '../../resources/js/components/ui/label-combobox/labelCombobox.js';
import { labelTokens } from '../../resources/js/lib/labelTokens.js';

const labels = [{ id: 1, name: 'Headliner', color: 'teal' }];
const colors = ['teal', 'rose'];

test('the frontend palette maps every locked token', () => {
    assert.deepEqual(Object.keys(labelTokens), [
        'teal',
        'soft_blue',
        'success',
        'warning',
        'danger',
        'violet',
        'sky',
        'rose',
        'slate',
        'charcoal',
    ]);
});

test('Enter selects an existing case-insensitive exact match', () => {
    const action = resolveLabelEnterAction({
        allowCreate: true,
        colors,
        labels,
        newLabels: [],
        query: ' headLINER ',
        selectedIds: new Set(),
    });

    assert.equal(action.type, 'toggle-existing');
    assert.equal(action.label.id, 1);
});

test('Enter never duplicates an already selected exact match', () => {
    const action = resolveLabelEnterAction({
        allowCreate: true,
        colors,
        labels,
        newLabels: [],
        query: 'Headliner',
        selectedIds: new Set([1]),
    });

    assert.deepEqual(action, { type: 'clear' });
});

test('Enter creates a non-matching label only when creation is allowed', () => {
    assert.equal(
        resolveLabelEnterAction({
            allowCreate: true,
            colors,
            labels,
            newLabels: [],
            query: 'Touring',
            selectedIds: new Set(),
        }).type,
        'create',
    );
    assert.equal(
        resolveLabelEnterAction({
            allowCreate: false,
            colors,
            labels,
            newLabels: [],
            query: 'Touring',
            selectedIds: new Set(),
        }).type,
        'none',
    );
});

test('creation stops at 20 new labels and requires a server palette', () => {
    const newLabels = Array.from({ length: 20 }, (_, index) => ({
        name: `Label ${index}`,
        color: 'teal',
    }));

    assert.equal(
        canCreateLabel({
            allowCreate: true,
            colors,
            labels,
            newLabels,
            query: 'Touring',
        }),
        false,
    );
    assert.equal(
        canCreateLabel({
            allowCreate: true,
            colors: [],
            labels,
            newLabels: [],
            query: 'Touring',
        }),
        false,
    );
});
