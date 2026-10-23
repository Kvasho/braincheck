import { useTranslation } from '@/hooks/use-translation';
import type { ReviewProps } from '../shared/types';
import type { TextReview as Review } from './types';

export default function TextReview({ review }: ReviewProps<Review>) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2">
            <p className="rounded-lg border p-3 break-words whitespace-pre-wrap">
                {review.text === '' ? '—' : review.text}
            </p>
            <p className="text-sm text-muted-foreground">
                {t('A teacher grades this answer.')}
            </p>
        </div>
    );
}
