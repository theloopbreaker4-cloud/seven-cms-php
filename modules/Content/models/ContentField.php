<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentField — schema row describing a single field of a content type.
 *
 * `field_type` enum decides how the value is stored inside `content_entries.data`
 * and rendered in the admin UI. `settings` JSON holds per-field options:
 *
 *   text         { "max": 255, "placeholder": "" }
 *   richtext     { "toolbar": ["bold","italic","link","image"] }
 *   number       { "min": 0, "max": 100, "step": 1 }
 *   boolean      { "default": false }
 *   image        { "ratio": "16:9" }       -- references media.id
 *   media        { "accept": ["image/*","video/*"] }
 *   select       { "options": [ {"value":"a","label":"A"} ] }
 *   multiselect  { "options": [...], "max": 5 }
 *   date         { "format": "Y-m-d" }
 *   datetime     { "format": "Y-m-d H:i" }
 *   relation     { "to": "recipe", "cardinality": "many" }   -- to = ContentType slug
 *   repeater     { "fields": [ {key,label,field_type,settings}, … ] }
 *   json         { }
 */
class ContentField extends Model
{
    public const TYPES = [
        'text', 'richtext', 'number', 'boolean',
        'image', 'media',
        'select', 'multiselect',
        'date', 'datetime',
        'relation', 'repeater', 'json',
    ];

    public ?int    $id         = null;
    public ?int    $typeId     = null;
    public string  $key        = '';
    public string  $label      = '';
    public string  $fieldType  = 'text';
    public int     $required   = 0;
    public int     $localized  = 0;
    public string  $settings   = '{}';
    public int     $sortOrder  = 0;
    public ?string $createdAt  = null;
    public ?string $updatedAt  = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function settingsArray(): array
    {
        $arr = json_decode($this->settings ?: '{}', true);
        return is_array($arr) ? $arr : [];
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'typeId'    => $this->typeId,
            'key'       => $this->key,
            'label'     => $this->label,
            'fieldType' => $this->fieldType,
            'required'  => (bool)$this->required,
            'localized' => (bool)$this->localized,
            'settings'  => $this->settingsArray(),
            'sortOrder' => $this->sortOrder,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * Coerce/validate a raw value submitted from the admin UI according to the field type.
     * Throws InvalidArgumentException for required-but-missing values.
     *
     * @return mixed Value to be stored inside `content_entries.data[key]`.
     */
    public function castValue($raw)
    {
        $isEmpty = $raw === null || $raw === '' || $raw === [];
        if ($isEmpty && $this->required) {
            throw new InvalidArgumentException("Field '{$this->key}' is required.");
        }
        if ($isEmpty) return null;

        return match ($this->fieldType) {
            'text', 'richtext' => (string)$raw,
            'number'           => is_numeric($raw) ? $raw + 0 : null,
            'boolean'          => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'image', 'media'   => is_numeric($raw) ? (int)$raw : null,
            'date', 'datetime' => (string)$raw,
            'select'           => (string)$raw,
            'multiselect'      => is_array($raw) ? array_map('strval', $raw) : [],
            'relation'         => is_array($raw) ? array_map('intval', array_filter($raw))
                                                 : (is_numeric($raw) ? [(int)$raw] : []),
            'repeater'         => is_array($raw) ? array_values($raw) : [],
            'json'             => is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?? null) : null),
            default            => $raw,
        };
    }
}
