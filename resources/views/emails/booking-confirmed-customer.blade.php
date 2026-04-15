@component('mail::message')
# Your booking is confirmed

Dear {{ $booking->value('customer_name') }},

Thank you for booking **{{ $tourTitle }}** with Funchal Tours. We're looking forward to exploring Madeira with you.

## Your booking

- **Tour:** {{ $tourTitle }}
- **Date:** {{ $booking->value('booking_date') }}
- **Guests:** {{ $booking->value('guests') }}
- **Total paid:** €{{ $amount }}

## What happens next

We'll send you hotel pickup details and the full day's itinerary at least 48 hours before your tour. If your plans change, just reply to this email.

@if($tourUrl)
@component('mail::button', ['url' => $tourUrl])
View tour details
@endcomponent
@endif

Looking forward to meeting you,<br>
Ana-Maria & the Funchal Tours team
@endcomponent
