<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Monitor = 'monitor';
    case Printer = 'printer';
    case Phone = 'phone';
    case Network = 'network';
    case Other = 'other';
}
