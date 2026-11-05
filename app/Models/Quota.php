<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
final class Quota extends Model {
    public function summary(string $owner): array { return ['target'=>null,'actual'=>null,'period'=>null]; }
}
