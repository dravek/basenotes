<?php

declare(strict_types=1);

namespace App\Repos;

final readonly class TokenDto
{
    public function __construct(
        public string   $id,
        public string   $userId,
        public string   $name,
        public string   $tokenHash,
        public string   $scopes,
        public int      $createdAt,
        public int|null $lastUsedAt,
        public int|null $revokedAt,
    ) {}
}

final class TokenRepository
{
    public function __construct(private \PDO $pdo) {}

    public function create(TokenDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (id, user_id, name, token_hash, scopes, created_at, last_used_at, revoked_at)
             VALUES (:id, :user_id, :name, :token_hash, :scopes, :created_at, :last_used_at, :revoked_at)'
        );
        $stmt->execute([
            'id'           => $dto->id,
            'user_id'      => $dto->userId,
            'name'         => $dto->name,
            'token_hash'   => $dto->tokenHash,
            'scopes'       => $dto->scopes,
            'created_at'   => $dto->createdAt,
            'last_used_at' => $dto->lastUsedAt,
            'revoked_at'   => $dto->revokedAt,
        ]);
    }

    /** @return list<TokenDto> */
    public function listByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, token_hash, scopes, created_at, last_used_at, revoked_at
             FROM api_tokens
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return array_map($this->rowToDto(...), $stmt->fetchAll());
    }

    public function findByHash(string $hash): TokenDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, token_hash, scopes, created_at, last_used_at, revoked_at
             FROM api_tokens
             WHERE token_hash = :token_hash'
        );
        $stmt->execute(['token_hash' => $hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    public function revoke(string $id, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = :revoked_at
             WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute([
            'revoked_at' => time(),
            'id'         => $id,
            'user_id'    => $userId,
        ]);
    }

    public function updateLastUsed(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET last_used_at = :last_used_at WHERE id = :id'
        );
        $stmt->execute(['last_used_at' => time(), 'id' => $id]);
    }

    private function rowToDto(array $row): TokenDto
    {
        return new TokenDto(
            id:         $row['id'],
            userId:     $row['user_id'],
            name:       $row['name'],
            tokenHash:  $row['token_hash'],
            scopes:     $row['scopes'],
            createdAt:  (int)$row['created_at'],
            lastUsedAt: $row['last_used_at'] !== null ? (int)$row['last_used_at'] : null,
            revokedAt:  $row['revoked_at']   !== null ? (int)$row['revoked_at']   : null,
        );
    }
}
