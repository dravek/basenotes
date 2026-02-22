<?php

declare(strict_types=1);

namespace App\Repos;

use App\Util\Id;

final readonly class UserDto
{
    public function __construct(
        public string $id,
        public string $email,
        public string $passwordHash,
        public int    $createdAt,
        public int    $updatedAt,
    ) {}
}

final class UserRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findByEmail(string $email): UserDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, created_at, updated_at FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return new UserDto(
            id:           $row['id'],
            email:        $row['email'],
            passwordHash: $row['password_hash'],
            createdAt:    (int)$row['created_at'],
            updatedAt:    (int)$row['updated_at'],
        );
    }

    public function findById(string $id): UserDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, created_at, updated_at FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return new UserDto(
            id:           $row['id'],
            email:        $row['email'],
            passwordHash: $row['password_hash'],
            createdAt:    (int)$row['created_at'],
            updatedAt:    (int)$row['updated_at'],
        );
    }

    public function create(UserDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, email, password_hash, created_at, updated_at)
             VALUES (:id, :email, :password_hash, :created_at, :updated_at)'
        );
        $stmt->execute([
            'id'            => $dto->id,
            'email'         => $dto->email,
            'password_hash' => $dto->passwordHash,
            'created_at'    => $dto->createdAt,
            'updated_at'    => $dto->updatedAt,
        ]);
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => $passwordHash,
            'updated_at'    => time(),
            'id'            => $id,
        ]);
    }
}
