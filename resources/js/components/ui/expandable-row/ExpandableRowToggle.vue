<script setup lang="ts">
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    expanded: boolean;
    label: string;
}>();

defineEmits<{
    (event: 'toggle'): void;
}>();
</script>

<template>
    <Button type="button" variant="ghost" size="sm" class="h-7 gap-1.5 px-2 text-primary hover:text-primary" @click="$emit('toggle')">
        <ChevronDown v-if="expanded" class="size-3.5" />
        <ChevronRight v-else class="size-3.5" />
        <span class="font-medium">{{ label }}</span>
    </Button>
</template>
