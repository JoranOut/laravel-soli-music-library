import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AudioPlayerProvider } from '@/contexts/audio-player-context';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Soli Muziekbibliotheek';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    setup({ el, App, props }) {
        const root = createRoot(el!);

        root.render(
            <StrictMode>
                <AudioPlayerProvider>
                    <App {...props} />
                </AudioPlayerProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
