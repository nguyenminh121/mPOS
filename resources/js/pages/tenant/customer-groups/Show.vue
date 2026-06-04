<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

defineProps<{
	customerGroup: {
		id: number;
		code: string;
		name: string;
		description?: string | null;
		default_price_list_name?: string | null;
		customers_count: number;
		is_active: boolean;
	};
}>();
</script>

<template>
	<Head title="Chi tiết nhóm khách hàng" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Button as-child variant="outline" size="icon">
						<Link :href="tenantRoutes.customerGroups.index()"><ArrowLeft class="size-5" /></Link>
					</Button>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">{{ customerGroup.name }}</h1>
						<p class="text-sm text-muted-foreground">Mã nhóm: {{ customerGroup.code }}</p>
					</div>
				</div>
				<Button as-child variant="outline">
					<Link :href="tenantRoutes.customerGroups.edit(customerGroup.id)"><Pencil class="size-4" />Sửa</Link>
				</Button>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<div class="mb-4 flex flex-wrap items-center gap-2">
					<Badge variant="outline">Mã: {{ customerGroup.code }}</Badge>
					<Badge :variant="customerGroup.is_active ? 'default' : 'secondary'">{{ customerGroup.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
				</div>

				<div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
					<div><span class="text-muted-foreground">Số khách hàng:</span> <span class="font-medium">{{ customerGroup.customers_count }}</span></div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Bảng giá mặc định:</span> {{ customerGroup.default_price_list_name || '-' }}</div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Mô tả:</span> {{ customerGroup.description || '-' }}</div>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
