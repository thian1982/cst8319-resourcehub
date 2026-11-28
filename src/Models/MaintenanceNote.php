<?php
declare(strict_types=1);

// Not used by the Demo 3 pages yet. The admin maintenance page moves to it in Part 4.
class MaintenanceNote
{
    private int $maintenanceNoteId;
    private int $resourceId;
    private string $note;
    private DateTimeImmutable $startedAt;
    private ?DateTimeImmutable $endedAt;

    public function __construct(int $maintenanceNoteId, int $resourceId, string $note,
                                DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt = null)
    {
        $this->maintenanceNoteId = $maintenanceNoteId;
        $this->resourceId = $resourceId;
        $this->note = $note;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public static function fromRow(array $row): MaintenanceNote
    {
        return new MaintenanceNote(
            (int) $row['maintenance_note_id'],
            (int) $row['resource_id'],
            (string) $row['note'],
            new DateTimeImmutable((string) $row['started_at']),
            empty($row['ended_at']) ? null : new DateTimeImmutable((string) $row['ended_at'])
        );
    }

    public function getId(): int { return $this->maintenanceNoteId; }
    public function getResourceId(): int { return $this->resourceId; }
    public function getNote(): string { return $this->note; }
    public function getStartedAt(): DateTimeImmutable { return $this->startedAt; }
    public function getEndedAt(): ?DateTimeImmutable { return $this->endedAt; }

    public function isOpen(): bool
    {
        return $this->endedAt === null;
    }
}
