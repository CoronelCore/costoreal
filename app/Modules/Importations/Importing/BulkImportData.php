<?php

namespace App\Modules\Importations\Importing;

final class BulkImportData
{
    public static function preview(array $result): array
    {
        return ['revision' => (int) $result['revision'], 'columns' => $result['columns'], 'count' => (int) $result['count'],
            'invalid_count' => (int) $result['invalid_count'], 'can_apply' => (bool) $result['can_apply'], 'totals' => $result['totals'],
            'cost_errors' => (object) $result['cost_errors'],
            'rows' => array_map(fn ($row) => ['row' => (int) $row['row'], 'data' => $row['data'], 'errors' => (object) $row['errors']], $result['rows'])];
    }
}
