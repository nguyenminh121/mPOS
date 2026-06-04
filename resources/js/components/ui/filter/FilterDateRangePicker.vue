<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import DateTimePicker from '@/components/DateTimePicker.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = defineProps<{
    from: string;
    to: string;
}>();

const emit = defineEmits<{
    (e: 'update:from', v: string): void;
    (e: 'update:to', v: string): void;
}>();

const customFrom = ref(props.from || '');
const customTo = ref(props.to || '');

watch(
    () => props.from,
    (value) => {
        customFrom.value = value || '';
    },
);

watch(
    () => props.to,
    (value) => {
        customTo.value = value || '';
    },
);

const isActive = computed(() => props.from !== '' || props.to !== '');

const pad = (value: number) => String(value).padStart(2, '0');

const toDateKey = (date: Date) => {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());
const endOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

const startOfWeek = (date: Date) => {
    const d = startOfDay(date);
    const offset = (d.getDay() + 6) % 7;
    d.setDate(d.getDate() - offset);
    return d;
};

const endOfWeek = (date: Date) => {
    const d = startOfWeek(date);
    d.setDate(d.getDate() + 6);
    return d;
};

const startOfMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth(), 1);
const endOfMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 1, 0);

const startOfQuarter = (date: Date) => {
    const quarterMonth = Math.floor(date.getMonth() / 3) * 3;
    return new Date(date.getFullYear(), quarterMonth, 1);
};

const endOfQuarter = (date: Date) => {
    const start = startOfQuarter(date);
    return new Date(start.getFullYear(), start.getMonth() + 3, 0);
};

const startOfYear = (date: Date) => new Date(date.getFullYear(), 0, 1);
const endOfYear = (date: Date) => new Date(date.getFullYear(), 11, 31);

const applyRange = (from: Date | null, to: Date | null) => {
    emit('update:from', from ? toDateKey(from) : '');
    emit('update:to', to ? toDateKey(to) : '');
};

const applyToday = () => {
    const today = new Date();
    applyRange(startOfDay(today), endOfDay(today));
};

const applyYesterday = () => {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    applyRange(startOfDay(yesterday), endOfDay(yesterday));
};

const applyThisWeek = () => {
    const now = new Date();
    applyRange(startOfWeek(now), endOfWeek(now));
};

const applyLastWeek = () => {
    const now = new Date();
    const start = startOfWeek(now);
    start.setDate(start.getDate() - 7);
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    applyRange(start, end);
};

const applyLastNDays = (days: number) => {
    const now = new Date();
    const end = endOfDay(now);
    const start = startOfDay(now);
    start.setDate(start.getDate() - (days - 1));
    applyRange(start, end);
};

const applyThisMonth = () => {
    const now = new Date();
    applyRange(startOfMonth(now), endOfMonth(now));
};

const applyLastMonth = () => {
    const now = new Date();
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    applyRange(startOfMonth(lastMonth), endOfMonth(lastMonth));
};

const applyThisQuarter = () => {
    const now = new Date();
    applyRange(startOfQuarter(now), endOfQuarter(now));
};

const applyLastQuarter = () => {
    const now = new Date();
    const quarterStart = startOfQuarter(now);
    const lastQuarterDate = new Date(quarterStart.getFullYear(), quarterStart.getMonth() - 1, 1);
    applyRange(startOfQuarter(lastQuarterDate), endOfQuarter(lastQuarterDate));
};

const applyThisYear = () => {
    const now = new Date();
    applyRange(startOfYear(now), endOfYear(now));
};

const applyLastYear = () => {
    const now = new Date();
    const lastYear = new Date(now.getFullYear() - 1, 0, 1);
    applyRange(startOfYear(lastYear), endOfYear(lastYear));
};

const applyAllTime = () => {
    applyRange(null, null);
};

const applyCustom = () => {
    const from = customFrom.value.trim();
    const to = customTo.value.trim();

    if (from !== '' && to !== '' && from > to) {
        emit('update:from', to);
        emit('update:to', from);
        return;
    }

    emit('update:from', from);
    emit('update:to', to);
};

const triggerLabel = computed(() => {
    const from = props.from || '';
    const to = props.to || '';

    if (from === '' && to === '') return 'Toàn thời gian';
    if (from !== '' && to !== '') return `${from} → ${to}`;
    if (from !== '') return `Từ ${from}`;
    return `Đến ${to}`;
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="h-9 w-full justify-start gap-2 px-3 text-sm font-normal"
                :class="isActive ? 'border-primary/60 bg-primary/5 text-primary' : ''"
            >
                <CalendarDays class="size-4 shrink-0" :class="isActive ? 'text-primary' : 'text-muted-foreground'" />
                <span class="truncate">{{ triggerLabel }}</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-[780px] max-w-[95vw] p-4" @pointer-down.stop>
            <div class="grid gap-4 xl:grid-cols-[1.1fr_1fr]">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Theo ngày</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="applyToday">Hôm nay</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyYesterday">Hôm qua</Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Theo tuần</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="applyThisWeek">Tuần này</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastWeek">Tuần trước</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastNDays(7)">7 ngày qua</Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Theo tháng</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="applyThisMonth">Tháng này</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastMonth">Tháng trước</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastNDays(30)">30 ngày qua</Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Theo quý</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="applyThisQuarter">Quý này</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastQuarter">Quý trước</Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Theo năm</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="applyThisYear">Năm nay</Button>
                            <Button type="button" variant="outline" size="sm" @click="applyLastYear">Năm trước</Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Khác</p>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" size="sm" @click="applyAllTime">Toàn thời gian</Button>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border/70 p-3">
                    <p class="text-sm font-semibold">Tùy chỉnh</p>
                    <p class="mt-1 text-xs text-muted-foreground">Lọc theo ngày, bỏ qua phần giờ.</p>

                    <div class="mt-3 grid gap-2">
                        <label class="text-xs text-muted-foreground">Từ ngày</label>
                        <DateTimePicker v-model="customFrom" expand date-only />

                        <label class="mt-1 text-xs text-muted-foreground">Đến ngày</label>
                        <DateTimePicker v-model="customTo" expand date-only />
                    </div>

                    <div class="mt-3 flex items-center justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="customFrom = ''; customTo = ''">Bỏ qua</Button>
                        <Button type="button" size="sm" @click="applyCustom">Áp dụng</Button>
                    </div>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
