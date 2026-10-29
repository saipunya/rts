<?php
declare(strict_types=1);

function demo_test_settings(): ?array {
    if (!is_admin() || empty($_SESSION['demo_test_sale_date']) || (int)($_SESSION['demo_test_user_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? -1)) return null;
    $date = (string)$_SESSION['demo_test_sale_date'];
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Bangkok'));
    if (!$parsed || $parsed->format('Y-m-d') !== $date) return null;
    return ['sale_date'=>$date,'placement_date'=>$parsed->modify('-2 days')->format('Y-m-d')];
}

function placement_entry_date(): string {
    $test = demo_test_settings();
    return $test['placement_date'] ?? (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
}

function can_weigh_on_price_date(string $date): bool {
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    if ($today >= $date) return true;
    $test = demo_test_settings();
    return $test !== null && $test['sale_date'] === $date;
}
