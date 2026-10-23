import { getActiveLanguage } from 'laravel-vue-i18n';

export const mealReportCounts = [
    'assigned',
    'projected',
    'used',
    'remaining',
    'extras',
    'total',
];

export const mealReportCountLabel = (counts, key) =>
    new Intl.NumberFormat(getActiveLanguage()).format(
        ['used', 'extras'].includes(key) ? -counts[key] : counts[key],
    );
