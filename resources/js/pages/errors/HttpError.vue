<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    status: number;
}>();

const statusText = computed(() => {
    const map: Record<number, string> = {
        401: '401',
        403: '403',
        404: '404',
        419: '419',
        429: '429',
        500: '500',
        503: '503',
    };

    return map[props.status] ?? String(props.status);
});

const title = computed(() => {
    const map: Record<number, string> = {
        401: 'Bạn chưa đăng nhập hoặc phiên làm việc không hợp lệ',
        403: 'Bạn không có quyền truy cập nội dung này',
        404: 'Không tìm thấy nội dung bạn yêu cầu',
        419: 'Phiên làm việc đã hết hạn',
        429: 'Bạn thao tác quá nhanh, vui lòng thử lại sau',
        500: 'Hệ thống đang gặp sự cố nội bộ',
        503: 'Hệ thống đang bảo trì, vui lòng quay lại sau',
    };

    return map[props.status] ?? 'Đã có lỗi xảy ra';
});

const description = computed(() => {
    const map: Record<number, string> = {
        401: 'Vui lòng đăng nhập lại để tiếp tục thao tác.',
        403: 'Nếu bạn nghĩ đây là nhầm lẫn, hãy liên hệ quản trị viên.',
        404: 'Liên kết có thể đã thay đổi hoặc nội dung đã bị xóa.',
        419: 'Bạn hãy tải lại trang hoặc đăng nhập lại.',
        429: 'Hãy chờ ít giây rồi thử lại.',
        500: 'Đội ngũ kỹ thuật đã được thông báo để xử lý.',
        503: 'Chúng tôi sẽ trở lại sớm nhất có thể.',
    };

    return map[props.status] ?? 'Vui lòng thử lại sau.';
});

const goBack = () => {
    if (typeof window === 'undefined') return;
    window.history.back();
};
</script>

<template>
    <Head :title="`Lỗi ${statusText}`" />

    <div class="flex min-h-screen items-center justify-center bg-background p-6">
        <div class="w-full max-w-xl rounded-2xl border border-border/70 bg-card p-8 text-center shadow-sm">
            <p class="text-6xl font-bold tracking-tight text-primary/80">{{ statusText }}</p>
            <h1 class="mt-4 text-xl font-semibold">{{ title }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ description }}</p>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                <Button type="button" variant="outline" @click="goBack">Quay lại trang trước</Button>
                <Button as-child>
                    <Link href="/dashboard">Về trang chính</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
