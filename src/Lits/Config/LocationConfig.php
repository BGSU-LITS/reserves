<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;

final class LocationConfig extends Config
{
    public function __construct(
        public string $title,
        public string $email,
        public string $instructions = '',
    ) {
    }
}
