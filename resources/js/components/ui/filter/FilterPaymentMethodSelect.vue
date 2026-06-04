<script setup lang="ts">
import { ChevronDown, CreditCard } from 'lucide-vue-next';
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

const PAYMENT_OPTIONS = [
    { value: 'CASH',          label: 'Tiền mặt' },
    { value: 'BANK_TRANSFER', label: 'Chuyển khoản' },
    { value: 'CARD',          label: 'Thẻ' },
    { value: 'COD',           label: 'COD' },
] as const;

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const currentLabel = computed(() =>
    PAYMENT_OPTIONS.find((o) => o.value === props.modelValue)?.label ?? 'Thanh toán',
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
                <CreditCard class="size-3.5" />
                {{ currentLabel }}
                <ChevronDown class="size-3.5 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-44">
            <DropdownMenuLabel class="text-xs text-muted-foreground">Phương thức thanh toán</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event as string)"
            >
                <DropdownMenuRadioItem value="">Tất cả</DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                    v-for="opt in PAYMENT_OPTIONS"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
