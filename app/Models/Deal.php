<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
final class Deal extends Model {
    public function pipeline(string $owner): array { return []; }
    public function attention(string $owner): array { return []; }
}
