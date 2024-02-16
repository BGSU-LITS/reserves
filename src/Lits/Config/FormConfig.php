<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;

final class FormConfig extends Config
{
    public string $subject = 'Course Reserves Registration';

    /** @var list<string> */
    public array $periods = [];

    /** @var list<LocationConfig> */
    public array $locations = [];
}
