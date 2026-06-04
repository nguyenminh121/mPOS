<script setup lang="ts">
import { Banknote } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void;
}>();

const isActive = computed(() => props.modelValue === 'true');

const toggle = () => emit('update:modelValue', isActive.value ? '' : 'true');
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="h-9 gap-1.5 transition-colors"
        :class="isActive
            ? 'border-amber-500/60 bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
            : ''"
        @click="toggle"
    >
        <Banknote class="size-3.5" />
        Có nợ
    </Button>
</template>
