<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight, ChevronUp, ChevronDown, Clock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

// ── Props / Emits ─────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    modelValue: string; // 'YYYY-MM-DDTHH:mm'
    expand?: boolean;
    disabled?: boolean;
    dateOnly?: boolean;
}>(), {
    expand: false,
    disabled: false,
    dateOnly: false,
});

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

// ── Internal state ────────────────────────────────────────────────────────────

const parse = (value: string) => {
    const d = value ? new Date(value) : new Date();
    return {
        year: d.getFullYear(),
        month: d.getMonth(),   // 0-indexed
        day: d.getDate(),
        hour: d.getHours(),
        minute: d.getMinutes(),
    };
};

const parts = ref(parse(props.modelValue));
const viewYear = ref(parts.value.year);
const viewMonth = ref(parts.value.month);
const open = ref(false);

watch(
    () => props.modelValue,
    (val) => {
        const p = parse(val);
        parts.value = p;
        viewYear.value = p.year;
        viewMonth.value = p.month;
    },
);

// ── Helpers ───────────────────────────────────────────────────────────────────

const pad = (n: number) => String(n).padStart(2, '0');

const commit = () => {
    const { year, month, day, hour, minute } = parts.value;
    const maxDay = new Date(year, month + 1, 0).getDate();
    const d = Math.min(day, maxDay);

    if (props.dateOnly) {
        emit('update:modelValue', `${year}-${pad(month + 1)}-${pad(d)}`);
        return;
    }

    emit('update:modelValue', `${year}-${pad(month + 1)}-${pad(d)}T${pad(hour)}:${pad(minute)}`);
};

// ── Display label ─────────────────────────────────────────────────────────────

const displayLabel = computed(() => {
    const { year, month, day, hour, minute } = parts.value;
    if (props.dateOnly) {
        return `${pad(day)}/${pad(month + 1)}/${year}`;
    }

    return `${pad(day)}/${pad(month + 1)}/${year}  ${pad(hour)}:${pad(minute)}`;
});

const triggerClass = computed(() => {
    if (props.expand) {
        return 'h-10 w-full justify-start gap-2 px-3 font-normal tabular-nums';
    }

    return 'h-9 gap-2 px-3 font-normal tabular-nums';
});

// ── Calendar ──────────────────────────────────────────────────────────────────

const WEEK_DAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const MONTH_NAMES = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

const calendarDays = computed(() => {
    const firstDay = new Date(viewYear.value, viewMonth.value, 1).getDay();
    const startOffset = (firstDay + 6) % 7; // Mon = 0
    const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
    const daysInPrev = new Date(viewYear.value, viewMonth.value, 0).getDate();

    const cells: { day: number; type: 'prev' | 'cur' | 'next' }[] = [];

    for (let i = startOffset - 1; i >= 0; i--) cells.push({ day: daysInPrev - i, type: 'prev' });
    for (let d = 1; d <= daysInMonth; d++) cells.push({ day: d, type: 'cur' });
    const rest = 42 - cells.length;
    for (let d = 1; d <= rest; d++) cells.push({ day: d, type: 'next' });

    return cells;
});

const prevMonth = () => {
    if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value--; }
    else viewMonth.value--;
};

const nextMonth = () => {
    if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value++; }
    else viewMonth.value++;
};

const selectDay = (day: number, type: 'prev' | 'cur' | 'next') => {
    if (type === 'prev') prevMonth();
    else if (type === 'next') nextMonth();
    parts.value.day = day;
    parts.value.month = viewMonth.value;
    parts.value.year = viewYear.value;
    commit();

    if (props.dateOnly) {
        open.value = false;
    }
};

const isSelected = (day: number, type: 'prev' | 'cur' | 'next') =>
    type === 'cur' &&
    day === parts.value.day &&
    viewMonth.value === parts.value.month &&
    viewYear.value === parts.value.year;

const isToday = (day: number, type: 'prev' | 'cur' | 'next') => {
    if (type !== 'cur') return false;
    const t = new Date();
    return day === t.getDate() && viewMonth.value === t.getMonth() && viewYear.value === t.getFullYear();
};

// ── Year list ─────────────────────────────────────────────────────────────────

const showYearPicker = ref(false);
const currentYear = new Date().getFullYear();
const yearList = Array.from({ length: 12 }, (_, i) => currentYear - 4 + i);

const selectYear = (y: number) => {
    viewYear.value = y;
    showYearPicker.value = false;
};

// ── Time ──────────────────────────────────────────────────────────────────────

const MINUTE_PRESETS = [0, 15, 30, 45];

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

const adjustHour = (delta: number) => {
    parts.value.hour = (parts.value.hour + delta + 24) % 24;
    commit();
};

const adjustMinute = (delta: number) => {
    parts.value.minute = (parts.value.minute + delta + 60) % 60;
    commit();
};

const setMinute = (m: number) => {
    parts.value.minute = m;
    commit();
};

const applyManualHour = (value: string) => {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) return;
    parts.value.hour = clamp(parsed, 0, 23);
    commit();
};

const applyManualMinute = (value: string) => {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) return;
    parts.value.minute = clamp(parsed, 0, 59);
    commit();
};

const setNow = () => {
    const d = new Date();
    parts.value = {
        year: d.getFullYear(),
        month: d.getMonth(),
        day: d.getDate(),
        hour: d.getHours(),
        minute: d.getMinutes(),
    };
    viewYear.value = parts.value.year;
    viewMonth.value = parts.value.month;
    commit();
};
</script>

<template>
    <DropdownMenu :open="open" @update:open="(value) => (open = value)">
        <!-- ── Trigger ────────────────────────────────────────────────────── -->
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                :class="triggerClass"
                :disabled="props.disabled"
            >
                <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
                {{ displayLabel }}
            </Button>
        </DropdownMenuTrigger>

        <!-- ── Panel ──────────────────────────────────────────────────────── -->
        <DropdownMenuContent align="end" class="w-72 p-0" @pointer-down.stop>

            <!-- Calendar section -->
            <div class="p-3">

                <!-- Month / Year navigation -->
                <div class="mb-2 flex items-center justify-between gap-1">
                    <Button variant="ghost" size="icon" class="size-7" @click="prevMonth">
                        <ChevronLeft class="size-4" />
                    </Button>

                    <button
                        type="button"
                        class="flex-1 rounded px-2 py-0.5 text-center text-sm font-semibold hover:bg-accent"
                        @click="showYearPicker = !showYearPicker"
                    >
                        {{ MONTH_NAMES[viewMonth] }} {{ viewYear }}
                    </button>

                    <Button variant="ghost" size="icon" class="size-7" @click="nextMonth">
                        <ChevronRight class="size-4" />
                    </Button>
                </div>

                <!-- Year picker overlay -->
                <Transition
                    enter-active-class="transition-all duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition-all duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="showYearPicker" class="mb-2 grid grid-cols-4 gap-1">
                        <button
                            v-for="y in yearList"
                            :key="y"
                            type="button"
                            class="rounded px-1 py-1.5 text-center text-sm transition-colors hover:bg-accent"
                            :class="y === viewYear ? 'bg-primary text-primary-foreground hover:bg-primary/90' : ''"
                            @click="selectYear(y)"
                        >
                            {{ y }}
                        </button>
                    </div>
                </Transition>

                <!-- Weekday headers -->
                <div class="mb-1 grid grid-cols-7">
                    <div
                        v-for="wd in WEEK_DAYS"
                        :key="wd"
                        class="py-1 text-center text-[11px] font-medium text-muted-foreground"
                    >
                        {{ wd }}
                    </div>
                </div>

                <!-- Day cells -->
                <div class="grid grid-cols-7 gap-y-0.5">
                    <button
                        v-for="(cell, i) in calendarDays"
                        :key="i"
                        type="button"
                        class="relative flex h-8 w-full items-center justify-center rounded text-sm transition-colors"
                        :class="[
                            cell.type !== 'cur' ? 'text-muted-foreground/40' : 'hover:bg-accent',
                            isSelected(cell.day, cell.type) && 'bg-primary text-primary-foreground hover:bg-primary/90',
                            !isSelected(cell.day, cell.type) && isToday(cell.day, cell.type) && 'font-semibold text-primary underline underline-offset-2',
                        ]"
                        @click="selectDay(cell.day, cell.type)"
                    >
                        {{ cell.day }}
                    </button>
                </div>
            </div>

            <template v-if="!props.dateOnly">
                <div class="border-t border-border/60 mx-3" />

                <div class="p-3">
                    <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <Clock class="size-3.5" />
                        Giờ trong ngày
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex flex-col items-center">
                            <Button variant="ghost" size="icon" class="size-7" @click="adjustHour(1)">
                                <ChevronUp class="size-3.5" />
                            </Button>
                            <input
                                :value="pad(parts.hour)"
                                type="number"
                                min="0"
                                max="23"
                                class="h-10 w-12 rounded-md border border-input bg-background text-center text-lg font-semibold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:ring-2 focus:ring-ring"
                                @input="applyManualHour(($event.target as HTMLInputElement).value)"
                            />
                            <Button variant="ghost" size="icon" class="size-7" @click="adjustHour(-1)">
                                <ChevronDown class="size-3.5" />
                            </Button>
                        </div>

                        <span class="mb-0.5 text-xl font-bold text-muted-foreground">:</span>

                        <div class="flex flex-col items-center">
                            <Button variant="ghost" size="icon" class="size-7" @click="adjustMinute(1)">
                                <ChevronUp class="size-3.5" />
                            </Button>
                            <input
                                :value="pad(parts.minute)"
                                type="number"
                                min="0"
                                max="59"
                                class="h-10 w-12 rounded-md border border-input bg-background text-center text-lg font-semibold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:ring-2 focus:ring-ring"
                                @input="applyManualMinute(($event.target as HTMLInputElement).value)"
                            />
                            <Button variant="ghost" size="icon" class="size-7" @click="adjustMinute(-1)">
                                <ChevronDown class="size-3.5" />
                            </Button>
                        </div>

                        <div class="ml-auto flex flex-col gap-1">
                            <button
                                v-for="m in MINUTE_PRESETS"
                                :key="m"
                                type="button"
                                class="h-6 w-10 rounded text-xs transition-colors hover:bg-accent"
                                :class="parts.minute === m ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'bg-muted/60'"
                                @click="setMinute(m)"
                            >
                                :{{ pad(m) }}
                            </button>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3 h-7 w-full gap-1.5 text-xs"
                        @click="setNow"
                    >
                        <Clock class="size-3" />
                        Dùng giờ hiện tại
                    </Button>
                </div>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
