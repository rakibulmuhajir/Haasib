<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { OutputLine, TableState } from '@/types/palette'

const props = defineProps<{
  output: OutputLine[]
  tableState: TableState | null
  formatText: (text: string) => string
  parseFormatTags: (text: string) => string
}>()

const emit = defineEmits<{
  'select-row': [index: number]
}>()

const outputRef = ref<HTMLDivElement | null>(null)

watch(
  () => props.output.length,
  () => {
    nextTick(() => {
      if (outputRef.value) {
        outputRef.value.scrollTop = outputRef.value.scrollHeight
      }
    })
  },
  { immediate: true },
)

function handleRowClick(index: number) {
  emit('select-row', index)
}
</script>

<template>
  <div ref="outputRef" class="palette-output">
    <template v-if="output.length === 0">
      <div class="palette-empty">
        Type a command (e.g., <span class="palette-cmd">invoice create</span>)
      </div>
    </template>
    <template v-for="(line, i) in output" :key="i">
      <div v-if="line.type === 'table'" class="palette-table">
        <div class="table-wrapper">
          <div v-if="line.headers?.length" class="table-header">
            <div v-for="(header, ci) in line.headers" :key="ci" class="table-cell table-cell--header">
              {{ header }}
            </div>
          </div>
          <div
            v-for="(row, ri) in (line.content as string[][])"
            :key="ri"
            class="table-row"
            :class="{
              'table-row--selected': tableState && i === output.length - 1 && ri === tableState.selectedRowIndex
            }"
            @click="handleRowClick(ri)"
          >
            <div v-for="(cell, ci) in row" :key="ci" class="table-cell" v-html="parseFormatTags(cell)"></div>
          </div>
        </div>
      </div>
      <div
        v-else
        class="palette-line"
        :class="{
          'palette-line--input': line.type === 'input',
          'palette-line--error': line.type === 'error',
          'palette-line--success': line.type === 'success',
        }"
        v-html="formatText(String(line.content))"
      />
    </template>
  </div>
</template>

<style scoped>
.palette-output {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px 16px;
  font-family: ui-monospace, monospace;
  font-size: 13px;
}

.palette-empty {
  color: #64748b;
  text-align: center;
  padding: 24px;
}

.palette-cmd {
  color: #22d3ee;
  font-family: ui-monospace, monospace;
}

.palette-line {
  padding: 4px 0;
  color: #e2e8f0;
  animation: fieldEnter var(--palette-duration-fast) ease-out;
}

.palette-line--input {
  color: #64748b;
}

.palette-line--error {
  color: #f87171;
}

.palette-line--success {
  color: #10b981;
}

.palette-table {
  margin: 8px 0;
  animation: fieldEnter var(--palette-duration-fast) ease-out;
}

.table-wrapper {
  overflow-x: auto;
}

.table-header {
  display: flex;
  border-bottom: 1px solid #475569;
}

.table-row {
  display: flex;
  border-bottom: 1px solid #1e293b;
  cursor: pointer;
  transition: color var(--palette-duration-fast) ease;
}

.table-row:hover {
  color: #22d3ee;
}

.table-cell {
  padding: 8px 12px;
  flex: 1;
  min-width: 80px;
  color: #f8fafc;
  font-family: ui-monospace, monospace;
}

.table-cell--header {
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}

.table-cell .cell-success {
  color: #10b981;
}

.table-cell .cell-warning {
  color: #f59e0b;
}

.table-cell .cell-error {
  color: #f87171;
}

.table-cell .cell-secondary {
  color: #64748b;
}

.table-cell .cell-muted {
  color: #475569;
}

.table-cell .cell-info {
  color: #22d3ee;
}

.table-cell .cell-primary {
  color: #a78bfa;
}

.table-row--selected {
  color: #22d3ee;
  border-left: 2px solid #22d3ee;
}

.table-row--selected::before {
  content: '›';
  color: #22d3ee;
  margin-right: 4px;
}
</style>
