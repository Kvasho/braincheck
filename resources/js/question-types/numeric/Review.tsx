import { useTranslation } from '@/hooks/use-translation';
import { ReviewMark } from '../shared/review-mark';
import type { ReviewProps } from '../shared/types';
import type { NumericReview as Review } from './types';

export default function NumericReview({ review }: ReviewProps<Review>) {
    const { t } = useTranslation();
    const unit = review.unit ? ` ${review.unit}` : '';

    return (
        <div className="space-y-2">
            <ReviewMark correct={undefined} selected>
                {t('Your answer')}:{' '}
                <span className="tabular">
                    {review.value === null ? '—' : `${review.value}${unit}`}
                </span>
            </ReviewMark>
            {review.correct !== undefined && (
                <ReviewMark correct>
                    {t('Correct answer')}:{' '}
                    <span className="tabular">
                        {review.correct}
                        {unit} (± {review.tolerance})
                    </span>
                </ReviewMark>
            )}
        </div>
    );
}
