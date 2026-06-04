<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    LayoutGrid,
    Menu,
    Package,
    Ruler,
    ReceiptText,
    ClipboardCheck,
    Settings,
    ShoppingCart,
    Tags,
    Users,
    UserRoundCog,
    BadgeDollarSign,
    FileText,
    X,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { tenantRoutes } from '@/lib/tenantRoutes';

// ── Types ────────────────────────────────────────────────────────────────────

type NavChild = {
    title: string;
    description: string;
    href: string;
    icon: typeof ShoppingCart;
};

type NavGroup = {
    label: string;
    icon: typeof ShoppingCart;
    children: NavChild[];
};

// ── Page props ────────────────────────────────────────────────────────────────

const page = usePage();
const dashboardHref = '/dashboard';
const posShortcutHref = tenantRoutes.sales.index();
const invoicePolicy = computed(() => page.props.invoicePolicy as { use_invoice_on_sale?: boolean } | undefined);
const showInvoiceFeatures = computed(() => Boolean(invoicePolicy.value?.use_invoice_on_sale));

// ── Active detection ─────────────────────────────────────────────────────────

function normalizePath(path: string): string {
    const cleanPath = path.split('?')[0].split('#')[0];
    const trimmed = cleanPath.replace(/\/+$/, '');
    return trimmed === '' ? '/' : trimmed;
}

function pathMatches(currentPath: string, targetPath: string): boolean {
    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}

function isActive(href: string): boolean {
    const currentPath = normalizePath(window.location.pathname);
    const targetPath = normalizePath(href);
    return pathMatches(currentPath, targetPath);
}

function buildSalesModeHref(mode: 'RETAIL' | 'PREORDER' | 'RETURN', preorderStatus?: 'PREORDER' | 'DRAFT'): string {
    const params = new URLSearchParams({
        mode,
        entry: 'tenant-header',
    });

    if (preorderStatus) {
        params.set('preorderStatus', preorderStatus);
    }

    return `${tenantRoutes.sales.index()}?${params.toString()}`;
}

function activeChildHref(group: NavGroup): string | null {
    const currentPath = normalizePath(window.location.pathname);

    const candidates = group.children
        .map((child) => ({
            href: child.href,
            normalizedHref: normalizePath(child.href),
        }))
        .filter((child) => pathMatches(currentPath, child.normalizedHref))
        .sort((a, b) => b.normalizedHref.length - a.normalizedHref.length);

    return candidates[0]?.href ?? null;
}

function isGroupActive(group: NavGroup): boolean {
    return activeChildHref(group) !== null;
}

// ── Mobile menu ───────────────────────────────────────────────────────────────

const mobileOpen = ref(false);
const openGroups = ref<Record<string, boolean>>({});
const desktopOpenGroup = ref<string | null>(null);

function toggleGroup(label: string) {
    openGroups.value[label] = !openGroups.value[label];
}

function closeMobile() {
    mobileOpen.value = false;
}

function toggleDesktopGroup(label: string) {
    desktopOpenGroup.value = desktopOpenGroup.value === label ? null : label;
}

function closeDesktopGroups() {
    desktopOpenGroup.value = null;
}

function handleGlobalPointerDown(event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (!target) return;

    if (target.closest('[data-desktop-nav-root]')) {
        return;
    }

    closeDesktopGroups();
}

function handleGlobalKeyDown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeDesktopGroups();
        closeMobile();
    }
}

onMounted(() => {
    window.addEventListener('popstate', closeMobile);
    document.addEventListener('pointerdown', handleGlobalPointerDown);
    document.addEventListener('keydown', handleGlobalKeyDown);
});
onUnmounted(() => {
    window.removeEventListener('popstate', closeMobile);
    document.removeEventListener('pointerdown', handleGlobalPointerDown);
    document.removeEventListener('keydown', handleGlobalKeyDown);
});

// ── Navigation data ───────────────────────────────────────────────────────────

const navGroups = computed<NavGroup[]>(() => {
    const orderChildren: NavChild[] = [
        {
            title: 'Đặt trước',
            description: 'Tạo đơn đặt trước',
            href: buildSalesModeHref('PREORDER', 'PREORDER'),
            icon: FileText,
        },
        {
            title: 'Đơn nháp',
            description: 'Tạo đơn nháp',
            href: buildSalesModeHref('PREORDER', 'DRAFT'),
            icon: FileText,
        },
        {
            title: 'Đơn hàng',
            description: 'Quản lý và theo dõi đơn hàng',
            href: tenantRoutes.orders.index(),
            icon: FileText,
        },
    ];

    if (showInvoiceFeatures.value) {
        orderChildren.push({
            title: 'Hóa đơn',
            description: 'Xuất và quản lý hóa đơn',
            href: tenantRoutes.invoices.index(),
            icon: ReceiptText,
        });

        orderChildren.push({
            title: 'Hoá đơn điện tử',
            description: 'Theo dõi dữ liệu hóa đơn điện tử đã xuất',
            href: tenantRoutes.eInvoices.index(),
            icon: ReceiptText,
        });
    }

    orderChildren.push({
        title: 'Trả hàng',
        description: 'Quản lý đơn trả hàng và hoàn tiền',
        href: tenantRoutes.returns.index(),
        icon: FileText,
    });

    return [
    {
        label: 'Đơn hàng',
        icon: ShoppingCart,
        children: orderChildren,
    },
    {
        label: 'Khách hàng',
        icon: Users,
        children: [
            {
                title: 'Khách hàng',
                description: 'Quản lý khách hàng lẻ, sỉ và doanh nghiệp',
                href: tenantRoutes.customers.index(),
                icon: Users,
            },
            {
                title: 'Nhóm khách hàng',
                description: 'Phân nhóm khách hàng và bảng giá mặc định',
                href: tenantRoutes.customerGroups.index(),
                icon: UserRoundCog,
            },
            {
                title: 'Bảng giá',
                description: 'Giá riêng theo khách hàng, nhóm, biến thể và UOM',
                href: tenantRoutes.priceLists.index(),
                icon: BadgeDollarSign,
            },
        ],
    },
    {
        label: 'Hàng hóa',
        icon: Package,
        children: [
            {
                title: 'Hàng hóa',
                description: 'Quản lý danh mục hàng hóa & đơn vị tính',
                href: tenantRoutes.products.index(),
                icon: Package,
            },
            {
                title: 'Danh mục hàng hóa',
                description: 'Phân loại hàng hóa theo danh mục',
                href: tenantRoutes.productCategories.index(),
                icon: Tags,
            },
            {
                title: 'Đơn vị tính (UOM)',
                description: 'Quản lý đơn vị tính và quy đổi',
                href: tenantRoutes.uoms.index(),
                icon: Ruler,
            },
        ],
    },
    {
        label: 'Kho hàng',
        icon: LayoutGrid,
        children: [
            {
                title: 'Danh sách kho',
                description: 'Quản lý thông tin kho',
                href: tenantRoutes.warehouses.index(),
                icon: LayoutGrid,
            },
            {
                title: 'Phiếu nhập kho',
                description: 'Quản lý nhập hàng theo lô',
                href: tenantRoutes.importReceipts.index(),
                icon: ReceiptText,
            },
            {
                title: 'Tồn kho',
                description: 'Theo dõi tồn kho theo kho và biến thể',
                href: tenantRoutes.stocks.index(),
                icon: Package,
            },
            {
                title: 'Soát kho',
                description: 'Kiểm kê thực tế và cân bằng tồn kho',
                href: tenantRoutes.stockTakes.index(),
                icon: ClipboardCheck,
            },
        ],
    },
    {
        label: 'Chi phí khác',
        icon: ReceiptText,
        children: [
            {
                title: 'Chi phí phát sinh',
                description: 'Danh mục phụ thu áp dụng cho bán hàng',
                href: tenantRoutes.surcharges.index(),
                icon: BadgeDollarSign,
            },
            {
                title: 'Chi phí doanh nghiệp',
                description: 'Theo dõi chi phí vận hành nội bộ doanh nghiệp',
                href: tenantRoutes.businessExpenses.index(),
                icon: ReceiptText,
            },
        ],
    },
    {
        label: 'Cài đặt',
        icon: Settings,
        children: [
            {
                title: 'Cấu hình mã',
                description: 'Thiết lập chuỗi mã cho đơn hàng, hóa đơn...',
                href: tenantRoutes.config.codeSequences.index(),
                icon: Settings,
            },
        ],
    },
    ];
});
</script>

<template>
    <!-- ── Desktop Header ─────────────────────────────────────────────────── -->
    <header class="sticky top-12 z-40 w-full border-b border-border/60 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div class="mx-auto flex h-12 max-w-screen-2xl items-center gap-4 px-4 lg:px-6">

            <!-- ── Desktop Nav ─────────────────────────────────────────────── -->
            <nav class="hidden h-full flex-1 items-center gap-1 lg:flex" data-desktop-nav-root>

                <!-- Dashboard (plain link) -->
                <Link
                    :href="dashboardHref"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                    :class="isActive('/dashboard') ? 'bg-accent/60 text-accent-foreground' : 'text-foreground/70'"
                >
                    <LayoutGrid class="size-4" />
                    Tổng quan
                </Link>

                <!-- Dropdown groups -->
                <div
                    v-for="group in navGroups"
                    :key="group.label"
                    class="group/menu relative"
                >
                    <!-- Trigger -->
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        :class="isGroupActive(group) ? 'bg-accent/60 text-accent-foreground' : 'text-foreground/70'"
                        @click.stop.prevent="toggleDesktopGroup(group.label)"
                    >
                        <component :is="group.icon" class="size-4" />
                        {{ group.label }}
                        <ChevronDown class="ml-0.5 size-3.5 transition-transform duration-200 group-hover/menu:rotate-180" />
                    </button>

                    <!-- Dropdown panel -->
                    <div
                        class="invisible absolute left-0 top-full z-50 mt-1 min-w-[240px] rounded-lg border border-border bg-popover p-1.5 shadow-lg opacity-0 transition-all duration-150 group-hover/menu:visible group-hover/menu:opacity-100"
                        :class="desktopOpenGroup === group.label ? 'visible opacity-100' : ''"
                    >
                        <Link
                            v-for="child in group.children"
                            :key="child.title"
                            :href="child.href"
                            class="flex items-start gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                            :class="activeChildHref(group) === child.href ? 'bg-accent text-accent-foreground' : 'text-foreground'"
                            @click="closeDesktopGroups"
                        >
                            <component
                                :is="child.icon"
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <div>
                                <p class="font-medium leading-none">{{ child.title }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ child.description }}</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <Link
                    :href="posShortcutHref"
                    class="ml-auto inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                    :class="isActive(posShortcutHref) ? 'bg-accent/60 text-accent-foreground' : 'text-foreground/70'"
                >
                    <ShoppingCart class="size-4" />
                    Bán Hàng
                </Link>
            </nav>

            <!-- ── Mobile hamburger ────────────────────────────────────────── -->
            <div class="ml-auto flex items-center lg:hidden">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-9"
                    @click="mobileOpen = !mobileOpen"
                    :aria-label="mobileOpen ? 'Đóng menu' : 'Mở menu'"
                >
                    <X v-if="mobileOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </Button>
            </div>
        </div>

        <!-- ── Mobile Nav Panel ─────────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="mobileOpen"
                class="border-t border-border bg-background px-4 pb-4 pt-2 lg:hidden"
            >
                <!-- Dashboard -->
                <Link
                    :href="dashboardHref"
                    class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium"
                    :class="isActive('/dashboard') ? 'bg-accent text-accent-foreground' : 'text-foreground'"
                    @click="closeMobile"
                >
                    <LayoutGrid class="size-4" />
                    Tổng quan
                </Link>

                <!-- Groups (accordion-style) -->
                <div v-for="group in navGroups" :key="group.label" class="mt-1">
                    <button
                        class="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
                        :class="isGroupActive(group) ? 'bg-accent/40 text-accent-foreground' : 'text-foreground'"
                        @click="toggleGroup(group.label)"
                    >
                        <component :is="group.icon" class="size-4" />
                        <span class="flex-1 text-left">{{ group.label }}</span>
                        <ChevronDown
                            class="size-3.5 transition-transform duration-200"
                            :class="openGroups[group.label] ? 'rotate-180' : ''"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition-all duration-150 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-100 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-if="openGroups[group.label]" class="ml-4 mt-0.5 space-y-0.5 overflow-hidden border-l border-border pl-3">
                            <Link
                                v-for="child in group.children"
                                :key="child.title"
                                :href="child.href"
                                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors"
                                :class="activeChildHref(group) === child.href ? 'bg-accent text-accent-foreground' : 'text-foreground/80 hover:bg-accent/60'"
                                @click="closeMobile"
                            >
                                <component :is="child.icon" class="size-4 shrink-0 text-muted-foreground" />
                                {{ child.title }}
                            </Link>
                        </div>
                    </Transition>
                </div>

                <Link
                    :href="posShortcutHref"
                    class="mt-2 flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium"
                    :class="isActive(posShortcutHref) ? 'bg-accent text-accent-foreground' : 'text-foreground hover:bg-accent/60'"
                    @click="closeMobile"
                >
                    <ShoppingCart class="size-4" />
                    Bán Hàng
                </Link>
            </div>
        </Transition>
    </header>
</template>
