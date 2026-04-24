<?php

namespace Tests\Feature\Filament\Concerns;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;

/**
 * Filament の Import / Export モデルと、Importer の identity columnMap を組み立てる補助。
 */
trait MakesFilamentImportExportRecords
{
    protected function createUserForFilament(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  class-string  $importerClass
     */
    protected function createImportModel(User $user, string $importerClass): Import
    {
        return Import::query()->create([
            'file_name' => 'test.csv',
            'file_path' => 'imports/'.Str::random(8).'.csv',
            'importer' => $importerClass,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }

    /**
     * CSV ヘッダー名と Importer 列名が同一の columnMap（テスト用の素直なマッピング）。
     *
     * @param  class-string  $importerClass
     * @return array<string, string>
     */
    protected function identityImporterColumnMap(string $importerClass): array
    {
        $map = [];
        foreach ($importerClass::getColumns() as $column) {
            if ($column instanceof ImportColumn) {
                $name = $column->getName();
                $map[$name] = $name;
            }
        }

        return $map;
    }

    /**
     * @param  class-string  $exporterClass
     * @return array<string, string>
     */
    protected function identityExporterColumnMap(string $exporterClass): array
    {
        $map = [];
        foreach ($exporterClass::getColumns() as $column) {
            if ($column instanceof ExportColumn) {
                $name = $column->getName();
                $map[$name] = $name;
            }
        }

        return $map;
    }

    /**
     * @param  class-string  $exporterClass
     */
    protected function createExportModel(User $user, string $exporterClass): Export
    {
        return Export::query()->create([
            'file_disk' => 'local',
            'file_name' => 'test.csv',
            'exporter' => $exporterClass,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }
}
