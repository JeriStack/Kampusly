<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use DateTimeImmutable;
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

    public function optionalString(string $field, int $maxLength = 255): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        $value = $this->input[$field];
        if (!is_string($value)) {
            $this->addError($field, sprintf('The %s field must be a string.', $field));
            return $this;
        }

        if (mb_strlen(trim($value)) > $maxLength) {
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

    public function requirePositiveInt(string $field): self
    {
        return $this->requireInt($field, 1);
    }

    public function optionalPositiveInt(string $field): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        return $this->requireInt($field, 1);
    }

    public function optionalInt(string $field, ?int $min = null, ?int $max = null): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        return $this->requireInt($field, $min, $max);
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
     * @param list<string> $allowedValues
     */
    public function optionalIn(string $field, array $allowedValues): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        return $this->requireIn($field, $allowedValues);
    }

    public function requireDate(string $field): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->addError($field, sprintf('The %s field is required.', $field));
            return $this;
        }

        if (!self::isValidIsoDate(trim($value))) {
            $this->addError($field, sprintf('The %s field must be a valid date in YYYY-MM-DD format.', $field));
        }

        return $this;
    }

    public function optionalDate(string $field): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        return $this->requireDate($field);
    }

    public function requireTime(string $field): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->addError($field, sprintf('The %s field is required.', $field));
            return $this;
        }

        if (self::parseTimeToSeconds(trim($value)) === null) {
            $this->addError($field, sprintf('The %s field must be a valid 24-hour time (HH:MM or HH:MM:SS).', $field));
        }

        return $this;
    }

    public function optionalTime(string $field): self
    {
        if (!array_key_exists($field, $this->input) || $this->input[$field] === null || $this->input[$field] === '') {
            return $this;
        }

        return $this->requireTime($field);
    }

    public function requireTimeOrder(string $startField, string $endField): self
    {
        $startRaw = $this->input[$startField] ?? null;
        $endRaw = $this->input[$endField] ?? null;

        if (!is_string($startRaw) || !is_string($endRaw)) {
            return $this;
        }

        $startSec = self::parseTimeToSeconds(trim($startRaw));
        $endSec = self::parseTimeToSeconds(trim($endRaw));

        if ($startSec !== null && $endSec !== null && $startSec >= $endSec) {
            $this->addError(
                $endField,
                sprintf('The %s field must be strictly later than %s.', $endField, $startField)
            );
        }

        return $this;
    }

    public function requireSchoolYear(string $field): self
    {
        $value = $this->input[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->addError($field, sprintf('The %s field is required.', $field));
            return $this;
        }

        $trimmed = trim($value);
        if (preg_match('/^(\d{4})-(\d{4})$/', $trimmed, $matches) !== 1
            || ((int) $matches[2] !== (int) $matches[1] + 1)
        ) {
            $this->addError(
                $field,
                sprintf('The %s field must be a consecutive academic year in YYYY-YYYY format (e.g., 2026-2027).', $field)
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

    public static function isValidIsoDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    public static function parseTimeToSeconds(string $time): ?int
    {
        if (preg_match('/^(?:([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?)$/', $time, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function addError(string $field, string $message): void
    {
        if (!array_key_exists($field, $this->errors)) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
