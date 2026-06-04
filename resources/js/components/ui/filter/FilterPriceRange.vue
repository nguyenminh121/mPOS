<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    min: string;
    max: string;
}>();

const emit = defineEmits<{
    (e: 'update:min', v: string): void;
    (e: 'update:max', v: string): void;
}>();

const isActive = computed(() => props.min !== '' || props.max !== '');
</script>

<template>
    <div
        class="flex items-center gap-1.5 rounded-md border px-2.5"
        :class="isActive ? 'border-primary/60 bg-primary/5' : 'border-input bg-background'"
    >
        <span class="shrink-0 text-xs text-muted-foreground">Giá</span>

        <input
            type="number"
            :value="min"
            placeholder="Từ"
            min="0"
            class="h-9 w-24 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            :class="isActive ? 'text-primary' : 'text-foreground'"
            @change="emit('update:min', ($event.target as HTMLInputElement).value)"
        />

        <span class="text-xs text-muted-foreground">—</span>

        <input
            type="number"
            :value="max"
            placeholder="Đến"
            min="0"
            class="h-9 w-24 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            :class="isActive ? 'text-primary' : 'text-foreground'"
            @change="emit('update:max', ($event.target as HTMLInputElement).value)"
        />
    </div>
</template>
