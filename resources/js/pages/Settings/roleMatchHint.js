/**
 * Values for the "<typed> matches <existing>" hint under the role name field.
 * It describes the last submitted name only, so it disappears as soon as the
 * field no longer holds that value.
 *
 * @param {{ match?: string, submitted?: string|null, current?: string }} state
 * @returns {{ typed: string, existing: string }|null}
 */
export function roleMatchHint({ match, submitted, current }) {
    if (!match || submitted === null || submitted === undefined) {
        return null;
    }

    if (current !== submitted) {
        return null;
    }

    return { typed: `"${submitted}"`, existing: match };
}
