<?php
declare(strict_types=1);

namespace SEOJusAI\Execution\DTO;

defined('ABSPATH') || exit;

/**
 * ExecutionIntentDTO
 *
 * Lightweight immutable data transfer object for execution intent records.
 * @invariant This object must not contain execution logic.
 */
final class ExecutionIntentDTO
{
    private int $id;
    private int $strategicDecisionId;
    private string $intentType;
    private string $status;
    private string $payloadJson;
    private string $createdAt;
    private string $updatedAt;
    private ?string $claimedBy;
    private ?string $claimedAt;
    private ?string $completedAt;
    private ?string $errorMessage;

    public function __construct(
        int $id,
        int $strategicDecisionId,
        string $intentType,
        string $status,
        string $payloadJson,
        string $createdAt,
        string $updatedAt,
        ?string $claimedBy = null,
        ?string $claimedAt = null,
        ?string $completedAt = null,
        ?string $errorMessage = null
    ) {
        $this->id = $id;
        $this->strategicDecisionId = $strategicDecisionId;
        $this->intentType = $intentType;
        $this->status = $status;
        $this->payloadJson = $payloadJson;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->claimedBy = $claimedBy;
        $this->claimedAt = $claimedAt;
        $this->completedAt = $completedAt;
        $this->errorMessage = $errorMessage;
    }

    public function id(): int { return $this->id; }
    public function strategicDecisionId(): int { return $this->strategicDecisionId; }
    public function intentType(): string { return $this->intentType; }
    public function status(): string { return $this->status; }
    public function payloadJson(): string { return $this->payloadJson; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
    public function claimedBy(): ?string { return $this->claimedBy; }
    public function claimedAt(): ?string { return $this->claimedAt; }
    public function completedAt(): ?string { return $this->completedAt; }
    public function errorMessage(): ?string { return $this->errorMessage; }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        $decoded = json_decode($this->payloadJson, true);
        return is_array($decoded) ? $decoded : [];
    }
}
