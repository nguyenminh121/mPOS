<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'search'): void;
}>();

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const onInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', value);

    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => emit('search'), 400);
};

const onEnter = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    emit('search');
};

const clear = () => {
    emit('update:modelValue', '');
    emit('search');
};
</script>

<template>
    <div class="relative min-w-[200px]">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
            :value="modelValue"
            :placeholder="placeholder ?? 'Tìm kiếm...'"
            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-8 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            @input="onInput"
            @keydown.enter="onEnter"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
