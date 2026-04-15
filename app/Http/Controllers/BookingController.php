<?php

namespace App\Http\Controllers;

use App\Services\BookingPricing;
use App\Services\BookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Webhook;

class BookingController extends Controller
{
    public function __construct(protected BookingRepository $bookings) {}

    /**
     * Create a Stripe embedded Checkout Session and persist a pending booking.
     * Request: tour slug, date (Y-m-d), guests, name, email, phone, notes
     */
    public function createSession(Request $request)
    {
        $data = $request->validate([
            'tour' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tour = Entry::query()
            ->where('collection', 'tours')
            ->where('slug', $data['tour'])
            ->first();

        abort_if(!$tour || !$tour->value('bookable'), 404, 'Tour not bookable');

        $basePrice = (int) ($tour->value('base_price') ?? 0);
        $maxGuests = (int) ($tour->value('max_guests') ?? 4);

        abort_if($data['guests'] > $maxGuests, 422, 'Too many guests');
        abort_if($basePrice <= 0, 500, 'Tour has no price');
        abort_if($this->bookings->isDateBlocked($data['date']), 422, 'Date unavailable');
        abort_if($this->bookings->isDateBooked($tour->id(), $data['date']), 422, 'Date already booked');

        $amount = BookingPricing::total($basePrice, $data['guests']);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = CheckoutSession::create([
            'ui_mode' => 'embedded_page',
            'mode' => 'payment',
            'redirect_on_completion' => 'never', // onComplete JS callback handles UI; webhook is authoritative
            'customer_email' => $data['email'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $tour->value('title').' — '.$data['date'],
                        'description' => trim(sprintf(
                            '%d %s · %s',
                            $data['guests'],
                            $data['guests'] === 1 ? 'guest' : 'guests',
                            $tour->value('duration') ?: 'Full day experience'
                        )),
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'tour_id' => $tour->id(),
                'tour_slug' => $tour->slug(),
                'tour_title' => $tour->value('title'),
                'booking_date' => $data['date'],
                'guests' => (string) $data['guests'],
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'] ?? '',
                'notes' => $data['notes'] ?? '',
            ],
        ]);

        $this->bookings->create([
            'title' => sprintf('%s — %s (%s)', $tour->value('title'), $data['date'], Str::limit($data['name'], 30)),
            'tour' => $tour->id(),
            'booking_date' => $data['date'],
            'guests' => $data['guests'],
            'amount_total' => $amount,
            'status' => 'pending',
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'] ?? null,
            'customer_notes' => $data['notes'] ?? null,
            'stripe_session_id' => $session->id,
        ]);

        return response()->json(['client_secret' => $session->client_secret]);
    }

    /**
     * Return URL after Stripe completes inside the embedded iframe.
     * Fetches the session and returns a small JSON status for the client.
     */
    public function complete(Request $request)
    {
        $sessionId = $request->query('session_id');
        abort_unless($sessionId, 400);

        Stripe::setApiKey(config('services.stripe.secret'));
        $session = CheckoutSession::retrieve($sessionId);

        return response()->json([
            'status' => $session->status,
            'payment_status' => $session->payment_status,
            'customer_email' => $session->customer_details->email ?? null,
            'amount_total' => $session->amount_total,
        ]);
    }

    /**
     * Stripe webhook — authoritative source for booking status.
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $this->bookings->markPaid(
                $session->id,
                $session->payment_intent ?? null,
            );
        }

        if ($event->type === 'checkout.session.expired' || $event->type === 'checkout.session.async_payment_failed') {
            $session = $event->data->object;
            $this->bookings->markCancelled($session->id);
        }

        return response('ok');
    }
}
