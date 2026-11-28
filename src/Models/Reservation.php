<?php
declare(strict_types=1);

class Reservation
{
    private int $reservationId;
    private int $userId;
    private int $resourceId;
    private DateTimeImmutable $start;
    private DateTimeImmutable $end;
    private string $status;
    private string $resourceName;
    private string $categoryName;

    public function __construct(int $reservationId, int $userId, int $resourceId, DateTimeImmutable $start,
                                DateTimeImmutable $end, string $status = 'active',
                                string $resourceName = '', string $categoryName = '')
    {
        $this->reservationId = $reservationId;
        $this->userId = $userId;
        $this->resourceId = $resourceId;
        $this->start = $start;
        $this->end = $end;
        $this->status = $status;
        $this->resourceName = $resourceName;
        $this->categoryName = $categoryName;
    }

    public static function fromRow(array $row): Reservation
    {
        return new Reservation(
            (int) $row['reservation_id'],
            (int) ($row['user_id'] ?? 0),
            (int) $row['resource_id'],
            new DateTimeImmutable((string) $row['start_datetime']),
            new DateTimeImmutable((string) $row['end_datetime']),
            (string) $row['status'],
            (string) ($row['resource_name'] ?? ''),
            (string) ($row['category_name'] ?? '')
        );
    }

    public function getId(): int { return $this->reservationId; }
    public function getUserId(): int { return $this->userId; }
    public function getResourceId(): int { return $this->resourceId; }
    public function getStart(): DateTimeImmutable { return $this->start; }
    public function getEnd(): DateTimeImmutable { return $this->end; }
    public function getStatus(): string { return $this->status; }
    public function getResourceName(): string { return $this->resourceName; }
    public function getCategoryName(): string { return $this->categoryName; }

    // Same rule as the database check: touching end and start times do not overlap.
    public function overlaps(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        return $this->status === 'active' && $this->start < $end && $this->end > $start;
    }

    // Users can only cancel their own active bookings that have not started yet.
    public function canBeCancelled(DateTimeInterface $now): bool
    {
        return $this->status === 'active' && $this->start > $now;
    }

    // An active booking whose end time has passed is shown as completed.
    public function displayStatus(DateTimeInterface $now): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }
        if ($this->status === 'completed' || ($this->status === 'active' && $this->end <= $now)) {
            return 'Completed';
        }
        return $this->start > $now ? 'Upcoming' : 'In Progress';
    }
}
