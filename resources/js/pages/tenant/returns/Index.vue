<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    FilterBar,
    FilterDateRangePicker,
    FilterResetButton,
    FilterSearchInput,
    FilterStatusSelect,
    FilterTagList,
} from '@/components/ui/filter';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ReturnRow = {
    id: number;
    code: string;
    status: string;
    total_amount?: number | string;
    refund_method?: string | null;
    reason?: string | null;
    return_date?: string | null;
    created_at?: string | null;
    customer?: { id?: number; name?: string } | null;
    order?: { id?: number; code?: string } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type ReturnPagination = {
    data: ReturnRow[];
    current_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

const props = defineProps<{
    returns: ReturnPagination;
}>();

const filters = ref({
    keyword: '',
    status: '',
    fromAt: '',
    toAt: '',
});

const toNumber = (value: number | string | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
    return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const normalizeText = (value: string | null | undefined): string => {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

const getTimestamp = (row: ReturnRow) => {
    const raw = row.return_date || row.created_at;
    if (!raw) return null;
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
};

const withinDateRange = (row: ReturnRow) => {
    const fromAt = filters.value.fromAt;
    const toAt = filters.value.toAt;
    if (fromAt === '' && toAt === '') return true;

    const ts = getTimestamp(row);
    if (ts === null) return false;

    if (fromAt) {
        const from = new Date(`${fromAt}T00:00:00`).getTime();
        if (ts < from) return false;
    }

    if (toAt) {
        const to = new Date(`${toAt}T23:59:59`).getTime();
        if (ts > to) return false;
    }

    return true;
};

const statusOptions = [
    { value: 'PENDING', label: 'Chờ xử lý' },
    { value: 'APPROVED', label: 'Đã duyệt' },
    { value: 'REJECTED', label: 'Từ chối' },
    { value: 'REFUNDED', label: 'Đã hoàn tiền' },
] as const;

const filteredRows = computed(() => {
    const keyword = normalizeText(filters.value.keyword);

    return props.returns.data
        .filter((row) => {
            if (keyword === '') return true;
            const searchable = normalizeText([
                row.code,
                row.order?.code,
                row.customer?.name,
                row.reason,
            ].join(' '));
            return searchable.includes(keyword);
        })
        .filter((row) => {
            if (filters.value.status === '') return true;
            return String(row.status ?? '').toUpperCase() === filters.value.status;
        })
        .filter(withinDateRange);
});

const statusText = (status: string) => {
    const map: Record<string, string> = {
        PENDING: 'Chờ xử lý',
        APPROVED: 'Đã duyệt',
        REJECTED: 'Từ chối',
        REFUNDED: 'Đã hoàn tiền',
    };

    return map[String(status ?? '').toUpperCase()] ?? status;
};

const activeTags = computed(() => {
    const tags: Array<{ key: string; label: string; value: string }> = [];

    if (filters.value.keyword.trim() !== '') {
        tags.push({ key: 'keyword', label: 'Từ khóa', value: filters.value.keyword.trim() });
    }

    if (filters.value.status !== '') {
        const found = statusOptions.find((item) => item.value === filters.value.status);
        tags.push({ key: 'status', label: 'Trạng thái', value: found?.label ?? filters.value.status });
    }

    if (filters.value.fromAt !== '') {
        tags.push({ key: 'fromAt', label: 'Từ ngày', value: filters.value.fromAt });
    }

    if (filters.value.toAt !== '') {
        tags.push({ key: 'toAt', label: 'Đến ngày', value: filters.value.toAt });
    }

    return tags;
});

const removeTag = (key: string) => {
    if (key in filters.value) {
        (filters.value as Record<string, string>)[key] = '';
    }
};

const resetFilters = () => {
    filters.value = {
        keyword: '',
        status: '',
        fromAt: '',
        toAt: '',
    };
};

const createReturnFromSalesHref = computed(() => {
    const params = new URLSearchParams({
        mode: 'RETURN',
        entry: 'returns-index',
    });

    return `${tenantRoutes.sales.index()}?${params.toString()}`;
});

const remove = (id: number) => {
    if (!confirm('Xóa phiếu trả hàng này?')) return;
    router.delete(tenantRoutes.returns.destroy(id));
};
</script>

<template>
    <Head title="Trả hàng" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Quản lý trả hàng</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Theo dõi, duyệt và xử lý các phiếu trả hàng theo đơn nguồn.</p>
                    </div>
                    <Link :href="createReturnFromSalesHref" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
                        Tạo trả hàng
                    </Link>
                </div>
            </section>

            <section class="space-y-3 rounded-2xl border border-border/70 bg-card p-5">
                <FilterBar>
                    <FilterSearchInput
                        v-model="filters.keyword"
                        placeholder="Tìm theo mã phiếu, mã đơn, khách hàng"
                        @search="() => {}"
                    />
                    <FilterStatusSelect v-model="filters.status" label="Trạng thái trả hàng" :options="statusOptions as any" />
                    <FilterDateRangePicker
                        :from="filters.fromAt"
                        :to="filters.toAt"
                        @update:from="(v) => (filters.fromAt = v)"
                        @update:to="(v) => (filters.toAt = v)"
                    />
                    <FilterResetButton :count="activeTags.length" @reset="resetFilters" />

                    <template #tags>
                        <FilterTagList :tags="activeTags" @remove="removeTag" />
                    </template>
                </FilterBar>

                <div class="overflow-hidden rounded-xl border border-border/70">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Mã phiếu trả</th>
                                <th class="px-3 py-2 text-left font-medium">Đơn nguồn</th>
                                <th class="px-3 py-2 text-left font-medium">Khách hàng</th>
                                <th class="px-3 py-2 text-left font-medium">Trạng thái</th>
                                <th class="px-3 py-2 text-right font-medium">Tổng hoàn</th>
                                <th class="px-3 py-2 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredRows" :key="row.id" class="border-t border-border/70">
                                <td class="px-3 py-2 font-medium">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ row.order?.code || '-' }}</td>
                                <td class="px-3 py-2">{{ row.customer?.name || 'Khách lẻ' }}</td>
                                <td class="px-3 py-2">{{ statusText(row.status) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.total_amount) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button type="button" variant="ghost" size="icon-sm">
                                                <MoreHorizontal class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="tenantRoutes.returns.show(row.id)">Xem chi tiết</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-red-600 focus:text-red-600" @click="remove(row.id)">
                                                Xóa phiếu trả
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
                                <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">Không có phiếu trả hàng phù hợp bộ lọc hiện tại.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
