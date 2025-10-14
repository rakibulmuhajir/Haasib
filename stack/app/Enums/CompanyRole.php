<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    /**
     * Get the display name for the role.
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Accountant => 'Accountant',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Get the description for the role.
     */
    public function getDescription(): string
    {
        return match($this) {
            self::Owner => 'Full control over company settings, users, and data',
            self::Admin => 'Manage company settings, users, and most operations',
            self::Accountant => 'Manage financial data, accounts, and reports',
            self::Viewer => 'Read-only access to company data',
        };
    }

    /**
     * Check if this role can manage users.
     */
    public function canManageUsers(): bool
    {
        return match($this) {
            self::Owner, self::Admin => true,
            self::Accountant, self::Viewer => false,
        };
    }

    /**
     * Check if this role can manage company settings.
     */
    public function canManageSettings(): bool
    {
        return match($this) {
            self::Owner, self::Admin => true,
            self::Accountant, self::Viewer => false,
        };
    }

    /**
     * Check if this role can manage financial data.
     */
    public function canManageFinancialData(): bool
    {
        return match($this) {
            self::Owner, self::Admin, self::Accountant => true,
            self::Viewer => false,
        };
    }

    /**
     * Check if this role can invite users.
     */
    public function canInviteUsers(): bool
    {
        return match($this) {
            self::Owner, self::Admin => true,
            self::Accountant, self::Viewer => false,
        };
    }

    /**
     * Get all available roles as options for select fields.
     */
    public static function getOptions(): array
    {
        return [
            self::Owner->value => self::Owner->getDisplayName(),
            self::Admin->value => self::Admin->getDisplayName(),
            self::Accountant->value => self::Accountant->getDisplayName(),
            self::Viewer->value => self::Viewer->getDisplayName(),
        ];
    }

    /**
     * Get roles that can be assigned by the current role.
     */
    public function getAssignableRoles(): array
    {
        return match($this) {
            self::Owner => [
                self::Owner,
                self::Admin,
                self::Accountant,
                self::Viewer,
            ],
            self::Admin => [
                self::Accountant,
                self::Viewer,
            ],
            self::Accountant, self::Viewer => [],
        };
    }

    /**
     * Check if this role can assign another role.
     */
    public function canAssignRole(CompanyRole $role): bool
    {
        return in_array($role, $this->getAssignableRoles());
    }

    /**
     * Get the hierarchical level of the role (higher number = more permissions).
     */
    public function getLevel(): int
    {
        return match($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Accountant => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Check if this role has equal or higher level than another role.
     */
    public function hasEqualOrHigherLevelThan(CompanyRole $role): bool
    {
        return $this->getLevel() >= $role->getLevel();
    }

    /**
     * Validate if the role value is valid.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, array_column(self::cases(), 'value'));
    }
}