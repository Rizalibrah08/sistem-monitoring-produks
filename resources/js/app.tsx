import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · Aisha Monitor` : 'Aisha Monitor'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page?.default) throw new Error(`Halaman ${name} tidak ditemukan.`);
        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#167a5b', showSpinner: false },
});
