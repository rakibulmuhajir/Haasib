// resources/js/palette/entities.ts
import type { PreExecuteContext, PostExecuteContext } from '@/palette/composables/usePalette'
export type FieldDef = {
  id: string
  label: string
  placeholder: string // e.g., "-amount"
  required: boolean
  type: 'text' | 'email' | 'password' | 'date' | 'money' | 'select' | 'remote'
  options?: string[]
  // UI + data-driven extensions
  picker?: 'inline' | 'panel'
  default?: string | ((params: Record<string, any>) => string)
  validate?: (value: any, params: Record<string, any>) => true | string
  source?: {
    kind: 'static' | 'remote'
    endpoint?: string
    queryKey?: string
    limit?: number
    valueKey: string
    labelKey?: string
    labelTemplate?: string
    dependsOn?: string[]
  }
}

export type VerbDef = {
  id: string // 'create' | 'delete' | 'assign' | 'unassign'
  label: string
  action: string // backend action id, e.g. 'company.create'
  fields: FieldDef[] // ordered, shown as grey flags one-by-one
  preExecute?: (context: PreExecuteContext) => boolean | Promise<boolean>
  postExecute?: (context: PostExecuteContext) => void | Promise<void>
}

export type EntityDef = {
  id: string // 'company' | 'user'
  label: string
  aliases: string[]
  verbs: VerbDef[]
}

export const entities: EntityDef[] = [
  {
    id: 'company',
    label: 'company',
    aliases: ['company', 'comp', 'co', 'cmp'],
    verbs: [
      {
        id: 'list',
        label: 'list',
        action: 'ui.list.companies',
        fields: [
          {
            id: 'company', label: 'Company', placeholder: '-company', required: false, type: 'remote', picker: 'panel',
            // This source is used to populate the list for the 'ui.list.companies' action.
            source: { kind: 'remote', endpoint: '/web/companies', valueKey: 'id', labelKey: 'name' }
          },
        ],
      },
      {
        id: 'create',
        label: 'create',
        action: 'company.create',
        fields: [
          { id: 'name', label: 'Name', placeholder: '-name', required: true, type: 'text' },
          {
            id: 'base_currency', label: 'Base currency', placeholder: '-base_currency', required: false, type: 'remote', picker: 'inline',
            default: 'USD',
            source: { kind: 'remote', endpoint: '/web/currencies/suggest', queryKey: 'q', limit: 12, valueKey: 'code', labelTemplate: '{code} — {name}' }
          },
          {
            id: 'language', label: 'Language', placeholder: '-language', required: false, type: 'remote', picker: 'inline',
            default: 'en',
            source: { kind: 'remote', endpoint: '/web/languages/suggest', queryKey: 'q', limit: 12, valueKey: 'code', labelTemplate: '{code} — {name}' }
          },
          {
            id: 'locale', label: 'Locale', placeholder: '-locale', required: false, type: 'remote', picker: 'inline',
            default: 'en-US',
            source: { kind: 'remote', endpoint: '/web/locales/suggest', queryKey: 'q', limit: 12, valueKey: 'tag', labelTemplate: '{tag}', dependsOn: ['language', 'country'] }
          },
        ],
      },
      {
        id: 'delete',
        label: 'delete',
        action: 'company.delete',
        fields: [
          {
            id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/companies', valueKey: 'id', labelKey: 'name' }
          },
        ],
        preExecute: (context: PreExecuteContext) => {
          const { params, companyDetails, deleteConfirmRequired, deleteConfirmText } = context
          const companyId = params.value['company']

          if (!companyId) return true

          const details = companyDetails.value[companyId]
          if (details) {
            if (!deleteConfirmRequired.value) {
              deleteConfirmRequired.value = details.slug || details.name
            }
            if (deleteConfirmText.value !== deleteConfirmRequired.value) {
              return false
            }
          }
          return true
        },
        postExecute: (context: PostExecuteContext) => {
          // Clear confirmation UI state after success
          context.palette.resetAll()
        },
      },

      {
        id: 'assign',
        label: 'assign',
        action: 'company.assign',
        fields: [
          {
            id: 'email', label: 'User email', placeholder: '-email', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/users/suggest', queryKey: 'q', valueKey: 'email', labelTemplate: '{name} — {email}' }
          },
          {
            id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/companies', valueKey: 'id', labelKey: 'name' }
          },
          { id: 'role', label: 'Role', placeholder: '-role', required: true, type: 'select', options: ['owner','admin','accountant','viewer'] },
        ],
      },
      {
        id: 'unassign',
        label: 'unassign',
        action: 'company.unassign',
        fields: [
          {
            id: 'email', label: 'User email', placeholder: '-email', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/users/suggest', queryKey: 'q', valueKey: 'email', labelTemplate: '{name} — {email}' }
          },
          {
            id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/companies', valueKey: 'id', labelKey: 'name' }
          },
        ],
      },
    ],
  },
  {
    id: 'user',
    label: 'user',
    aliases: ['user', 'usr', 'users'],
    verbs: [
      {
        id: 'list',
        label: 'list',
        action: 'ui.list.users',
        fields: [
          {
            id: 'email', label: 'Email', placeholder: '-email', required: false, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/users/suggest', queryKey: 'q', valueKey: 'email', labelTemplate: '{name} — {email}' }
          },
        ],
      },
      {
        id: 'create',
        label: 'create',
        action: 'user.create',
        fields: [
          { id: 'name', label: 'Name', placeholder: '-name', required: true, type: 'text' },
          { id: 'email', label: 'Email', placeholder: '-email', required: true, type: 'email' },
          { id: 'password', label: 'Password', placeholder: '-password', required: false, type: 'password' },
          { id: 'system_role', label: 'System role', placeholder: '-system_role', required: false, type: 'select', options: ['superadmin'] },
        ],
      },
      {
        id: 'delete',
        label: 'delete',
        action: 'user.delete',
        fields: [
          {
            id: 'email', label: 'User to delete', placeholder: '-email', required: true, type: 'remote', picker: 'panel',
            source: { kind: 'remote', endpoint: '/web/users/suggest', queryKey: 'q', valueKey: 'email', labelTemplate: '{name} — {email}' }
          },
        ],
      },
    ],
  },
]
