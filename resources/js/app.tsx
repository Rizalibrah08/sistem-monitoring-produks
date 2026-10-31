import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Workspace from './Pages/Workspace';
import { PreviewProvider } from './mocks/preview-provider';
import type { Role } from './types';

createInertiaApp({
    title: (title) => (title ? `${title} · Aisha Monitor` : 'Aisha Monitor'),
    resolve: () => Workspace,
    setup({ el, App, props }) {
        createRoot(el).render(
            <PreviewProvider initialRole={props.initialPage.props.initialRole as Role}>
                <App {...props} />
            </PreviewProvider>,
        );
    },
    progress: { color: '#167a5b', showSpinner: false },
});
