<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Kampusly\Shared\Domain\Exception\ValidationException;

final class RequestValidator
{
    /** @var array<string, mixed> */
    private array $input;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function requireString(string $field, int $minLength = 1, int $maxLength = 255): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->addError($field, sprintf('The %s field is required.', $field));
            return $this;
        }

        $length = mb_strlen(trim($value));
        if ($length < $minLength) {
            $this->addError(
                $field,
                sprintf('The %s field must be at least %d character(s).', $field, $minLength)
            );
        }
        if ($length > $maxLength) {
            $this->addError(
                $field,
                sprintf('The %s field must not exceed %d characters.', $field, $maxLength)
            );
        }

        return $this;
    }

    public function requireEmail(string $field): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->addError($field, sprintf('The %s field is required.', $field));
            return $this;
        }

        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, sprintf('The %s field must be a valid email address.', $field));
        }

        return $this;
    }

    public function requireInt(string $field, ?int $min = null, ?int $max = null): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            $this->addError($field, sprintf('The %s field must be an integer.', $field));
            return $this;
        }

        $intValue = (int) $value;
        if ($min !== null && $intValue < $min) {
            $this->addError($field, sprintf('The %s field must be at least %d.', $field, $min));
        }
        if ($max !== null && $intValue > $max) {
            $this->addError($field, sprintf('The %s field must not exceed %d.', $field, $max));
        }

        return $this;
    }

    /**
     * @param list<string> $allowedValues
     */
    public function requireIn(string $field, array $allowedValues): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || !in_array($value, $allowedValues, true)) {
            $this->addError(
                $field,
                sprintf('The %s field must be one of: %s.', $field, implode(', ', $allowedValues))
            );
        }

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function validateOrThrow(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!array_key_exists($field, $this->errors)) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}

