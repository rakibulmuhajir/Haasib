<?php

use App\Models\Command;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command palette can be activated with Ctrl+K', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertSeeIn(['text', 'title'], 'Dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette with Ctrl+K
    $page->keys(['Control', 'k']);

    // Wait for the command palette to appear
    $page->waitFor('.p-dialog-content', 1000)
        ->assertSee('Type a command or search...')
        ->assertVisible('input#command-palette-input');
});

test('command palette shows suggestions when typing', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    // Create some test commands
    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'invoice.create',
        'description' => 'Create a new invoice',
        'is_active' => true,
    ]);

    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'customer.create',
        'description' => 'Create a new customer',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Type in search
    $page->type('input#command-palette-input', 'invoice');

    // Wait for suggestions to appear
    $page->waitForText('Create a new invoice', 500);

    $page->assertSee('Create a new invoice')
        ->assertSee('invoice.create');
});

test('keyboard navigation works in command palette', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    // Create test commands
    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'invoice.create',
        'description' => 'Create a new invoice',
        'is_active' => true,
    ]);

    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'customer.create',
        'description' => 'Create a new customer',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Type to get suggestions
    $page->type('input#command-palette-input', 'c');
    $page->waitFor(500);

    // Test arrow navigation
    $page->keys('ArrowDown');
    $page->keys('ArrowUp');
    $page->keys('ArrowDown');

    // Press Enter to select
    $page->keys('Enter');

    // Command palette should close after selection
    $page->waitUntilMissing('.p-dialog-content', 2000);
});

test('command palette can be closed with Escape', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Close with Escape
    $page->keys('Escape');

    // Command palette should be hidden
    $page->waitUntilMissing('.p-dialog-content', 2000);
});

test('command palette shows view tabs and allows switching', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Check that tabs are visible
    $page->assertSee('Commands')
        ->assertSee('History')
        ->assertSee('Templates');

    // Switch to History view
    $page->click('button:contains("History")');
    $page->waitFor(300);
    $page->assertSee('No command history found');

    // Switch to Templates view
    $page->click('button:contains("Templates")');
    $page->waitFor(300);
    $page->assertSee('No templates found');

    // Switch back to Commands
    $page->click('button:contains("Commands")');
    $page->waitFor(300);
});

test('command palette shows execution feedback', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    // Create a test command that will succeed
    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'test.success',
        'description' => 'Test successful command',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Type and select command
    $page->type('input#command-palette-input', 'test.success');
    $page->waitForText('Test successful command', 500);
    $page->keys('Enter');

    // Wait for success message
    $page->waitForText('Command executed successfully!', 2000);
    $page->assertSee('.p-message.p-message-success');

    // Palette should close automatically
    $page->waitUntilMissing('.p-dialog-content', 3000);
});

test('command palette shows error feedback for failed commands', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    // Create a test command that will fail
    Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'test.failure',
        'description' => 'Test failed command',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Type and select command
    $page->type('input#command-palette-input', 'test.failure');
    $page->waitForText('Test failed command', 500);
    $page->keys('Enter');

    // Wait for error message
    $page->waitForText('Command execution failed', 2000);
    $page->assertSee('.p-message.p-message-error');
});

test('command palette is accessible with screen readers', function () {
    $user = User::factory()->create();
    $company = Company::Factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Check for accessibility attributes
    $page->assertElementExists('input#command-palette-input[aria-label]')
        ->assertElementExists('div[role="dialog"]')
        ->assertElementExists('input#command-palette-input[autocomplete="off"]');
});

test('command palette works in dark mode', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Switch to dark mode (assuming there's a toggle)
    // For this test, we'll simulate dark mode by adding the class
    $page->evaluate('document.documentElement.classList.add("dark")');

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Check that dark mode styles are applied
    $page->assertClassContains('.p-dialog', 'dark:bg-gray-800');

    // Type and verify dark mode input
    $page->type('input#command-palette-input', 'test');
    $page->assertClassContains('input#command-palette-input', 'dark:bg-gray-700');
});

test('command palette has proper keyboard shortcuts displayed', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Activate command palette
    $page->keys(['Control', 'k']);
    $page->waitFor('.p-dialog-content', 1000);

    // Check that keyboard shortcuts are displayed
    $page->assertSee('Ctrl+K to open')
        ->assertSee('↑↓ to navigate')
        ->assertSee('Enter to select')
        ->assertSee('Tab to switch views')
        ->assertSee('Esc to close');

    // Check kbd elements for proper styling
    $page->assertElementExists('kbd');
});

test('command palette prevents default browser behavior', function () {
    $user = User::Factory()->create();
    $company = Company::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($user);

    $page = $this->visit('/dashboard')
        ->assertNoJavascriptErrors();

    // Focus on an input field first
    $page->focus('body'); // Remove focus from any existing elements

    // Press Ctrl+K - should open command palette, not browser search
    $page->keys(['Control', 'k']);

    // Wait for command palette to appear instead of browser search
    $page->waitFor('.p-dialog-content', 1000)
        ->assertSee('Type a command or search...')
        ->assertNot('find') // Should not trigger browser find dialog
        ->assertNot('Google Chrome'); // Should not trigger browser search
});
