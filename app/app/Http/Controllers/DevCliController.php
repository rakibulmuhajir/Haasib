<?php
// app/Http/Controllers/DevCliController.php
namespace App\Http\Controllers;

use App\Support\CommandBus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DevCliController extends Controller
{
    public function index()
    {
        abort_unless(config('app.dev_console_enabled'), 403);
        return Inertia::render('DevCli', ['examples' => [
            'help',
            'user:add --name="Jane Doe" --email=jane@example.com --password=secret',
            'company:add --name="Acme"',
            'company:assign --email=jane@example.com --company=Acme --role=admin',
            'company:unassign --email=jane@example.com --company=Acme',
            'user:delete --email=jane@example.com',
            'company:delete --company=Acme',
            'bootstrap:demo --name="Founder" --email=founder@example.com --companies="Acme,BetaCo"',
        ]]);
    }

    public function execute(Request $request)
    {
        abort_unless(config('app.dev_console_enabled'), 403);
        $cmd = trim((string) $request->input('command'));
        if ($cmd === '' || $cmd === 'help') {
            return response()->json(['ok'=>true,'help'=>true]);
        }
        [$name, $opts] = $this->parse($request->string('command'));
        $name = $this->normalize($name);
        try {
            $user = $request->user();

            if ($name === 'bootstrap:demo') {
                $companies = array_filter(array_map('trim', explode(',', $opts['companies'] ?? '')));
                $summary = CommandBus::dispatch('user.create', [
                    'name' => $opts['name'] ?? 'Founder',
                    'email' => $opts['email'] ?? '',
                    'password' => $opts['password'] ?? null,
                ], $user);
                foreach ($companies as $co) {
                    CommandBus::dispatch('company.create', ['name' => $co], $user);
                    CommandBus::dispatch('company.assign', [
                        'email' => $opts['email'] ?? '',
                        'company' => $co,
                        'role' => $opts['role'] ?? 'owner',
                    ], $user);
                }
                return response()->json(['ok'=>true, 'output'=>['summary'=>$summary, 'companies'=>$companies]]);
            }

            $action = str_replace(':', '.', $name);
            if (str_ends_with($action, '.add')) {
                $action = str_replace('.add', '.create', $action);
            }

            $valid = [
                'user.create', 'user.delete', 'company.create', 'company.delete', 'company.assign', 'company.unassign',
            ];
            if (!in_array($action, $valid, true)) {
                return response()->json(['ok'=>false,'error'=>"Unknown command: {$name}"], 422);
            }

            if ($action === 'company.assign') {
                $opts['role'] = $opts['role'] ?? 'viewer';
            }
            if ($action === 'user.create') {
                $opts['name'] = $opts['name'] ?? 'User';
            }

            $result = CommandBus::dispatch($action, $opts, $user);
            return response()->json(['ok'=>true, 'output'=>$result]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false, 'error'=>$e->getMessage()], 422);
        }
    }

    private function parse(string $cmd): array {
        $parts = preg_split('/\s+/', trim($cmd)); $first = array_shift($parts) ?? '';
        $opts = [];
        foreach ($parts as $p) if (preg_match('/--([^=\s]+)=?(.*)?/',$p,$m)) $opts[$m[1]] = trim($m[2] ?? '', '"\'');
        return [$first . ' ' . ($parts[0] ?? ''), $opts]; // keep first two tokens for normalization
    }

    private function normalize(string $twoTokens): string {
        [$t1, $t2] = array_pad(explode(' ', trim($twoTokens), 2), 2, '');
        $entity = match (true) {
            str_starts_with($t1, 'u') => 'user',
            str_starts_with($t1, 'c') => 'company',
            str_starts_with($t1, 'boot') => 'bootstrap',
            default => str_contains($t1, ':') ? explode(':', $t1, 2)[0] : $t1,
        };
        if ($entity === 'bootstrap') return 'bootstrap:demo';
        $action = match (true) {
            str_starts_with($t2, 'ass') => 'assign',
            str_starts_with($t2, 'unass') => 'unassign',
            str_starts_with($t2, 'del') || str_starts_with($t2, 'rm') => 'delete',
            str_starts_with($t2, 'add') => 'add',
            default => (str_contains($t1, ':') ? explode(':', $t1, 2)[1] ?? '' : ''),
        };
        return trim($entity . ':' . $action, ':');
    }
}
