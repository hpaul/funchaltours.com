<?php

namespace App\Tags;

use App\Services\BookingPricing;
use App\Services\BookingRepository;
use Statamic\Tags\Tags;

class BookingTag extends Tags
{
    /** @var string */
    protected static $handle = 'booking';

    /** {{ booking:blocked_dates_json }} — JSON array of blocked dates (YYYY-MM-DD). */
    public function blockedDatesJson(): string
    {
        return json_encode(app(BookingRepository::class)->blockedDates());
    }

    /** {{ booking:booked_dates_json tour="{id}" }} — JSON array of booked/pending dates for a tour. */
    public function bookedDatesJson(): string
    {
        $tourId = $this->params->get('tour');
        if (!$tourId) return '[]';
        return json_encode(app(BookingRepository::class)->bookedDates($tourId));
    }

    /** {{ booking:discounts_json }} — JSON map of guests → discount %. */
    public function discountsJson(): string
    {
        return json_encode(BookingPricing::DISCOUNTS);
    }
}
