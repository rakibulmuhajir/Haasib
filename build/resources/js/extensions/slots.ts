import type { Component } from 'vue'

export interface SlotEntry {
    module: string
    component: Component
    order?: number
}

const slots: Record<string, SlotEntry[]> = {}

/** A module adds UI to a named place in a core page. Re-registering a module's entry replaces it. */
export function registerSlot(name: string, entry: SlotEntry): void {
    const list = (slots[name] ??= []).filter((e) => e.module !== entry.module)
    list.push(entry)
    slots[name] = list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
}

export function slotEntries(name: string): SlotEntry[] {
    return slots[name] ?? []
}
