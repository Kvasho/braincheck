<x-mail::message>
# {{ __('Hello :name,', ['name' => $name]) }}

@if ($approved)
{{ __('Good news: your request for teacher access on BrainCheck was approved. You can now build question banks, create tests and manage groups.') }}
@else
{{ __('Thank you for your interest in teaching on BrainCheck. After review, your request for teacher access was not approved this time.') }}
@endif

@if ($note)
<x-mail::panel>
**{{ __('Note from the reviewer') }}:** {{ $note }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
{{ __('Open BrainCheck') }}
</x-mail::button>

@unless ($approved)
{{ __('You can send a new request after :days days.', ['days' => config('braincheck.teacher_request_cooldown_days')]) }}
@endunless

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
