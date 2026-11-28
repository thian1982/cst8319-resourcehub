<?php
declare(strict_types=1);

// Thrown by ReservationService when a booking is refused.
// Each page decides what message to show for each reason.
class BookingException extends DomainException
{
    public const RESOURCE_UNAVAILABLE = 'resource_unavailable';
    public const TIME_CONFLICT = 'time_conflict';
    public const INACTIVE_USER = 'inactive_user';

    private string $reason;

    public function __construct(string $reason)
    {
        parent::__construct('Booking refused: ' . $reason);
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
