#!/bin/bash

# Command Palette Migration Script
# Usage: ./deploy-commands.sh [environment] [options]

set -e

ENVIRONMENT=${1:-local}
FORCE=${2:-false}
BACKUP=${3:-true}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Command Palette Deployment Script${NC}"
echo -e "${BLUE}Environment: ${ENVIRONMENT}${NC}"
echo -e "${BLUE}Force: ${FORCE}${NC}"
echo -e "${BLUE}Backup: ${BACKUP}${NC}"
echo ""

# Function to check if we're in the right directory
check_directory() {
    if [ ! -f "artisan" ]; then
        echo -e "${RED}Error: Not in Laravel project directory. artisan file not found.${NC}"
        exit 1
    fi
}

# Function to create backup
create_backup() {
    if [ "$BACKUP" = "true" ]; then
        echo -e "${YELLOW}Creating database backup...${NC}"
        timestamp=$(date +"%Y%m%d_%H%M%S")
        backup_file="backup_${ENVIRONMENT}_${timestamp}.sql"
        
        php artisan db:show > /dev/null 2>&1 && \
        php artisan db:backup --path=./backups/${backup_file} || \
        mysqldump -L --add-drop-table -h ${DB_HOST:-localhost} -u ${DB_USERNAME:-root} ${DB_DATABASE:-haasib} > ./backups/${backup_file} 2>/dev/null || \
        echo -e "${YELLOW}Warning: Could not create automatic backup${NC}"
        
        echo -e "${GREEN}Backup created: ${backup_file}${NC}"
    fi
}

# Function to run migrations
run_migrations() {
    echo -e "${YELLOW}Running Command Palette migrations...${NC}"
    
    if [ "$FORCE" = "true" ]; then
        php artisan migrate:force --path=database/migrations/2025_10_13_165707_create_commands_table.php
        php artisan migrate:force --path=database/migrations/2025_10_13_165755_create_command_executions_table.php
        php artisan migrate:force --path=database/migrations/2025_10_13_165756_create_command_history_table.php
        php artisan migrate:force --path=database/migrations/2025_10_13_165756_create_command_templates_table.php
        php artisan migrate:force --path=database/migrations/2025_10_14_120000_create_command_configurations_table.php
        php artisan migrate:force --path=database/migrations/2025_10_14_120100_create_command_analytics_table.php
        php artisan migrate:force --path=database/migrations/2025_10_14_120200_add_command_performance_indexes.php
    else
        php artisan migrate --path=database/migrations/2025_10_13_165707_create_commands_table.php
        php artisan migrate --path=database/migrations/2025_10_13_165755_create_command_executions_table.php
        php artisan migrate --path=database/migrations/2025_10_13_165756_create_command_history_table.php
        php artisan migrate --path=database/migrations/2025_10_13_165756_create_command_templates_table.php
        php artisan migrate --path=database/migrations/2025_10_14_120000_create_command_configurations_table.php
        php artisan migrate --path=database/migrations/2025_10_14_120100_create_command_analytics_table.php
        php artisan migrate --path=database/migrations/2025_10_14_120200_add_command_performance_indexes.php
    fi
    
    echo -e "${GREEN}Migrations completed successfully!${NC}"
}

# Function to run seeders
run_seeders() {
    echo -e "${YELLOW}Running Command Palette seeders...${NC}"
    
    if [ "$FORCE" = "true" ]; then
        php artisan db:seed --class=CommandSeeder --force
        php artisan db:seed --class=CommandConfigurationSeeder --force
    else
        php artisan db:seed --class=CommandSeeder
        php artisan db:seed --class=CommandConfigurationSeeder
    fi
    
    echo -e "${GREEN}Seeders completed successfully!${NC}"
}

# Function to clear caches
clear_caches() {
    echo -e "${YELLOW}Clearing application caches...${NC}"
    
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    
    # For production, also clear opcode cache
    if [ "$ENVIRONMENT" = "production" ]; then
        php artisan optimize:clear
    fi
    
    echo -e "${GREEN}Caches cleared!${NC}"
}

# Function to optimize for production
optimize_production() {
    if [ "$ENVIRONMENT" = "production" ]; then
        echo -e "${YELLOW}Optimizing for production...${NC}"
        
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan optimize
        
        echo -e "${GREEN}Production optimization completed!${NC}"
    fi
}

# Function to verify installation
verify_installation() {
    echo -e "${YELLOW}Verifying Command Palette installation...${NC}"
    
    # Check if tables exist
    php artisan tinker --execute="
        \$tables = ['commands', 'command_executions', 'command_history', 'command_templates', 'command_configurations', 'command_analytics'];
        foreach (\$tables as \$table) {
            if (!Schema::hasTable(\$table)) {
                echo \"Error: Table \$table does not exist\n\";
                exit(1);
            }
        }
        echo \"All Command Palette tables exist\n\";
    "
    
    # Check if seeders ran
    php artisan tinker --execute="
        \$count = App\Models\Command::count();
        if (\$count == 0) {
            echo \"Warning: No commands found in database\n\";
        } else {
            echo \"Found \$count commands\n\";
        }
    "
    
    echo -e "${GREEN}Installation verified!${NC}"
}

# Main execution
main() {
    check_directory
    create_backup
    run_migrations
    run_seeders
    clear_caches
    optimize_production
    verify_installation
    
    echo ""
    echo -e "${GREEN}Command Palette deployment completed successfully!${NC}"
    echo -e "${BLUE}Environment: ${ENVIRONMENT}${NC}"
    echo -e "${BLUE}Timestamp: $(date)${NC}"
}

# Handle script interruption
trap 'echo -e "\n${RED}Deployment interrupted!${NC}"; exit 1' INT

# Run main function
main "$@"