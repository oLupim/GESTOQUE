import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

createInertiaApp({
    title: (title) => (title ? `${title} · Gestoque` : 'Gestoque'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`];
        if (!page) throw new Error(`Página não encontrada: ${name}`);
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#C9281F' },
});
