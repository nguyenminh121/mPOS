<script setup lang="ts">
import { ChevronDown, Users } from 'lucide-vue-next';
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
    groups: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const currentLabel = computed(() => {
    if (!props.modelValue) return 'Nhóm KH';
    return props.groups.find((g) => String(g.id) === props.modelValue)?.name ?? 'Nhóm KH';
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
                <Users class="size-3.5" />
                {{ currentLabel }}
                <ChevronDown class="size-3.5 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-52 overflow-y-auto">
            <DropdownMenuLabel class="text-xs text-muted-foreground">Nhóm khách hàng</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup
                :model-value="modelValue"
                @update:model-value="emit('update:modelValue', $event as string)"
            >
                <DropdownMenuRadioItem value="">Tất cả nhóm</DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                    v-for="group in groups"
                    :key="group.id"
                    :value="String(group.id)"
                >
                    {{ group.name }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
