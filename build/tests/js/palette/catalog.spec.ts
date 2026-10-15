import { describe, expect, it } from 'vitest'
import { GRAMMAR, COMMAND_DESCRIPTIONS } from '@/palette/grammar'
import { getSchema, registerCatalogSchemas, type CatalogEntry } from '@/palette/schemas'
import { parse } from '@/palette/parser'

const entries: CatalogEntry[] = [
  {
    key: 'vendor.create', entity: 'vendor', verb: 'create', description: 'Add a supplier',
    query: false, form: false, form_url: null,
    args: [{ name: 'name', required: true, inputType: 'text' }],
    flags: [{ name: 'email', required: false, inputType: 'email' }],
  },
  {
    key: 'customer.create', entity: 'customer', verb: 'create', description: 'SERVER VERSION',
    query: false, form: false, form_url: null,
    args: [{ name: 'zzz', required: true, inputType: 'text' }], flags: [],
  },
  {
    key: 'fuel.amanat.movement', entity: 'fuel.amanat', verb: 'movement', description: 'Record amanat movement',
    query: false, form: false, form_url: null,
    args: [{ name: 'amount', required: true, inputType: 'number' }],
    flags: [{ name: 'item_id', required: false, inputType: 'search', searchEntity: 'item' }],
  },
]

describe('palette catalog', () => {
  registerCatalogSchemas(entries)

  it('adds new entities to GRAMMAR in place', () => {
    expect(GRAMMAR.vendor.verbs.map(v => v.name)).toContain('create')
    expect(GRAMMAR['fuel.amanat']).toBeTruthy()
    expect(COMMAND_DESCRIPTIONS['vendor.create']).toBe('Add a supplier')
  })

  it('does not overwrite hand-written customer.create', () => {
    const schema = getSchema('customer', 'create')!
    expect(schema.args.map(a => a.name)).not.toContain('zzz')
    expect(COMMAND_DESCRIPTIONS['customer.create']).not.toBe('SERVER VERSION')
  })

  it('getSchema returns the catalog schema', () => {
    expect(getSchema('vendor', 'create')?.flags[0].name).toBe('email')
  })

  it('parses a catalog command with a positional argument and flag', () => {
    const p = parse('vendor create Acme --email=a@b.c')
    expect(p.entity).toBe('vendor')
    expect(p.verb).toBe('create')
    expect(p.flags.name).toBe('Acme')
    expect(p.flags.email).toBe('a@b.c')
  })

  it('parses dotted entities', () => {
    const spaced = parse('fuel.amanat movement 500')
    expect(spaced.entity).toBe('fuel.amanat')
    expect(spaced.verb).toBe('movement')
    expect(spaced.flags.amount).toBe('500')
    const dotted = parse('fuel.amanat.movement 500')
    expect(dotted.entity).toBe('fuel.amanat')
    expect(dotted.verb).toBe('movement')
  })
})
