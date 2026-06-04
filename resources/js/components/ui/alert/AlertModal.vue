<script setup lang="ts">
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const props = withDefaults(defineProps<{
	open: boolean;
	title?: string;
	description?: string;
	confirmText?: string;
	cancelText?: string;
	loading?: boolean;
}>(), {
	title: 'Xác nhận thao tác',
	description: '',
	confirmText: 'Xác nhận',
	cancelText: 'Hủy',
	loading: false,
});

const emit = defineEmits<{
	(event: 'update:open', value: boolean): void;
	(event: 'confirm'): void;
	(event: 'cancel'): void;
}>();

const onCancel = () => {
	emit('cancel');
	emit('update:open', false);
};

const onConfirm = () => {
	emit('confirm');
};
</script>

<template>
	<Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-lg">
			<DialogHeader>
				<DialogTitle>{{ props.title }}</DialogTitle>
				<DialogDescription>{{ props.description }}</DialogDescription>
			</DialogHeader>

			<DialogFooter>
				<Button type="button" variant="outline" :disabled="props.loading" @click="onCancel">
					{{ props.cancelText }}
				</Button>
				<Button type="button" :disabled="props.loading" @click="onConfirm">
					{{ props.loading ? 'Đang xử lý...' : props.confirmText }}
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
