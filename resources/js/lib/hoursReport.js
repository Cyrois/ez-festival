import { getActiveLanguage, trans } from 'laravel-vue-i18n';

export const formatHoursReportDuration = (minutes) =>
    trans('team.reports.duration', {
        hours: Math.floor(minutes / 60),
        minutes: String(minutes % 60).padStart(2, '0'),
    });

export const formatHoursReportMoney = (cents) =>
    cents == null
        ? trans('team.reports.unavailable')
        : new Intl.NumberFormat(getActiveLanguage(), {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(cents / 100);

export const formatHoursReportPay = (person) =>
    person.employment_type === 'volunteer'
        ? trans('team.advancement.employment_type.volunteer')
        : person.hourly_pay == null
          ? trans('team.reports.no_rate')
          : trans('team.reports.hourly_rate', { rate: person.hourly_pay });
