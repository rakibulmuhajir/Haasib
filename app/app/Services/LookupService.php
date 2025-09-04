<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LookupService
{
    public function __construct(private Request $request)
    {
    }

    /**
     * Suggest records from a table using generic lookup behavior.
     *
     * @param string $table   The table name to query.
     * @param array  $fields  Configuration including:
     *                        - 'select': columns to return
     *                        - 'search': columns to apply the q filter
     *                        - 'order': column to order by (optional)
     * @param array  $filters Map of request param => column config. Each value may
     *                        be a string column name, an array with 'column' and optional
     *                        'type' => 'bool', or a callable receiving ($query,$value).
     */
    public function suggest(string $table, array $fields, array $filters = [])
    {
        $q = (string) $this->request->query('q', '');
        $limit = (int) $this->request->query('limit', 10);

        $select = $fields['select'] ?? [];
        $search = $fields['search'] ?? [];
        $orderBy = $fields['order'] ?? null;

        $query = DB::table($table);

        if ($q !== '' && $search) {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
            $query->where(function ($w) use ($search, $like) {
                foreach ($search as $idx => $col) {
                    if ($idx === 0) {
                        $w->where($col, 'ilike', $like);
                    } else {
                        $w->orWhere($col, 'ilike', $like);
                    }
                }
            });
        }

        foreach ($filters as $param => $config) {
            $value = $this->request->query($param);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_callable($config)) {
                $config($query, $value);
                continue;
            }

            if (is_array($config)) {
                $column = $config['column'] ?? $param;
                if (($config['type'] ?? null) === 'bool') {
                    $value = (bool) $value;
                }
            } else {
                $column = $config;
            }

            $query->where($column, $value);
        }

        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        return $query->limit($limit)->get($select);
    }
}
