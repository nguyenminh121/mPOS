<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            class="flex w-32 h-auto items-center justify-center rounded-md text-sidebar-primary-foreground"
        >
            <AppLogoIcon class="fill-current text-white dark:text-black" />
        </div>
        <div class="grid flex-1 text-left text-md">
            <span class="mb-0.5 truncate leading-tight font-semibold"
                ></span
            >
        </div>
    </div>
</template>
