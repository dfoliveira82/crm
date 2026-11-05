<?php
declare(strict_types=1);
namespace App\Services\Rm;
final class RmException extends \RuntimeException {
    public function __construct(string $message, public readonly bool $unavailable=false, public readonly int $httpStatus=0) { parent::__construct($message); }
}
