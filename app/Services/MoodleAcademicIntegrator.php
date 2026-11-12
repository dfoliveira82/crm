<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class MoodleAcademicIntegrator
{
    private MoodleClient $client;
    private array $categoryCache = [];

    public function __construct(?MoodleClient $client = null)
    {
        $this->client = $client ?? new MoodleClient();
    }

    public function synchronize(array $groups): array
    {
        if ($groups === []) throw new RuntimeException('Nenhuma turma foi selecionada para integração.');

        $summary = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'categories_reused' => 0,
            'courses_created' => 0,
            'courses_updated' => 0,
            'courses' => [],
        ];

        foreach ($groups as $group) {
            $typeCategory = $this->ensureCategory(
                (string) $group['tipo_ensino'],
                (string) $group['type_idnumber'],
                0,
                $summary
            );
            $periodCategory = $this->ensureCategory(
                (string) $group['periodo'],
                (string) $group['period_idnumber'],
                $typeCategory,
                $summary
            );
            $courseCategory = $this->ensureCategory(
                (string) $group['curso'],
                (string) $group['course_idnumber'],
                $periodCategory,
                $summary
            );
            $classCategory = $this->ensureCategory(
                (string) $group['turma'],
                (string) $group['idnumber'],
                $courseCategory,
                $summary
            );

            foreach ((array) ($group['disciplines'] ?? []) as $discipline) {
                $idnumber = (string) ($discipline['idnumber'] ?? '');
                if ($idnumber === '') continue;

                $course = [
                    'fullname' => $this->courseFullname($discipline, $group),
                    'shortname' => $this->shortname($idnumber),
                    'categoryid' => $classCategory,
                    'idnumber' => $idnumber,
                    'summary' => $this->courseSummary($discipline, $group),
                    'summaryformat' => 1,
                    'startdate' => $this->timestamp((string) $group['periodo_inicio']),
                    'enddate' => $this->timestamp((string) $group['periodo_fim'], true),
                    'visible' => 1,
                ];
                $existing = $this->client->findCourse($idnumber);
                if ($existing) {
                    $course['id'] = (int) ($existing['id'] ?? 0);
                    if ($course['id'] <= 0) throw new RuntimeException('O Moodle retornou um curso sem ID para ' . $idnumber . '.');
                    $this->client->updateCourse($course);
                    $summary['courses_updated']++;
                } else {
                    $course['id'] = $this->client->createCourse($course);
                    $summary['courses_created']++;
                }
                $summary['courses'][] = [
                    'idnumber' => $idnumber,
                    'moodle_course_id' => (int) $course['id'],
                    'cod_coligada' => (int) $group['cod_coligada'],
                    'id_turma_disc' => (int) $discipline['id_turma_disc'],
                ];
            }
        }

        return $summary;
    }

    private function ensureCategory(string $name, string $idnumber, int $parent, array &$summary): int
    {
        if (isset($this->categoryCache[$idnumber])) {
            return $this->categoryCache[$idnumber];
        }

        $existing = $this->client->findCategory($idnumber);
        if ($existing) {
            $id = (int) ($existing['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('O Moodle retornou uma categoria sem ID para ' . $idnumber . '.');

            $currentParent = (int) ($existing['parent'] ?? 0);
            $currentName = trim((string) ($existing['name'] ?? ''));
            if ($currentParent !== $parent || $currentName !== trim($name)) {
                $this->client->updateCategory($id, $name, $parent);
                $updated = $this->client->findCategory($idnumber);
                if (!$updated || (int) ($updated['id'] ?? 0) !== $id || (int) ($updated['parent'] ?? 0) !== $parent) {
                    throw new RuntimeException('O Moodle não confirmou o pai da categoria ' . $idnumber . '.');
                }
                $summary['categories_updated']++;
            } else {
                $summary['categories_reused']++;
            }
            return $this->categoryCache[$idnumber] = $id;
        }

        $id = $this->client->createCategory($name, $parent, $idnumber);
        $created = $this->client->findCategory($idnumber);
        if (!$created || (int) ($created['id'] ?? 0) !== $id || (int) ($created['parent'] ?? 0) !== $parent) {
            throw new RuntimeException('O Moodle não confirmou o pai da categoria criada ' . $idnumber . '.');
        }
        $summary['categories_created']++;
        return $this->categoryCache[$idnumber] = $id;
    }

    private function courseFullname(array $discipline, array $group): string
    {
        return trim((string) ($discipline['name'] ?? 'Disciplina')) . ' · ' . trim((string) $group['turma']);
    }

    private function courseSummary(array $discipline, array $group): string
    {
        return 'Disciplina integrada do TOTVS RM. Curso: ' . $group['curso']
            . '. Turma: ' . $group['turma']
            . '. Período letivo: ' . $group['periodo']
            . '. Código da disciplina: ' . ($discipline['cod_disc'] ?? '');
    }

    private function shortname(string $idnumber): string
    {
        $shortname = preg_replace('/[^A-Za-z0-9_-]+/', '-', $idnumber) ?: 'rm-course';
        return substr($shortname, 0, 100);
    }

    private function timestamp(string $date, bool $endOfDay = false): int
    {
        if ($date === '') return 0;
        $parsed = DateTimeImmutable::createFromFormat('d/m/Y', $date, new DateTimeZone('America/Sao_Paulo'));
        if (!$parsed) return 0;
        return $endOfDay
            ? $parsed->setTime(23, 59, 59)->getTimestamp()
            : $parsed->setTime(0, 0, 0)->getTimestamp();
    }
}
