<?php

namespace App\Modules\Core\Actions;

use RuntimeException;

class ModuleDependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $modules
     */
    public function __construct(string $message, public readonly array $modules = [])
    {
        parent::__construct($message);
    }
}
