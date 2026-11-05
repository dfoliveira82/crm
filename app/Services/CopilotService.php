<?php
declare(strict_types=1);
namespace App\Services;
final class CopilotService {
    public function reply(string $message): string {
        $message=trim($message);
        if($message==='') return 'Digite uma pergunta para começar.';
        return 'Recebi sua pergunta. Para gerar uma análise baseada nos dados reais, configure as entidades correspondentes em app/Config/tables.php.';
    }
}
