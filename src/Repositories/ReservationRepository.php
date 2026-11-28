<?php
declare(strict_types=1);

class ReservationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Overlap rule: an active booking conflicts if it starts before the new end
    // and ends after the new start. Uses idx_reservations_resource_status_time.
    public function hasConflict(
        int $resourceId,
        string $start,
        string $end
    ): bool {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM reservations
             WHERE resource_id = :resource_id
               AND status = 'active'
               AND start_datetime < :new_end
               AND end_datetime > :new_start
             LIMIT 1"
        );

        $stmt->execute([
            'resource_id' => $resourceId,
            'new_end' => $end,
            'new_start' => $start
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(
        int $userId,
        int $resourceId,
        string $start,
        string $end
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reservations
                (
                    user_id,
                    resource_id,
                    start_datetime,
                    end_datetime,
                    status
                )
             VALUES
                (
                    :user_id,
                    :resource_id,
                    :start_time,
                    :end_time,
                    'active'
                )"
        );

        $stmt->execute([
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'start_time' => $start,
            'end_time' => $end
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // Cancels only if the booking belongs to this user, is active and has not started.
    public function cancelForUser(
        int $reservationId,
        int $userId
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE reservations
             SET status = 'cancelled',
                 cancelled_at = NOW()
             WHERE reservation_id = :reservation_id
               AND user_id = :user_id
               AND status = 'active'
               AND start_datetime > NOW()"
        );

        $stmt->execute([
            'reservation_id' => $reservationId,
            'user_id' => $userId
        ]);

        return $stmt->rowCount() === 1;
    }

    // Admins can cancel any active booking.
    public function cancelAny(
        int $reservationId
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE reservations
             SET status = 'cancelled',
                 cancelled_at = NOW()
             WHERE reservation_id = :reservation_id
               AND status = 'active'"
        );

        $stmt->execute([
            'reservation_id' => $reservationId
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @return Reservation[] newest first, with resource and category names */
    public function findForUser(
        int $userId
    ): array {
        $stmt = $this->pdo->prepare(
            'SELECT
                r.reservation_id,
                r.user_id,
                r.resource_id,
                r.start_datetime,
                r.end_datetime,
                r.status,
                res.name AS resource_name,
                c.name AS category_name
             FROM reservations r
             JOIN resources res
                ON res.resource_id = r.resource_id
             LEFT JOIN categories c
                ON c.category_id = res.category_id
             WHERE r.user_id = :user_id
             ORDER BY
                r.start_datetime DESC,
                r.reservation_id DESC'
        );

        $stmt->execute([
            'user_id' => $userId
        ]);

        return array_map(
            [Reservation::class, 'fromRow'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // Totals for the profile page. Ended active bookings count as completed.
    public function countsForUser(
        int $userId
    ): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'active'
                             AND start_datetime > NOW()
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS upcoming,
                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'completed'
                              OR (
                                  status = 'active'
                                  AND end_datetime <= NOW()
                              )
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed
             FROM reservations
             WHERE user_id = :user_id"
        );

        $stmt->execute([
            'user_id' => $userId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' =>
                (int) ($row['total'] ?? 0),

            'upcoming' =>
                (int) ($row['upcoming'] ?? 0),

            'completed' =>
                (int) ($row['completed'] ?? 0),
        ];
    }

    // The database clock. Pages compare bookings against this instead of PHP's clock,
    // because PHP and MySQL can be set to different time zones (e.g. on XAMPP).
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            (string) $this->pdo
                ->query('SELECT NOW()')
                ->fetchColumn()
        );
    }
}