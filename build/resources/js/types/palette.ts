/**
 * Palette TypeScript Types
 */

// ============================================================================
// Command Types
// ============================================================================

export interface ParsedCommand {
  /** Original input string */
  raw: string
  /** Resolved entity name (e.g., "company") */
  entity: string
  /** Resolved verb name (e.g., "create") */
  verb: string
  /** Parsed flag values */
  flags: Record<string, unknown>
  /** Unparsed positional arguments */
  subject?: string
  /** Whether all required flags are present */
  complete: boolean
  /** Confidence score 0-1 */
  confidence: number
  /** Parse errors */
  errors: string[]
  /** Generated idempotency key */
  idemKey: string
}

export interface CommandResponse {
  /** Success indicator */
  ok: boolean
  /** Error code (on failure) */
  code?: string
  /** Human-readable message */
  message?: string
  /** Response data (table, record, etc.) */
  data?: TableData | Record<string, unknown>
  /** Additional metadata */
  meta?: Record<string, unknown>
  /** URL to redirect to */
  redirect?: string
  /** Undo action info */
  undo?: UndoAction
  /** Validation errors by field */
  errors?: Record<string, string[]>
  /** Whether this is a replayed idempotent response */
  replayed?: boolean
  /** HTTP status code */
  status?: number
}

export interface UndoAction {
  /** Action identifier */
  action: string
  /** Parameters to reverse the action */
  params: Record<string, unknown>
  /** Unix timestamp when undo expires */
  expiresAt: number
  /** Description of what will be undone */
  message: string
}

// ============================================================================
// Grammar Types
// ============================================================================

export interface EntityDefinition {
  /** Canonical entity name */
  name: string
  /** Short aliases (e.g., ["co", "comp"] for "company") */
  shortcuts: string[]
  /** Available operations */
  verbs: VerbDefinition[]
  /** Default verb when only entity is specified */
  defaultVerb: string
}

export interface VerbDefinition {
  /** Canonical verb name */
  name: string
  /** Alternative names (e.g., ["new", "add"] for "create") */
  aliases: string[]
  /** Flag definitions */
  flags: FlagDefinition[]
  /** Whether positional subject is expected */
  requiresSubject: boolean
}

export interface FlagDefinition {
  /** Flag name (used with --name) */
  name: string
  /** Short form (used with -n) */
  shorthand?: string
  /** Value type */
  type: 'boolean' | 'string' | 'number'
  /** Whether flag must be provided */
  required: boolean
  /** Default value if not provided */
  default?: unknown
}

// ============================================================================
// Output Types
// ============================================================================

export type OutputType = 'input' | 'output' | 'error' | 'success' | 'table'

export interface OutputLine {
  /** Line type for styling */
  type: OutputType
  /** Text content or table data */
  content: string | string[][]
  /** Table headers (if type is 'table') */
  headers?: string[]
  /** Table footer (if type is 'table') */
  footer?: string
}

export interface TableData {
  /** Column headers */
  headers: string[]
  /** Row data (array of cell arrays) */
  rows: string[][]
  /** Footer text */
  footer?: string
}

// ============================================================================
// Suggestion Types
// ============================================================================

export interface Suggestion {
  /** Suggestion category */
  type: 'command' | 'entity' | 'verb' | 'flag' | 'value' | 'history'
  /** Value to insert */
  value: string
  /** Display label */
  label: string
  /** Optional description */
  description?: string
  /** Optional icon */
  icon?: string
  /** Match score for sorting */
  score?: number
  /** Category for grouping */
  category?: string
}

// ============================================================================
// Quick Actions Types
// ============================================================================

export interface QuickAction {
  /** Number key to activate (0-9) */
  key: string
  /** Display label */
  label: string
  /** Command template (e.g., "company switch {slug}") */
  command: string
  /** Does this action need selected row data? */
  needsRow: boolean
  /** Sub-prompt text if additional input needed */
  prompt?: string
}

export interface TableState {
  /** Column headers */
  headers: string[]
  /** Row data */
  rows: string[][]
  /** Currently selected row index */
  selectedRowIndex: number
  /** Which entity this table represents */
  entity: string
  /** Which verb produced this table */
  verb: string
}
