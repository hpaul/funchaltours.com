<?php

namespace App\Services;

/**
 * Pricing for Funchal Tours bookings.
 * Discounts by group size — authoritative server-side calculation.
 */
class BookingPricing
{
    /** Percent off the per-person price, keyed by group size */
    public const DISCOUNTS = [
        1 => 0,
        2 => 20,
        3 => 30,
        4 => 30,
    ];

    /** Total in cents for N guests at a given per-person price (in EUR). */
    public static function total(int $basePricePerPerson, int $guests): int
    {
        $discount = self::DISCOUNTS[$guests] ?? 30; // cap at 30% for >4
        $perPerson = $basePricePerPerson * (100 - $discount) / 100;
        $totalEur = $perPerson * $guests;

        return (int) round($totalEur * 100); // in cents for Stripe
    }

    /** Human-readable breakdown (used in the widget for live total). */
    public static function breakdown(int $basePricePerPerson, int $guests): array
    {
        $discount = self::DISCOUNTS[$guests] ?? 30;
        $subtotal = $basePricePerPerson * $guests;
        $discountAmount = (int) round($subtotal * $discount / 100);
        $total = $subtotal - $discountAmount;

        return [
            'guests' => $guests,
            'base_per_person' => $basePricePerPerson,
            'subtotal' => $subtotal,
            'discount_percent' => $discount,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ];
    }
}
