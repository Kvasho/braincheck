import { useEffect, useState } from 'react';

/**
 * Detects the same attempt open in another tab (BroadcastChannel, same
 * browser). The newer tab gets `true` and shows a warning. Answers from
 * both tabs would overwrite each other; the server accepts the last save.
 */
export function useSingleTab(attempt: string): [boolean, () => void] {
    const [duplicate, setDuplicate] = useState(false);

    useEffect(() => {
        if (typeof BroadcastChannel === 'undefined') {
            return;
        }

        const channel = new BroadcastChannel(`braincheck-attempt-${attempt}`);
        const id = crypto.randomUUID();
        const openedAt = performance.timeOrigin + performance.now();

        channel.onmessage = (
            event: MessageEvent<{
                type: string;
                id: string;
                openedAt?: number;
            }>,
        ) => {
            const message = event.data;

            if (message.id === id) {
                return;
            }

            if (message.type === 'hello') {
                channel.postMessage({ type: 'present', id, openedAt });
            }

            if (
                message.type === 'present' &&
                (message.openedAt ?? 0) < openedAt
            ) {
                setDuplicate(true);
            }
        };

        channel.postMessage({ type: 'hello', id });

        return () => channel.close();
    }, [attempt]);

    return [duplicate, () => setDuplicate(false)];
}
