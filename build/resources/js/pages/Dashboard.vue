<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { dashboard } from '@/routes'
import type { BreadcrumbItem } from '@/types'
import { Building2, Plus } from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard().url }]
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto max-w-xl py-12">
      <Card variant="detail">
        <CardHeader>
          <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-lg bg-muted">
            <Building2 class="h-5 w-5 text-muted-foreground" />
          </div>
          <CardTitle>Create your first company</CardTitle>
          <CardDescription>You do not currently belong to a company.</CardDescription>
        </CardHeader>
        <CardContent>
          <Button @click="router.visit('/companies/create')">
            <Plus class="mr-2 h-4 w-4" /> Create company
          </Button>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
