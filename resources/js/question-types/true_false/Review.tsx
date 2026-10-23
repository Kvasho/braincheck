import { useTranslation } from '@/hooks/use-translation';
import { ReviewMark } from '../shared/review-mark';
import type { ReviewProps } from '../shared/types';
import type { TrueFalseReview as Review } from './types';

export default function TrueFalseReview({ review }: ReviewProps<Review>) {
    const { t } = useTranslation();

    return (
        <ul className="grid gap-2 sm:grid-cols-2">
            {[true, false].map((option) => (
                <li key={String(option)}>
                    <ReviewMark
                        correct={
                            review.correct === undefined
                                ? undefined
                                : review.correct === option
                        }
                        selected={review.value === option}
                    >
                        {option ? t('True') : t('False')}
                    </ReviewMark>
                </li>
            ))}
        </ul>
    );
}
