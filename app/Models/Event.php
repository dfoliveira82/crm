<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
use DateTimeImmutable;
use DateTimeZone;

final class Event extends Model {
    public function today(string $owner): array { return []; }

    public function upcoming(string $owner, DateTimeImmutable $from, int $limit = 4): array
    {
        if ($owner === '') return [];
        $items = $this->forRange($owner, $from->format('Y-m-d'), $from->modify('+365 days')->format('Y-m-d'));
        $currentDay = $from->format('Y-m-d');
        $items = array_values(array_filter($items, static function (array $item) use ($currentDay): bool {
            try {
                // O dashboard trabalha por dia: mantém o dia atual e os
                // próximos, removendo eventos encerrados em dias anteriores.
                return substr((string) ($item['end'] ?? ''), 0, 10) >= $currentDay;
            } catch (\Throwable) {
                return false;
            }
        }));
        usort($items, static fn(array $left, array $right): int => strcmp((string) ($left['start'] ?? ''), (string) ($right['start'] ?? '')));
        return array_slice($items, 0, max(0, $limit));
    }

    /**
     * Consulta eventos somente quando a entidade `events` estiver configurada.
     * Os nomes físicos ficam em app/Config/tables.php para não presumir o
     * schema do RM/SQL Server.
     */
    public function forRange(string $owner, string $start, string $end): array
    {
        $entity = $this->entity('events');
        $columns = is_array($entity['columns'] ?? null) ? $entity['columns'] : [];
        if (!$entity || !$columns['title'] || !$columns['starts_at'] || !$columns['ends_at']) return [];

        $column = fn(string $key): ?string => !empty($columns[$key]) ? $this->identifier((string) $columns[$key]) : null;
        $idColumn = $column('id');
        $ownerColumn = $column('owner');
        $titleColumn = $column('title');
        $startsColumn = $column('starts_at');
        $endsColumn = $column('ends_at');
        $locationColumn = $column('location');
        $descriptionColumn = $column('description');
        $select = [
            ($idColumn ?: 'NULL') . ' AS [event_id]',
            $titleColumn . ' AS [event_title]',
            $startsColumn . ' AS [event_start]',
            $endsColumn . ' AS [event_end]',
            ($locationColumn ?: 'NULL') . ' AS [event_location]',
            ($descriptionColumn ?: 'NULL') . ' AS [event_description]',
        ];
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $rangeEnd = (new DateTimeImmutable($end . ' 00:00:00', $timezone))->modify('+1 day');
        $params = [$rangeEnd->format('Y-m-d H:i:s'), $start . ' 00:00:00'];
        $where = [$startsColumn . ' < ?', $endsColumn . ' >= ?'];
        if ($ownerColumn && $owner !== '') {
            $where[] = $ownerColumn . ' = ?';
            $params[] = $owner;
        }

        $sql = 'SELECT TOP 500 ' . implode(', ', $select)
            . ' FROM ' . $this->identifier((string) $entity['table'])
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $startsColumn;
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        $dayStart = new DateTimeImmutable($start . ' 00:00:00', $timezone);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            try {
                $eventStart = new DateTimeImmutable((string) $row['event_start'], $timezone);
                $eventEnd = new DateTimeImmutable((string) $row['event_end'], $timezone);
            } catch (\Throwable) {
                continue;
            }
            $eventDayStart = new DateTimeImmutable($eventStart->format('Y-m-d') . ' 00:00:00', $timezone);
            $minutesFromDayStart = (int) floor(($eventStart->getTimestamp() - $eventDayStart->getTimestamp()) / 60);
            $duration = max(30, (int) ceil(($eventEnd->getTimestamp() - $eventStart->getTimestamp()) / 60));
            $items[] = [
                'id' => $row['event_id'],
                'title' => (string) $row['event_title'],
                'start' => $eventStart->format(DateTimeImmutable::ATOM),
                'end' => $eventEnd->format(DateTimeImmutable::ATOM),
                'date' => $eventStart->format('Y-m-d'),
                'time' => $eventStart->format('H:i'),
                'location' => (string) ($row['event_location'] ?? ''),
                'description' => (string) ($row['event_description'] ?? ''),
                // A grade visível começa às 7h; convertemos o horário absoluto
                // para pixels relativos ao primeiro slot da agenda.
                'top' => max(0, $minutesFromDayStart - (7 * 60)),
                'height' => max(30, $duration),
            ];
        }
        return $items;
    }

    public function create(string $owner, string $title, string $startsAt, string $endsAt, string $location = '', string $description = ''): int
    {
        $entity = $this->entity('events');
        if (!$entity) throw new \RuntimeException('A tabela da agenda não está configurada.');

        $columns = $entity['columns'];
        $required = ['owner', 'title', 'starts_at', 'ends_at'];
        foreach ($required as $key) {
            if (empty($columns[$key])) throw new \RuntimeException("A coluna de evento '{$key}' não está configurada.");
        }

        $insertColumns = [$this->identifier((string) $columns['owner']), $this->identifier((string) $columns['title']), $this->identifier((string) $columns['starts_at']), $this->identifier((string) $columns['ends_at'])];
        $values = [$owner, $title, $startsAt, $endsAt];
        if (!empty($columns['location'])) {
            $insertColumns[] = $this->identifier((string) $columns['location']);
            $values[] = $location !== '' ? $location : null;
        }
        if (!empty($columns['description'])) {
            $insertColumns[] = $this->identifier((string) $columns['description']);
            $values[] = $description !== '' ? $description : null;
        }

        $sql = 'INSERT INTO ' . $this->identifier((string) $entity['table'])
            . ' (' . implode(', ', $insertColumns) . ')'
            . ($columns['id'] ? ' OUTPUT INSERTED.' . $this->identifier((string) $columns['id']) : '')
            . ' VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ');';
        $statement = $this->db()->prepare($sql);
        $statement->execute($values);
        return $columns['id'] ? (int) $statement->fetchColumn() : 0;
    }
}
