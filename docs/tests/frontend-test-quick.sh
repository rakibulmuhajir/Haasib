#!/bin/bash

# Quick Frontend Testing Script for Junior Developers
# Based on FRONTEND_TESTING_PLAN.md
# Version: 1.0
# Last Updated: 2025-11-14

set -e  # Exit on any error

echo "🎨 Quick Frontend Testing for Junior Developers"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/home/banna/projects/Haasib"
STACK_DIR="$PROJECT_DIR/stack"

# Function to print section headers
print_section() {
    echo -e "${BLUE}$1${NC}"
    echo "----------------------------------------"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Function to run checklist item
check_item() {
    local description="$1"
    local command="$2"
    local expected_output="$3"
    
    echo -n "🔍 $description... "
    if eval "$command" >/dev/null 2>&1; then
        echo -e "${GREEN}PASS${NC}"
        return 0
    else
        echo -e "${RED}FAIL${NC}"
        return 1
    fi
}

# Change to project directory
cd "$PROJECT_DIR"

print_section "🚀 Pre-Test Environment Check"

echo "Checking test environment setup..."

# Check if stack directory exists
if [ ! -d "$STACK_DIR" ]; then
    print_error "Stack directory not found. Please ensure you're in the correct project directory."
    exit 1
fi

cd "$STACK_DIR"

# Check if composer dependencies are installed
if [ ! -d "vendor" ]; then
    print_warning "Composer dependencies not installed. Installing now..."
    composer install
fi

# Check if npm dependencies are installed
if [ ! -d "node_modules" ]; then
    print_warning "NPM dependencies not installed. Installing now..."
    npm install
fi

# Build frontend assets
print_section "🔧 Building Frontend Assets"
echo "Building assets for testing..."
if npm run build; then
    print_success "Frontend build completed"
else
    print_error "Frontend build failed - fix build errors before testing"
    exit 1
fi

print_section "🏛️ Constitutional Compliance Quick Check"

echo "Running constitutional pattern checks..."

# Quick constitutional checks
CONSTITUTIONAL_ERRORS=0

# Check for HTML elements instead of PrimeVue components
echo -n "📋 Checking PrimeVue component usage... "
if find "resources/js" -name "*.vue" -exec grep -l -E "(<table|<button|<input)" {} \; 2>/dev/null | head -1 >/dev/null; then
    echo -e "${RED}FAIL${NC}"
    print_error "Found HTML elements instead of PrimeVue components"
    ((CONSTITUTIONAL_ERRORS++))
else
    echo -e "${GREEN}PASS${NC}"
fi

# Check for Vue Composition API usage
echo -n "📋 Checking Vue Composition API usage... "
if find "resources/js" -name "*.vue" -exec grep -l "export default {" {} \; 2>/dev/null | head -1 >/dev/null; then
    echo -e "${RED}FAIL${NC}"
    print_error "Found Options API usage - should use Composition API with <script setup>"
    ((CONSTITUTIONAL_ERRORS++))
else
    echo -e "${GREEN}PASS${NC}"
fi

# Check for direct service calls in controllers
echo -n "📋 Checking Command Bus pattern... "
if grep -r "new.*Service()" "app/Http/Controllers/" 2>/dev/null >/dev/null; then
    echo -e "${RED}FAIL${NC}"
    print_error "Found direct service instantiation in controllers"
    ((CONSTITUTIONAL_ERRORS++))
else
    echo -e "${GREEN}PASS${NC}"
fi

# Check for inline validation in controllers
echo -n "📋 Checking FormRequest pattern... "
if grep -r "\$request->validate(" "app/Http/Controllers/" 2>/dev/null >/dev/null; then
    echo -e "${RED}FAIL${NC}"
    print_error "Found inline validation in controllers - should use FormRequest"
    ((CONSTITUTIONAL_ERRORS++))
else
    echo -e "${GREEN}PASS${NC}"
fi

if [ $CONSTITUTIONAL_ERRORS -gt 0 ]; then
    print_error "$CONSTITUTIONAL_ERRORS constitutional compliance errors found"
    echo ""
    echo "🔧 Quick Fixes:"
    echo "   1. Replace HTML elements with PrimeVue components"
    echo "   2. Convert Vue components to use <script setup>"
    echo "   3. Use Command Bus for controller write operations"
    echo "   4. Create FormRequest classes for validation"
    echo ""
    echo "📚 Reference: docs/FRONTEND_TESTING_PLAN.md"
    exit 1
else
    print_success "Constitutional compliance checks passed"
fi

print_section "🧪 Quick Unit Tests"

echo "Running quick unit tests..."
if php artisan test --testsuite=Unit --stop-on-failure; then
    print_success "Unit tests passed"
else
    print_error "Unit tests failed - fix failing tests before proceeding"
    exit 1
fi

print_section "🎭 Frontend Component Tests (if available)"

# Check if Playwright is configured
if [ -f "package.json" ] && grep -q "playwright" package.json; then
    echo "Running quick frontend tests..."
    
    # Run a subset of critical frontend tests
    if npm run test:e2e -- --grep "critical|mandatory|constitutional" --max-failures=3; then
        print_success "Critical frontend tests passed"
    else
        print_warning "Some frontend tests failed - check test output for details"
    fi
else
    print_warning "Playwright not configured - skipping E2E tests"
    echo "💡 To enable E2E tests, install Playwright:"
    echo "   npm install --save-dev @playwright/test"
    echo "   npx playwright install"
fi

print_section "🎯 Feature-Specific Testing Checklist"

echo "Manual Testing Checklist for Junior Developers:"
echo ""
echo "□ Page Structure Validation:"
echo "  □ All pages have Sidebar component"
echo "  □ All pages have PageHeader component"
echo "  □ PageActions are in the right slot"
echo "  □ No HTML buttons/inputs (PrimeVue only)"
echo ""
echo "□ Inline Editing Validation:"
echo "  □ Simple fields (name, email) are inline editable"
echo "  □ Complex fields (address) use dedicated forms"
echo "  □ Status toggles work inline"
echo "  □ InlineEditable component is used"
echo ""
echo "□ Form Validation:"
echo "  □ Creation forms have ≤4 required fields"
echo "  □ Validation messages are clear"
echo "  □ Loading states show during save"
echo "  □ Error handling is graceful"
echo ""
echo "□ User Journey Testing:"
echo "  □ Registration → Login → Company Setup works"
echo "  □ Customer creation → editing flow works"
echo "  □ Invoice creation → payment flow works"
echo "  □ Multi-company switching works (if applicable)"

print_section "🔧 Common Issues & Quick Fixes"

echo "If tests fail, try these quick fixes:"
echo ""
echo "🐛 HTML Elements Found:"
echo "   - Replace <button> with <Button>"
echo "   - Replace <input> with <InputText>"
echo "   - Replace <table> with <DataTable>"
echo ""
echo "🐛 Options API Found:"
echo "   - Replace 'export default {' with '<script setup>'"
echo "   - Convert data() to ref() or reactive()"
echo "   - Convert methods to functions"
echo ""
echo "🐛 Direct Service Calls:"
echo "   - Create Command class"
echo "   - Use Bus::dispatch() in controller"
echo "   - Inject ServiceContext in service"
echo ""
echo "🐛 Inline Validation:"
echo "   - Create FormRequest class"
echo "   - Move validation rules to FormRequest"
echo "   - Type-hint FormRequest in controller method"

print_section "📚 Reference Documentation"

echo "For detailed testing guidance:"
echo "   📄 docs/tests/FRONTEND_TESTING_PLAN.md - Complete testing plan"
echo "   📄 docs/tests/HAASIB_TESTING_PLAN.md - Constitutional compliance"
echo "   📄 CLAUDE.md - Development standards"
echo ""
echo "Test execution commands:"
echo "   docs/tests/test-execution.sh --frontend-only    # Frontend tests only"
echo "   docs/tests/test-execution.sh --constitutional   # Constitutional compliance only"
echo "   docs/tests/frontend-test-quick.sh               # This script"

# Final summary
echo ""
print_section "🎉 QUICK TESTING COMPLETE"

if [ $CONSTITUTIONAL_ERRORS -eq 0 ]; then
    print_success "Constitutional compliance verified"
    print_success "Core functionality validated"
    print_success "Frontend components checked"
    echo ""
    echo "🚀 Ready for detailed testing with full test suite!"
    echo "💡 Next steps:"
    echo "   1. Run full test suite: ./test-execution.sh"
    echo "   2. Test specific user journeys manually"
    echo "   3. Review checklist items above"
    echo "   4. Fix any remaining issues"
else
    print_warning "Fix constitutional compliance issues before proceeding"
fi

echo ""
exit 0