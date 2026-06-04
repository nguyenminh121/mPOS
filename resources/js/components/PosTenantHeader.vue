<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, User, Warehouse } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import orders from '@/routes/tenant/orders';

type WarehouseOption = { id: number; name: string };
type SellerOption = { id: number; name: string };
type OrderType = 'RETAIL' | 'WHOLESALE';

const props = defineProps<{
    warehouseId: number | null;
    sellerId: number | null;
    type: OrderType;
    warehouses: WarehouseOption[];
    sellers: SellerOption[];
}>();

const emit = defineEmits<{
    (event: 'update:warehouseId', value: number | null): void;
    (event: 'update:sellerId', value: number | null): void;
    (event: 'update:type', value: OrderType): void;
}>();

const selectedWarehouse = computed(() => props.warehouses.find((w) => w.id === props.warehouseId));
const selectedSeller = computed(() => props.sellers.find((s) => s.id === props.sellerId));

const warehouseValue = computed({
    get: () => props.warehouseId?.toString() ?? '',
    set: (v: string) => emit('update:warehouseId', v ? Number(v) : null),
});

const sellerValue = computed({
    get: () => props.sellerId?.toString() ?? '',
    set: (v: string) => emit('update:sellerId', v ? Number(v) : null),
});

const orderTypeValue = computed({
    get: () => props.type,
    set: (value: OrderType) => emit('update:type', value),
});

const orderTypeLabel = computed(() => (props.type === 'WHOLESALE' ? 'Bán sỉ' : 'Bán lẻ'));
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border/70 bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/80">
        <div class="flex h-14 items-center gap-3 px-4">
            <!-- Back to Dashboard -->
            <Button as-child variant="ghost" size="sm" class="shrink-0 gap-1.5 text-muted-foreground hover:text-foreground">
                <Link :href="orders.index()">
                    <ArrowLeft class="size-4" />
                    <span class="hidden sm:inline">Đơn hàng</span>
                </Link>
            </Button>

            <div class="hidden h-5 w-px bg-border/60 sm:block" />

            <span class="hidden text-sm font-semibold text-foreground sm:block">Bán hàng POS</span>

            <!-- Spacer -->
            <div class="flex-1" />

            <!-- Warehouse selector -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-[180px] justify-between gap-2 px-3"
                        :class="!selectedWarehouse ? 'text-muted-foreground' : ''"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Warehouse class="size-4 shrink-0" />
                            <span class="truncate text-sm">
                                {{ selectedWarehouse?.name ?? 'Chọn kho hàng' }}
                            </span>
                        </span>
                        <ChevronDown class="size-3.5 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <Warehouse class="size-3.5" />
                        Kho hàng
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioGroup v-model="warehouseValue">
                        <DropdownMenuRadioItem value="">
                            <span class="text-muted-foreground">Không chọn</span>
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem
                            v-for="warehouse in props.warehouses"
                            :key="warehouse.id"
                            :value="warehouse.id.toString()"
                        >
                            {{ warehouse.name }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Seller selector -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-[180px] justify-between gap-2 px-3"
                        :class="!selectedSeller ? 'text-muted-foreground' : ''"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <User class="size-4 shrink-0" />
                            <span class="truncate text-sm">
                                {{ selectedSeller?.name ?? 'Chọn người bán' }}
                            </span>
                        </span>
                        <ChevronDown class="size-3.5 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <User class="size-3.5" />
                        Người bán
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioGroup v-model="sellerValue">
                        <DropdownMenuRadioItem value="">
                            <span class="text-muted-foreground">Không chọn</span>
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem
                            v-for="seller in props.sellers"
                            :key="seller.id"
                            :value="seller.id.toString()"
                        >
                            {{ seller.name }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Order type selector -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-[150px] justify-between gap-2 px-3"
                    >
                        <span class="truncate text-sm">{{ orderTypeLabel }}</span>
                        <ChevronDown class="size-3.5 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-40">
                    <DropdownMenuLabel class="text-xs font-medium text-muted-foreground">
                        Loại đơn hàng
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioGroup v-model="orderTypeValue">
                        <DropdownMenuRadioItem value="RETAIL">Bán lẻ</DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="WHOLESALE">Bán sỉ</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
