<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\TeacherRequestStatus;
use App\Models\TeacherAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a student the outcome of their teacher access request. Queued; sent in
 * the user's preferred locale (Mail::to($user) applies HasLocalePreference).
 */
final class TeacherRequestReviewed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Synapse mail theme: resources/views/vendor/mail/html/themes/synapse.css */
    public $theme = 'synapse';

    public function __construct(public readonly TeacherAccessRequest $teacherRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved()
                ? __('Your teacher access request was approved')
                : __('Your teacher access request was reviewed'),
        );
    }

    public function content(): Content
    {
        $this->teacherRequest->loadMissing('user');

        return new Content(
            markdown: 'mail.teacher-request-reviewed',
            with: [
                'approved' => $this->approved(),
                'name' => $this->teacherRequest->user->name,
                'note' => $this->teacherRequest->review_note,
                'url' => route('dashboard'),
            ],
        );
    }

    private function approved(): bool
    {
        return $this->teacherRequest->status === TeacherRequestStatus::Approved;
    }
}
