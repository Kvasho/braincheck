import { useEffect, useState } from 'react';
import type { SaveStatus } from '@/lib/exam-autosave';
import { ExamAutosave } from '@/lib/exam-autosave';
import student from '@/routes/student';

/**
 * React state for the player's autosave queue (see ExamAutosave). Returns
 * the queue (stable for the attempt), its status and the unsaved count.
 */
export function useExamAutosave(
    attempt: string,
    onExpired: (resultUrl: string | null) => void,
) {
    const [state, setState] = useState<{ status: SaveStatus; unsaved: number }>(
        {
            status: 'saved',
            unsaved: 0,
        },
    );
    const [autosave] = useState(
        () =>
            new ExamAutosave(
                (position) =>
                    student.attempts.answers.update.url({ attempt, position }),
                setState,
                onExpired,
            ),
    );

    useEffect(() => () => autosave.dispose(), [autosave]);

    return { autosave, ...state } as const;
}
