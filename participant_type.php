<?php
declare(strict_types=1);

function collection_participant_labels(): array {
    return ['member' => 'สมาชิก', 'general' => 'เกษตรกรทั่วไป'];
}

function collection_participant_icon(?string $type, bool $decorative = false): string {
    $type = strtolower(trim($type ?? ''));
    $label = collection_participant_labels()[$type] ?? null;
    if ($label === null) return '';

    $attributes = $decorative ? 'aria-hidden="true"' :
        'role="img" aria-label="' . e($label) . '" title="' . e($label) . '"';
    return '<span class="participant-type-icon participant-type-' . e($type) . '" ' . $attributes . '></span>';
}
