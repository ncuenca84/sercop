<?php

declare(strict_types=1);

class Validator
{
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data);
        $v->validate($rules);
        return $v;
    }

    private function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleStr) as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $ruleName, $param);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        $label = ucfirst(str_replace('_', ' ', $field));
        match ($rule) {
            'required'  => (!isset($value) || $value === '') && $this->addError($field, "{$label} es requerido."),
            'email'     => $value && !filter_var($value, FILTER_VALIDATE_EMAIL) && $this->addError($field, "{$label} no es un email válido."),
            'numeric'   => $value && !is_numeric($value) && $this->addError($field, "{$label} debe ser numérico."),
            'integer'   => $value && !ctype_digit((string)$value) && $this->addError($field, "{$label} debe ser entero."),
            'min'       => $value !== null && strlen((string)$value) < (int)$param && $this->addError($field, "{$label} debe tener al menos {$param} caracteres."),
            'max'       => $value !== null && strlen((string)$value) > (int)$param && $this->addError($field, "{$label} no puede superar {$param} caracteres."),
            'min_val'   => $value !== null && (float)$value < (float)$param && $this->addError($field, "{$label} debe ser mayor o igual a {$param}."),
            'date'      => $value && !strtotime($value) && $this->addError($field, "{$label} no es una fecha válida."),
            'in'        => $value && !in_array($value, explode(',', $param ?? '')) && $this->addError($field, "{$label} tiene un valor no permitido."),
            'confirmed' => ($value !== ($this->data[$field . '_confirmation'] ?? null)) && $this->addError($field, "{$label} no coincide con la confirmación."),
            'unique'    => $this->checkUnique($field, $value, $param),
            default     => null,
        };
    }

    private function checkUnique(string $field, mixed $value, ?string $param): void
    {
        if (!$value || !$param) return;
        [$table, $col, $exceptId] = array_pad(explode(',', $param), 3, null);
        $sql = "SELECT id FROM {$table} WHERE {$col} = ? AND deleted_at IS NULL";
        $params = [$value];
        if ($exceptId) { $sql .= " AND id != ?"; $params[] = $exceptId; }
        if (DB::selectOne($sql, $params)) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $this->addError($field, "{$label} ya existe en el sistema.");
        }
    }

    private function addError(string $field, string $msg): void
    {
        $this->errors[$field][] = $msg;
    }

    public function fails(): bool   { return !empty($this->errors); }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }

    public function firstError(): string
    {
        foreach ($this->errors as $msgs) return $msgs[0];
        return '';
    }
}
