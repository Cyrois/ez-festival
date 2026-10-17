// Return an ordered prefix, reserving the actual overflow chip width. Even a
// very long first label stays visible (truncated by flex) rather than vanishing.
export function fitLabels(widths, available, chipWidths, gap) {
    if (!widths.length) return 0;
    const total = widths.reduce((sum, width) => sum + width, 0);
    if (total + gap * (widths.length - 1) <= available) return widths.length;
    let used = total;
    for (let count = widths.length - 1; count >= 1; count -= 1) {
        used -= widths[count];
        if (used + gap * count + chipWidths[widths.length - count] <= available)
            return count;
    }
    return 1;
}
