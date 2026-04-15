<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

class BookingConfirmedCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Entry $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your booking is confirmed — '.$this->booking->value('booking_date'),
        );
    }

    public function content(): Content
    {
        $tour = $this->booking->value('tour');
        if (is_array($tour)) $tour = $tour[0] ?? null;
        $tourEntry = $tour ? EntryFacade::find($tour) : null;

        return new Content(
            markdown: 'emails.booking-confirmed-customer',
            with: [
                'booking' => $this->booking,
                'tourTitle' => $tourEntry?->value('title') ?? 'Your tour',
                'tourUrl' => $tourEntry?->url(),
                'amount' => number_format((int) $this->booking->value('amount_total') / 100, 2),
            ],
        );
    }
}
