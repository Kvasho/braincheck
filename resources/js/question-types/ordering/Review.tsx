import { useTranslation } from '@/hooks/use-translation';
import { ReviewMark } from '../shared/review-mark';
import type { ReviewProps } from '../shared/types';
import type { OrderingReview as Review } from './types';

export default function OrderingReview({ review }: ReviewProps<Review>) {
    const { t } = useTranslation();
    const byId = new Map(review.items.map((item) => [item.id, item]));

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <section className="space-y-2">
                <h3 className="text-sm font-medium">{t('Your order')}</h3>
                <ol className="space-y-2">
                    {review.answered.map((item, index) => (
                        <li key={item.id}>
                            <ReviewMark correct={item.is_correct} selected>
                                <span className="tabular mr-2 text-muted-foreground">
                                    {index + 1}.
                                </span>
                                {item.text}
                            </ReviewMark>
                        </li>
                    ))}
                </ol>
            </section>
            {review.correct_order && (
                <section className="space-y-2">
                    <h3 className="text-sm font-medium">
                        {t('Correct order')}
                    </h3>
                    <ol className="space-y-2">
                        {review.correct_order.map((id, index) => (
                            <li key={id}>
                                <ReviewMark correct>
                                    <span className="tabular mr-2 text-muted-foreground">
                                        {index + 1}.
                                    </span>
                                    {byId.get(id)?.text}
                                </ReviewMark>
                            </li>
                        ))}
                    </ol>
                </section>
            )}
        </div>
    );
}
