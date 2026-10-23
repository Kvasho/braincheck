import { sendJson } from '@/lib/exam-client';

export type SaveStatus = 'saved' | 'saving' | 'offline' | 'rejected';

export type AnswerChange = {
    hasAnswer: boolean;
    answer?: unknown;
    flagged?: boolean;
};

export const AUTOSAVE_DEBOUNCE_MS = 800;
const RETRY_START_MS = 1000;
const RETRY_MAX_MS = 30000;

type Listener = (state: { status: SaveStatus; unsaved: number }) => void;

/**
 * Autosave queue of the exam player, independent of React:
 * - per question: debounced (800 ms), one request at a time, in order;
 * - `saveNow` on question change, `flush` before submit;
 * - network errors, 429 and 5xx: the change is kept (a newer one wins) and
 *   retried with exponential backoff (1 s … 30 s);
 * - 409: the server finished the attempt, `onExpired` takes over;
 * - 422: the server refused the answer.
 * Unsaved answers live only in memory, never in localStorage.
 */
export class ExamAutosave {
    private pending = new Map<number, AnswerChange>();
    private timers = new Map<number, number>();
    private chains = new Map<number, Promise<void>>();
    private retryDelay = RETRY_START_MS;
    private retryTimer: number | undefined;
    private stopped = false;
    private status: SaveStatus = 'saved';

    constructor(
        private readonly urlFor: (position: number) => string,
        private readonly onChange: Listener,
        private readonly onExpired: (resultUrl: string | null) => void,
    ) {}

    queue(position: number, change: AnswerChange, immediate = false): void {
        if (this.stopped) {
            return;
        }

        this.merge(position, change);
        this.emit();
        window.clearTimeout(this.timers.get(position));

        if (immediate) {
            void this.schedule(position);

            return;
        }

        this.timers.set(
            position,
            window.setTimeout(
                () => void this.schedule(position),
                AUTOSAVE_DEBOUNCE_MS,
            ),
        );
    }

    saveNow(position: number): void {
        if (this.pending.has(position)) {
            window.clearTimeout(this.timers.get(position));
            void this.schedule(position);
        }
    }

    async flush(): Promise<void> {
        this.timers.forEach((timer) => window.clearTimeout(timer));
        this.timers.clear();
        await Promise.all(
            [...this.pending.keys()].map((position) => this.schedule(position)),
        );
        await Promise.all(this.chains.values());
    }

    get unsaved(): number {
        return this.pending.size;
    }

    dispose(): void {
        this.stopped = true;
        this.timers.forEach((timer) => window.clearTimeout(timer));
        window.clearTimeout(this.retryTimer);
    }

    private merge(position: number, change: AnswerChange): void {
        const current = this.pending.get(position);

        this.pending.set(position, {
            hasAnswer: change.hasAnswer || (current?.hasAnswer ?? false),
            answer: change.hasAnswer ? change.answer : current?.answer,
            flagged: change.flagged ?? current?.flagged,
        });
    }

    private schedule(position: number): Promise<void> {
        const next = (this.chains.get(position) ?? Promise.resolve()).then(() =>
            this.send(position),
        );
        this.chains.set(position, next);

        return next;
    }

    private async send(position: number): Promise<void> {
        const change = this.pending.get(position);

        if (!change || this.stopped) {
            return;
        }

        this.pending.delete(position);
        this.setStatus('saving');

        const body: Record<string, unknown> = {};

        if (change.hasAnswer) {
            body.answer = change.answer ?? null;
        }

        if (change.flagged !== undefined) {
            body.flagged = change.flagged;
        }

        const result = await sendJson<{ saved_at: string }>(
            'PUT',
            this.urlFor(position),
            body,
        );

        if (result.ok) {
            this.retryDelay = RETRY_START_MS;
            this.setStatus(this.pending.size === 0 ? 'saved' : 'saving');

            return;
        }

        if (result.status === 409) {
            this.stopped = true;
            const url = result.data?.result_url;
            this.onExpired(typeof url === 'string' ? url : null);

            return;
        }

        if (result.status === 422) {
            this.setStatus('rejected');

            return;
        }

        // Keep the change unless a newer one for this question is waiting.
        if (!this.pending.has(position)) {
            this.pending.set(position, change);
        }

        this.setStatus('offline');
        window.clearTimeout(this.retryTimer);
        this.retryTimer = window.setTimeout(() => {
            [...this.pending.keys()].forEach((key) => void this.schedule(key));
        }, this.retryDelay);
        this.retryDelay = Math.min(this.retryDelay * 2, RETRY_MAX_MS);
    }

    private setStatus(status: SaveStatus): void {
        this.status = status;
        this.emit();
    }

    private emit(): void {
        this.onChange({ status: this.status, unsaved: this.pending.size });
    }
}
