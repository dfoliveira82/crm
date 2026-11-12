<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class MoodleIntegrationState extends Model
{
    private const TABLE = 'dbo.CRM_MOODLE_COURSE_SYNC';

    public function statuses(array $idnumbers): array
    {
        $idnumbers = array_values(array_unique(array_filter(array_map('strval', $idnumbers))));
        if ($idnumbers === []) return [];

        $placeholders = implode(',', array_fill(0, count($idnumbers), '?'));
        $sql = 'SELECT RM_IDNUMBER, STATUS, MOODLE_COURSE_ID FROM ' . $this->identifier(self::TABLE)
            . ' WHERE RM_IDNUMBER IN (' . $placeholders . ')';
        $statement = $this->db()->prepare($sql);
        $statement->execute($idnumbers);

        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['RM_IDNUMBER']] = [
                'status' => (string) $row['STATUS'],
                'moodle_course_id' => (int) ($row['MOODLE_COURSE_ID'] ?? 0),
            ];
        }
        return $result;
    }

    public function markIntegrated(string $idnumber, int $moodleCourseId): void
    {
        $sql = 'IF EXISTS (SELECT 1 FROM ' . $this->identifier(self::TABLE) . ' WHERE RM_IDNUMBER = ?)
                BEGIN
                    UPDATE ' . $this->identifier(self::TABLE) . '
                       SET MOODLE_COURSE_ID = ?, STATUS = ?, LAST_SYNC_AT = SYSDATETIME(), LAST_ERROR = NULL, UPDATED_AT = SYSDATETIME()
                     WHERE RM_IDNUMBER = ?
                END
                ELSE
                BEGIN
                    INSERT INTO ' . $this->identifier(self::TABLE) . ' (RM_IDNUMBER, MOODLE_COURSE_ID, STATUS, LAST_SYNC_AT)
                    VALUES (?, ?, ?, SYSDATETIME())
                END';
        $statement = $this->db()->prepare($sql);
        $statement->execute([
            $idnumber, $moodleCourseId, 'INTEGRADA', $idnumber,
            $idnumber, $moodleCourseId, 'INTEGRADA',
        ]);
    }
}
