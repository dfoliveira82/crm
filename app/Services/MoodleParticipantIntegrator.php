<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\RmClassParticipants;
use RuntimeException;

final class MoodleParticipantIntegrator
{
    private MoodleClient $client;
    private RmClassParticipants $participants;
    private array $userCache = [];
    private array $studentLinkCache = [];

    public function __construct(?MoodleClient $client = null, ?RmClassParticipants $participants = null)
    {
        $this->client = $client ?? new MoodleClient();
        $this->participants = $participants ?? new RmClassParticipants();
    }

    public function synchronize(array $disciplines): array
    {
        $summary = [
            'users_created' => 0,
            'users_updated' => 0,
            'student_links_saved' => 0,
            'enrolments' => 0,
            'disciplines' => 0,
        ];

        foreach ($disciplines as $discipline) {
            $course = $this->client->findCourse((string) ($discipline['idnumber'] ?? ''));
            $courseId = (int) ($course['id'] ?? 0);
            if ($courseId <= 0) {
                throw new RuntimeException('A disciplina ainda não possui curso no Moodle: ' . ($discipline['name'] ?? 'sem nome'));
            }

            $enrolments = [];
            foreach ((array) ($discipline['students'] ?? []) as $student) {
                [$userId, $created] = $this->ensureUser($student, 'ALUNO', (int) $discipline['cod_coligada']);
                $studentRa = trim((string) ($student['RA'] ?? ''));
                $studentLinkKey = (int) $discipline['cod_coligada'] . ':' . $studentRa;
                if (!isset($this->studentLinkCache[$studentLinkKey])) {
                    $this->participants->saveStudentMoodleId(
                        (int) $discipline['cod_coligada'],
                        $studentRa,
                        $userId
                    );
                    $this->studentLinkCache[$studentLinkKey] = true;
                    $summary['student_links_saved']++;
                }
                $summary[$created ? 'users_created' : 'users_updated']++;
                $enrolments[] = [
                    'roleid' => (int) Env::get('MOODLE_STUDENT_ROLE_ID', 5),
                    'userid' => $userId,
                    'courseid' => $courseId,
                ];
            }
            foreach ((array) ($discipline['teachers'] ?? []) as $teacher) {
                [$userId, $created] = $this->ensureUser($teacher, 'PROFESSOR', (int) $discipline['cod_coligada']);
                $summary[$created ? 'users_created' : 'users_updated']++;
                $enrolments[] = [
                    'roleid' => (int) Env::get('MOODLE_TEACHER_ROLE_ID', 3),
                    'userid' => $userId,
                    'courseid' => $courseId,
                ];
            }

            $this->client->enrolUsers($enrolments);
            $summary['enrolments'] += count($enrolments);
            $summary['disciplines']++;
        }
        return $summary;
    }

    private function ensureUser(array $person, string $type, int $codColigada): array
    {
        $rawCode = trim((string) ($person['RA'] ?? $person['CODPROF'] ?? ''));
        if ($rawCode === '') throw new RuntimeException('Participante do RM sem identificador.');
        $kind = $type === 'PROFESSOR' ? 'professor' : 'aluno';
        $idnumber = 'rm:' . $kind . ':' . $codColigada . ':' . $rawCode;
        if (isset($this->userCache[$idnumber])) return [$this->userCache[$idnumber], false];

        $existing = $this->client->findUsersByField('idnumber', [$idnumber]);
        $firstname = trim((string) ($person['NOME'] ?? 'Participante'));
        $parts = preg_split('/\s+/u', $firstname, 2) ?: [$firstname];
        $first = (string) ($parts[0] ?? 'Participante');
        $last = trim((string) ($parts[1] ?? 'RM')) ?: 'RM';
        $safeCode = preg_replace('/[^A-Za-z0-9._-]+/', '', $rawCode) ?: (string) $codColigada;
        $email = $kind === 'aluno'
            ? strtolower($safeCode . '@fadipa.br')
            : trim((string) ($person['EMAIL'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower($kind . '.' . $codColigada . '.' . $safeCode . '@localhost.invalid');
        }

        if (isset($existing[0]['id'])) {
            $userId = (int) $existing[0]['id'];
            $update = [
                'id' => $userId,
                'firstname' => $first,
                'lastname' => $last,
                'email' => $email,
                'idnumber' => $idnumber,
            ];
            if ($kind === 'aluno') $update['username'] = strtolower($safeCode . '@fadipa.br');
            $this->client->updateUser($update);
            return [$this->userCache[$idnumber] = $userId, false];
        }

        $username = $kind === 'aluno'
            ? strtolower($safeCode . '@fadipa.br')
            : strtolower($kind . '_' . $codColigada . '_' . $safeCode);
        $userData = [
            'username' => substr($username, 0, 100),
            'auth' => (string) Env::get('MOODLE_USER_AUTH', 'manual'),
            'firstname' => $first,
            'lastname' => $last,
            'email' => $email,
            'idnumber' => $idnumber,
            'lang' => 'pt_br',
        ];
        if ($kind === 'aluno') {
            $cpf = preg_replace('/\D+/', '', (string) ($person['CPF'] ?? ''));
            if (strlen($cpf) !== 11) {
                throw new RuntimeException('O aluno ' . $rawCode . ' não possui CPF válido para gerar a senha do Moodle.');
            }
            $userData['password'] = 'Alu@' . $cpf;
        } else {
            $userData['createpassword'] = 1;
        }
        $userId = $this->client->createUser($userData);
        return [$this->userCache[$idnumber] = $userId, true];
    }
}
