<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\Entry as EntryFacade;

class BookingConfirmedCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Pass only the booking ID — Statamic Entry objects don't serialize cleanly for queued jobs. */
    public function __construct(public string $bookingId) {}

    public function envelope(): Envelope
    {
        $booking = $this->booking();
        return new Envelope(
            subject: 'Your booking is confirmed — '.($booking?->value('booking_date') ?? ''),
        );
    }

    public function content(): Content
    {
        $booking = $this->booking();
        $tour = $booking?->value('tour');
        if (is_array($tour)) $tour = $tour[0] ?? null;
        $tourEntry = $tour ? EntryFacade::find($tour) : null;

        return new Content(
            markdown: 'emails.booking-confirmed-customer',
            with: [
                'booking' => $booking,
                'tourTitle' => $tourEntry?->value('title') ?? 'Your tour',
                'tourUrl' => $tourEntry?->url(),
                'amount' => $booking ? number_format((int) $booking->value('amount_total') / 100, 2) : '0.00',
            ],
        );
    }

    protected function booking(): ?\Statamic\Contracts\Entries\Entry
    {
        return EntryFacade::find($this->bookingId);
    }
}
