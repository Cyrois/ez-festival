import { cva } from 'class-variance-authority';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 rounded-lg border border-transparent font-sans font-bold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35 disabled:pointer-events-none disabled:opacity-45 cursor-pointer',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-white hover:bg-primary-hover',
                secondary:
                    'bg-secondary-soft text-secondary hover:bg-secondary-soft/80',
                cancel: 'bg-muted text-white hover:bg-charcoal',
                outline:
                    'border-line bg-transparent text-charcoal hover:bg-page',
                'outline-secondary':
                    'border-secondary/30 bg-ground text-secondary hover:bg-secondary-soft',
                ghost: 'bg-transparent text-primary hover:bg-primary-soft',
                danger: 'bg-danger text-white hover:bg-danger/90',
                'outline-danger':
                    'border-danger/30 bg-transparent text-danger hover:bg-danger/5',
            },
            size: {
                sm: 'h-8 px-3 text-[13px]',
                md: 'h-10 px-4 text-sm',
                lg: 'h-12 px-5 text-[15px]',
                icon: 'h-10 w-10 p-0',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
        },
    },
);
