<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
	customer: {
		code?: string | null;
		name: string;
		type: string;
		phone?: string | null;
		email?: string | null;
		tax_code?: string | null;
		company_name?: string | null;
		group_name?: string | null;
		price_list_name?: string | null;
		credit_limit: number;
		default_due_days: number;
		description?: string | null;
		is_active: boolean;
	};
}>();

const customerTypeLabel = computed(() => {
	switch (props.customer.type) {
		case 'wholesale':
			return 'Bán sỉ';
		case 'company':
			return 'Doanh nghiệp';
		default:
			return 'Bán lẻ';
	}
});

const formatCurrency = (value: number | string | null | undefined) => Number(value || 0).toLocaleString('vi-VN');
</script>

<template>
	<section class="rounded-2xl border border-border/70 bg-card p-5">
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<Badge variant="outline">{{ customerTypeLabel }}</Badge>
			<Badge :variant="customer.is_active ? 'default' : 'secondary'">{{ customer.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
		</div>

		<div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
			<div><span class="text-muted-foreground">SĐT:</span> {{ customer.phone || '-' }}</div>
			<div><span class="text-muted-foreground">Email:</span> {{ customer.email || '-' }}</div>
			<div><span class="text-muted-foreground">Công ty:</span> {{ customer.company_name || '-' }}</div>
			<div><span class="text-muted-foreground">MST:</span> {{ customer.tax_code || '-' }}</div>
			<div><span class="text-muted-foreground">Nhóm KH:</span> {{ customer.group_name || '-' }}</div>
			<div><span class="text-muted-foreground">Bảng giá:</span> {{ customer.price_list_name || '-' }}</div>
			<div><span class="text-muted-foreground">Hạn mức:</span> {{ formatCurrency(customer.credit_limit) }}</div>
			<div><span class="text-muted-foreground">Công nợ mặc định:</span> {{ customer.default_due_days }} ngày</div>
			<div class="md:col-span-2"><span class="text-muted-foreground">Mô tả:</span> {{ customer.description || '-' }}</div>
		</div>
	</section>
</template>
