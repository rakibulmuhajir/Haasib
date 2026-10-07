<?php

use App\Services\ValueTrailBatch;

test('evidence batches bound children and reject mixed versions', function () {
    $nodes = ['root' => ['id' => 'root', 'children' => [], 'source' => null, 'value' => 100]];
    for ($i = 0; $i < 100; $i++) {
        $id = 'child:'.$i;
        $nodes['root']['children'][] = $id;
        $nodes[$id] = ['id' => $id, 'children' => [], 'value' => 1, 'source' => ['href' => '/private-record']];
    }
    $graph = ['nodes' => $nodes, 'roots' => ['total' => 'root']];
    request()->headers->set('X-Value-Trail-Node', 'total');
    $first = app(ValueTrailBatch::class)->present($graph);
    expect(count($first['nodes']))->toBe(41)
        ->and($first['nodes']['root']['children_total'])->toBe(100)
        ->and($first['nodes']['root']['value'])->toBe(100)
        ->and($first['nodes']['child:0']['source'])->toBeNull();
    request()->headers->set('X-Value-Trail-Offset', '40');
    request()->headers->set('X-Value-Trail-Version', $first['batch']['version']);
    $second = app(ValueTrailBatch::class)->present($graph);
    expect($second['nodes']['root']['children'][0])->toBe('child:40');
    request()->headers->set('X-Value-Trail-Node', 'child:0');
    request()->headers->set('X-Value-Trail-Offset', '0');
    expect(app(ValueTrailBatch::class)->present($graph)['nodes']['child:0']['source']['href'])->toBe('/private-record');
    $graph['nodes']['root']['value'] = 101;
    expect(app(ValueTrailBatch::class)->present($graph))->toHaveKey('error');
});
