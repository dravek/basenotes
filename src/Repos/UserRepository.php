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
        public bool   $isAdmin,
        public ?int   $disabledAt,
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
            'SELECT id, email, password_hash, is_admin, disabled_at, created_at, updated_at
             FROM users WHERE email = :email'
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
            isAdmin:      (bool)$row['is_admin'],
            disabledAt:   $row['disabled_at'] !== null ? (int)$row['disabled_at'] : null,
            createdAt:    (int)$row['created_at'],
            updatedAt:    (int)$row['updated_at'],
        );
    }

    public function findById(string $id): UserDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, is_admin, disabled_at, created_at, updated_at
             FROM users WHERE id = :id'
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
            isAdmin:      (bool)$row['is_admin'],
            disabledAt:   $row['disabled_at'] !== null ? (int)$row['disabled_at'] : null,
            createdAt:    (int)$row['created_at'],
            updatedAt:    (int)$row['updated_at'],
        );
    }

    public function create(UserDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, email, password_hash, is_admin, disabled_at, created_at, updated_at)
             VALUES (:id, :email, :password_hash, :is_admin, :disabled_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'id'            => $dto->id,
            'email'         => $dto->email,
            'password_hash' => $dto->passwordHash,
            'is_admin'      => $dto->isAdmin,
            'disabled_at'   => $dto->disabledAt,
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

    /** @return list<UserDto> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, email, password_hash, is_admin, disabled_at, created_at, updated_at
             FROM users ORDER BY created_at DESC, id DESC'
        );
        $rows = $stmt->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $users[] = new UserDto(
                id:           $row['id'],
                email:        $row['email'],
                passwordHash: $row['password_hash'],
                isAdmin:      (bool)$row['is_admin'],
                disabledAt:   $row['disabled_at'] !== null ? (int)$row['disabled_at'] : null,
                createdAt:    (int)$row['created_at'],
                updatedAt:    (int)$row['updated_at'],
            );
        }
        return $users;
    }

    public function setDisabledAt(string $id, ?int $disabledAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET disabled_at = :disabled_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'disabled_at' => $disabledAt,
            'updated_at'  => time(),
            'id'          => $id,
        ]);
    }

    public function setAdminByEmail(string $email, bool $isAdmin): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_admin = :is_admin, updated_at = :updated_at WHERE lower(email) = lower(:email)'
        );
        $stmt->execute([
            'is_admin'  => $isAdmin,
            'updated_at'=> time(),
            'email'     => $email,
        ]);
        return $stmt->rowCount() > 0;
    }
}
