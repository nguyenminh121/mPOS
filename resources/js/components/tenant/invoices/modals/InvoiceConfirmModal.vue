<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(defineProps<{
	open: boolean;
	loading?: boolean;
}>(), {
	loading: false,
});

const emit = defineEmits<{
	(event: 'update:open', value: boolean): void;
	(event: 'confirm', createOrderFromInvoice: boolean): void;
}>();

const onChoose = (createOrderFromInvoice: boolean) => {
	emit('confirm', createOrderFromInvoice);
};
</script>

<template>
	<Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-lg">
			<DialogHeader>
				<DialogTitle>Xác nhận tạo hóa đơn</DialogTitle>
				<DialogDescription>
					Bạn có muốn tạo đơn hàng từ hóa đơn này không?
				</DialogDescription>
			</DialogHeader>

			<DialogFooter class="gap-2">
				<Button type="button" variant="outline" :disabled="props.loading" @click="onChoose(false)">
					{{ props.loading ? 'Đang xử lý...' : 'Chỉ tạo hóa đơn' }}
				</Button>
				<Button type="button" :disabled="props.loading" @click="onChoose(true)">
					{{ props.loading ? 'Đang xử lý...' : 'Tạo hóa đơn + đơn hàng' }}
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
