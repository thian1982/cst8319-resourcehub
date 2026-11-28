<?php
declare(strict_types=1);

class Category
{
    private int $categoryId;
    private string $name;
    private string $description;

    public function __construct(int $categoryId, string $name, string $description = '')
    {
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->description = $description;
    }

    public static function fromRow(array $row): Category
    {
        return new Category((int) $row['category_id'], (string) $row['name'], (string) ($row['description'] ?? ''));
    }

    public function getId(): int { return $this->categoryId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
}
