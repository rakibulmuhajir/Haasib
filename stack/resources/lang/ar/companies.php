<?php

return [
    // General
    'companies' => 'الشركات',
    'company' => 'الشركة',
    'create_company' => 'إنشاء شركة',
    'edit_company' => 'تعديل الشركة',
    'delete_company' => 'حذف الشركة',
    'company_details' => 'تفاصيل الشركة',
    'company_settings' => 'إعدادات الشركة',
    'company_information' => 'معلومات الشركة',

    // Company Fields
    'name' => 'اسم الشركة',
    'slug' => 'الرابط المخصص',
    'industry' => 'القطاع',
    'country' => 'البلد',
    'base_currency' => 'العملة الأساسية',
    'currency' => 'العملة',
    'timezone' => 'المنطقة الزمنية',
    'language' => 'اللغة',
    'locale' => 'الإعدادات المحلية',
    'settings' => 'الإعدادات',
    'is_active' => 'نشطة',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',

    // Company Actions
    'create_new_company' => 'إنشاء شركة جديدة',
    'edit_company_details' => 'تعديل تفاصيل الشركة',
    'deactivate_company' => 'تعطيل الشركة',
    'activate_company' => 'تفعيل الشركة',
    'switch_company' => 'تبديل الشركة',
    'switch_to_company' => 'التبديل إلى :company',
    'current_company' => 'الشركة الحالية',

    // Validation Messages
    'name_required' => 'اسم الشركة مطلوب.',
    'name_min' => 'يجب أن يكون اسم الشركة حرفين على الأقل.',
    'name_max' => 'يجب ألا يزيد اسم الشركة عن 255 حرفاً.',
    'name_unique' => 'شركة بهذا الاسم موجودة بالفعل.',
    'name_invalid' => 'اسم الشركة يحتوي على أحرف غير صالحة.',
    'slug_invalid' => 'يجب أن يحتوي الرابط المخصص على أحرف صغيرة وأرقام وشرطات فقط.',
    'slug_unique' => 'رابط مخصص بهذا الاسم موجود بالفعل.',
    'currency_required' => 'العملة مطلوبة.',
    'currency_invalid' => 'العملة المحددة غير صالحة.',
    'country_required' => 'البلد مطلوب.',
    'country_invalid' => 'البلد المحدد غير صالح.',
    'timezone_invalid' => 'المنطقة الزمنية المحددة غير صالحة.',
    'language_invalid' => 'اللغة المحددة غير صالحة.',
    'locale_invalid' => 'يجب أن تكون الإعدادات المحلية بالتنسيق مثل ar_SA.',

    // Business Logic Validation
    'cannot_deactivate_with_users' => 'لا يمكن تعطيل الشركة مع مستخدمين نشطين. يرجى تعطيل المستخدمين أولاً أو نقل الملكية.',
    'cannot_change_currency_with_transactions' => 'لا يمكن تغيير العملة بعد إنشاء معاملات مالية.',
    'cannot_change_timezone_with_fiscal_years' => 'لا يمكن تغيير المنطقة الزمنية بعد إنشاء السنوات المالية.',
    'max_users_limit_exceeded' => 'لا يمكن أن يكون الحد الأقصى للمستخدمين (:limit) أقل من المستخدمين الحاليين (:current).',
    'storage_format_invalid' => 'يجب أن يكون حد التخزين بالتنسيق مثل 1GB، 500MB، 2TB، إلخ.',
    'ownership_transfer_required' => 'يجب توفير معرف المالك الجديد عند نقل الملكية.',

    // Success Messages
    'company_created' => 'تم إنشاء شركة ":name" بنجاح!',
    'company_updated' => 'تم تحديث شركة ":name" بنجاح!',
    'company_deleted' => 'تم حذف شركة ":name" بنجاح!',
    'company_activated' => 'تم تفعيل شركة ":name" بنجاح!',
    'company_deactivated' => 'تم تعطيل شركة ":name" بنجاح!',
    'company_switched' => 'تم التبديل إلى ":name" بنجاح!',

    // Company Context
    'context_switched' => 'تم تبديل سياق الشركة بنجاح!',
    'current_context' => 'سياق الشركة الحالي',
    'available_companies' => 'الشركات المتاحة',
    'no_companies_available' => 'لا توجد شركات متاحة.',
    'select_company' => 'اختر الشركة',

    // Company Members
    'members' => 'الأعضاء',
    'team_members' => 'أعضاء الفريق',
    'invite_member' => 'دعوة عضو',
    'manage_members' => 'إدارة الأعضاء',
    'member_role' => 'دور العضو',
    'member_status' => 'حالة العضو',
    'active' => 'نشط',
    'inactive' => 'غير نشط',
    'joined_at' => 'تاريخ الانضمام',
    'remove_member' => 'إزالة العضو',
    'update_role' => 'تحديث الدور',
    'change_role' => 'تغيير الدور',

    // Roles
    'roles' => 'الأدوار',
    'owner' => 'المالك',
    'admin' => 'المدير',
    'accountant' => 'المحاسب',
    'manager' => 'المدير',
    'employee' => 'الموظف',
    'viewer' => 'المشاهد',
    'role_owner' => 'المالك',
    'role_admin' => 'المدير',
    'role_accountant' => 'المحاسب',
    'role_manager' => 'المدير',
    'role_employee' => 'الموظف',
    'role_viewer' => 'المشاهد',

    // Company Invitations
    'invitations' => 'الدعوات',
    'invitation' => 'الدعوة',
    'send_invitation' => 'إرسال دعوة',
    'invite_user' => 'دعوة مستخدم',
    'invitation_sent' => 'تم إرسال الدعوة بنجاح!',
    'invitation_accepted' => 'تم قبول الدعوة!',
    'invitation_rejected' => 'تم رفض الدعوة!',
    'invitation_expired' => 'انتهت صلاحية الدعوة!',
    'pending_invitations' => 'الدعوات المعلقة',
    'accepted_invitations' => 'الدعوات المقبولة',
    'rejected_invitations' => 'الدعوات المرفوضة',
    'expired_invitations' => 'الدعوات المنتهية',
    'resend_invitation' => 'إعادة إرسال الدعوة',
    'cancel_invitation' => 'إلغاء الدعوة',
    'accept_invitation' => 'قبول الدعوة',
    'reject_invitation' => 'رفض الدعوة',

    // Invitation Fields
    'email' => 'البريد الإلكتروني',
    'role' => 'الدور',
    'message' => 'الرسالة',
    'expires_at' => 'تاريخ الانتهاء',
    'created_at' => 'تاريخ الإنشاء',
    'accepted_at' => 'تاريخ القبول',
    'status' => 'الحالة',
    'invited_by' => 'دعا بواسطة',
    'invitation_token' => 'رمز الدعوة',
    'invitation_url' => 'رابط الدعوة',

    // Company Settings
    'features' => 'الميزات',
    'preferences' => 'التفضيلات',
    'limits' => 'الحدود',
    'accounting' => 'المحاسبة',
    'reporting' => 'التقارير',
    'invoicing' => 'الفواتير',
    'inventory' => 'المخزون',
    'project_management' => 'إدارة المشاريع',
    'fleet_management' => 'إدارة الأسطول',
    'pos' => 'نقطة البيع',
    'rd_management' => 'إدارة البحث والتطوير',
    'asset_management' => 'إدارة الأصول',

    // Preferences
    'theme' => 'المظهر',
    'theme_light' => 'فاتح',
    'theme_dark' => 'داكن',
    'theme_auto' => 'تلقائي',

    // Limits
    'max_users' => 'الحد الأقصى للمستخدمين',
    'max_storage' => 'الحد الأقصى للتخزين',

    // Company Lists
    'all_companies' => 'جميع الشركات',
    'active_companies' => 'الشركات النشطة',
    'inactive_companies' => 'الشركات غير النشطة',
    'my_companies' => 'شركاتي',
    'search_companies' => 'البحث عن الشركات...',
    'filter_by_country' => 'تصفية حسب البلد',
    'filter_by_status' => 'تصفية حسب الحالة',
    'sort_by' => 'ترتيب حسب',
    'sort_name' => 'الاسم',
    'sort_created_at' => 'تاريخ الإنشاء',
    'sort_updated_at' => 'تاريخ التحديث',
    'sort_order' => 'ترتيب',
    'sort_asc' => 'تصاعدي',
    'sort_desc' => 'تنازلي',

    // Pagination
    'per_page' => 'لكل صفحة',
    'showing' => 'عرض',
    'of' => 'من',
    'results' => 'نتيجة',
    'previous' => 'السابق',
    'next' => 'التالي',
    'first' => 'الأول',
    'last' => 'الأخير',

    // Company Statistics
    'total_companies' => 'إجمالي الشركات',
    'active_users' => 'المستخدمون النشطون',
    'total_users' => 'إجمالي المستخدمين',
    'total_invitations' => 'إجمالي الدعوات',
    'pending_invitations_count' => 'الدعوات المعلقة',
    'company_created_this_month' => 'الشركات المنشأة هذا الشهر',
    'users_added_this_month' => 'المستخدمون المضافون هذا الشهر',

    // Company Features
    'enable_features' => 'تفعيل الميزات',
    'disabled_features' => 'الميزات المعطلة',
    'feature_not_available' => 'هذه الميزة غير متاحة في خطتك الحالية.',
    'upgrade_plan' => 'ترقية الخطة',

    // Company Permissions
    'companies_view' => 'عرض الشركات',
    'companies_create' => 'إنشاء الشركات',
    'companies_update' => 'تحديث الشركات',
    'companies_delete' => 'حذف الشركات',
    'companies_export' => 'تصدير الشركات',
    'companies_invite' => 'دعوة المستخدمين',
    'companies_manage_members' => 'إدارة الأعضاء',
    'companies_switch_context' => 'تبديل سياق الشركة',

    // Error Messages
    'company_not_found' => 'الشركة غير موجودة.',
    'access_denied' => 'الوصول مرفوض.',
    'insufficient_permissions' => 'صلاحيات غير كافية لأداء هذا الإجراء.',
    'cannot_access_company' => 'لا يوجد لديك صلاحية للوصول إلى هذه الشركة.',
    'company_already_exists' => 'شركة بهذا الاسم موجودة بالفعل.',
    'invitation_already_sent' => 'تم إرسال دعوة بالفعل إلى هذا البريد الإلكتروني.',
    'user_already_member' => 'هذا المستخدم عضو بالفعل في الشركة.',
    'cannot_remove_owner' => 'لا يمكن إزالة مالك الشركة.',
    'cannot_invite_yourself' => 'لا يمكن دعوة نفسك إلى الشركة.',

    // Confirmation Messages
    'confirm_delete_company' => 'هل أنت متأكد من حذف هذه الشركة؟ لا يمكن التراجع عن هذا الإجراء.',
    'confirm_remove_member' => 'هل أنت متأكد من إزالة هذا العضو من الشركة؟',
    'confirm_deactivate_company' => 'هل أنت متأكد من تعطيل هذه الشركة؟',
    'confirm_invite_resend' => 'هل أنت متأكد من إعادة إرسال هذه الدعوة؟',

    // Instructions
    'company_create_instructions' => 'املأ تفاصيل الشركة أدناه لإنشاء شركة جديدة.',
    'invitation_instructions' => 'أدخل عنوان البريد الإلكتروني للشخص الذي تريد دعوته للانضمام إلى شركتك.',
    'role_instructions' => 'اختر الدور المناسب لهذا العضو في الفريق.',

    // Placeholders
    'company_name_placeholder' => 'أدخل اسم الشركة',
    'email_placeholder' => 'أدخل البريد الإلكتروني',
    'search_placeholder' => 'البحث عن الشركات...',
    'message_placeholder' => 'أضف رسالة شخصية (اختياري)',

    // Help Text
    'company_name_help' => 'سيتم عرض هذا في جميع أنحاء التطبيق.',
    'slug_help' => 'يستخدم في الروابط ومراجع الشركة. يتم إنشاؤه تلقائياً من الاسم.',
    'currency_help' => 'العملة الافتراضية لجميع المعاملات المالية.',
    'timezone_help' => 'المنطقة الزمنية الافتراضية لعرض التاريخ والوقت.',
    'language_help' => 'اللغة الافتراضية لواجهة الشركة.',
    'role_help' => 'يحدد ما يمكن لهذا المستخدم الوصول إليه في الشركة.',

    // Company Dashboard
    'dashboard' => 'لوحة التحكم',
    'company_dashboard' => 'لوحة تحكم الشركة',
    'welcome_message' => 'مرحباً بك في :company!',
    'quick_actions' => 'إجراءات سريعة',
    'recent_activity' => 'النشاط الحديث',
    'company_overview' => 'نظرة عامة على الشركة',
    'quick_stats' => 'إحصائات سريعة',

    // Navigation
    'company_navigation' => 'التنقل في الشركة',
    'switcher_title' => 'تبديل الشركة',
    'no_companies_yet' => 'لا توجد شركات بعد',
    'create_first_company' => 'أنشئ أول شركة لك',

    // Empty States
    'no_companies_found' => 'لم يتم العثور على شركات.',
    'no_members_found' => 'لم يتم العثور على أعضاء.',
    'no_invitations_found' => 'لم يتم العثور على دعوات.',
    'no_pending_invitations' => 'لا توجد دعوات معلقة.',

    // Company Reports
    'reports' => 'التقارير',
    'company_report' => 'تقرير الشركة',
    'member_report' => 'تقرير الأعضاء',
    'invitation_report' => 'تقرير الدعوات',
    'generate_report' => 'إنشاء تقرير',
    'export_data' => 'تصدير البيانات',

    // Company Export
    'export_companies' => 'تصدير الشركات',
    'export_members' => 'تصدير الأعضاء',
    'export_invitations' => 'تصدير الدعوات',
    'export_format' => 'تنسيق التصدير',
    'export_csv' => 'CSV',
    'export_excel' => 'Excel',
    'export_pdf' => 'PDF',
    'download_export' => 'تنزيل الملف المصدّر',

    // Company Search
    'search_companies_by_name' => 'البحث عن الشركات بالاسم...',
    'search_members_by_name' => 'البحث عن الأعضاء بالاسم أو البريد الإلكتروني...',
    'search_invitations_by_email' => 'البحث عن الدعوات بالبريد الإلكتروني...',
    'clear_search' => 'مسح البحث',
    'search_results' => 'نتائج البحث',
    'no_search_results' => 'لم يتم العثور على نتائج لـ ":query".',

    // Company Filters
    'filter' => 'تصفية',
    'clear_filters' => 'مسح المرشحات',
    'active_filters' => 'المرشحات النشطة',
    'filter_by_role' => 'تصفية حسب الدور',
    'filter_by_status' => 'تصفية حسب الحالة',
    'filter_by_country' => 'تصفية حسب البلد',
    'filter_by_date_range' => 'تصفية حسب النطاق الزمني',

    // Company Bulk Actions
    'bulk_actions' => 'إجراءات جماعية',
    'select_all' => 'تحديد الكل',
    'deselect_all' => 'إلغاء تحديد الكل',
    'selected_items' => ':count عناصر محددة',
    'bulk_delete' => 'حذف المحدد',
    'bulk_export' => 'تصدير المحدد',
    'bulk_invite' => 'إرسال الدعوات',
    'bulk_activate' => 'تفعيل المحدد',
    'bulk_deactivate' => 'تعطيل المحدد',

    // Company History
    'history' => 'التاريخ',
    'company_history' => 'تاريخ الشركة',
    'view_history' => 'عرض التاريخ',
    'activity_log' => 'سجل النشاط',
    'audit_trail' => 'أثر التدقيق',

    // Company Settings Advanced
    'advanced_settings' => 'الإعدادات المتقدمة',
    'company_configuration' => 'تكوين الشركة',
    'security_settings' => 'إعدادات الأمان',
    'integration_settings' => 'إعدادات التكامل',
    'notification_settings' => 'إعدادات الإشعارات',
    'backup_settings' => 'إعدادات النسخ الاحتياطي',

    // Company Statuses
    'status_active' => 'نشط',
    'status_inactive' => 'غير نشط',
    'status_suspended' => 'معلق',
    'status_archived' => 'مؤرش',

    // Company Categories
    'company_categories' => 'فئات الشركات',
    'business_type' => 'نوع العمل',
    'company_size' => 'حجم الشركة',
    'revenue_range' => 'نطاق الإيرادات',
    'employee_count' => 'عدد الموظفين',

    // Company Documents
    'documents' => 'الوثائق',
    'upload_document' => 'رفع مستند',
    'company_documents' => 'وثائق الشركة',
    'document_types' => 'أنواعد المستندات',

    // Company Integrations
    'integrations' => 'التكاملات',
    'connected_services' => 'الخدمات المتصلة',
    'integration_settings' => 'إعدادات التكامل',
    'connect_service' => 'ربط خدمة',
    'disconnect_service' => 'فصل خدمة',
];
