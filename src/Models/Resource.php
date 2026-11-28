<?php
declare(strict_types=1);

// A bookable resource. categoryName comes from a JOIN with categories when the page needs it.
class Resource
{
    private int $resourceId;
    private string $name;
    private string $description;
    private string $location;
    private string $status;
    private string $categoryName;

    public function __construct(int $resourceId, string $name, string $description, string $location,
                                string $status, string $categoryName = '')
    {
        $this->resourceId = $resourceId;
        $this->name = $name;
        $this->description = $description;
        $this->location = $location;
        $this->status = $status;
        $this->categoryName = $categoryName;
    }

    public static function fromRow(array $row): Resource
    {
        return new Resource(
            (int) ($row['resource_id'] ?? $row['id']),
            (string) $row['name'],
            (string) ($row['description'] ?? ''),
            (string) ($row['location'] ?? ''),
            (string) ($row['status'] ?? 'available'),
            (string) ($row['category'] ?? '')
        );
    }

    public function getId(): int { return $this->resourceId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getLocation(): string { return $this->location; }
    public function getStatus(): string { return $this->status; }
    public function getCategoryName(): string { return $this->categoryName; }

    // Status only says if the item can be booked at all, not if a time slot is free.
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'maintenance' => 'Maintenance',
            'inactive' => 'Inactive',
            default => 'Unknown',
        };
    }
}
