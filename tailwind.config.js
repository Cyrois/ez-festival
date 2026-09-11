/** @type {import('tailwindcss').Config} */
/**
 * Artist Tree brand palette.
 * Tailwind 4 also loads these via @theme in resources/css/app.css so utilities
 * (bg-brand, text-accent, …) are generated reliably with @tailwindcss/vite.
 * Keep both in sync when changing tokens.
 */
export default {
  theme: {
    extend: {
      colors: {
        charcoal: '#1A1A1A',
        page: '#F4F5F7',
        muted: '#6B7280',
        line: '#E5E7EB',
        brand: {
          DEFAULT: '#1F7A74',
          hover: '#196560',
        },
        accent: '#3D6B8A',
        danger: '#B91C1C',
        success: '#65A30D',
        warning: '#CA8A04',
      },
    },
  },
};
