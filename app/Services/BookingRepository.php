<?php

namespace App\Services;

use App\Mail\BookingConfirmedCustomer;
use App\Mail\BookingReceivedOwner;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;

class BookingRepository
{
    /** All dates blocked globally via the configuration global. Returns array of Y-m-d strings. */
    public function blockedDates(): array
    {
        $global = \Statamic\Facades\GlobalSet::findByHandle('configuration');
        if (!$global) return [];
        $blocked = $global->inDefaultSite()->get('blocked_dates', []);

        return collect($blocked)
            ->pluck('date')
            ->filter()
            ->values()
            ->all();
    }

    public function isDateBlocked(string $date): bool
    {
        return in_array($date, $this->blockedDates(), true);
    }

    /**
     * Dates already booked (paid or pending within the last 15 min) for a given tour.
     * Expired pending bookings are treated as released.
     */
    public function bookedDates(string $tourId): array
    {
        // Statamic's `entries` field stores values as array even with max_items:1.
        // We fetch all bookings and filter in-memory for reliable matching.
        $entries = Entry::query()->where('collection', 'bookings')->get();

        $releaseAfter = now()->subMinutes(15);

        return $entries
            ->filter(function ($e) use ($tourId) {
                $tour = $e->value('tour');
                if (is_array($tour)) return in_array($tourId, $tour, true);
                return $tour === $tourId;
            })
            ->filter(function ($e) use ($releaseAfter) {
                $status = $e->value('status');
                if ($status === 'paid') return true;
                if ($status === 'pending' && $e->lastModified() > $releaseAfter) return true;
                return false;
            })
            ->map(fn($e) => $e->value('booking_date'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isDateBooked(string $tourId, string $date): bool
    {
        return in_array($date, $this->bookedDates($tourId), true);
    }

    public function create(array $data): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()
            ->collection('bookings')
            ->slug(Str::uuid()->toString())
            ->data($data);

        $entry->save();

        return $entry;
    }

    public function findBySession(string $sessionId): ?\Statamic\Contracts\Entries\Entry
    {
        return Entry::query()
            ->where('collection', 'bookings')
            ->where('stripe_session_id', $sessionId)
            ->first();
    }

    public function markPaid(string $sessionId, ?string $paymentIntent = null): void
    {
        $booking = $this->findBySession($sessionId);
        if (!$booking) return;
        if ($booking->value('status') === 'paid') return; // idempotent

        $booking->set('status', 'paid');
        if ($paymentIntent) $booking->set('stripe_payment_intent', $paymentIntent);
        $booking->save();

        // Queue emails on Redis — only the booking ID is serialized to avoid Entry-serialization issues.
        try {
            $id = $booking->id();
            Mail::to($booking->value('customer_email'))->queue(new BookingConfirmedCustomer($id));
            Mail::to(config('services.bookings.notification_email'))->queue(new BookingReceivedOwner($id));
        } catch (\Throwable $e) {
            \Log::error('Booking email queue failed: '.$e->getMessage(), ['booking' => $booking->id()]);
        }
    }

    public function markCancelled(string $sessionId): void
    {
        $booking = $this->findBySession($sessionId);
        if (!$booking) return;
        if ($booking->value('status') === 'paid') return; // don't overwrite paid

        $booking->set('status', 'cancelled');
        $booking->save();
    }
}
