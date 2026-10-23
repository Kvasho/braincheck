import { ArrowRight } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { ReviewMark } from '../shared/review-mark';
import type { ReviewProps } from '../shared/types';
import type { MatchingReview as Review } from './types';

export default function MatchingReview({ review }: ReviewProps<Review>) {
    const { t } = useTranslation();
    const left = new Map(review.left.map((item) => [item.id, item.text]));
    const right = new Map(review.right.map((item) => [item.id, item.text]));
    const given = new Map(review.given_pairs.map((pair) => [pair.left, pair]));

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <section className="space-y-2">
                <h3 className="text-sm font-medium">{t('Your matches')}</h3>
                <ul className="space-y-2">
                    {review.left.map((item) => {
                        const pair = given.get(item.id);

                        return (
                            <li key={item.id}>
                                <ReviewMark
                                    correct={pair?.is_correct}
                                    selected={pair !== undefined}
                                >
                                    {item.text}{' '}
                                    <ArrowRight
                                        className="inline size-3.5"
                                        aria-label={t('matched with')}
                                    />{' '}
                                    {pair
                                        ? right.get(pair.right)
                                        : t('Not matched')}
                                </ReviewMark>
                            </li>
                        );
                    })}
                </ul>
            </section>
            {review.correct_pairs && (
                <section className="space-y-2">
                    <h3 className="text-sm font-medium">
                        {t('Correct matches')}
                    </h3>
                    <ul className="space-y-2">
                        {review.correct_pairs.map((pair) => (
                            <li key={pair.left}>
                                <ReviewMark correct>
                                    {left.get(pair.left)}{' '}
                                    <ArrowRight
                                        className="inline size-3.5"
                                        aria-label={t('matched with')}
                                    />{' '}
                                    {right.get(pair.right)}
                                </ReviewMark>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
