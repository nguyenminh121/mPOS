<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const STATUS_OPTIONS = [
    { value: 'DRAFT',     label: 'Nháp' },
    { value: 'CONFIRMED', label: 'Đã xác nhận' },
    { value: 'COMPLETED', label: 'Hoàn thành' },
    { value: 'CANCELLED', label: 'Đã hủy' },
] as const;

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const currentLabel = computed(() =>
    STATUS_OPTIONS.find((o) => o.value === props.modelValue)?.label ?? 'Trạng thái',
);

const isActive = computed(() => props.modelValue !== '');
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="h-9 gap-1.5"
                :class="isActive ? 'border-primary/60 bg-primary/5 text-primary' : ''"
            >
                {{ currentLabel }}
                <ChevronDown class="size-3.5 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-48">
            <DropdownMenuLabel class="text-xs text-muted-foreground">Trạng thái đơn hàng</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event as string)"
            >
                <DropdownMenuRadioItem value="">Tất cả</DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                    v-for="opt in STATUS_OPTIONS"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
