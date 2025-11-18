<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateLayoutCompliance extends Command
{
    protected $signature = 'layout:validate 
                          {--path=resources/js/Pages : Path to validate}
                          {--strict : Use strict validation mode}
                          {--json : Output results as JSON}';

    protected $description = 'Validate layout compliance against STRICT LAYOUT STANDARDS';

    protected array $violations = [];
    protected array $warnings = [];
    protected int $pagesChecked = 0;

    public function handle(): int
    {
        $this->info('🎨 Validating Layout Compliance Against STRICT STANDARDS');
        $this->info('═══════════════════════════════════════════════════════');

        $path = base_path($this->option('path'));
        
        if (!is_dir($path)) {
            $this->error("Path does not exist: {$path}");
            return 1;
        }

        $this->validatePages($path);
        $this->displayResults();

        return $this->violations ? 1 : 0;
    }

    protected function validatePages(string $path): void
    {
        $files = File::allFiles($path);
        $vueFiles = array_filter($files, fn($file) => $file->getExtension() === 'vue');

        foreach ($vueFiles as $file) {
            $this->validateFile($file);
        }
    }

    protected function validateFile($file): void
    {
        $this->pagesChecked++;
        $content = File::get($file->getPathname());
        $relativePath = str_replace(base_path(), '', $file->getPathname());

        // STRICT LAYOUT STANDARDS VALIDATION

        // 1. Mandatory Components Check
        $this->checkMandatoryComponents($content, $relativePath);

        // 2. Forbidden HTML Elements Check  
        $this->checkForbiddenElements($content, $relativePath);

        // 3. Layout Hierarchy Check
        $this->checkLayoutHierarchy($content, $relativePath);

        // 4. Permission Integration Check
        $this->checkPermissionIntegration($content, $relativePath);

        // 5. Import Order Check
        $this->checkImportOrder($content, $relativePath);

        // 6. Component Usage Check
        $this->checkComponentUsage($content, $relativePath);
        
        // 7. Blue-Whale Theme Compliance Check
        $this->checkThemeCompliance($content, $relativePath);
    }

    protected function checkMandatoryComponents(string $content, string $path): void
    {
        $mandatoryComponents = [
            'LayoutShell' => 'Every page MUST use LayoutShell',
            'UniversalPageHeader' => 'Every page MUST use UniversalPageHeader for single-row design',
        ];

        foreach ($mandatoryComponents as $component => $message) {
            if (!preg_match("/<{$component}/", $content)) {
                $this->addViolation($path, "MISSING_MANDATORY_COMPONENT", $message);
            }
        }

        // Check for single-row header structure
        if (preg_match('/UniversalPageHeader/', $content)) {
            if (!preg_match('/title=/', $content)) {
                $this->addWarning($path, "MISSING_TITLE_PROP", "UniversalPageHeader should have title prop");
            }
        }
    }

    protected function checkForbiddenElements(string $content, string $path): void
    {
        $forbiddenElements = [
            '<table' => 'Use PrimeVue DataTable instead of HTML table',
            '<button' => 'Use PrimeVue Button instead of HTML button',
            '<input' => 'Use PrimeVue InputText/InputNumber instead of HTML input',
            '<form' => 'Use Inertia form handling instead of HTML form',
            '<select' => 'Use PrimeVue Dropdown instead of HTML select',
            '<textarea' => 'Use PrimeVue Textarea instead of HTML textarea',
        ];

        foreach ($forbiddenElements as $element => $message) {
            if (preg_match("/{$element}/i", $content)) {
                $this->addViolation($path, "FORBIDDEN_HTML_ELEMENT", "{$message} (found: {$element})");
            }
        }
    }

    protected function checkLayoutHierarchy(string $content, string $path): void
    {
        // Check for mandatory grid structure
        if (preg_match('/content-grid-5-6/', $content)) {
            if (!preg_match('/main-content/', $content)) {
                $this->addViolation($path, "MISSING_MAIN_CONTENT", "Grid must have main-content div");
            }
            if (!preg_match('/sidebar-content/', $content)) {
                $this->addViolation($path, "MISSING_SIDEBAR_CONTENT", "Grid must have sidebar-content div");
            }
        }

        // Check for multiple header rows (forbidden)
        $headerMatches = preg_match_all('/<div[^>]*header[^>]*>/', $content);
        if ($headerMatches > 1) {
            $this->addViolation($path, "MULTIPLE_HEADERS", "Only single-row header allowed (UniversalPageHeader)");
        }
    }

    protected function checkPermissionIntegration(string $content, string $path): void
    {
        // Check if page has actions but no permission checks
        if (preg_match('/pageActions|defaultActions/', $content)) {
            if (!preg_match('/can\.|props\.can/', $content)) {
                $this->addViolation($path, "MISSING_PERMISSION_CHECKS", "Page actions must include permission checks");
            }
        }

        // Check if buttons exist without permission guards
        $buttonMatches = preg_match_all('/<Button[^>]*>/', $content);
        $permissionChecks = preg_match_all('/v-if.*can\./', $content);
        
        if ($buttonMatches > $permissionChecks && $buttonMatches > 2) {
            $this->addWarning($path, "INSUFFICIENT_PERMISSION_GUARDS", "Consider adding permission checks to buttons");
        }
    }

    protected function checkImportOrder(string $content, string $path): void
    {
        // Extract import section
        if (preg_match('/<script setup>(.*?)<\/script>/s', $content, $matches)) {
            $scriptContent = $matches[1];
            $imports = [];
            
            preg_match_all('/import.*from [\'"]([^\'"]*)[\'"]/', $scriptContent, $importMatches);
            
            if (!empty($importMatches[1])) {
                $imports = $importMatches[1];
                
                // Check import order: Vue → Inertia → PrimeVue → App → Utils
                $vueImports = array_filter($imports, fn($import) => str_contains($import, 'vue'));
                $inertiaImports = array_filter($imports, fn($import) => str_contains($import, '@inertiajs'));
                $primeVueImports = array_filter($imports, fn($import) => str_contains($import, 'primevue'));
                $appImports = array_filter($imports, fn($import) => str_contains($import, '@/'));
                
                // Basic order validation - could be enhanced
                if (!empty($vueImports) && !empty($primeVueImports)) {
                    $firstVue = array_search(current($vueImports), $imports);
                    $firstPrime = array_search(current($primeVueImports), $imports);
                    
                    if ($firstVue > $firstPrime) {
                        $this->addWarning($path, "IMPORT_ORDER", "Vue imports should come before PrimeVue imports");
                    }
                }
            }
        }
    }

    protected function checkComponentUsage(string $content, string $path): void
    {
        // Check for Options API usage (forbidden)
        if (preg_match('/export default \{/', $content)) {
            $this->addViolation($path, "FORBIDDEN_OPTIONS_API", "Use Composition API (<script setup>) only");
        }

        // Check for proper PrimeVue component usage
        if (preg_match('/<DataTable/', $content)) {
            if (!preg_match('/dataKey=/', $content)) {
                $this->addWarning($path, "MISSING_DATA_KEY", "DataTable should have dataKey prop");
            }
        }

    }

    protected function checkThemeCompliance(string $content, string $path): void
    {
        // Check for blue-whale theme usage on Sidebar
        if (preg_match('/<Sidebar/', $content)) {
            if (!preg_match('/theme=["\']blu-whale["\']/', $content)) {
                $this->addViolation($path, "MISSING_BLU_WHALE_THEME", "Sidebar must use blu-whale theme");
            }
        }

        // Check for data-theme attributes on root elements
        if (preg_match('/App\.vue|Layout.*\.vue/i', $path)) {
            if (!preg_match('/data-theme=["\'][^"\']*blue-whale[^"\']*["\']/', $content)) {
                $this->addViolation($path, "MISSING_THEME_ATTRIBUTE", "Root components must have data-theme with blue-whale");
            }
        }

        // Check for hard-coded colors (hex codes)
        if (preg_match('/#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3}/', $content)) {
            $this->addViolation($path, "HARD_CODED_COLORS", "Use CSS custom properties instead of hard-coded colors");
        }

        // Check for non-blue-whale theme usage
        $forbiddenThemes = [
            'theme=["\']default["\']',
            'theme=["\']material["\']',
            'theme=["\']bootstrap["\']',
            'theme=["\']custom["\']',
            'data-theme=["\'](?!.*blue-whale).*["\']'
        ];

        foreach ($forbiddenThemes as $pattern) {
            if (preg_match("/$pattern/", $content)) {
                $this->addViolation($path, "FORBIDDEN_THEME_USAGE", "Only blue-whale theme is allowed");
            }
        }

        // Check for CSS custom property usage
        if (preg_match('/style=["\'][^"\']*color:\s*(?!var\(--)/i', $content)) {
            $this->addWarning($path, "INLINE_STYLE_COLORS", "Consider using CSS custom properties for colors");
        }

        // Check for useTheme composable in components with theme switching
        if (preg_match('/isDark|theme.*toggle|light.*mode|dark.*mode/i', $content)) {
            if (!preg_match('/useTheme|from.*useTheme/', $content)) {
                $this->addWarning($path, "MISSING_THEME_COMPOSABLE", "Components with theme logic should use useTheme composable");
            }
        }
    }

    protected function addViolation(string $path, string $code, string $message): void
    {
        $this->violations[] = [
            'path' => $path,
            'code' => $code,
            'message' => $message,
            'severity' => 'ERROR'
        ];
    }

    protected function addWarning(string $path, string $code, string $message): void
    {
        $this->warnings[] = [
            'path' => $path,
            'code' => $code,
            'message' => $message,
            'severity' => 'WARNING'
        ];
    }

    protected function displayResults(): void
    {
        if ($this->option('json')) {
            $this->outputJson();
            return;
        }

        $this->info("\n📊 VALIDATION RESULTS");
        $this->info("══════════════════════");
        $this->info("Pages checked: {$this->pagesChecked}");
        $this->info("Violations: " . count($this->violations));
        $this->info("Warnings: " . count($this->warnings));

        if ($this->violations) {
            $this->error("\n❌ LAYOUT STANDARD VIOLATIONS:");
            foreach ($this->violations as $violation) {
                $this->error("  {$violation['path']}");
                $this->error("    └─ {$violation['code']}: {$violation['message']}");
            }
        }

        if ($this->warnings) {
            $this->warn("\n⚠️  LAYOUT WARNINGS:");
            foreach ($this->warnings as $warning) {
                $this->warn("  {$warning['path']}");
                $this->warn("    └─ {$warning['code']}: {$warning['message']}");
            }
        }

        if (!$this->violations && !$this->warnings) {
            $this->info("\n✅ ALL PAGES COMPLY WITH STRICT LAYOUT STANDARDS!");
            $this->info("🎉 Zero deviation detected - migration ready!");
        }

        $this->info("\n📋 COMPLIANCE SCORE: " . $this->calculateComplianceScore() . "%");
    }

    protected function outputJson(): void
    {
        $result = [
            'pages_checked' => $this->pagesChecked,
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'compliance_score' => $this->calculateComplianceScore(),
            'passed' => empty($this->violations)
        ];

        echo json_encode($result, JSON_PRETTY_PRINT);
    }

    protected function calculateComplianceScore(): float
    {
        if ($this->pagesChecked === 0) {
            return 100;
        }

        $totalIssues = count($this->violations) + (count($this->warnings) * 0.5);
        $maxPossibleIssues = $this->pagesChecked * 10; // Rough estimate
        
        $score = max(0, 100 - ($totalIssues / $maxPossibleIssues * 100));
        
        return round($score, 1);
    }
}