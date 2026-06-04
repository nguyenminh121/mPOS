<script setup lang="ts">
import SystemHeader from '@/components/SystemHeader.vue';
import TenantHeader from '@/components/TenantHeader.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <slot name="system-header">
            <SystemHeader />
        </slot>
        <slot name="header">
            <TenantHeader />
        </slot>
        <main class="mx-auto w-full max-w-[1800px] flex-1 px-4 py-6 lg:px-6">
            <slot />
        </main>
    </div>
</template>
