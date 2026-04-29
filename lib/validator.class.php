<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Fluent validator.
 *
 * Usage:
 *   $v = Validator::make($data)
 *       ->rule('email',    'required|email')
 *       ->rule('password', 'required|min:8')
 *       ->rule('username', 'required|max:32|unique:user,userName');
 *
 *   if ($v->fails()) {
 *       // $v->errors() — ['field' => ['message', ...], ...]
 *       // $v->firstError() — first message string
 *   }
 */
class Validator
{
    private array $data;
    private array $rules   = [];
    private array $errors  = [];
    private bool  $checked = false;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): static
    {
        return new static($data);
    }

    public function rule(string $field, string $rules): static
    {
        $this->rules[$field] = $rules;
        $this->checked = false;
        return $this;
    }

    public function fails(): bool
    {
        if (!$this->checked) $this->validate();
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        if (!$this->checked) $this->validate();
        return $this->errors;
    }

    public function firstError(): string
    {
        $all = $this->errors();
        foreach ($all as $msgs) {
            return $msgs[0] ?? '';
        }
        return '';
    }

    private function validate(): void
    {
        $this->errors  = [];
        $this->checked = true;

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $parts = explode('|', $ruleString);

            foreach ($parts as $part) {
                [$rule, $param] = array_pad(explode(':', $part, 2), 2, null);

                $error = match ($rule) {
                    'required' => $this->checkRequired($value, $field),
                    'email'    => $this->checkEmail($value, $field),
                    'min'      => $this->checkMin($value, $field, (int)$param),
                    'max'      => $this->checkMax($value, $field, (int)$param),
                    'numeric'  => $this->checkNumeric($value, $field),
                    'alpha'    => $this->checkAlpha($value, $field),
                    'url'      => $this->checkUrl($value, $field),
                    'unique'   => $this->checkUnique($value, $field, $param),
                    'in'       => $this->checkIn($value, $field, $param),
                    'confirmed'=> $this->checkConfirmed($value, $field),
                    default    => null,
                };

                if ($error !== null) {
                    $this->errors[$field][] = $error;
                }
            }
        }
    }

    private function checkRequired(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return ucfirst($field) . ' is required.';
        }
        return null;
    }

    private function checkEmail(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ucfirst($field) . ' must be a valid email address.';
        }
        return null;
    }

    private function checkMin(mixed $value, string $field, int $min): ?string
    {
        if ($value === null || $value === '') return null;
        $len = is_string($value) ? mb_strlen($value) : (int)$value;
        if ($len < $min) {
            return ucfirst($field) . ' must be at least ' . $min . (is_string($value) ? ' characters.' : '.');
        }
        return null;
    }

    private function checkMax(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') return null;
        $len = is_string($value) ? mb_strlen($value) : (int)$value;
        if ($len > $max) {
            return ucfirst($field) . ' must not exceed ' . $max . (is_string($value) ? ' characters.' : '.');
        }
        return null;
    }

    private function checkNumeric(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) {
            return ucfirst($field) . ' must be a number.';
        }
        return null;
    }

    private function checkAlpha(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        if (!preg_match('/^[\p{L}\s]+$/u', (string)$value)) {
            return ucfirst($field) . ' must contain only letters.';
        }
        return null;
    }

    private function checkUrl(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ucfirst($field) . ' must be a valid URL.';
        }
        return null;
    }

    /**
     * unique:table,column  — checks DB for existing record.
     * Column defaults to field name (camelCase converted to snake_case) if omitted.
     */
    private function checkUnique(mixed $value, string $field, ?string $param): ?string
    {
        if ($value === null || $value === '') return null;

        [$table, $col] = array_pad(explode(',', $param ?? '', 2), 2, '');
        $col   = $col ?: $field;
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $col));

        $count = \R::getCell(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $snake . '` = ?',
            [$value]
        );

        if ((int)$count > 0) {
            return ucfirst($field) . ' already exists.';
        }
        return null;
    }

    /**
     * in:val1,val2,val3
     */
    private function checkIn(mixed $value, string $field, ?string $param): ?string
    {
        if ($value === null || $value === '') return null;
        $allowed = explode(',', $param ?? '');
        if (!in_array((string)$value, $allowed, true)) {
            return ucfirst($field) . ' must be one of: ' . implode(', ', $allowed) . '.';
        }
        return null;
    }

    /**
     * confirmed — expects data[field_confirmation] to match.
     */
    private function checkConfirmed(mixed $value, string $field): ?string
    {
        $confirm = $this->data[$field . '_confirmation'] ?? null;
        if ($value !== $confirm) {
            return ucfirst($field) . ' confirmation does not match.';
        }
        return null;
    }
}
