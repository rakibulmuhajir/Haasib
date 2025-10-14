<?php

namespace App\Services;

use App\Models\Command;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CommandSuggestionService
{
    private CommandRegistryService $commandRegistry;

    private array $cache = [];

    private int $cacheTimeout = 300; // 5 minutes

    public function __construct(CommandRegistryService $commandRegistry)
    {
        $this->commandRegistry = $commandRegistry;
    }

    public function getSuggestions(Company $company, User $user, string $input, array $context = []): array
    {
        $cacheKey = $this->generateCacheKey($company->id, $user->id, $input, $context);

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($company, $user, $input, $context) {
            return $this->generateSuggestions($company, $user, $input, $context);
        });
    }

    public function getCommandByName(Company $company, User $user, string $commandName): ?Command
    {
        $command = $this->commandRegistry->getCommandByName($company, $commandName);

        if (! $command || ! $command->userHasPermission($user)) {
            return null;
        }

        return $command;
    }

    private function generateSuggestions(Company $company, User $user, string $input, array $context): array
    {
        $input = strtolower(trim($input));

        if (strlen($input) < 2) {
            return [];
        }

        $availableCommands = $this->commandRegistry->getAvailableCommands($company)
            ->filter(fn ($command) => $command->userHasPermission($user));

        $suggestions = [];

        // Exact matches first
        foreach ($availableCommands as $command) {
            $score = $this->calculateExactMatchScore($input, $command, $context);
            if ($score > 0) {
                $suggestions[] = $this->createSuggestion($command, $score, 'exact');
            }
        }

        // Fuzzy matches
        foreach ($availableCommands as $command) {
            if ($this->isAlreadySuggested($suggestions, $command->id)) {
                continue;
            }

            $score = $this->calculateFuzzyScore($input, $command, $context);
            if ($score > 0.3) { // Threshold for fuzzy matching
                $suggestions[] = $this->createSuggestion($command, $score, 'fuzzy');
            }
        }

        // Sort by confidence score and limit to top 10
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestions, 0, 10);
    }

    private function calculateExactMatchScore(string $input, Command $command, array $context): float
    {
        $name = strtolower($command->name);
        $description = strtolower($command->description);

        // Exact name match
        if ($input === $name) {
            return 1.0;
        }

        // Name starts with input
        if (str_starts_with($name, $input)) {
            return 0.9;
        }

        // Name contains input
        if (str_contains($name, $input)) {
            return 0.8;
        }

        // Description contains input
        if (str_contains($description, $input)) {
            return 0.6;
        }

        // Word boundaries in name
        $words = explode(' ', str_replace(['.', '-', '_'], ' ', $name));
        foreach ($words as $word) {
            if (str_starts_with($word, $input)) {
                return 0.7;
            }
        }

        return 0.0;
    }

    private function calculateFuzzyScore(string $input, Command $command, array $context): float
    {
        $name = strtolower($command->name);
        $description = strtolower($command->description);

        // Levenshtein distance for name
        $nameScore = $this->calculateLevenshteinScore($input, $name);

        // Soundex similarity for name
        $soundexScore = $this->calculateSoundexScore($input, $name);

        // Context boost
        $contextBoost = $this->calculateContextBoost($command, $context);

        // Recent usage boost (would need to implement recent usage tracking)
        $usageBoost = $this->calculateUsageBoost($command);

        return max($nameScore, $soundexScore) + $contextBoost + $usageBoost;
    }

    private function calculateLevenshteinScore(string $input, string $target): float
    {
        $distance = levenshtein($input, $target);
        $maxLength = max(strlen($input), strlen($target));

        if ($maxLength === 0) {
            return 0.0;
        }

        $similarity = 1 - ($distance / $maxLength);

        return max(0, $similarity * 0.7); // Scale down fuzzy matches
    }

    private function calculateSoundexScore(string $input, string $target): float
    {
        $inputSoundex = soundex($input);
        $targetSoundex = soundex($target);

        if ($inputSoundex === $targetSoundex) {
            return 0.5;
        }

        return 0.0;
    }

    private function calculateContextBoost(Command $command, array $context): float
    {
        $boost = 0.0;

        // Page context boost
        if (isset($context['page'])) {
            $page = strtolower($context['page']);
            $name = strtolower($command->name);

            if (str_contains($name, $page)) {
                $boost += 0.1;
            }
        }

        // Recent action context boost
        if (isset($context['recent_actions'])) {
            foreach ($context['recent_actions'] as $action) {
                if ($action === $command->name) {
                    $boost += 0.05;
                }
            }
        }

        return min($boost, 0.2); // Cap context boost at 0.2
    }

    private function calculateUsageBoost(Command $command): float
    {
        // This would typically query recent usage statistics
        // For now, return a small random boost to simulate varied usage
        return rand(0, 10) / 100; // 0 to 0.1
    }

    private function createSuggestion(Command $command, float $confidence, string $matchType): array
    {
        return [
            'id' => $command->id,
            'name' => $command->name,
            'description' => $command->description,
            'parameters' => $command->parameters,
            'required_permissions' => $command->required_permissions,
            'confidence' => round($confidence, 2),
            'match_type' => $matchType,
            'highlighted_name' => $this->highlightMatch($command->name, $matchType),
            'estimated_duration' => $this->estimateDuration($command),
        ];
    }

    private function highlightMatch(string $name, string $matchType): string
    {
        // Simple highlighting logic - could be enhanced
        return $name;
    }

    private function estimateDuration(Command $command): string
    {
        // Estimate based on command complexity
        $parameterCount = count($command->parameters);

        if ($parameterCount === 0) {
            return '< 1s';
        }

        if ($parameterCount <= 2) {
            return '1-3s';
        }

        return '3-10s';
    }

    private function isAlreadySuggested(array $suggestions, string $commandId): bool
    {
        foreach ($suggestions as $suggestion) {
            if ($suggestion['id'] === $commandId) {
                return true;
            }
        }

        return false;
    }

    private function generateCacheKey(string $companyId, string $userId, string $input, array $context): string
    {
        $contextHash = md5(serialize($context));

        return "command_suggestions:{$companyId}:{$userId}:".md5($input.$contextHash);
    }

    public function clearCacheForUser(Company $company, User $user): void
    {
        $pattern = "command_suggestions:{$company->id}:{$user->id}:*";

        // This is a simplified approach - in production you might use a more sophisticated cache clearing strategy
        Cache::flush(); // For now, clear all suggestions cache
    }
}
