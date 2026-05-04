<?php
namespace App\Core;

class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = compact('type', 'message');
    }

    public static function pull(): array
    {
        $msgs = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $msgs;
    }
}
