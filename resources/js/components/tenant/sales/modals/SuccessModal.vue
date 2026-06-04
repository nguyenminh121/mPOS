<script setup lang="ts">
import { CheckCircle2, ReceiptText } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
	open: boolean;
	totalAmount: number;
	amountReceived: number;
	changeAmount: number;
	itemCount: number;
	customerName: string;
	posMode?: 'RETAIL' | 'PREORDER' | 'RETURN';
	preorderStatus?: 'PREORDER' | 'DRAFT';
	createdOrderRef?: string | number | null;
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
	'create-new': [];
	'view-order': [orderRef: string | number];
}>();

const formattedTotal = computed(() => Number(props.totalAmount || 0).toLocaleString('vi-VN'));
const formattedReceived = computed(() => Number(props.amountReceived || 0).toLocaleString('vi-VN'));
const formattedChange = computed(() => Number(props.changeAmount || 0).toLocaleString('vi-VN'));
const isPreorderMode = computed(() => props.posMode === 'PREORDER');
const isReturnMode = computed(() => props.posMode === 'RETURN');
const preorderTitle = computed(() => props.preorderStatus === 'DRAFT' ? 'Lưu nháp thành công' : 'Tạo đơn đặt trước thành công');
const summaryTitle = computed(() => {
	if (isPreorderMode.value) {
		return preorderTitle.value;
	}

	if (isReturnMode.value) {
		return 'Tạo phiếu trả hàng thành công';
	}

	return 'Thanh toán thành công';
});
const summaryDescription = computed(() => {
	if (props.posMode === 'PREORDER') {
		return props.preorderStatus === 'DRAFT'
			? 'Đơn nháp đã được ghi nhận thành công trong hệ thống.'
			: 'Đơn đặt trước đã được ghi nhận thành công trong hệ thống.';
	}

	if (props.posMode === 'RETURN') {
		return 'Phiếu trả hàng đã được ghi nhận thành công trong hệ thống.';
	}

	return 'Đơn hàng đã được ghi nhận thành công trong hệ thống.';
});
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-md">
			<DialogHeader>
				<DialogTitle class="flex items-center gap-2">
					<CheckCircle2 class="size-5 text-green-600" />
					{{ summaryTitle }}
				</DialogTitle>
				<DialogDescription>
					{{ summaryDescription }}
				</DialogDescription>
			</DialogHeader>

			<div class="rounded-xl border border-border/70 bg-muted/30 p-4">
				<div class="mb-3 flex items-center gap-2 text-sm font-medium">
					<ReceiptText class="size-4" />
					{{ isReturnMode ? 'Thông tin phiếu trả hàng' : 'Thông tin hóa đơn' }}
				</div>

				<div class="space-y-2 text-sm">
					<div class="flex items-center justify-between">
						<span class="text-muted-foreground">Khách hàng</span>
						<span class="font-medium">{{ customerName }}</span>
					</div>
					<div class="flex items-center justify-between">
						<span class="text-muted-foreground">Số sản phẩm</span>
						<span class="font-medium">{{ itemCount }}</span>
					</div>
					<div class="flex items-center justify-between">
						<span class="text-muted-foreground">{{ isReturnMode ? 'Tổng hoàn trả' : 'Tổng thanh toán' }}</span>
						<span class="font-semibold">{{ formattedTotal }}</span>
					</div>
					<div class="flex items-center justify-between">
						<span class="text-muted-foreground">{{ isReturnMode ? 'Khách nhận' : 'Khách đưa' }}</span>
						<span class="font-medium">{{ formattedReceived }}</span>
					</div>
					<div class="flex items-center justify-between border-t border-border/70 pt-2">
						<span class="text-muted-foreground">{{ isReturnMode ? 'Cần trả lại cho khách' : 'Tiền thừa trả khách' }}</span>
						<span class="font-semibold">{{ formattedChange }}</span>
					</div>
				</div>
			</div>

			<DialogFooter class="gap-2">
				<template v-if="isPreorderMode">
					<Button
						type="button"
						variant="outline"
						class="flex-1"
						:disabled="!createdOrderRef"
						@click="createdOrderRef ? emit('view-order', createdOrderRef) : undefined"
					>
						Xem đơn hàng
					</Button>
					<Button type="button" class="flex-1" @click="emit('create-new')">
						Tạo đơn mới
					</Button>
				</template>
				<Button v-else type="button" class="w-full" @click="emit('update:open', false)">
					Đóng
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
