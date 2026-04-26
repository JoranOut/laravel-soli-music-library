import * as React from 'react';
import { AudioPlayerBar } from '@/components/audio-player-bar';
import { SidebarInset } from '@/components/ui/sidebar';

export function AppContent({
    children,
    ...props
}: React.ComponentProps<'main'>) {

    return (
        <SidebarInset {...props}>
            <div className="flex-1">{children}</div>
            <AudioPlayerBar />
        </SidebarInset>
    );
}
