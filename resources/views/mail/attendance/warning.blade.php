<x-mail::message>
# {{ $action->warning_subject }}

Yth. {{ $action->user->name }},

{{ $action->note }}

Surat ini diterbitkan oleh administrator {{ config('app.name') }} pada {{ $action->created_at->translatedFormat('d F Y') }}.

Hormat kami,<br>
Administrator {{ config('app.name') }}
</x-mail::message>
