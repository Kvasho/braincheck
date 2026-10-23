import { ChoiceReview } from '../shared/choice';
import type { ReviewProps } from '../shared/types';
import type { MultipleChoiceReview as Review } from './types';

export default function MultipleChoiceReview({ review }: ReviewProps<Review>) {
    return <ChoiceReview options={review.options} />;
}
