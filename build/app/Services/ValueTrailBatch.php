<?php

namespace App\Services;

/** Bound the evidence transferred per exploration, without changing report calculations. */
class ValueTrailBatch
{
    public function present(array $graph): array
    {
        $key = request()->header('X-Value-Trail-Node');
        if (! $key || isset($graph['error'])) {
            return $graph;
        }
        $id = $graph['roots'][$key] ?? $key;
        if (! isset($graph['nodes'][$id])) {
            return ['error' => 'This value is no longer available. Reopen its explanation.'];
        }
        $version = hash('sha256', json_encode($graph));
        $expected = request()->header('X-Value-Trail-Version');
        if ($expected && ! hash_equals($version, $expected)) {
            return ['error' => 'The calculation changed. Close and reopen its explanation.'];
        }
        $offset = max(0, (int) request()->header('X-Value-Trail-Offset', '0'));
        $parent = $graph['nodes'][$id];
        $total = count($parent['children']);
        $parent['children'] = array_slice($parent['children'], $offset, 40);
        $parent['children_total'] = $total;
        $parent['children_loaded'] = min($offset + 40, $total);
        $nodes = [$id => $parent];
        foreach ($parent['children'] as $child) {
            if (! isset($graph['nodes'][$child])) {
                continue;
            }
            $node = $graph['nodes'][$child];
            $node['children_total'] = count($node['children']);
            $node['children_loaded'] = 0;
            $node['children'] = [];
            $node['source'] = null;
            $node['summary'] = true;
            $nodes[$child] = $node;
        }

        return ['nodes' => $nodes, 'roots' => [$key => $id], 'context' => $graph['context'] ?? [],
            'batch' => ['parent' => $id, 'offset' => $offset, 'version' => $version]];
    }
}
