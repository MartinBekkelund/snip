<?php
/**
 * Audit Logging for Admin Operations
 *
 * Logs all administrative actions for security and compliance
 * @package    Snip
 * @version    1.0.3
 */

class AuditLog {
    private $db;

    // Action constants
    const ACTION_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    const ACTION_LOGIN_FAILED = 'LOGIN_FAILED';
    const ACTION_LOGOUT = 'LOGOUT';
    const ACTION_URL_CREATE = 'URL_CREATE';
    const ACTION_URL_READ = 'URL_READ';
    const ACTION_URL_UPDATE = 'URL_UPDATE';
    const ACTION_URL_DELETE = 'URL_DELETE';
    const ACTION_AUTH_CHECK = 'AUTH_CHECK';

    // Status constants
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_BLOCKED = 'blocked';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Log an admin action
     */
    public function log(
        string $action,
        string $status = self::STATUS_SUCCESS,
        ?int $resourceId = null,
        ?string $details = null,
        ?string $ip = null
    ): void {
        try {
            $ip = $ip ?? getClientIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $timestamp = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare('
                INSERT INTO audit_logs
                (action, status, resource_id, ip_address, user_agent, details, logged_at)
                VALUES (:action, :status, :resource_id, :ip, :user_agent, :details, :timestamp)
            ');

            $stmt->execute([
                ':action' => $action,
                ':status' => $status,
                ':resource_id' => $resourceId,
                ':ip' => $ip,
                ':user_agent' => $userAgent,
                ':details' => $details,
                ':timestamp' => $timestamp
            ]);
        } catch (Exception $e) {
            error_log('Audit log error: ' . $e->getMessage());
            // Don't throw - logging failure shouldn't break the app
        }
    }

    /**
     * Get audit logs with pagination
     */
    public function getLogs(
        int $page = 1,
        int $limit = 50,
        ?string $action = null,
        ?string $status = null
    ): array {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if ($action) {
                $where[] = 'action = :action';
                $params[':action'] = $action;
            }

            if ($status) {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Count total
            $countSql = "SELECT COUNT(*) FROM audit_logs $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get logs
            $sql = "
                SELECT id, action, status, resource_id, ip_address, user_agent, details, logged_at
                FROM audit_logs
                $whereClause
                ORDER BY logged_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'logs' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log('Audit log retrieval error: ' . $e->getMessage());
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ];
        }
    }

    /**
     * Get action summary (count by action)
     */
    public function getActionSummary(): array {
        try {
            $stmt = $this->db->prepare('
                SELECT action, status, COUNT(*) as count
                FROM audit_logs
                WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY action, status
                ORDER BY count DESC
            ');
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Audit summary error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed login attempts (potential brute force)
     */
    public function getFailedLoginAttempts(int $minutes = 60): array {
        try {
            $stmt = $this->db->prepare('
                SELECT ip_address, COUNT(*) as attempts, MAX(logged_at) as last_attempt
                FROM audit_logs
                WHERE action = :action
                AND status = :status
                AND logged_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                GROUP BY ip_address
                HAVING attempts >= 3
                ORDER BY attempts DESC
            ');

            $stmt->execute([
                ':action' => self::ACTION_LOGIN_FAILED,
                ':status' => self::STATUS_FAILURE,
                ':minutes' => $minutes
            ]);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Audit failed login error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean old logs (retention policy)
     */
    public function cleanOldLogs(int $days = 90): int {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM audit_logs
                WHERE logged_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ');

            $stmt->execute([':days' => $days]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log('Audit log cleanup error: ' . $e->getMessage());
            return 0;
        }
    }
}
