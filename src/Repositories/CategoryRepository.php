<?php
declare(strict_types=1);

class CategoryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return string[] category names in alphabetical order */
    public function findAllNames(): array
    {
        return $this->pdo->query('SELECT name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return Category[] */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT category_id, name, description FROM categories ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map([Category::class, 'fromRow'], $rows);
    }
}
