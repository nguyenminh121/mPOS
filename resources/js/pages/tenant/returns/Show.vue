<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';
const props = defineProps<{ returnOrder: any }>();
</script>

<template>
    <Head :title="`Return ${props.returnOrder.code}`" />
    <TenantLayout>
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Return Detail</h1>
                <div class="space-x-3">
                    <Link :href="tenantRoutes.returns.index()" class="underline">Back</Link>
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-4 border rounded p-4 text-sm">
                <div><span class="font-medium">Code:</span> {{ props.returnOrder.code }}</div>
                <div><span class="font-medium">Status:</span> {{ props.returnOrder.status }}</div>
                <div><span class="font-medium">Amount:</span> {{ props.returnOrder.total_amount }}</div>
                <div><span class="font-medium">Order:</span> {{ props.returnOrder.order?.code || '-' }}</div>
                <div><span class="font-medium">Customer:</span> {{ props.returnOrder.customer?.name || '-' }}</div>
                <div><span class="font-medium">Refund Method:</span> {{ props.returnOrder.refund_method || '-' }}</div>
            </div>
            <div class="border rounded overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40"><tr><th class="p-3 text-left">Variant</th><th class="p-3 text-left">Qty</th><th class="p-3 text-left">Refund</th><th class="p-3 text-left">Condition</th></tr></thead>
                    <tbody>
                        <tr v-for="item in props.returnOrder.items" :key="item.id" class="border-t">
                            <td class="p-3">{{ item.product_variant?.name || item.product_variant_id }}</td>
                            <td class="p-3">{{ item.quantity }}</td>
                            <td class="p-3">{{ item.refund_amount }}</td>
                            <td class="p-3">{{ item.condition || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
