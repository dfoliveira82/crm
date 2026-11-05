<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
final class Kpi extends Model {
    public function all(string $owner): array { return ['win_rate'=>null,'average_deal'=>null,'sales_cycle'=>null,'pipeline_coverage'=>null]; }
}
