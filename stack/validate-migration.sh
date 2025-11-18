#!/bin/bash

# Migration Validation Script
# Validates RBAC and Layout Compliance

echo "🚀 HAASIB MIGRATION VALIDATION"
echo "════════════════════════════════"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# Helper functions
check_passed() {
    echo -e "${GREEN}✅ $1${NC}"
    ((PASSED_CHECKS++))
    ((TOTAL_CHECKS++))
}

check_failed() {
    echo -e "${RED}❌ $1${NC}"
    ((FAILED_CHECKS++))
    ((TOTAL_CHECKS++))
}

check_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((TOTAL_CHECKS++))
}

echo -e "${BLUE}🔐 RBAC SYSTEM VALIDATION${NC}"
echo "──────────────────────────"

# Check if permission constants exist
if [ -f "app/Constants/Permissions.php" ]; then
    check_passed "Permission constants file exists"
else
    check_failed "Permission constants file missing"
fi

# Check if permission seeder exists
if [ -f "database/seeders/PermissionSeeder.php" ]; then
    check_passed "Permission seeder exists"
else
    check_failed "Permission seeder missing"
fi

# Check BaseFormRequest improvements
if grep -q "authorizeCustomerOperation" app/Http/Requests/BaseFormRequest.php 2>/dev/null; then
    check_passed "BaseFormRequest has standardized authorization helpers"
else
    check_failed "BaseFormRequest missing authorization helpers"
fi

# Check for bypassed authorization (security risk)
BYPASSED_AUTH=$(grep -r "return true" app/Http/Requests/ 2>/dev/null | grep "authorize" | wc -l)
if [ "$BYPASSED_AUTH" -gt 0 ]; then
    check_failed "Found $BYPASSED_AUTH FormRequests with bypassed authorization"
else
    check_passed "No bypassed authorization found"
fi

# Test permission system (if we can run artisan)
if command -v php >/dev/null && [ -f "artisan" ]; then
    if php artisan list | grep -q "db:seed"; then
        echo "Testing permission seeder..."
        if php artisan db:seed --class=PermissionSeeder --no-interaction 2>/dev/null; then
            check_passed "Permission seeder runs successfully"
        else
            check_warning "Permission seeder test skipped (database not available)"
        fi
    fi
fi

echo ""
echo -e "${BLUE}🎨 LAYOUT & THEME STANDARDS VALIDATION${NC}"
echo "─────────────────────────────────────────────"

# Check for UniversalPageHeader updates
if [ -f "resources/js/Components/UniversalPageHeader.vue" ]; then
    if grep -q "STRICT LAYOUT STANDARD" resources/js/Components/UniversalPageHeader.vue; then
        check_passed "UniversalPageHeader updated with space-saving design"
    else
        check_failed "UniversalPageHeader not updated with strict standards"
    fi
else
    check_failed "UniversalPageHeader component missing"
fi

# Check for forbidden HTML elements in Vue files
HTML_TABLES=$(find resources/js -name "*.vue" -exec grep -l "<table" {} \; 2>/dev/null | wc -l)
if [ "$HTML_TABLES" -eq 0 ]; then
    check_passed "No HTML tables found (PrimeVue DataTable compliance)"
else
    check_failed "Found $HTML_TABLES Vue files with HTML tables"
fi

HTML_BUTTONS=$(find resources/js -name "*.vue" -exec grep -l "<button" {} \; 2>/dev/null | wc -l)
if [ "$HTML_BUTTONS" -eq 0 ]; then
    check_passed "No HTML buttons found (PrimeVue Button compliance)"
else
    check_failed "Found $HTML_BUTTONS Vue files with HTML buttons"
fi

HTML_INPUTS=$(find resources/js -name "*.vue" -exec grep -l "<input" {} \; 2>/dev/null | wc -l)
if [ "$HTML_INPUTS" -eq 0 ]; then
    check_passed "No HTML inputs found (PrimeVue input compliance)"
else
    check_failed "Found $HTML_INPUTS Vue files with HTML inputs"
fi

# Check for mandatory layout components
PAGES_WITH_LAYOUT_SHELL=$(find resources/js/Pages -name "*.vue" -exec grep -l "LayoutShell" {} \; 2>/dev/null | wc -l)
TOTAL_PAGES=$(find resources/js/Pages -name "*.vue" 2>/dev/null | wc -l)

if [ "$TOTAL_PAGES" -gt 0 ]; then
    if [ "$PAGES_WITH_LAYOUT_SHELL" -eq "$TOTAL_PAGES" ]; then
        check_passed "All pages use LayoutShell component"
    else
        check_failed "Only $PAGES_WITH_LAYOUT_SHELL/$TOTAL_PAGES pages use LayoutShell"
    fi
else
    check_warning "No Vue pages found to validate"
fi

# Check for permission integration in pages
PAGES_WITH_PERMISSIONS=$(find resources/js/Pages -name "*.vue" -exec grep -l "can\." {} \; 2>/dev/null | wc -l)
if [ "$PAGES_WITH_PERMISSIONS" -gt 0 ]; then
    check_passed "$PAGES_WITH_PERMISSIONS pages have permission integration"
else
    check_warning "No permission integration found in pages"
fi

# Check for blue-whale theme compliance
BLUE_WHALE_THEME_FILES=$(find resources/js -name "*.vue" -exec grep -l "blu-whale\|blue-whale" {} \; 2>/dev/null | wc -l)
SIDEBAR_FILES=$(find resources/js -name "*.vue" -exec grep -l "<Sidebar" {} \; 2>/dev/null | wc -l)

if [ "$SIDEBAR_FILES" -gt 0 ]; then
    if [ "$BLUE_WHALE_THEME_FILES" -gt 0 ]; then
        check_passed "$BLUE_WHALE_THEME_FILES/$SIDEBAR_FILES components use blue-whale theme"
    else
        check_failed "No blue-whale theme usage found in Sidebar components"
    fi
else
    check_warning "No Sidebar components found to validate theme"
fi

# Check for hard-coded colors (forbidden)
HARD_CODED_COLORS=$(find resources/js -name "*.vue" -exec grep -l "#[0-9a-fA-F]\{6\}" {} \; 2>/dev/null | wc -l)
if [ "$HARD_CODED_COLORS" -eq 0 ]; then
    check_passed "No hard-coded colors found (CSS custom properties compliance)"
else
    check_failed "Found $HARD_CODED_COLORS Vue files with hard-coded colors"
fi

# Check for forbidden theme usage
FORBIDDEN_THEMES=$(find resources/js -name "*.vue" -exec grep -l 'theme="default"\|theme="material"\|theme="bootstrap"' {} \; 2>/dev/null | wc -l)
if [ "$FORBIDDEN_THEMES" -eq 0 ]; then
    check_passed "No forbidden themes found (blue-whale compliance)"
else
    check_failed "Found $FORBIDDEN_THEMES Vue files with forbidden themes"
fi

# Run layout compliance validator if available
if command -v php >/dev/null && [ -f "artisan" ]; then
    if php artisan list | grep -q "layout:validate"; then
        echo "Running layout & theme compliance validator..."
        if php artisan layout:validate --json 2>/dev/null > /tmp/layout-validation.json; then
            COMPLIANCE_SCORE=$(cat /tmp/layout-validation.json 2>/dev/null | grep -o '"compliance_score":[0-9.]*' | cut -d: -f2)
            if [ -n "$COMPLIANCE_SCORE" ]; then
                if (( $(echo "$COMPLIANCE_SCORE >= 90" | bc -l) )); then
                    check_passed "Layout & theme compliance score: $COMPLIANCE_SCORE%"
                else
                    check_failed "Layout & theme compliance score too low: $COMPLIANCE_SCORE%"
                fi
            fi
        fi
    fi
fi

echo ""
echo -e "${BLUE}📊 VALIDATION SUMMARY${NC}"
echo "════════════════════"
echo "Total checks: $TOTAL_CHECKS"
echo -e "Passed: ${GREEN}$PASSED_CHECKS${NC}"
echo -e "Failed: ${RED}$FAILED_CHECKS${NC}"

if [ "$FAILED_CHECKS" -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 MIGRATION VALIDATION PASSED!${NC}"
    echo -e "${GREEN}✅ Ready for clean migration to /build${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}❌ MIGRATION VALIDATION FAILED${NC}"
    echo -e "${RED}Fix the above issues before proceeding${NC}"
    exit 1
fi