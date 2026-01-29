<?php
declare(strict_types=1);

namespace SEOJusAI\Execution\DTO;

defined('ABSPATH') || exit;

/**
 * ExecutionResultDTO
 *
 * @invariant Read-only result container. No side-effects, no retries.
 */
final class ExecutionResultDTO
{
    private bool $success;
    private string $message;

    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(bool $success, string $message = '', array $data = [])
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }

    public function success(): bool { return $this->success; }
    public function message(): string { return $this->message; }

    /** @return array<string,mixed> */
    public function data(): array { return $this->data; }

    public static function ok(string $message = '', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(string $message = ''): self
    {
        return new self(false, $message, []);
    }
}
