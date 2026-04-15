@component('mail::message')
# New booking received

**{{ $tourTitle }}** — {{ $booking->value('booking_date') }}

## Customer

- **Name:** {{ $booking->value('customer_name') }}
- **Email:** {{ $booking->value('customer_email') }}
- **Phone:** {{ $booking->value('customer_phone') ?? '—' }}

## Booking

- **Date:** {{ $booking->value('booking_date') }}
- **Guests:** {{ $booking->value('guests') }}
- **Amount paid:** €{{ $amount }}
- **Stripe session:** `{{ $booking->value('stripe_session_id') }}`

@if($booking->value('customer_notes'))
## Notes from customer

> {{ $booking->value('customer_notes') }}
@endif

@component('mail::button', ['url' => url('/cp/collections/bookings/entries/'.$booking->id())])
View in control panel
@endcomponent
@endcomponent
