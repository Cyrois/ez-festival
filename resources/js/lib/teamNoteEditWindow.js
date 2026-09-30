export const isWithinTeamNoteEditWindow = (editableUntil, now = Date.now()) => {
    if (!editableUntil) {
        return false;
    }

    const deadline = Date.parse(editableUntil);

    return Number.isFinite(deadline) && now < deadline;
};
