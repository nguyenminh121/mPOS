<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { formatCurrencyVnd, formatDateTimeVnWithZone, invoiceStatusLabel, mapInvoiceToTemplate } from '@/lib/invoiceTemplate';
import { tenantRoutes } from '@/lib/tenantRoutes';

const props = defineProps<{ invoice: any }>();

const templateInvoice = computed(() => mapInvoiceToTemplate(props.invoice));
</script>

<template>
    <Head :title="`Chi tiết ${templateInvoice.code}`" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết hóa đơn {{ templateInvoice.code }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Tra cứu thông tin phát hành, người mua và các dòng sản phẩm đã ghi nhận.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="tenantRoutes.invoices.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                            Quay lại
                        </Link>
                    </div>
                </div>
            </section>

            <Alert>
                <FileText class="size-4" />
                <AlertTitle>Dòng hàng trên hóa đơn</AlertTitle>
                <AlertDescription>
                    Mỗi dòng thể hiện sản phẩm, ĐVT, số lượng và đơn giá thực tế đã xuất. Nếu hóa đơn gộp cuối ngày, các dòng đã được gộp theo cấu hình nghiệp vụ.
                </AlertDescription>
            </Alert>

            <section class="grid gap-4 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Mã hóa đơn</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.code || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</p>
                    <p class="mt-1 text-sm font-semibold">{{ invoiceStatusLabel(templateInvoice.status) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Ngày xuất</p>
                    <p class="mt-1 text-sm font-semibold">{{ formatDateTimeVnWithZone(templateInvoice.saleAt) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Khách hàng</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.customer.name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Số điện thoại</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.customer.phone || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Mã số thuế</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.customer.taxCode || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Đơn hàng nguồn</p>
                    <p class="mt-1 text-sm font-semibold">{{ props.invoice.order?.code || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Đơn vị phát hành</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.createdByUser.fullName || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">E-Invoices</p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ String(props.invoice.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase() === 'EXPORTED' ? 'Đã đồng bộ' : 'Chưa đồng bộ' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Thời điểm đồng bộ</p>
                    <p class="mt-1 text-sm font-semibold">{{ formatDateTimeVnWithZone(props.invoice.e_invoiced_at || null) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Email</p>
                    <p class="mt-1 text-sm font-semibold">{{ templateInvoice.customer.email || '-' }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold">Dữ liệu E-Invoices</h2>
                    <Link :href="tenantRoutes.eInvoices.index()" class="text-sm text-primary hover:underline">
                        Xem danh sách E-Invoices
                    </Link>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <h2 class="mb-3 text-base font-semibold">Sản phẩm trên hóa đơn</h2>
                <div class="overflow-x-auto rounded-xl border border-border/70">
                    <table class="min-w-[900px] w-full text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Sản phẩm</th>
                                <th class="px-3 py-2 text-left font-medium">ĐVT</th>
                                <th class="px-3 py-2 text-right font-medium">Số lượng</th>
                                <th class="px-3 py-2 text-right font-medium">Đơn giá</th>
                                <th class="px-3 py-2 text-right font-medium">Giảm giá</th>
                                <th class="px-3 py-2 text-right font-medium">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in templateInvoice.items" :key="item.id ?? `${item.productName}-${item.unitName}`" class="border-t border-border/70">
                                <td class="px-3 py-2">{{ item.productName }}</td>
                                <td class="px-3 py-2">{{ item.unitName }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrencyVnd(item.quantity) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrencyVnd(item.price) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrencyVnd(item.discount) }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ formatCurrencyVnd(item.total) }}</td>
                            </tr>
                            <tr v-if="templateInvoice.items.length === 0" class="border-t border-border/70">
                                <td colspan="6" class="px-3 py-4 text-center text-muted-foreground">Hóa đơn chưa có dòng sản phẩm.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ml-auto w-full max-w-md rounded-2xl border border-border/70 bg-card p-5">
                <h2 class="text-base font-semibold">Tổng kết hóa đơn</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Tổng tiền hàng</span>
                        <span class="font-medium">{{ formatCurrencyVnd(templateInvoice.subtotal) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Thuế</span>
                        <span class="font-medium">{{ formatCurrencyVnd(templateInvoice.taxAmount) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-border/70 pt-2 text-base">
                        <span class="font-semibold">Tổng thanh toán</span>
                        <span class="font-semibold text-primary">{{ formatCurrencyVnd(templateInvoice.finalAmount) }}</span>
                    </div>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>