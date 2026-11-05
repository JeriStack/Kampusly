import type { Config } from 'tailwindcss'

export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Brand palette — see src/assets/styles/tokens.css for CSS variables
        navy: {
          DEFAULT: '#123B78',
          50: '#eef3fb',
          100: '#d7e3f4',
          600: '#1b4b8f',
          700: '#123B78',
          800: '#0f3263',
          900: '#0c274d',
        },
        action: {
          DEFAULT: '#2563EB',
          hover: '#1d4ed8',
          active: '#1e40af',
        },
        accent: {
          DEFAULT: '#0D9488',
          soft: '#ccfbf1',
        },
        // Theme-aware tokens that flip under `.dark` (see tokens.css).
        surface: 'rgb(var(--surface) / <alpha-value>)',
        canvas: 'rgb(var(--canvas) / <alpha-value>)',
        ink: 'rgb(var(--ink) / <alpha-value>)',
        muted: 'rgb(var(--muted) / <alpha-value>)',
        subtle: 'rgb(var(--subtle) / <alpha-value>)',
        hairline: 'rgb(var(--border) / <alpha-value>)',
        'hairline-soft': 'rgb(var(--border-soft) / <alpha-value>)',
      },
      fontFamily: {
        sans: [
          'Inter',
          'Geist Sans',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'Roboto',
          'sans-serif',
        ],
      },
      borderRadius: {
        card: '1rem',
      },
      boxShadow: {
        card: 'var(--shadow-card)',
        'card-hover': 'var(--shadow-card-hover)',
      },
      maxWidth: {
        content: '1200px',
      },
    },
  },
  plugins: [],
} satisfies Config
