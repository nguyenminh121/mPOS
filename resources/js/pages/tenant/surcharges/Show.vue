<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

const props = defineProps<{
	surcharge: {
		id: number;
		code: string;
		name: string;
		description?: string | null;
		type: 'PERCENT' | 'FIXED_AMOUNT';
		value: number;
		is_auto_apply: boolean;
		is_refundable: boolean;
		is_active: boolean;
		category?: string | null;
		created_by_name?: string | null;
		created_at?: string | null;
	};
}>();

const formatValue = () => {
	if (props.surcharge.type === 'PERCENT') {
		return `${props.surcharge.value}%`;
	}

	return `${props.surcharge.value.toLocaleString('vi-VN')} đ`;
};
</script>

<template>
	<Head title="Chi tiết chi phí phát sinh" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Button as-child variant="outline" size="icon">
						<Link :href="tenantRoutes.surcharges.index()"><ArrowLeft class="size-5" /></Link>
					</Button>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">{{ surcharge.name }}</h1>
						<p class="text-sm text-muted-foreground">Mã: {{ surcharge.code }}</p>
					</div>
				</div>
				<Button as-child variant="outline">
					<Link :href="tenantRoutes.surcharges.edit(surcharge.id)"><Pencil class="size-4" />Sửa</Link>
				</Button>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<div class="mb-4 flex flex-wrap items-center gap-2">
					<Badge variant="outline">{{ surcharge.type === 'PERCENT' ? 'Phần trăm' : 'Số tiền cố định' }}</Badge>
					<Badge :variant="surcharge.is_active ? 'default' : 'secondary'">{{ surcharge.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
					<Badge v-if="surcharge.is_auto_apply" variant="outline">Tự động áp dụng</Badge>
					<Badge v-if="surcharge.is_refundable" variant="outline">Hoàn trả được</Badge>
				</div>

				<div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
					<div><span class="text-muted-foreground">Giá trị:</span> {{ formatValue() }}</div>
					<div><span class="text-muted-foreground">Phân loại:</span> {{ surcharge.category || '-' }}</div>
					<div><span class="text-muted-foreground">Người tạo:</span> {{ surcharge.created_by_name || '-' }}</div>
					<div><span class="text-muted-foreground">Ngày tạo:</span> {{ surcharge.created_at || '-' }}</div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Mô tả:</span> {{ surcharge.description || '-' }}</div>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
