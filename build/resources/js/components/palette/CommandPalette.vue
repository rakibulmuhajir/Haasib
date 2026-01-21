<script setup lang="ts">
import { Transition } from 'vue'
import PaletteHeader from '@/components/palette/PaletteHeader.vue'
import PaletteOutput from '@/components/palette/PaletteOutput.vue'
import { useCommandPalette, type CommandPaletteProps } from '@/composables/palette/useCommandPalette'

const props = defineProps<CommandPaletteProps>()
const emit = defineEmits<{
  'update:visible': [v: boolean]
  'company-switched': [company: { id: string; name: string; slug: string; base_currency: string }]
}>()

const {
  activeChipFieldType,
  activeChipIndex,
  activeCompany,
  activateChip,
  canExecute,
  chipPickerHeader,
  chipSuggestionIndex,
  chipSuggestions,
  chipSuggestionsLoading,
  chips,
  clearImageField,
  clearInput,
  close,
  closeSubPrompt,
  confirmSubPrompt,
  entitySearchQuery,
  entityVerbInput,
  entityVerbInputEl,
  executing,
  formatText,
  getChipIndex,
  getQuickActionLabel,
  handleChipClick,
  handleChipInput,
  handleChipKeydown,
  handleEntityVerbKeydown,
  handleImageSelect,
  handleQuickAction,
  optionalChips,
  output,
  parseFormatTags,
  missingRequiredChips,
  quickActions,
  requiredChips,
  selectChipSuggestion,
  selectRow,
  selectSidebarItem,
  selectSuggestion,
  setChipInputRef,
  showChipPicker,
  showQuickActions,
  showSidebar,
  showSubPrompt,
  showSuggestions,
  sidebarIndex,
  sidebarItems,
  sidebarTitle,
  stage,
  statusMessage,
  subPromptAction,
  subPromptInput,
  subPromptInputEl,
  suggestionIndex,
  suggestions,
  tableState,
} = useCommandPalette(props, emit)
</script>

<template>
  <Teleport to="body">
    <!-- Animated Backdrop & Palette Wrapper -->
    <Transition name="palette">
      <div v-if="visible" class="palette-wrapper" @click.self="close">
        <!-- Backdrop -->
        <div class="palette-backdrop" @click="close" />

        <!-- Palette Modal -->
        <div class="palette palette-modal">
      <!-- Header -->
      <PaletteHeader :active-company="activeCompany" :stage="stage" />

      <!-- Main body with sidebar -->
      <div class="palette-body">
        <!-- Main content area -->
        <div class="palette-main">
          <!-- Output Area -->
          <PaletteOutput
            :output="output"
            :table-state="tableState"
            :format-text="formatText"
            :parse-format-tags="parseFormatTags"
            @select-row="selectRow"
          />

          <!-- Input Area -->
          <div class="palette-input-area">
            <!-- Line 1: Command + Required Args (inline) -->
            <div class="palette-input-row">
              <span class="palette-prompt" :class="{ 'palette-prompt--busy': executing }">
                {{ executing ? '⋯' : '❯' }}
              </span>

              <!-- Entity/Verb Input -->
              <input
                ref="entityVerbInputEl"
                v-model="entityVerbInput"
                type="text"
                class="palette-entity-verb-input"
                :class="{ 'palette-entity-verb-input--has-chips': stage === 'chips' }"
                :disabled="executing"
                :placeholder="stage === 'entity' ? 'entity...' : stage === 'verb' ? 'action...' : ''"
                autocomplete="off"
                autocorrect="off"
                spellcheck="false"
                @keydown="handleEntityVerbKeydown"
              />

              <!-- Required Args (inline on same line as command) -->
              <template v-if="stage === 'chips'">
                <div
                  v-for="(chip, index) in requiredChips"
                  :key="chip.name"
                  class="field field--inline field--animated"
                  :class="{
                    'field--active': chip.isActive,
                    'field--filled': chip.status === 'filled',
                    'field--error': chip.status === 'error',
                  }"
                  :style="{ animationDelay: `${index * 30}ms` }"
                  @click="handleChipClick(getChipIndex(chip))"
                >
                  <span class="field-label">{{ chip.name }}</span>
                  <template v-if="chip.searchEntity && chip.displayLabel && !chip.isActive">
                    <span class="field-value">{{ chip.displayLabel }}</span>
                  </template>
                  <input
                    v-else
                    :ref="(el) => setChipInputRef(getChipIndex(chip), el as HTMLInputElement)"
                    :type="chip.inputType === 'number' ? 'number' : chip.inputType === 'email' ? 'email' : 'text'"
                    class="field-input"
                    :value="chip.isActive && chip.searchEntity ? entitySearchQuery : (chip.isActive ? chip.value : (chip.displayLabel || chip.value))"
                    :placeholder="chip.isActive ? (chip.placeholder || '') : ''"
                    @input="handleChipInput(getChipIndex(chip), ($event.target as HTMLInputElement).value)"
                    @keydown="handleChipKeydown($event, getChipIndex(chip))"
                    @focus="activateChip(getChipIndex(chip))"
                  />
                </div>
              </template>

              <!-- Clear button -->
              <button
                v-if="entityVerbInput || chips.length > 0"
                class="palette-clear-btn"
                @click="clearInput"
                title="Clear input (Ctrl+L)"
                tabindex="-1"
              >
                ✕
              </button>
            </div>

            <!-- Line 2: Optional Flags (with -- prefix) -->
            <div v-if="stage === 'chips' && optionalChips.length > 0" class="palette-flags-row">
              <div
                v-for="(chip, index) in optionalChips"
                :key="chip.name"
                class="field field--inline field--optional field--animated"
                :class="{
                  'field--active': chip.isActive,
                  'field--filled': chip.status === 'filled',
                  'field--error': chip.status === 'error',
                }"
                :style="{ animationDelay: `${(requiredChips.length + index) * 30}ms` }"
                @click="handleChipClick(getChipIndex(chip))"
              >
                <span class="field-label">{{ chip.name }}</span>
                <!-- Image field -->
                <template v-if="chip.inputType === 'image'">
                  <span v-if="chip.file" class="field-value field-value--file">{{ chip.file.name }}</span>
                  <label v-else class="field-file-picker" :class="{ 'field-file-picker--active': chip.isActive }">
                    <span class="field-file-placeholder">{{ chip.placeholder || 'select' }}</span>
                    <input
                      type="file"
                      :accept="chip.accept"
                      class="field-file-input"
                      @change="handleImageSelect(getChipIndex(chip), $event)"
                      @focus="activateChip(getChipIndex(chip))"
                    />
                  </label>
                  <button v-if="chip.file" class="field-clear-btn" @click="clearImageField(getChipIndex(chip))" tabindex="-1">✕</button>
                </template>
                <!-- Value display -->
                <template v-else-if="chip.searchEntity && chip.displayLabel && !chip.isActive">
                  <span class="field-value">{{ chip.displayLabel }}</span>
                </template>
                <!-- Input -->
                <input
                  v-else
                  :ref="(el) => setChipInputRef(getChipIndex(chip), el as HTMLInputElement)"
                  :type="chip.inputType === 'number' ? 'number' : chip.inputType === 'email' ? 'email' : 'text'"
                  class="field-input"
                  :value="chip.isActive && chip.searchEntity ? entitySearchQuery : (chip.isActive ? chip.value : (chip.displayLabel || chip.value))"
                  :placeholder="chip.isActive ? (chip.placeholder || '') : ''"
                  @input="handleChipInput(getChipIndex(chip), ($event.target as HTMLInputElement).value)"
                  @keydown="handleChipKeydown($event, getChipIndex(chip))"
                  @focus="activateChip(getChipIndex(chip))"
                />
              </div>
            </div>

            <!-- Vertical Dropdown (for entity/verb suggestions) -->
            <div v-if="showSuggestions && suggestions.length > 0" class="palette-dropdown">
              <button
                v-for="(suggestion, index) in suggestions"
                :key="suggestion.value"
                class="dropdown-item"
                :class="{ 'dropdown-item--selected': index === suggestionIndex }"
                @click="selectSuggestion(index)"
                @mouseenter="suggestionIndex = index"
              >
                <span class="dropdown-label">{{ suggestion.label }}</span>
                <span v-if="suggestion.description" class="dropdown-meta">{{ suggestion.description }}</span>
              </button>
            </div>

            <!-- Status Bar -->
            <div class="palette-status">
              <span class="status-message" :class="{ 'status-ready': canExecute }">
                {{ statusMessage }}
              </span>
              <span v-if="missingRequiredChips.length" class="status-pill">
                Required: {{ missingRequiredChips.map((chip) => chip.name).join(', ') }}
              </span>
              <div class="status-keys">
                <template v-if="stage === 'chips'">
                  <span><kbd>Tab</kbd> next</span>
                  <span><kbd>↑↓</kbd> dropdown</span>
                  <span><kbd>Enter</kbd> {{ canExecute ? 'execute' : 'next' }}</span>
                </template>
                <template v-else-if="tableState">
                  <span><kbd>↑↓</kbd> select row</span>
                  <span><kbd>1-9</kbd> actions</span>
                </template>
                <template v-else>
                  <span><kbd>Tab</kbd> select</span>
                  <span><kbd>←→</kbd> navigate</span>
                </template>
                <span><kbd>Ctrl+L</kbd> clear</span>
                <span><kbd>Esc</kbd> close</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div v-if="showQuickActions" class="palette-sidebar">
          <div class="sidebar-header">
            Quick Actions
            <span v-if="tableState" class="sidebar-debug">
              ({{ tableState.entity }}.{{ tableState.verb }})
            </span>
          </div>
          <div class="sidebar-actions">
            <div
              v-for="(action, index) in quickActions"
              :key="action.key"
              class="sidebar-action sidebar-action--animated"
              :class="{
                'sidebar-action--needs-row': action.needsRow && !tableState,
                'sidebar-action--disabled': action.needsRow && (!tableState || tableState.selectedRowIndex < 0)
              }"
              :style="{ animationDelay: `${index * 25}ms` }"
              @click="handleQuickAction(action)"
              :aria-label="`Quick action ${action.key}: ${getQuickActionLabel(action, tableState)}`"
            >
              <span class="action-key">{{ action.key }}</span>
              <span class="action-label">{{ getQuickActionLabel(action, tableState) }}</span>
            </div>
          </div>
          <div class="sidebar-hint">
            Press the shown number keys (0-9)
            <template v-if="tableState">
              <br />
              <span class="hint-small">Use ↑↓ to select row</span>
            </template>
          </div>
        </div>

        <!-- Chip Picker Sidebar (iOS wheel style) -->
        <div v-else-if="showChipPicker" class="palette-sidebar">
          <div class="sidebar-header">{{ chipPickerHeader }}</div>

          <!-- Date quick picks -->
          <div v-if="activeChipFieldType === 'date'" class="sidebar-picker">
            <button
              v-for="(item, index) in sidebarItems"
              :key="item.value"
              class="picker-item"
              :class="{ 'picker-item--selected': index === sidebarIndex }"
              :data-distance="Math.min(Math.abs(index - sidebarIndex), 4)"
              @click="selectSidebarItem(index)"
              @mouseenter="sidebarIndex = index"
            >
              <kbd class="picker-key">{{ index + 1 }}</kbd>
              <span class="picker-label">{{ item.label }}</span>
            </button>
            <div class="picker-hint">Or type: dd/mm/yyyy</div>
          </div>

          <!-- Select/Lookup/Search picker -->
          <template v-else>
            <!-- Loading state for search -->
            <div v-if="chipSuggestionsLoading" class="sidebar-loading">
              <span class="sidebar-spinner">…</span> Searching
            </div>

            <!-- Results with iOS wheel effect -->
            <div v-else-if="chipSuggestions.length > 0" class="sidebar-picker">
              <button
                v-for="(suggestion, index) in chipSuggestions"
                :key="suggestion.value"
                class="picker-item"
                :class="{ 'picker-item--selected': index === chipSuggestionIndex }"
                :data-distance="Math.min(Math.abs(index - chipSuggestionIndex), 4)"
                @click="selectChipSuggestion(index)"
                @mouseenter="chipSuggestionIndex = index"
              >
                <span class="picker-label">{{ suggestion.label }}</span>
                <span v-if="suggestion.meta" class="picker-meta">{{ suggestion.meta }}</span>
              </button>
            </div>

            <!-- Empty/hint state -->
            <div v-else class="sidebar-empty">
              <template v-if="chips[activeChipIndex]?.inputType === 'search'">
                <template v-if="entitySearchQuery.length > 0 && entitySearchQuery.length < 2">
                  Type at least 2 characters
                </template>
                <template v-else>
                  Start typing to search
                </template>
              </template>
              <template v-else>
                No options available
              </template>
            </div>
          </template>

          <div class="sidebar-hint">
            <kbd>↑↓</kbd> navigate · <kbd>Enter</kbd> select
          </div>
        </div>

        <!-- Sidebar with quick picks (legacy for other use cases) -->
        <div v-else-if="showSidebar" class="palette-sidebar">
          <div class="sidebar-header">{{ sidebarTitle }}</div>
          <div class="sidebar-items">
            <button
              v-for="(item, index) in sidebarItems"
              :key="item.value"
              class="sidebar-item sidebar-item--animated"
              :class="{ 'sidebar-item--selected': index === sidebarIndex }"
              :style="{ animationDelay: `${index * 25}ms` }"
              @click="selectSidebarItem(index)"
              @mouseenter="sidebarIndex = index"
            >
              <kbd class="sidebar-key">{{ index + 1 }}</kbd>
              <span v-if="item.icon" class="sidebar-icon">{{ item.icon }}</span>
              <span class="sidebar-label">{{ item.label }}</span>
              <span v-if="item.meta" class="sidebar-meta">{{ item.meta }}</span>
            </button>
          </div>
          <div class="sidebar-hint">
            Press <kbd>1</kbd>-<kbd>9</kbd> to select
          </div>
        </div>
      </div>
        </div>
      </div>
    </Transition>

    <!-- Sub-Prompt Modal -->
    <div v-if="showSubPrompt && subPromptAction" class="subprompt-backdrop" @click="closeSubPrompt">
      <div class="subprompt-modal" @click.stop>
        <div class="subprompt-header">
          <span class="subprompt-title">{{ subPromptAction.label }}</span>
          <button class="subprompt-close" @click="closeSubPrompt">✕</button>
        </div>
        <div class="subprompt-body">
          <p class="subprompt-prompt">{{ subPromptAction.prompt }}</p>
          <input
            ref="subPromptInputEl"
            v-model="subPromptInput"
            type="text"
            class="subprompt-input"
            placeholder="Enter value..."
            @keydown.enter="confirmSubPrompt"
            @keydown.esc="closeSubPrompt"
          />
        </div>
        <div class="subprompt-footer">
          <button class="subprompt-btn subprompt-btn--cancel" @click="closeSubPrompt">
            Cancel
          </button>
          <button class="subprompt-btn subprompt-btn--confirm" @click="confirmSubPrompt">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* ============================================================================
   Animation Foundation
   ============================================================================ */

/* Custom Properties for Timing - defined on wrapper element for cascading */

/* Keyframes - simplified to opacity only (htop/btop style) */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}

/* Stagger animation for fields/items */
@keyframes fieldEnter {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Entrance/Exit Transition Classes */
.palette-enter-active {
  animation: fadeIn var(--palette-duration-normal) ease-out;
}

.palette-leave-active {
  animation: fadeOut var(--palette-duration-fast) ease-in;
}

.palette-enter-active .palette {
  animation: fadeIn var(--palette-duration-normal) ease-out;
}

.palette-leave-active .palette {
  opacity: 0;
  transition: opacity var(--palette-duration-fast) ease-in;
}

/* ============================================================================
   Base Styles
   ============================================================================ */

/* Base */
.palette-wrapper {
  /* Animation timing variables */
  --palette-duration-fast: 100ms;
  --palette-duration-normal: 150ms;
  --palette-duration-slow: 200ms;
  --palette-ease: cubic-bezier(0.16, 1, 0.3, 1); /* ease-out-expo */
  --palette-ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1); /* slight overshoot */

  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 10vh;
}

.palette-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
}

.palette {
  position: relative;
  width: 80vw;
  max-width: 1200px;
  height: 80vh;
  max-height: 80vh;
  background: #0f172a;
  border: 1px solid #334155;
  display: flex;
  flex-direction: column;
  z-index: 1;
  overflow: hidden;
}

/* Body layout with sidebar */
.palette-body {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.palette-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

/* Sidebar */
.palette-sidebar {
  width: 240px;
  background: rgba(15, 23, 42, 0.95);
  border-left: 1px solid #334155;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 12px 16px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  border-bottom: 1px solid #1e293b;
}

.sidebar-items {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.sidebar-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  background: transparent;
  border: none;
  border-left: 2px solid transparent;
  cursor: pointer;
  text-align: left;
  transition: color var(--palette-duration-fast) ease;
  margin-bottom: 2px;
}

/* Staggered entrance animation for sidebar items */
.sidebar-item--animated {
  animation: fieldEnter var(--palette-duration-normal) ease-out backwards;
}

.sidebar-item:hover,
.sidebar-item--selected {
  border-left-color: #22d3ee;
  color: #22d3ee;
}

.sidebar-item--selected {
  border-left-color: #22d3ee;
}

.sidebar-key {
  color: #fbbf24;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
  font-family: ui-monospace, monospace;
}

.sidebar-icon {
  font-size: 14px;
  flex-shrink: 0;
}

.sidebar-label {
  color: #e2e8f0;
  font-size: 12px;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-item--selected .sidebar-label {
  color: #22d3ee;
}

.sidebar-meta {
  color: #64748b;
  font-size: 10px;
  flex-shrink: 0;
}

.sidebar-hint {
  padding: 10px 16px;
  font-size: 10px;
  color: #64748b;
  border-top: 1px solid #1e293b;
  text-align: center;
}

.sidebar-hint kbd {
  color: #94a3b8;
  font-size: 9px;
  margin: 0 2px;
  font-family: ui-monospace, monospace;
}

/* iOS Wheel Picker Styles */
.sidebar-picker {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0;
  display: flex;
  flex-direction: column;
}

.sidebar-loading,
.sidebar-empty {
  padding: 20px 16px;
  text-align: center;
  color: #64748b;
  font-size: 12px;
}

.sidebar-spinner {
  display: inline-block;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Picker item with distance-based opacity (iOS wheel effect) */
.picker-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 16px;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: opacity 0.15s ease, color 0.15s ease;
  color: #e2e8f0;
  font-family: ui-monospace, monospace;
  font-size: 13px;
}

/* Distance-based opacity for iOS wheel effect */
.picker-item[data-distance="0"] { opacity: 1; }
.picker-item[data-distance="1"] { opacity: 0.7; }
.picker-item[data-distance="2"] { opacity: 0.4; }
.picker-item[data-distance="3"] { opacity: 0.2; }
.picker-item[data-distance="4"] { opacity: 0.1; }

.picker-item:hover {
  opacity: 1;
  color: #22d3ee;
}

.picker-item--selected {
  color: #22d3ee;
  opacity: 1;
}

.picker-item--selected::before {
  content: '›';
  margin-right: 4px;
  color: #22d3ee;
}

.picker-key {
  color: #fbbf24;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.picker-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.picker-meta {
  color: #64748b;
  font-size: 11px;
  flex-shrink: 0;
}

.picker-item--selected .picker-meta {
  color: #94a3b8;
}

.picker-hint {
  padding: 8px 16px;
  font-size: 10px;
  color: #475569;
  font-style: italic;
}

/* Input Area */
.palette-input-area {
  border-top: 1px solid #334155;
}

.palette-input-row {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  gap: 8px;
  overflow-x: auto;
}

.palette-prompt {
  color: #22d3ee;
  font-weight: 600;
  font-size: 16px;
  flex-shrink: 0;
}

.palette-prompt--busy {
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.palette-entity-verb-input {
  background: transparent;
  border: none;
  outline: none;
  color: #e2e8f0;
  font-size: 14px;
  font-family: ui-monospace, monospace;
  width: 100%;
  flex: 1;
}

.palette-entity-verb-input--has-chips {
  width: auto;
  flex-shrink: 0;
  color: #94a3b8;
}

.palette-entity-verb-input::placeholder {
  color: #475569;
}

/* Clear button */
.palette-clear-btn {
  flex-shrink: 0;
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 12px;
  cursor: pointer;
  transition: color 0.15s;
  margin-left: 8px;
  font-family: ui-monospace, monospace;
}

.palette-clear-btn:hover {
  color: #ef4444;
}

/* Flags row (Line 2 - optional flags with -- prefix) */
.palette-flags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 4px 16px 8px 28px; /* Indent to align with content after prompt */
  font-family: ui-monospace, monospace;
}

/* Field base styles */
.field {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: ui-monospace, monospace;
  font-size: 13px;
  transition: opacity var(--palette-duration-fast) ease;
}

/* Inline fields (for command line layout) */
.field--inline {
  display: inline-flex;
  flex-shrink: 0;
}

.field--inline .field-label {
  min-width: auto; /* No min-width for inline */
}

.field--inline .field-input {
  width: auto;
  min-width: 60px;
  max-width: 150px;
}

/* Staggered entrance animation for fields */
.field--animated {
  animation: fieldEnter var(--palette-duration-normal) ease-out backwards;
}

/* Field states - color only, no boxes */
.field--active .field-label {
  color: #22d3ee;
}

.field--filled .field-label {
  color: #10b981;
}

.field--error .field-label {
  color: #f43f5e;
}

.field--optional {
  opacity: 0.6;
}

.field--optional .field-label::before {
  content: '--';
  color: #475569;
}

.field-label {
  color: #94a3b8;
  flex-shrink: 0;
}

.field-label::after {
  content: ':';
}

.field-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #f8fafc;
  font-size: 13px;
  font-family: ui-monospace, monospace;
}

.field-input::placeholder {
  color: #475569;
}

.field--active .field-input {
  color: #22d3ee;
}

.field-value {
  color: #f8fafc;
}

.field-value--file {
  color: #10b981;
  font-style: italic;
}

/* File picker for image fields */
.field-file-picker {
  cursor: pointer;
  color: #64748b;
  display: inline-flex;
  align-items: center;
}

.field-file-picker--active {
  color: #22d3ee;
}

.field-file-placeholder {
  font-size: 13px;
}

.field-file-input {
  display: none;
}

.field-clear-btn {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  font-size: 10px;
  padding: 0 4px;
  margin-left: 4px;
  transition: color 0.15s;
}

.field-clear-btn:hover {
  color: #f43f5e;
}

/* Format hint for date fields */
.field-format-hint {
  color: #475569;
  font-size: 11px;
  margin-left: 8px;
  font-style: italic;
}

/* Dropdown indicator (chevron) */
.field-dropdown-btn {
  background: transparent;
  border: none;
  color: #475569;
  cursor: pointer;
  font-size: 10px;
  transition: color 0.15s;
}

.field-dropdown-btn:hover,
.field-dropdown-btn--open {
  color: #22d3ee;
}

/* Vertical Dropdown (fzf-style) */
.palette-dropdown {
  display: inline-flex;
  flex-direction: column;
  margin: 8px 16px;
  border: 1px solid #334155;
  max-height: 200px;
  overflow-y: auto;
  animation: fieldEnter var(--palette-duration-normal) ease-out;
  font-family: ui-monospace, monospace;
  width: fit-content;
  min-width: 120px;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 12px;
  cursor: pointer;
  transition: color var(--palette-duration-fast) ease;
  color: #f8fafc;
  white-space: nowrap;
}

.dropdown-item:hover {
  color: #22d3ee;
}

.dropdown-item--selected {
  color: #22d3ee;
}

.dropdown-item--selected::before {
  content: '›';
  margin-right: 4px;
}

.dropdown-label {
  color: inherit;
  font-size: 13px;
}

.dropdown-meta {
  color: #64748b;
  font-size: 11px;
}

/* Field Dropdown (for enum values and entity search) */
.field-dropdown {
  display: inline-flex;
  flex-direction: column;
  margin: 4px 16px 8px 116px; /* Align with field values */
  border: 1px solid #334155;
  max-height: 200px;
  overflow-y: auto;
  animation: fieldEnter var(--palette-duration-normal) ease-out;
  font-family: ui-monospace, monospace;
  width: fit-content;
  min-width: 120px;
}

.field-dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 12px;
  cursor: pointer;
  transition: color var(--palette-duration-fast) ease;
  color: #f8fafc;
  white-space: nowrap;
}

.field-dropdown-item:hover,
.field-dropdown-item--selected {
  color: #22d3ee;
}

.field-dropdown-item--selected::before {
  content: '›';
  margin-right: 4px;
}

.field-dropdown-loading {
  padding: 12px 16px;
  color: #64748b;
  font-size: 12px;
}

.field-dropdown-empty {
  padding: 12px 16px;
  color: #475569;
  font-size: 12px;
}

.field-dropdown-spinner {
  display: inline-block;
  color: #22d3ee;
  animation: pulse 1s infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Entity field styling */
.field--entity .field-value {
  color: #a78bfa;
}

/* Status Bar */
.palette-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  border-top: 1px solid #1e293b;
  font-size: 11px;
  font-family: ui-monospace, monospace;
}

.status-message {
  color: #f59e0b;
}

.status-ready {
  color: #10b981;
}

.status-pill {
  background: #0f172a;
  color: #e2e8f0;
  border: 1px solid #1f2937;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 10px;
  margin-left: 12px;
}

.status-keys {
  display: flex;
  gap: 12px;
  color: #64748b;
}

.status-keys kbd {
  color: #94a3b8;
  font-size: 10px;
  margin-right: 4px;
}

/* Quick Actions Sidebar */
.sidebar-actions {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.sidebar-action {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  margin-bottom: 2px;
  background: transparent;
  border: none;
  border-left: 2px solid transparent;
  cursor: pointer;
  transition: color var(--palette-duration-fast) ease;
  color: #e2e8f0;
}

/* Staggered entrance animation for quick actions */
.sidebar-action--animated {
  animation: fieldEnter var(--palette-duration-normal) ease-out backwards;
}

.sidebar-action:hover {
  border-left-color: #22d3ee;
  color: #22d3ee;
}

.sidebar-action--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.sidebar-action--disabled:hover {
  border-left-color: transparent;
  color: inherit;
}

.action-key {
  color: #fbbf24;
  font-weight: 600;
  font-size: 12px;
  flex-shrink: 0;
  font-family: ui-monospace, monospace;
  min-width: 16px;
}

.action-label {
  flex: 1;
  color: inherit;
  font-size: 12px;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-debug {
  display: block;
  font-size: 10px;
  color: #fbbf24;
  text-transform: none;
  margin-top: 4px;
  font-weight: normal;
}

.hint-small {
  font-size: 10px;
  opacity: 0.7;
}

/* Sub-prompt modal */
.subprompt-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.subprompt-modal {
  background: #0f172a;
  border: 1px solid #334155;
  max-width: 400px;
  width: 90%;
}

.subprompt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #334155;
}

.subprompt-title {
  font-weight: 600;
  color: #e2e8f0;
}

.subprompt-close {
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 16px;
  cursor: pointer;
  padding: 4px;
}

.subprompt-body {
  padding: 20px;
}

.subprompt-prompt {
  margin: 0 0 16px 0;
  color: #94a3b8;
  font-size: 14px;
}

.subprompt-input {
  width: 100%;
  background: transparent;
  border: 1px solid #334155;
  color: #e2e8f0;
  padding: 10px 12px;
  font-size: 14px;
  font-family: ui-monospace, monospace;
}

.subprompt-input:focus {
  outline: none;
  border-color: #22d3ee;
}

.subprompt-footer {
  display: flex;
  gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid #334155;
  justify-content: flex-end;
}

.subprompt-btn {
  padding: 8px 16px;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid #334155;
  background: transparent;
  transition: color 0.15s;
  font-family: ui-monospace, monospace;
}

.subprompt-btn--cancel {
  color: #64748b;
}

.subprompt-btn--cancel:hover {
  color: #94a3b8;
}

.subprompt-btn--confirm {
  color: #22d3ee;
  border-color: #22d3ee;
}

.subprompt-btn--confirm:hover {
  color: #06b6d4;
}

/* ============================================================================
   Accessibility: Reduced Motion Support
   ============================================================================ */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
