import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const card = readFileSync(
    new URL(
        '../../resources/js/components/team/TeamPassCard.vue',
        import.meta.url,
    ),
    'utf8',
);
const translations = JSON.parse(
    readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'),
);

test('new team passes are visibly distinguished from saved passes', () => {
    assert.match(card, /assignment\.id == null/);
    assert.match(card, /border-l-4/);
    assert.match(card, /border-l-primary/);
    assert.match(card, /bg-primary-soft/);
    assert.match(card, /v-if="isNew"[\s\S]*passes\.status\.new/);
    assert.match(card, /v-if="!isNew"[\s\S]*:variant="status\.variant"/);
    assert.equal(translations['team.member.passes.status.new'], 'New');
});
