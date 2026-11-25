<?php
declare(strict_types=1);

namespace NovaTech\Auth;

/**
 * Common authentication contract for DB and Active Directory LDAP providers.
 */
interface AuthProvider
{
    /**
     * @return array<string, mixed>|null Authenticated user row from `users` table, or null on invalid credentials/lock.
     */
    public function authenticate(string $username, string $password): ?array;
}
