<script setup lang="ts">
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    count?: number;
}>();

defineEmits<{
    (e: 'reset'): void;
}>();
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="h-9 gap-1.5 text-muted-foreground"
        @click="$emit('reset')"
    >
        <RotateCcw class="size-3.5" />
        Xóa bộ lọc
        <span
            v-if="count"
            class="flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground"
        >
            {{ count }}
        </span>
    </Button>
</template>
