export const normalizeLabelName = (value) => value.trim().toLocaleLowerCase();

export const findExactLabel = (labels, query) => {
    const normalizedQuery = normalizeLabelName(query);

    return normalizedQuery
        ? labels.find(
              (label) => normalizeLabelName(label.name) === normalizedQuery,
          )
        : undefined;
};

export const canCreateLabel = ({
    allowCreate,
    colors,
    labels,
    maxNewLabels = 20,
    newLabels,
    query,
}) =>
    allowCreate &&
    colors.length > 0 &&
    normalizeLabelName(query) !== '' &&
    !findExactLabel(labels, query) &&
    !findExactLabel(newLabels, query) &&
    newLabels.length < maxNewLabels;

export const resolveLabelEnterAction = ({
    allowCreate,
    colors,
    labels,
    maxNewLabels = 20,
    newLabels,
    query,
    selectedIds,
}) => {
    const existing = findExactLabel(labels, query);

    if (existing) {
        return selectedIds.has(Number(existing.id))
            ? { type: 'clear' }
            : { type: 'toggle-existing', label: existing };
    }

    if (findExactLabel(newLabels, query)) {
        return { type: 'clear' };
    }

    if (
        canCreateLabel({
            allowCreate,
            colors,
            labels,
            maxNewLabels,
            newLabels,
            query,
        })
    ) {
        return { type: 'create' };
    }

    return { type: 'none' };
};
