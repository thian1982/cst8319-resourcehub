<?php
declare(strict_types=1);

// Booking rules shared by the user booking page and the admin reservations page.
class ReservationService
{
    private PDO $pdo;
    private ResourceRepository $resources;
    private ReservationRepository $reservations;
    private UserRepository $users;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->resources = new ResourceRepository($pdo);
        $this->reservations = new ReservationRepository($pdo);
        $this->users = new UserRepository($pdo);
    }

    // "Check availability" button. Read only, nothing is saved.
    // Returns 'free', 'conflict' or 'unavailable'.
    public function checkAvailability(int $resourceId, DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        if ($this->resources->findStatus($resourceId) !== 'available') {
            return 'unavailable';
        }
        return $this->hasConflict($resourceId, $start, $end) ? 'conflict' : 'free';
    }

    public function hasConflict(int $resourceId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $this->reservations->hasConflict($resourceId, self::toSql($start), self::toSql($end));
    }

    // Saves a booking or throws BookingException. Everything runs in one transaction and
    // the resource row is locked first, so if two people book the same slot at the same
    // time the second request waits and then sees the first booking.
    public function createReservation(int $userId, int $resourceId, DateTimeImmutable $start,
                                      DateTimeImmutable $end, bool $checkUserIsActive = false): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($this->resources->findStatus($resourceId, true) !== 'available') {
                throw new BookingException(BookingException::RESOURCE_UNAVAILABLE);
            }
            if ($checkUserIsActive) {
                $user = $this->users->findById($userId);
                if ($user === null || !$user->isActive()) {
                    throw new BookingException(BookingException::INACTIVE_USER);
                }
            }
            if ($this->hasConflict($resourceId, $start, $end)) {
                throw new BookingException(BookingException::TIME_CONFLICT);
            }
            $id = $this->reservations->create($userId, $resourceId, self::toSql($start), self::toSql($end));
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelForUser(int $reservationId, int $userId): bool
    {
        return $this->reservations->cancelForUser($reservationId, $userId);
    }

    public function cancelAsAdmin(int $reservationId): bool
    {
        return $this->reservations->cancelAny($reservationId);
    }

    private static function toSql(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }
}
