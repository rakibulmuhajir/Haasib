import { describe, it, expect } from 'vitest'
import { parseCommand } from '@/palette/parser'
import { entities } from '@/palette/entities'

describe('parser (devops verbs)', () => {
  it('parses company create', () => {
    const p = parseCommand('company create Acme', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('company')
    expect(p!.verbId).toBe('create')
    expect(p!.params).toMatchObject({ name: 'Acme' })
  })

  it('parses user create with name and email', () => {
    const p = parseCommand('user create Jane jane@example.com', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('user')
    expect(p!.verbId).toBe('create')
    expect(p!.params.email).toBe('jane@example.com')
    expect(p!.params.name).toBe('Jane')
  })

  it('parses user delete with email', () => {
    const p = parseCommand('delete user jane@example.com', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('user')
    expect(p!.verbId).toBe('delete')
    expect(p!.params).toMatchObject({ email: 'jane@example.com' })
  })

  it('biases create with email to user', () => {
    const p = parseCommand('create jane@example.com', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('user')
    expect(p!.verbId).toBe('create')
    expect(p!.params.email).toBe('jane@example.com')
  })

  it('biases delete with email to user', () => {
    const p = parseCommand('delete jane@example.com', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('user')
    expect(p!.verbId).toBe('delete')
    expect(p!.params.email).toBe('jane@example.com')
  })

  it('parses company assign with to/as', () => {
    const p = parseCommand('assign jane@example.com to Acme as admin', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('company')
    expect(p!.verbId).toBe('assign')
    expect(p!.params).toMatchObject({ email: 'jane@example.com', company: 'Acme', role: 'admin' })
  })

  it('parses company unassign with from', () => {
    const p = parseCommand('company unassign jane@example.com from Acme', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('company')
    expect(p!.verbId).toBe('unassign')
    expect(p!.params).toMatchObject({ email: 'jane@example.com', company: 'Acme' })
  })

  it('parses flags in freeform (assign)', () => {
    const p = parseCommand('assign -email jane@example.com -company Acme -role admin', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('company')
    expect(p!.verbId).toBe('assign')
    expect(p!.params).toMatchObject({ email: 'jane@example.com', company: 'Acme', role: 'admin' })
  })

  it('parses flags in freeform (user create)', () => {
    const p = parseCommand('user create -name Jane -email jane@example.com', entities)
    expect(p).toBeTruthy()
    expect(p!.entityId).toBe('user')
    expect(p!.verbId).toBe('create')
    expect(p!.params).toMatchObject({ name: 'Jane', email: 'jane@example.com' })
  })
})
