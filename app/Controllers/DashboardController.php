<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Session::get('user', []);

        // Valores de referência da interface. A integração com o banco será ligada
        // quando as tabelas/colunas educacionais forem confirmadas em tables.php.
        $data = [
            'user' => $user,
            'dashboard' => [
                'metrics' => [
                    [
                        'title' => 'Alunos Ativos',
                        'value' => 11,
                        'comparison' => '98,5% vs 2026/2',
                        'direction' => 'down',
                        'icon' => 'fa-users',
                        'tone' => 'blue',
                        'href' => 'ava/gestao-de-alunos',
                    ],
                    [
                        'title' => 'Pré-Matrículas',
                        'value' => 0,
                        'comparison' => '0,0% vs 2026/2',
                        'direction' => 'neutral',
                        'icon' => 'fa-file-lines',
                        'tone' => 'green',
                        'href' => 'educacional/gestao-de-processo-seletivo',
                    ],
                ],
                'attendances' => [
                    'total' => 103,
                    'items' => [
                        ['label' => 'Concluídos', 'value' => 33, 'percent' => '32,0%', 'color' => '#35c477'],
                        ['label' => 'Em andamento', 'value' => 69, 'percent' => '67,0%', 'color' => '#5793f7'],
                        ['label' => 'Aguardando Aluno', 'value' => 0, 'percent' => '0,0%', 'color' => '#f5a623'],
                        ['label' => 'Cancelados', 'value' => 1, 'percent' => '1,0%', 'color' => '#f05252'],
                    ],
                ],
                'activities' => [
                    ['day' => '20', 'month' => 'MAI', 'title' => 'Rematrícula Online - Fase 2', 'period' => '20/05/2025 a 30/05/2025', 'tone' => 'blue'],
                    ['day' => '26', 'month' => 'MAI', 'title' => 'Trancamento de Matrícula', 'period' => '26/05/2025 a 06/06/2025', 'tone' => 'green'],
                    ['day' => '02', 'month' => 'JUN', 'title' => 'Solicitação de Aproveitamento', 'period' => '02/06/2025 a 13/06/2025', 'tone' => 'orange'],
                    ['day' => '10', 'month' => 'JUN', 'title' => 'Prazo para Lançamento de Notas', 'period' => '10/06/2025 a 14/06/2025', 'tone' => 'purple'],
                ],
            ],
        ];

        return $this->render('dashboard/index', $data);
    }
}
