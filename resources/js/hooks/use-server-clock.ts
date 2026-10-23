import { useEffect, useState } from 'react';

/**
 * "Now" on the server's clock: offset = server_now − client_now at load, so
 * a wrong (or manipulated) device clock doesn't change the countdown. The
 * server decides about time anyway; this only drives the display.
 */
export function useServerClock(serverNow: string): number {
    const [offset] = useState(() => new Date(serverNow).getTime() - Date.now());
    const [now, setNow] = useState(() => Date.now() + offset);

    useEffect(() => {
        const timer = window.setInterval(
            () => setNow(Date.now() + offset),
            1000,
        );

        return () => window.clearInterval(timer);
    }, [offset]);

    return now;
}

/** 3725 s → "1:02:05", 125 s → "2:05". */
export function formatDuration(totalSeconds: number): string {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = String(seconds % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}
