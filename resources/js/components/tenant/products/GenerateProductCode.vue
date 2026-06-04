<script setup lang="ts">
import { WandSparkles } from 'lucide-vue-next';

defineProps<{
    loading?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    generate: [];
}>();
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-border/70 bg-background px-3 py-2 text-xs font-medium transition hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="disabled || loading"
        @click="emit('generate')"
    >
        <WandSparkles class="size-3.5" />
        {{ loading ? 'Đang tạo mã...' : 'Tạo mã SKU & Barcode' }}
    </button>
</template>
