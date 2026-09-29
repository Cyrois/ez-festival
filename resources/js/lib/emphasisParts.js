const MARK = '\u0000';

/**
 * Translate a sentence and mark some of its placeholders for emphasis,
 * without putting HTML in the translation.
 *
 * emphasisParts(trans, 'key', { name: 'Staff' }) returns
 * [{ text: 'Staff', emphasis: true }, { text: ' will leave…', emphasis: false }].
 *
 * @param {(key: string, replacements?: Record<string, string>) => string} translate
 * @param {string} key
 * @param {Record<string, string|number>} values
 * @returns {{ text: string, emphasis: boolean }[]}
 */
export function emphasisParts(translate, key, values) {
    const markers = Object.fromEntries(
        Object.keys(values).map((name) => [name, `${MARK}${name}${MARK}`]),
    );

    return translate(key, markers)
        .split(MARK)
        .map((chunk, index) =>
            index % 2 === 1
                ? { text: String(values[chunk] ?? ''), emphasis: true }
                : { text: chunk, emphasis: false },
        )
        .filter((part) => part.text !== '');
}
