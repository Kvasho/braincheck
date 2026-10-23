import { useEffect, useState } from 'react';
import { sendJson } from '@/lib/exam-client';
import student from '@/routes/student';
import type { AntiCheatEvent } from '@/types';

export const EVENT_BATCH_INTERVAL_MS = 10000;
/** Same as braincheck.attempt_events_per_request. */
export const EVENTS_PER_REQUEST = 50;
/** Local buffer cap while offline (the server caps per attempt anyway). */
const BUFFER_MAX = 200;

type ClientEvent = {
    type: Exclude<AntiCheatEvent, 'session_changed'>;
    meta: Record<string, number> | null;
};

/**
 * Integrity events while the exam is open, sent in batches every 10 s and
 * right before submit. Only the event type and small numbers (current
 * question, pasted length): never what was copied or pasted. The server
 * stamps the time. Events never change a score; teachers see them.
 */
class ExamEvents {
    private buffer: ClientEvent[] = [];
    private sending = false;

    constructor(private readonly url: string) {}

    record(event: ClientEvent): void {
        if (this.buffer.length < BUFFER_MAX) {
            this.buffer.push(event);
        }
    }

    async flush(): Promise<void> {
        if (this.sending || this.buffer.length === 0) {
            return;
        }

        this.sending = true;
        const batch = this.buffer.splice(0, EVENTS_PER_REQUEST);
        const result = await sendJson<null>('POST', this.url, {
            events: batch,
        });

        // Network error: try again with the next batch (409 = finished: drop).
        if (!result.ok && result.status === 0) {
            this.buffer.unshift(...batch);
        }

        this.sending = false;
    }
}

export function useExamEvents(attempt: string, currentPosition: () => number) {
    const [events] = useState(
        () => new ExamEvents(student.attempts.events.store.url(attempt)),
    );

    useEffect(() => {
        const meta = () => ({ question: currentPosition() });
        let wasFullscreen = document.fullscreenElement !== null;

        const onVisibility = () => {
            if (document.visibilityState === 'hidden') {
                events.record({ type: 'tab_hidden', meta: meta() });
            }
        };
        const onBlur = () =>
            events.record({ type: 'window_blur', meta: meta() });
        const onCopy = () => events.record({ type: 'copy', meta: meta() });
        const onPaste = (event: ClipboardEvent) =>
            events.record({
                type: 'paste',
                meta: {
                    ...meta(),
                    chars: event.clipboardData?.getData('text').length ?? 0,
                },
            });
        // Only when the student opted into fullscreen (intro page button).
        const onFullscreen = () => {
            const isFullscreen = document.fullscreenElement !== null;

            if (wasFullscreen && !isFullscreen) {
                events.record({ type: 'fullscreen_exit', meta: meta() });
            }

            wasFullscreen = isFullscreen;
        };

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('blur', onBlur);
        document.addEventListener('copy', onCopy);
        document.addEventListener('paste', onPaste);
        document.addEventListener('fullscreenchange', onFullscreen);
        const timer = window.setInterval(
            () => void events.flush(),
            EVENT_BATCH_INTERVAL_MS,
        );

        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('blur', onBlur);
            document.removeEventListener('copy', onCopy);
            document.removeEventListener('paste', onPaste);
            document.removeEventListener('fullscreenchange', onFullscreen);
            window.clearInterval(timer);
        };
    }, [events, currentPosition]);

    return events;
}
