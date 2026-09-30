import assert from 'node:assert/strict';
import test from 'node:test';
import {
    isWithinTeamNoteEditWindow,
    teamNoteEditState,
} from '../../resources/js/lib/teamNoteEditWindow.js';

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

test('the note screen hides Edit and closes an open edit after expiry', () => {
    const savedAt = Date.parse('2026-09-30T12:00:00Z');
    const note = {
        id: 42,
        pending: false,
        editable_until: new Date(savedAt + 5 * 60 * 1000).toISOString(),
    };

    assert.deepEqual(
        teamNoteEditState({
            note,
            now: savedAt + 299_999,
            canWrite: true,
            editingId: note.id,
        }),
        { showEdit: true, shouldCloseOpenEdit: false },
    );
    assert.deepEqual(
        teamNoteEditState({
            note,
            now: savedAt + 300_000,
            canWrite: true,
            editingId: note.id,
        }),
        { showEdit: false, shouldCloseOpenEdit: true },
    );
});
