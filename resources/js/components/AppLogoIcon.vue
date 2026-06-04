<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img :class="['h-auto w-full rounded-md', 'fill-current', 'text-white', 'dark:text-black', className]" src="/Onivaco-Header.png" alt="App Logo" />
</template>
