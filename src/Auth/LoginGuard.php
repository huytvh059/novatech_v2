<?php
declare(strict_types=1);

namespace NovaTech\Auth;

use NovaTech\Db;
use Throwable;

/**
 * Per-(username, IP) brute-force lockout guard (5 failures -> 5-minute lock) shared across DB & LDAP auth.
 */
final class LoginGuard
{
    /**
     * @param callable(): (?array<string, mixed>) $verify
     *
     * @return array<string, mixed>|null
     */
    public static function attempt(string $username, callable $verify): ?array
    {
        $key = hash('sha256', mb_strtolower($username) . '|' . Db::clientIp());
        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            Db::query(
                'INSERT INTO login_attempts (attempt_key) VALUES (?) ON DUPLICATE KEY UPDATE attempt_key = VALUES(attempt_key)',
                [$key]
            );
            $attempt = Db::one(
                'SELECT *, (locked_until > NOW()) AS blocked FROM login_attempts WHERE attempt_key = ? FOR UPDATE',
                [$key]
            );
            $actor = Db::one('SELECT id, username FROM users WHERE username = ?', [$username])
                ?? ['id' => null, 'username' => $username];

            if ($attempt !== null && (int) ($attempt['blocked'] ?? 0) === 1) {
                Db::audit('login_failed', 'Tạm khóa theo username/IP', 'locked', $actor);
                $pdo->commit();

                return null;
            }

            if ($attempt !== null && $attempt['locked_until'] !== null) {
                $attempt['failures'] = 0;
            }

            $user = $verify();
            if ($user === null) {
                $fails = (int) ($attempt['failures'] ?? 0) + 1;
                Db::query(
                    'UPDATE login_attempts SET failures = ?, locked_until = IF(? >= 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NULL), last_attempt = NOW() WHERE attempt_key = ?',
                    [$fails, $fails, $key]
                );
                if (!empty($actor['id'])) {
                    Db::query(
                        'UPDATE users SET so_lan_sai = ?, khoa_den = IF(? >= 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NULL) WHERE id = ?',
                        [$fails, $fails, $actor['id']]
                    );
                }
                Db::audit('login_failed', 'Đăng nhập', 'failure', $actor);
                $pdo->commit();

                return null;
            }

            Db::query(
                'UPDATE login_attempts SET failures = 0, locked_until = NULL, last_attempt = NOW() WHERE attempt_key = ?',
                [$key]
            );
            Db::query('UPDATE users SET so_lan_sai = 0, khoa_den = NULL WHERE id = ?', [$user['id']]);
            Db::audit('login_success', 'Đăng nhập (' . ($user['auth_source'] ?? 'db') . ')', 'success', $user);
            $pdo->commit();

            return $user;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
