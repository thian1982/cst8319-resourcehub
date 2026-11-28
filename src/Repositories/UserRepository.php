<?php
declare(strict_types=1);

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $userId): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, full_name, email, role, account_status FROM users WHERE user_id = :id LIMIT 1'
        );

        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? User::fromRow($row) : null;
    }

    // Includes the password hash, so only the login code should call this.
    public function findByEmailForLogin(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, role, account_status FROM users WHERE email = :email LIMIT 1'
        );

        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? User::fromRow($row) : null;
    }
}
