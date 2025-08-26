<?php
// app/Http/Controllers/DevCliController.php
namespace App\Http\Controllers;

use App\Support\DevOpsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DevCliController extends Controller
{
    public function index()
    {
        abort_unless(config('app.dev_console_enabled'), 403);
        return Inertia::render('DevCli', ['examples' => [
            'help',
            'haasib:user:add --name="Jane Doe" --email=jane@example.com --password=secret',
            'haasib:company:add --name="Acme"',
            'haasib:company:assign --email=jane@example.com --company=Acme --role=admin',
            'haasib:company:unassign --email=jane@example.com --company=Acme',
            'haasib:user:delete --email=jane@example.com',
            'haasib:company:delete --company=Acme',
            'haasib:bootstrap:demo --name="Founder" --email=founder@example.com --companies="Acme,BetaCo"',
        ]]);
    }

    public function execute(Request $request)
    {
        abort_unless(config('app.dev_console_enabled'), 403);
        $cmd = trim((string) $request->input('command'));
        if ($cmd === '' || $cmd === 'help') {
            return response()->json(['ok'=>true,'help'=>true]);
        }
        // Parse "verb --k=v" into name + options
        [$name, $opts] = $this->parse($cmd);
        try {
            // Prefer calling services directly instead of Artisan::call for structured output
            $ops = app(DevOpsService::class);
            $map = [
                'haasib:user:add'        => fn() => $ops->createUser($opts['name'] ?? 'User', $opts['email'] ?? '', $opts['password'] ?? null),
                'haasib:company:add'     => fn() => $ops->createCompany($opts['name'] ?? ''),
                'haasib:company:assign'  => fn() => $ops->assignCompany($opts['email'] ?? '', $opts['company'] ?? '', $opts['role'] ?? 'viewer'),
                'haasib:company:unassign'=> fn() => $ops->unassignCompany($opts['email'] ?? '', $opts['company'] ?? ''),
                'haasib:user:delete'     => fn() => $ops->deleteUser($opts['email'] ?? ''),
                'haasib:company:delete'  => fn() => $ops->deleteCompany($opts['company'] ?? ''),
                'haasib:bootstrap:demo'  => fn() => $ops->createUserAndCompanies($opts['name'] ?? 'Founder', $opts['email'] ?? '', array_filter(array_map('trim', explode(',', $opts['companies'] ?? ''))), $opts['role'] ?? 'owner'),
            ];
            if (!isset($map[$name])) {
                return response()->json(['ok'=>false, 'error'=>"Unknown command: {$name}"], 422);
            }
            return response()->json(['ok'=>true, 'output'=>$map[$name]()]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false, 'error'=>$e->getMessage()], 422);
        }
    }

    private function parse(string $cmd): array
    {
        $parts = preg_split('/\s+/', $cmd); $name = array_shift($parts); $opts = [];
        foreach ($parts as $p) if (preg_match('/--([^=\s]+)=?(.*)?/',$p,$m)) $opts[$m[1]] = trim($m[2] ?? '', '"\'');
        return [$name, $opts];
    }
}
