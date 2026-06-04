<script setup lang="ts">
import { ChevronDown, Tag } from 'lucide-vue-next';
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
    categories: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const currentLabel = computed(() => {
    if (!props.modelValue) return 'Danh mục';
    return props.categories.find((c) => String(c.id) === props.modelValue)?.name ?? 'Danh mục';
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
                <Tag class="size-3.5" />
                {{ currentLabel }}
                <ChevronDown class="size-3.5 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-56 overflow-y-auto">
            <DropdownMenuLabel class="text-xs text-muted-foreground">Danh mục sản phẩm</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event as string)"
            >
                <DropdownMenuRadioItem value="">Tất cả danh mục</DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                    v-for="cat in categories"
                    :key="cat.id"
                    :value="String(cat.id)"
                >
                    {{ cat.name }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
