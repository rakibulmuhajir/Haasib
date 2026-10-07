export interface ValueTrailSource {
    restricted?: boolean
    unavailable?: boolean
    label?: string
    date?: string
    field?: string
    href?: string
    recorded_at?: string | null
    recorded_by?: string | null
}

export interface ValueTrailNode {
    id: string
    label: string
    value: number
    currency?: string
    unit: string
    explanation: string
    formula: string | null
    estimated: boolean
    children: string[]
    children_total?: number
    children_loaded?: number
    summary?: boolean
    source: ValueTrailSource | null
}

export interface ValueTrail {
    batch?: { parent: string; offset: number; version: string }
    nodes: Record<string, ValueTrailNode>
    roots: Record<string, string>
    context?: {
        label?: string
        start_date?: string
        end_date?: string
        [filter: string]: unknown
    }
    error?: string
}
