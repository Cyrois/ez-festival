import { trans } from 'laravel-vue-i18n';

export const formatHoursReportDuration = (minutes) =>
    trans('team.reports.duration', {
        hours: Math.floor(minutes / 60),
        minutes: String(minutes % 60).padStart(2, '0'),
    });
