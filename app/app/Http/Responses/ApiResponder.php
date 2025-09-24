<?php

namespace App\Http\Responses;

trait ApiResponder
{
    protected function ok($data = null, ?string $message = null, array $meta = [], int $status = 200)
    {
        $payload = ['success' => true];
        if (! is_null($data)) {
            $payload['data'] = $data;
        }
        if (! is_null($message)) {
            $payload['message'] = $message;
        }
        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function fail(string $code, string $message, int $status = 400, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => false,
            'error' => $message,
            'code' => $code,
        ], $extra), $status);
    }
}
