<?php
declare(strict_types=1);

namespace SEOJusAI\Execution;

defined('ABSPATH') || exit;

use SEOJusAI\Execution\DTO\ExecutionIntentDTO;

/**
 * ExecutionIntentRepository
 *
 * Storage-only persistence layer for execution intents.
 *
 * @boundary Execution is controlled exclusively by AutopilotExecutionLoop.
 * UI, REST, and Admin layers must never create or mutate intents directly.
 * @boundary ExecutionIntent is a stored system state, not an executor.
 */
final class ExecutionIntentRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'seojusai_execution_intents';
    }

    public function tableName(): string
    {
        return $this->table;
    }

    private function tableExists(): bool
    {
        global $wpdb;
        // Fail-closed: if query fails, treat as missing.
        $like = $this->table;
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
        return is_string($found) && $found === $like;
    }

    /**
     * Create execution intent if none exists for decision (idempotent).
     *
     * @param array<string,mixed> $payload
     */
    public function createFromDecision(int $decisionId, string $intentType, array $payload): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;

        $decisionId = (int) $decisionId;
        if ($decisionId <= 0) {
            return false;
        }

        // One StrategicDecision → max ONE ExecutionIntent
        $existing = $this->getByDecisionId($decisionId);
        if ($existing instanceof ExecutionIntentDTO) {
            return true;
        }

        $payloadJson = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson)) {
            $payloadJson = '{}';
        }

        $now = current_time('mysql', 1);

        $inserted = $wpdb->insert(
            $this->table,
            [
                'strategic_decision_id' => $decisionId,
                'intent_type'           => sanitize_key($intentType),
                'status'                => 'pending',
                'payload'               => $payloadJson,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            ['%d','%s','%s','%s','%s','%s']
        );

        return $inserted === 1;
    }

    public function getByDecisionId(int $decisionId): ?ExecutionIntentDTO
    {
        if (!$this->tableExists()) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE strategic_decision_id = %d LIMIT 1",
                (int) $decisionId
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->rowToDto($row) : null;
    }

    public function findById(int $intentId): ?ExecutionIntentDTO
    {
        if (!$this->tableExists()) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                (int) $intentId
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->rowToDto($row) : null;
    }

    /**
     * @return ExecutionIntentDTO[]
     */
    public function getPendingByType(string $intentType, int $limit = 10): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        global $wpdb;

        $limit = max(1, min(50, (int) $limit));
        $intentType = sanitize_key($intentType);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE status = 'pending' AND intent_type = %s
                 ORDER BY created_at ASC, id ASC
                 LIMIT %d",
                $intentType,
                $limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (is_array($r)) {
                $dto = $this->rowToDto($r);
                if ($dto) $out[] = $dto;
            }
        }
        return $out;
    }

    /**
     * Atomically claim the next pending intent (pending -> running).
     * Returns claimed DTO or null if none could be claimed.
     */
    public function claimNextPending(string $workerId, string $intentType = 'analysis'): ?ExecutionIntentDTO
    {
        if (!$this->tableExists()) {
            return null;
        }

        global $wpdb;

        $workerId = substr((string) $workerId, 0, 191);
        $intentType = sanitize_key($intentType);
        $now = current_time('mysql', 1);

        // Atomic update using derived subquery to avoid race conditions.
        $sql = "
            UPDATE {$this->table}
            SET status = 'running',
                claimed_by = %s,
                claimed_at = %s,
                updated_at = %s
            WHERE id = (
                SELECT id FROM (
                    SELECT id FROM {$this->table}
                    WHERE status = 'pending' AND intent_type = %s
                    ORDER BY created_at ASC, id ASC
                    LIMIT 1
                ) AS t
            )
            AND status = 'pending'
        ";

        $prepared = $wpdb->prepare($sql, $workerId, $now, $now, $intentType);
        $affected = $wpdb->query($prepared);

        if (!is_int($affected) || $affected !== 1) {
            return null;
        }

        // Fetch claimed row by worker + timestamp.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE status = 'running' AND claimed_by = %s
                 ORDER BY claimed_at DESC, id DESC
                 LIMIT 1",
                $workerId
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->rowToDto($row) : null;
    }

    public function markCompleted(int $intentId, string $workerId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;

        $workerId = substr((string) $workerId, 0, 191);
        $now = current_time('mysql', 1);

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table}
                 SET status = 'completed',
                     completed_at = %s,
                     updated_at = %s
                 WHERE id = %d AND status = 'running' AND claimed_by = %s",
                $now, $now, (int) $intentId, $workerId
            )
        );

        return is_int($updated) && $updated === 1;
    }

    public function markFailed(int $intentId, string $workerId, ?string $errorMessage = null): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;

        $workerId = substr((string) $workerId, 0, 191);
        $now = current_time('mysql', 1);
        $err = is_string($errorMessage) ? wp_strip_all_tags($errorMessage) : '';
        $err = mb_substr($err, 0, 500);

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table}
                 SET status = 'failed',
                     error_message = %s,
                     updated_at = %s
                 WHERE id = %d AND status = 'running' AND claimed_by = %s",
                $err, $now, (int) $intentId, $workerId
            )
        );

        return is_int($updated) && $updated === 1;
    }

    private function rowToDto(array $row): ?ExecutionIntentDTO
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id <= 0) return null;

        return new ExecutionIntentDTO(
            $id,
            isset($row['strategic_decision_id']) ? (int) $row['strategic_decision_id'] : 0,
            (string) ($row['intent_type'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['payload'] ?? '{}'),
            (string) ($row['created_at'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
            isset($row['claimed_by']) ? (string) $row['claimed_by'] : null,
            isset($row['claimed_at']) ? (string) $row['claimed_at'] : null,
            isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            isset($row['error_message']) ? (string) $row['error_message'] : null
        );
    }
}
