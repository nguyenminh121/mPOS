<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';

const page = usePage();
const auth = computed(() => page.props.auth);
const dashboardHref = '/dashboard';
</script>

<template>
    <header class="sticky top-0 z-50 w-full border-b border-border/60 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div class="mx-auto flex h-12 max-w-screen-2xl items-center justify-between px-4 lg:px-6">
            <!-- Logo -->
            <Link :href="dashboardHref" class="flex shrink-0 items-center">
                <AppLogo />
            </Link>

            <!-- User avatar dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative size-8 rounded-full focus-within:ring-2 focus-within:ring-primary"
                    >
                        <Avatar class="size-7 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="auth.user.avatar"
                                :src="auth.user.avatar"
                                :alt="auth.user.name"
                            />
                            <AvatarFallback class="rounded-full bg-neutral-200 text-xs font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                {{ getInitials(auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
