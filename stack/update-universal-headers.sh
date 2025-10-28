#!/bin/bash

# Script to update all pages to use UniversalPageHeader
# This script will:
# 1. Update imports
# 2. Replace page headers with universal headers
# 3. Configure appropriate settings for each page type

echo "Starting universal page header migration..."

# Array of all pages to update
pages=(
    "resources/js/Pages/Invoicing/InvoiceList.vue"
    "resources/js/Pages/Dashboard.vue"
    "resources/js/Pages/Companies/Index.vue"
    "resources/js/Pages/Companies/Create.vue"
    "resources/js/Pages/Companies/Show.vue"
    "resources/js/Pages/Accounting/JournalEntries/Batches/Index.vue"
    "resources/js/Pages/Accounting/JournalEntries/Batches/Show.vue"
    "resources/js/Pages/Accounting/JournalEntries/TrialBalance.vue"
    "resources/js/Pages/Accounting/Customers/Index.vue"
    "resources/js/Pages/Accounting/Payments/AuditTimeline.vue"
    "resources/js/Pages/Accounting/Payments/Batches.vue"
    "resources/js/Pages/Accounting/Payments/ReportingDashboard.vue"
    "resources/js/Pages/Accounting/Payments/PaymentReversals.vue"
    "resources/js/Pages/Ledger/PeriodClose/Index.vue"
    "resources/js/Pages/Ledger/PeriodClose/Show.vue"
    "resources/js/Pages/Profile/Index.vue"
    "resources/js/Pages/Reporting/Templates/Index.vue"
    "resources/js/Pages/Reporting/Statements/Index.vue"
    "resources/js/Pages/Reporting/Schedules/Index.vue"
    "resources/js/Pages/Reporting/Dashboard/Index.vue"
)

# Function to update page based on its type
update_page_header() {
    local page="$1"
    echo "Processing: $page"
    
    # Create backup
    cp "$page" "$page.backup"
    
    # Update imports - replace PageActions with UniversalPageHeader
    sed -i 's|import PageActions from '\''@\/Components\/PageActions\.vue'\''|import UniversalPageHeader from '\''@\/Components\/UniversalPageHeader.vue'\''|g' "$page"
    
    # Determine page type and configure accordingly
    if [[ "$page" == *"Dashboard"* ]]; then
        # Dashboard pages - no search needed
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Dashboard"\
      description="Comprehensive dashboard and analytics"\
      subDescription="Monitor your business performance and key metrics"\
      :show-search="false"\
    />' "$page"
        
    elif [[ "$page" == *"Customers"* ]]; then
        # Customer pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Customers"\
      description="Manage your customer relationships and information"\
      subDescription="Create, edit, and manage customer accounts"\
      :show-search="true"\
      search-placeholder="Search customers..."\
    />' "$page"
        
    elif [[ "$page" == *"Companies"* ]]; then
        # Company pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Companies"\
      description="Manage your business entities and organizations"\
      subDescription="Create and configure company settings and preferences"\
      :show-search="true"\
      search-placeholder="Search companies..."\
    />' "$page"
        
    elif [[ "$page" == *"Invoices"* ]]; then
        # Invoice pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Invoices"\
      description="Create, manage, and track your invoices"\
      subDescription="Send professional invoices and monitor payment status"\
      :show-search="true"\
      search-placeholder="Search invoices..."\
    />' "$page"
        
    elif [[ "$page" == *"Payments"* ]]; then
        # Payment pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Payments"\
      description="Manage payment processing and transactions"\
      subDescription="Track receipts, reversals, and payment allocations"\
      :show-search="true"\
      search-placeholder="Search payments..."\
    />' "$page"
        
    elif [[ "$page" == *"JournalEntries"* || "$page" == *"Batches"* ]]; then
        # Journal entry pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Journal Entries"\
      description="Manage and process journal entry batches"\
      subDescription="Review, approve, and post accounting entries"\
      :show-search="true"\
      search-placeholder="Search journal entries..."\
    />' "$page"
        
    elif [[ "$page" == *"Reporting"* ]]; then
        # Reporting pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Reports"\
      description="Generate and manage business reports"\
      subDescription="Create insightful reports for business analysis"\
      :show-search="true"\
      search-placeholder="Search reports..."\
    />' "$page"
        
    elif [[ "$page" == *"PeriodClose"* ]]; then
        # Period close pages
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Period Close"\
      description="Manage monthly closing workflows"\
      subDescription="Complete accounting period closing procedures"\
      :show-search="true"\
      search-placeholder="Search periods..."\
    />' "$page"
        
    elif [[ "$page" == *"Profile"* ]]; then
        # Profile pages - no search needed
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Profile"\
      description="Manage your personal information and account settings"\
      subDescription="Update your profile and preferences"\
      :show-search="false"\
    />' "$page"
        
    else
        # Default configuration
        sed -i '/<!-- Page Header -->/,/<\/div>/c\
    <!-- Universal Page Header -->\
    <UniversalPageHeader\
      title="Management"\
      description="Manage your content and settings"\
      :show-search="true"\
    />' "$page"
    fi
    
    echo "  ✓ Updated: $page"
}

# Process each page
for page in "${pages[@]}"; do
    if [[ -f "$page" ]]; then
        update_page_header "$page"
    else
        echo "  ⚠ File not found: $page"
    fi
done

echo ""
echo "Migration completed!"
echo "All pages have been updated to use UniversalPageHeader"
echo "Backup files created with .backup extension"
echo ""
echo "Next steps:"
echo "1. Test each page to ensure functionality"
echo "2. Update page-specific actions as needed"
echo "3. Remove .backup files when confident"