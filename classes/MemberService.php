<?php

require_once __DIR__ . '/Database.php';

class MemberService
{
    public const STATUSES = ['Active', 'Suspended', 'Pending'];

    public static function create(array $data): array
    {
        $pdo = Database::connection();
        $firstName = self::cleanName($data['first_name'] ?? $data['FirstName'] ?? '');
        $lastName = self::cleanName($data['last_name'] ?? $data['LastName'] ?? '');
        $nationalId = self::cleanNationalId($data['national_id'] ?? $data['NationalID'] ?? '');
        $phone = self::cleanPhone($data['phone'] ?? $data['PrimaryNumber'] ?? '');
        $email = self::cleanEmail($data['email'] ?? $data['Email'] ?? '');
        $password = trim((string)($data['password'] ?? $data['Password'] ?? ''));

        self::validateRequired($firstName, $lastName, $nationalId, $phone, $password);

        if (self::nationalIdExists($nationalId)) {
            throw new InvalidArgumentException('A member with this National ID already exists.');
        }

        $memberId = self::generateMemberId($nationalId, $firstName, $lastName);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO members (MemberID, NationalID, FirstName, LastName, PrimaryNumber, Email, Password, Status)
             VALUES (:member_id, :national_id, :first_name, :last_name, :phone, :email, :password, :status)'
        );
        $stmt->execute([
            ':member_id' => $memberId,
            ':national_id' => $nationalId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone,
            ':email' => $email ?: null,
            ':password' => $passwordHash,
            ':status' => 'Active',
        ]);

        self::linkUnmatchedContributions($memberId, $nationalId);

        return [
            'MemberID' => $memberId,
            'NationalID' => $nationalId,
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'PrimaryNumber' => $phone,
            'Email' => $email,
            'Status' => 'Active',
        ];
    }

    public static function update(string $memberId, array $data): void
    {
        $memberId = trim($memberId);
        $firstName = self::cleanName($data['first_name'] ?? '');
        $lastName = self::cleanName($data['last_name'] ?? '');
        $nationalId = self::cleanNationalId($data['national_id'] ?? '');
        $phone = self::cleanPhone($data['phone'] ?? '');
        $email = self::cleanEmail($data['email'] ?? '');
        $status = trim((string)($data['status'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));

        if ($memberId === '' || $firstName === '' || $lastName === '' || $nationalId === '' || $phone === '') {
            throw new InvalidArgumentException('Member ID, National ID, first name, last name, and phone are required.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid member status.');
        }

        if (self::nationalIdExists($nationalId, $memberId)) {
            throw new InvalidArgumentException('Another member already uses this National ID.');
        }

        $fields = [
            'NationalID = :national_id',
            'FirstName = :first_name',
            'LastName = :last_name',
            'PrimaryNumber = :phone',
            'Email = :email',
            'Status = :status',
        ];
        $params = [
            ':member_id' => $memberId,
            ':national_id' => $nationalId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone,
            ':email' => $email ?: null,
            ':status' => $status,
        ];

        if ($password !== '') {
            $fields[] = 'Password = :password';
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE members SET ' . implode(', ', $fields) . ' WHERE MemberID = :member_id'
        );
        $stmt->execute($params);

        self::linkUnmatchedContributions($memberId, $nationalId);
    }

    public static function generateMemberId(string $nationalId, string $firstName, string $lastName): string
    {
        $nationalPrefix = substr(preg_replace('/\D+/', '', $nationalId), 0, 4);
        if (strlen($nationalPrefix) < 4) {
            throw new InvalidArgumentException('National ID must contain at least 4 digits.');
        }

        $base = strtoupper($nationalPrefix . substr($firstName, 0, 1) . substr($lastName, 0, 1));
        $stmt = Database::connection()->prepare(
            'SELECT MemberID FROM members WHERE MemberID LIKE :prefix ORDER BY LENGTH(MemberID) DESC, MemberID DESC'
        );
        $stmt->execute([':prefix' => $base . '%']);
        $max = 0;

        foreach ($stmt->fetchAll() as $row) {
            if (preg_match('/^' . preg_quote($base, '/') . '(\d+)$/', $row['MemberID'], $matches)) {
                $max = max($max, (int)$matches[1]);
            }
        }

        return $base . ($max + 1);
    }

    public static function linkUnmatchedContributions(string $memberId, string $nationalId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE contributions SET MemberID = :member_id WHERE NationalID = :national_id AND MemberID IS NULL'
        );
        $stmt->execute([
            ':member_id' => $memberId,
            ':national_id' => $nationalId,
        ]);
    }

    private static function nationalIdExists(string $nationalId, ?string $exceptMemberId = null): bool
    {
        $sql = 'SELECT MemberID FROM members WHERE NationalID = :national_id';
        $params = [':national_id' => $nationalId];

        if ($exceptMemberId !== null) {
            $sql .= ' AND MemberID <> :member_id';
            $params[':member_id'] = $exceptMemberId;
        }

        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }

    private static function validateRequired(string $firstName, string $lastName, string $nationalId, string $phone, string $password): void
    {
        if ($firstName === '' || $lastName === '' || $nationalId === '' || $phone === '' || $password === '') {
            throw new InvalidArgumentException('First name, last name, phone, password, and National ID are required.');
        }
    }

    private static function cleanName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private static function cleanNationalId(string $value): string
    {
        return trim($value);
    }

    private static function cleanPhone(string $value): string
    {
        return trim(preg_replace('/\s+/', '', $value));
    }

    private static function cleanEmail(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address or leave it blank.');
        }

        return $value;
    }
}
