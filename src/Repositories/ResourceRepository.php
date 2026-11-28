<?php
declare(strict_types=1);

class ResourceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $resourceId): ?Resource
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.resource_id, r.name, r.description, r.location, r.status, c.name AS category
             FROM resources AS r
             LEFT JOIN categories AS c ON c.category_id = r.category_id
             WHERE r.resource_id = :resource_id
             LIMIT 1'
        );

        $stmt->execute([
            'resource_id' => $resourceId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Resource::fromRow($row) : null;
    }

    /** @return Resource[] everything except inactive resources, for the catalogue page */
    public function findForCatalogue(): array
    {
        $rows = $this->pdo->query(
            "SELECT r.resource_id, r.name, r.description, r.location, r.status, c.name AS category
             FROM resources r
             INNER JOIN categories c ON c.category_id = r.category_id
             WHERE r.status <> 'inactive'
             ORDER BY c.name, r.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            [Resource::class, 'fromRow'],
            $rows
        );
    }

    /** @return Resource[] a few available resources for the home page */
    public function findFeatured(int $limit = 4): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.resource_id, r.name, r.description, r.location, r.status, c.name AS category
             FROM resources r
             JOIN categories c ON c.category_id = r.category_id
             WHERE r.status = 'available'
             ORDER BY r.resource_id
             LIMIT :lim"
        );

        $stmt->bindValue(
            'lim',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return array_map(
            [Resource::class, 'fromRow'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // Returns the resource status, or null if it does not exist.
    // With $lockRow = true the row stays locked until the transaction ends,
    // so two bookings for the same resource have to wait for each other.
    public function findStatus(
        int $resourceId,
        bool $lockRow = false
    ): ?string {
        $sql =
            'SELECT status FROM resources WHERE resource_id = :id'
            . ($lockRow ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'id' => $resourceId
        ]);

        $status = $stmt->fetchColumn();

        return $status === false
            ? null
            : (string) $status;
    }
}