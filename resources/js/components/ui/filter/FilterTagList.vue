<script setup lang="ts">
import FilterTag from './FilterTag.vue';

defineProps<{
    tags: { key: string; label: string; value: string }[];
}>();

defineEmits<{
    (e: 'remove', key: string): void;
}>();
</script>

<template>
    <div v-if="tags.length > 0" class="flex flex-wrap items-center gap-1.5">
        <FilterTag
            v-for="tag in tags"
            :key="tag.key"
            :label="tag.label"
            :value="tag.value"
            @remove="$emit('remove', tag.key)"
        />
    </div>
</template>
