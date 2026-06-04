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

const props = defineProps<{
    modelValue: string;
    label?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const currentLabel = computed(() => {
    if (props.modelValue === 'true')  return 'Đang bán';
    if (props.modelValue === 'false') return 'Ngừng bán';
    return props.label ?? 'Tình trạng';
});

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
        <DropdownMenuContent align="start" class="w-44">
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                {{ label ?? 'Tình trạng bán' }}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event as string)"
            >
                <DropdownMenuRadioItem value="">Tất cả</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="true">Đang bán</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="false">Ngừng bán</DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
