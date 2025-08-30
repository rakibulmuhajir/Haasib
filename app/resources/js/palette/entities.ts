// resources/js/palette/entities.ts
export type FieldDef = {
  id: string
  label: string
  placeholder: string // e.g., "-amount"
  required: boolean
  type: 'text' | 'email' | 'password' | 'date' | 'money' | 'select'
  options?: string[]
}

export type VerbDef = {
  id: string // 'create' | 'delete' | 'assign' | 'unassign'
  label: string
  action: string // backend action id, e.g. 'company.create'
  fields: FieldDef[] // ordered, shown as grey flags one-by-one
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
          { id: 'company', label: 'Company', placeholder: '-company', required: false, type: 'text' },
        ],
      },
      {
        id: 'create',
        label: 'create',
        action: 'company.create',
        fields: [
          { id: 'name', label: 'Name', placeholder: '-name', required: true, type: 'text' },
        ],
      },
      {
        id: 'delete',
        label: 'delete',
        action: 'company.delete',
        fields: [
          { id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'text' },
        ],
      },
      {
        id: 'assign',
        label: 'assign',
        action: 'company.assign',
        fields: [
          { id: 'email', label: 'User email', placeholder: '-email', required: true, type: 'email' },
          { id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'text' },
          { id: 'role', label: 'Role', placeholder: '-role', required: true, type: 'select', options: ['owner','admin','accountant','viewer'] },
        ],
      },
      {
        id: 'unassign',
        label: 'unassign',
        action: 'company.unassign',
        fields: [
          { id: 'email', label: 'User email', placeholder: '-email', required: true, type: 'email' },
          { id: 'company', label: 'Company', placeholder: '-company', required: true, type: 'text' },
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
          { id: 'email', label: 'Email', placeholder: '-email', required: false, type: 'text' },
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
          { id: 'email', label: 'Email', placeholder: '-email', required: true, type: 'email' },
        ],
      },
    ],
  },
]
