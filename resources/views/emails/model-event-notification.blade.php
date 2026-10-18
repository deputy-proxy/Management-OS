<x-mail::message>
# {{ $details['subject'] }}

{{ $details['message'] }}

<x-mail::button :url="$details['url']">
Open Management OS
</x-mail::button>

</x-mail::message>
