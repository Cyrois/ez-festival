export const isWithinTeamNoteEditWindow = (editableUntil, now = Date.now()) => {
    if (!editableUntil) {
        return false;
    }

    const deadline = Date.parse(editableUntil);

    return Number.isFinite(deadline) && now < deadline;
};

export const teamNoteEditState = ({
    note,
    now = Date.now(),
    canWrite,
    editingId = null,
}) => {
    const showEdit = Boolean(
        canWrite &&
        !note.pending &&
        isWithinTeamNoteEditWindow(note.editable_until, now),
    );

    return {
        showEdit,
        shouldCloseOpenEdit: editingId === note.id && !showEdit,
    };
};
