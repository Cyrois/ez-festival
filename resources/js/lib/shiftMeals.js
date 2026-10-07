import { shiftBreakDays, wallMinutes } from './shiftBreaks.js';

let nextKey = 0;

export function draftShiftMeals(rows = []) {
    return rows.map((row) => ({
        ...row,
        _key: row.id ? `saved-meal-${row.id}` : `draft-meal-${++nextKey}`,
        assignment_keys: [...(row.assignment_keys ?? row.assignment_ids ?? [])],
    }));
}

export function shiftMealPayload(rows) {
    return rows.map((row) => ({
        meal_id: Number(row.meal_id),
        assignment_keys: row.assignment_keys.map(Number),
    }));
}

export function removeMealRecipient(rows, key) {
    return rows.map((row) => ({
        ...row,
        assignment_keys: row.assignment_keys.filter((value) => value !== key),
    }));
}

export function mealsForAssignment(rows, key) {
    return rows.filter((row) =>
        (row.assignment_keys ?? row.assignment_ids ?? []).includes(key),
    );
}

// Calendar arithmetic matches the stored event-local shift times, regardless of device timezone.
export function shiftMealOptions(meals, start, end, selected = []) {
    const days = shiftBreakDays(start, end);
    const first = wallMinutes(start);
    const last = wallMinutes(end);
    const middle = (first + last) / 2;
    const choices = meals
        .filter(
            (meal) => days.includes(meal.date) && !selected.includes(meal.id),
        )
        .map((meal) => {
            const from = wallMinutes(`${meal.date}T${meal.starts_at}`);
            let to = wallMinutes(`${meal.date}T${meal.ends_at}`);
            if (to <= from) to += 1440;
            return {
                ...meal,
                suggested: from < last && to > first,
                distance: Math.abs(from - middle),
                start: from,
            };
        });
    const tie = (a, b) =>
        a.start - b.start || a.name.localeCompare(b.name) || a.id - b.id;
    const suggested = choices
        .filter((meal) => meal.suggested)
        .sort((a, b) => a.distance - b.distance || tie(a, b));
    return {
        suggested,
        other: choices.filter((meal) => !meal.suggested).sort(tie),
        days,
    };
}

export function shiftMealErrors(rows, errors) {
    const result = {};
    for (const [path, message] of Object.entries(errors)) {
        const match =
            /^meals\.(\d+)\.(meal_id|assignment_keys)(?:\.\d+)?$/.exec(path);
        const key = match && rows[Number(match[1])]?._key;
        if (key) {
            result[key] ??= {};
            result[key][match[2]] = message;
        }
    }
    return result;
}
