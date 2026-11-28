<?php
declare(strict_types=1);

// A row from the users table. Administrators are users with role = 'admin'.
class User
{
    private int $userId;
    private string $fullName;
    private string $email;
    private string $passwordHash;
    private string $role;
    private string $accountStatus;

    public function __construct(int $userId, string $fullName, string $email, string $role,
                                string $accountStatus, string $passwordHash = '')
    {
        $this->userId = $userId;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->role = $role;
        $this->accountStatus = $accountStatus;
        $this->passwordHash = $passwordHash;
    }

    public static function fromRow(array $row): User
    {
        return new User(
            (int) $row['user_id'],
            (string) ($row['full_name'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) $row['role'],
            (string) $row['account_status'],
            (string) ($row['password_hash'] ?? '')
        );
    }

    public function getId(): int { return $this->userId; }
    public function getFullName(): string { return $this->fullName; }
    public function getEmail(): string { return $this->email; }
    public function getRole(): string { return $this->role; }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Only active accounts with a known role are allowed to sign in.
    public function isActive(): bool
    {
        return $this->accountStatus === 'active' && in_array($this->role, ['user', 'admin'], true);
    }

    public function checkPassword(string $password): bool
    {
        return $this->passwordHash !== '' && password_verify($password, $this->passwordHash);
    }

    // The pages were written with arrays, so this keeps the same keys they expect.
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'role' => $this->role,
            'account_status' => $this->accountStatus,
        ];
    }
}
