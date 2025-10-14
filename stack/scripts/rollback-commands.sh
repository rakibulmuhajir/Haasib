#!/bin/bash

# Command Palette Rollback Script
# Usage: ./rollback-commands.sh [environment] [steps]

set -e

ENVIRONMENT=${1:-local}
STEPS=${2:-all}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Command Palette Rollback Script${NC}"
echo -e "${BLUE}Environment: ${ENVIRONMENT}${NC}"
echo -e "${BLUE}Steps: ${STEPS}${NC}"
echo ""

# Function to check if we're in the right directory
check_directory() {
    if [ ! -f "artisan" ]; then
        echo -e "${RED}Error: Not in Laravel project directory. artisan file not found.${NC}"
        exit 1
    fi
}

# Function to create backup before rollback
create_rollback_backup() {
    echo -e "${YELLOW}Creating pre-rollback backup...${NC}"
    timestamp=$(date +"%Y%m%d_%H%M%S")
    backup_file="rollback_backup_${ENVIRONMENT}_${timestamp}.sql"
    
    php artisan db:show > /dev/null 2>&1 && \
    php artisan db:backup --path=./backups/${backup_file} || \
    mysqldump -L --add-drop-table -h ${DB_HOST:-localhost} -u ${DB_USERNAME:-root} ${DB_DATABASE:-haasib} > ./backups/${backup_file} 2>/dev/null || \
    echo -e "${YELLOW}Warning: Could not create automatic backup${NC}"
    
    echo -e "${GREEN}Rollback backup created: ${backup_file}${NC}"
}

# Function to rollback specific migrations
rollback_migrations() {
    echo -e "${YELLOW}Rolling back Command Palette migrations...${NC}"
    
    # Define migrations in reverse order
    declare -a migrations=(
        "2025_10_14_120200_add_command_performance_indexes"
        "2025_10_14_120100_create_command_analytics_table"
        "2025_10_14_120000_create_command_configurations_table"
        "2025_10_13_165756_create_command_templates_table"
        "2025_10_13_165756_create_command_history_table"
        "2025_10_13_165755_create_command_executions_table"
        "2025_10_13_165707_create_commands_table"
    )
    
    # Rollback based on steps requested
    case $STEPS in
        "all")
            for migration in "${migrations[@]}"; do
                echo -e "${YELLOW}Rolling back: ${migration}${NC}"
                php artisan migrate:rollback --path=database/migrations/${migration}.php
            done
            ;;
        "analytics")
            echo -e "${YELLOW}Rolling back analytics tables...${NC}"
            php artisan migrate:rollback --path=database/migrations/2025_10_14_120100_create_command_analytics_table.php
            ;;
        "config")
            echo -e "${YELLOW}Rolling back configuration table...${NC}"
            php artisan migrate:rollback --path=database/migrations/2025_10_14_120000_create_command_configurations_table.php
            ;;
        "indexes")
            echo -e "${YELLOW}Rolling back performance indexes...${NC}"
            php artisan migrate:rollback --path=database/migrations/2025_10_14_120200_add_command_performance_indexes.php
            ;;
        *)
            echo -e "${RED}Invalid rollback steps: ${STEPS}${NC}"
            echo "Valid options: all, analytics, config, indexes"
            exit 1
            ;;
    esac
    
    echo -e "${GREEN}Rollback completed successfully!${NC}"
}

# Function to clear caches
clear_caches() {
    echo -e "${YELLOW}Clearing application caches...${NC}"
    
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    
    echo -e "${GREEN}Caches cleared!${NC}"
}

# Function to verify rollback
verify_rollback() {
    echo -e "${YELLOW}Verifying rollback...${NC}"
    
    # Check if command tables still exist
    php artisan tinker --execute="
        \$tables = ['commands', 'command_executions', 'command_history', 'command_templates', 'command_configurations', 'command_analytics'];
        \$existing = [];
        foreach (\$tables as \$table) {
            if (Schema::hasTable(\$table)) {
                \$existing[] = \$table;
            }
        }
        
        if (count(\$existing) > 0) {
            echo 'Warning: Some command tables still exist: ' . implode(', ', \$existing) . \"\n\";
        } else {
            echo 'All Command Palette tables have been removed\n';
        }
    "
    
    echo -e "${GREEN}Rollback verified!${NC}"
}

# Function to show rollback summary
show_summary() {
    echo ""
    echo -e "${BLUE}Rollback Summary${NC}"
    echo -e "${BLUE}Environment: ${ENVIRONMENT}${NC}"
    echo -e "${BLUE}Steps: ${STEPS}${NC}"
    echo -e "${BLUE}Timestamp: $(date)${NC}"
    echo ""
    echo -e "${YELLOW}Note: Database backups are available in ./backups/${NC}"
    echo -e "${YELLOW}To restore: php artisan db:restore --path=./backups/[backup_file]${NC}"
}

# Main execution
main() {
    check_directory
    create_rollback_backup
    rollback_migrations
    clear_caches
    verify_rollback
    show_summary
    
    echo -e "${GREEN}Command Palette rollback completed!${NC}"
}

# Handle script interruption
trap 'echo -e "\n${RED}Rollback interrupted!${NC}"; exit 1' INT

# Run main function
main "$@"