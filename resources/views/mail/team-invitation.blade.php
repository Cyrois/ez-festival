<x-mail::message>
# {{ __('mail.team_invitation.heading') }}

{{ __('mail.team_invitation.invited_by', ['name' => $inviterName]) }}

<x-mail::button :url="$url">
{{ __('mail.team_invitation.action') }}
</x-mail::button>

{{ __('mail.team_invitation.expires') }}

{{ $url }}
</x-mail::message>
