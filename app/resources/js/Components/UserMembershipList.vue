<script setup>
import { Link } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Disclosure, DisclosureButton, DisclosurePanel, Transition } from '@headlessui/vue'

defineProps({
  memberships: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  roleOptions: { type: Array, required: true },
})

defineEmits(['update-role', 'unassign'])
</script>

<template>
  <div class="overflow-hidden bg-white shadow sm:rounded-md">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <div class="font-medium">Company Memberships</div>
    </div>
    <ul role="list" class="divide-y divide-gray-200">
      <li v-for="m in memberships" :key="m.id + ':' + m.slug" class="px-6 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-sm font-medium text-gray-900">{{ m.name }}</div>
            <div class="text-xs text-gray-500">{{ m.slug }}</div>
          </div>
          <div class="flex items-center gap-2">
            <select v-model="m.role" class="rounded border-gray-300 text-sm">
              <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <PrimaryButton @click="$emit('update-role', m)">Update</PrimaryButton>
            <SecondaryButton @click="$emit('unassign', m)">Remove</SecondaryButton>
          </div>
        </div>
        <Disclosure>
          <DisclosureButton class="mt-2 text-xs text-indigo-600 hover:underline">More</DisclosureButton>
          <Transition enter="transition ease-out duration-150" enter-from="opacity-0 -translate-y-1" enter-to="opacity-100 translate-y-0" leave="transition ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0 -translate-y-1">
            <DisclosurePanel class="mt-2 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
              <div class="flex items-center justify-between">
                <div>
                  <div><span class="text-gray-500">Company ID:</span> {{ m.id }}</div>
                  <div><span class="text-gray-500">Slug:</span> {{ m.slug }}</div>
                </div>
                <div class="flex items-center gap-2">
                  <Link :href="route('admin.companies.show', m.slug)" class="text-indigo-600 hover:underline">Open company</Link>
                  <button type="button" class="text-gray-600 hover:text-gray-900" @click="navigator.clipboard?.writeText(m.slug)">Copy slug</button>
                </div>
              </div>
            </DisclosurePanel>
          </Transition>
        </Disclosure>
      </li>
      <li v-if="!loading && memberships.length === 0" class="px-6 py-4 text-sm text-gray-500">No memberships.</li>
      <li v-if="loading" class="px-6 py-4 text-sm text-gray-500">Loading…</li>
    </ul>
  </div>
</template>
