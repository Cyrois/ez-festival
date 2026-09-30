import assert from 'node:assert/strict';
import test from 'node:test';
import { isWithinTeamNoteEditWindow } from '../../resources/js/lib/teamNoteEditWindow.js';

test('an open page stops offering note edit when five minutes pass', () => {
    const savedAt = Date.parse('2026-09-30T12:00:00Z');
    const editableUntil = new Date(savedAt + 5 * 60 * 1000).toISOString();

    assert.equal(
        isWithinTeamNoteEditWindow(editableUntil, savedAt + 299_999),
        true,
    );
    assert.equal(
        isWithinTeamNoteEditWindow(editableUntil, savedAt + 300_000),
        false,
    );
});
