<?php

namespace QuebecStudioMods\ConsentKit\Register;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use QuebecStudioMods\ConsentKit\Core\Decision;
use QuebecStudioMods\ConsentKit\Core\Registry;
use RuntimeException;

/** The register, in the database `Connection` resolves. */
class Register
{
    public const DECISIONS = 'consent_decisions';

    public const PRESENTATIONS = 'consent_presentations';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string, mixed> $decision */
    public function store(array $decision): string
    {
        $decision['categories'] = json_encode($decision['categories'], JSON_THROW_ON_ERROR);
        $decision['created_at'] = $decision['updated_at'] = $decision['decidedAt'];

        return (string)$this->db(self::DECISIONS)->insertGetId($decision);
    }

    /**
     * The id of the stored screen, inserting it the first time it is seen.
     *
     * @param  array<string, mixed>  $presentation  hash, language and canonical payload
     */
    public function presentationId(array $presentation): string
    {
        $hash = (string)$presentation['hash'];
        $id = $this->findPresentation($hash);

        if ($id !== null) {
            return $id;
        }

        try {
            $this->db(self::PRESENTATIONS)->insert([
                'hash' => $hash,
                'language' => $presentation['language'],
                'payload' => $presentation['payload'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {

            return $this->findPresentation($hash) ?? throw $e;
        }

        return $this->findPresentation($hash) ?? throw new RuntimeException('The presentation could not be stored.');
    }

    /**
     * @param  array<string, mixed>  $filters  site, outcome, from, to
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function page(array $filters = [], int $page = 1, int $perPage = 50, string $sort = 'decidedAt', string $dir = 'desc'): array
    {
        return [
            'rows' => $this->rows($this->filtered($filters), $page, $perPage, $sort, $dir),
            'total' => $this->filtered($filters)->count(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $row = $this->filtered([])
            ->leftJoin(self::PRESENTATIONS . ' as p', 'p.id', '=', 'd.presentationId')
            ->where('d.id', $id)
            ->select(['d.*', 'p.hash', 'p.payload'])
            ->first();

        return $row === null ? null : $this->hydrate((array)$row);
    }

    /**
     * @param  array<int, string>  $hashes
     * @return array<string, mixed>
     */
    public function screens(array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return $this->db(self::PRESENTATIONS)
            ->whereIn('hash', $hashes)
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->hash => json_decode($row->payload, true)])
            ->all();
    }

    public function total(): int
    {
        return $this->db(self::DECISIONS)->count();
    }

    public function oldest(): ?string
    {
        $oldest = $this->db(self::DECISIONS)->min('decidedAt');

        return $oldest === null ? null : (string)$oldest;
    }

    public function hasRecords(): bool
    {
        return $this->db(self::DECISIONS)->exists();
    }

    /** Deletes decisions older than the cutoff, or every one when it is null. */
    public function purge(?string $before = null): int
    {
        $query = $this->db(self::DECISIONS);

        if ($before !== null) {
            $query->where('decidedAt', '<', $before);
        }

        $deleted = $query->delete();

        $this->db(self::PRESENTATIONS)
            ->whereNotIn('id', $this->db(self::DECISIONS)->select('presentationId'))
            ->delete();

        return $deleted;
    }

    private function db(string $table): Builder
    {
        return DB::connection($this->connection->name())->table($table);
    }

    /**
     * The query the panel filters apply to, aliased `d` as the rest of the
     * class expects.
     */
    public function query(): Builder
    {
        return $this->db(self::DECISIONS . ' as d');
    }

    /**
     * Rows for one page of an already filtered query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(Builder $query, int $page, int $perPage, string $sort, string $dir): array
    {
        $sort = in_array($sort, Registry::SORTABLE, true) ? $sort : 'decidedAt';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return (clone $query)
            ->leftJoin(self::PRESENTATIONS . ' as p', 'p.id', '=', 'd.presentationId')
            ->select([
                'd.id', 'd.siteHandle', 'd.userId', 'd.language', 'd.decidedAt',
                'd.action', 'd.origin', 'd.categories', 'd.outcome',
                'd.consentVersion', 'p.hash',
            ])
            ->orderBy("d.$sort", $dir)
            ->orderByDesc('d.id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): array => $this->hydrate((array)$row))
            ->all();
    }

    /** @param array<string, mixed> $filters */
    private function filtered(array $filters): Builder
    {
        $query = $this->db(self::DECISIONS . ' as d');

        if (!empty($filters['site'])) {
            $query->where('d.siteHandle', $filters['site']);
        }

        if (!empty($filters['outcome']) && in_array($filters['outcome'], Decision::OUTCOMES, true)) {
            $query->where('d.outcome', $filters['outcome']);
        }

        if (!empty($filters['from'])) {
            $query->where('d.decidedAt', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('d.decidedAt', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (string)$row['id'];
        $row['categories'] = json_decode((string)$row['categories'], true) ?: [];

        return $row;
    }

    private function findPresentation(string $hash): ?string
    {
        $id = $this->db(self::PRESENTATIONS)->where('hash', $hash)->value('id');

        return $id === null ? null : (string)$id;
    }
}
