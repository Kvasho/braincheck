import { ChoiceReview } from '../shared/choice';
import type { ReviewProps } from '../shared/types';
import type { SingleChoiceReview as Review } from './types';

export default function SingleChoiceReview({ review }: ReviewProps<Review>) {
    return <ChoiceReview options={review.options} />;
}
