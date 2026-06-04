<script setup lang="ts">
import { X } from 'lucide-vue-next';

defineProps<{
    label: string;
    value: string;
}>();

defineEmits<{
    (e: 'remove'): void;
}>();
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2.5 py-0.5 text-xs">
        <span class="text-muted-foreground">{{ label }}:</span>
        <span class="font-medium">{{ value }}</span>
        <button
            type="button"
            class="ml-0.5 rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
            @click="$emit('remove')"
        >
            <X class="size-3" />
        </button>
    </span>
</template>
