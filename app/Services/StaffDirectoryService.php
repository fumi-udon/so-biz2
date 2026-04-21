<?php

namespace App\Services;

use App\Models\Staff;

final class StaffDirectoryService
{
    /**
     * @return list<array{id:int,name:string}>
     */
    public function activeOptions(?int $shopId = null): array
    {
        return $this->activeQuery($shopId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Staff $s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->values()
            ->all();
    }

    public function findActiveById(int $staffId, ?int $shopId = null): ?Staff
    {
        return $this->activeQuery($shopId)
            ->whereKey($staffId)
            ->first();
    }

    /**
     * @return list<array{id:int,name:string,level:int}>
     */
    public function approverOptions(int $shopId, int $minLevel): array
    {
        return $this->activeQuery($shopId)
            ->with('jobLevel')
            ->orderBy('name')
            ->get(['id', 'name', 'job_level_id'])
            ->filter(fn (Staff $s): bool => (int) ($s->jobLevel?->level ?? 0) >= $minLevel)
            ->map(fn (Staff $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'level' => (int) ($s->jobLevel?->level ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,level:int}>
     */
    public function approverCandidateOptions(int $shopId): array
    {
        return $this->activeQuery($shopId)
            ->with('jobLevel')
            ->orderBy('name')
            ->get(['id', 'name', 'job_level_id'])
            ->map(fn (Staff $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'level' => (int) ($s->jobLevel?->level ?? 0),
            ])
            ->values()
            ->all();
    }

    private function activeQuery(?int $shopId)
    {
        $q = Staff::query()->where('is_active', true);
        if ($shopId !== null && $shopId > 0) {
            $q->where('shop_id', $shopId);
        }

        return $q;
    }
}
