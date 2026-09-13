/** @type {import('tailwindcss').Config} */
/**
 * Artist Tree design tokens.
 * Tailwind 4 also loads these via @theme in resources/css/app.css so utilities
 * (bg-brand, bg-primary, text-secondary, …) are generated reliably with
 * @tailwindcss/vite. Keep both in sync when changing tokens.
 *
 * brand → alias of primary (do not break existing bg-brand classes).
 * accent → alias of secondary (legacy name).
 */
export default {
    theme: {
        extend: {
            colors: {
                charcoal: '#1A1A1A',
                page: '#F4F5F7',
                muted: '#6B7280',
                line: '#E5E7EB',
                ground: {
                    DEFAULT: '#FFFFFF',
                    dark: '#1A1A1A',
                },
                primary: {
                    DEFAULT: '#1F7A74',
                    hover: '#196560',
                    soft: '#E6F3F2',
                },
                secondary: {
                    DEFAULT: '#3D6B8A',
                    soft: '#E8EEF2',
                },
                brand: {
                    DEFAULT: '#1F7A74',
                    hover: '#196560',
                },
                accent: '#3D6B8A',
                danger: '#B91C1C',
                success: '#65A30D',
                warning: '#CA8A04',
            },
            borderRadius: {
                lg: '0.5rem',
            },
        },
    },
};
