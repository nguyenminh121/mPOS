<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

const props = defineProps<{
    codeSequences: Array<{ id: number; entity_type: string; prefix: string; separator?: string | null; date_format?: string | null; sequence_length?: number | null; reset_period?: string | null }>;
    stockPolicy: { allow_negative_stock: boolean };
    invoicePolicy: { use_invoice_on_sale: boolean; invoice_mode: 'STANDARD' | 'POS' | 'BATCH' };
}>();

const createForm = useForm({ entity_type: '', prefix: '', separator: '-', date_format: 'Ymd', sequence_length: 6, reset_period: 'YEARLY' });
const stockPolicyForm = useForm({ allow_negative_stock: Boolean(props.stockPolicy?.allow_negative_stock) });
const invoicePolicyForm = useForm({
    use_invoice_on_sale: Boolean(props.invoicePolicy?.use_invoice_on_sale),
    invoice_mode: props.invoicePolicy?.invoice_mode ?? 'POS',
});
const submitCreate = () => createForm.post(tenantRoutes.config.codeSequences.store());
const submitStockPolicy = () => stockPolicyForm.put(tenantRoutes.config.stockPolicy.update());
const submitInvoicePolicy = () => invoicePolicyForm.put(tenantRoutes.config.invoicePolicy.update());

const remove = (id: number) => {
    if (!confirm('Delete code sequence?')) return;
    router.delete(tenantRoutes.config.codeSequences.destroy(id));
};
</script>

<template>
    <Head title="Tenant Config" />
    <TenantLayout>
        <div class="p-6 space-y-5">
            <h1 class="text-2xl font-semibold">Tenant Code Prefix Configuration</h1>

            <form class="grid gap-3 border rounded p-4" @submit.prevent="submitStockPolicy">
                <h2 class="text-lg font-medium">Thiết lập tồn kho</h2>
                <label class="inline-flex items-center gap-2">
                    <input v-model="stockPolicyForm.allow_negative_stock" type="checkbox" class="size-4" />
                    <span>Cho phép tồn kho âm khi bán hàng</span>
                </label>
                <p class="text-sm text-muted-foreground">Tắt: theo chuẩn ERP (không đủ tồn kho sẽ không tạo đơn). Bật: cho phép bán âm kho.</p>
                <button :disabled="stockPolicyForm.processing" class="w-fit px-4 py-2 rounded bg-black text-white">Lưu chính sách tồn kho</button>
            </form>

            <form class="grid gap-3 border rounded p-4" @submit.prevent="submitInvoicePolicy">
                <h2 class="text-lg font-medium">Thiết lập hóa đơn</h2>
                <label class="inline-flex items-center gap-2">
                    <input v-model="invoicePolicyForm.use_invoice_on_sale" type="checkbox" class="size-4" />
                    <span>Tự động xuất hóa đơn khi thanh toán thành công</span>
                </label>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Chế độ hóa đơn điện tử</label>
                    <select v-model="invoicePolicyForm.invoice_mode" class="border rounded px-3 py-2 w-full md:max-w-md">
                        <option value="STANDARD">STANDARD - Hóa đơn điện tử tiêu chuẩn (ký số real-time)</option>
                        <option value="POS">POS - Hóa đơn điện tử từ máy tính tiền (bill tại quầy)</option>
                        <option value="BATCH">BATCH - Gộp cuối ngày cho khách lẻ</option>
                    </select>
                </div>
                <p class="text-sm text-muted-foreground">
                    Tiêu chuẩn theo SRS: Khách doanh nghiệp/cần VAT dùng STANDARD, khách lẻ tại quầy dùng POS hoặc BATCH tùy đăng ký thuế của tenant.
                </p>
                <div class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Luồng công nợ: Đơn hoàn thành vẫn phải có chứng từ hóa đơn theo cấu hình. Thu tiền sau đó xử lý bằng Phiếu Thu (Payment/Receipt), không xuất lại hóa đơn bán hàng.
                </div>
                <button :disabled="invoicePolicyForm.processing" class="w-fit px-4 py-2 rounded bg-black text-white">Lưu chính sách hóa đơn</button>
            </form>

            <form class="grid md:grid-cols-6 gap-2 border rounded p-4" @submit.prevent="submitCreate">
                <input v-model="createForm.entity_type" placeholder="ORDER / INVOICE" class="border rounded px-3 py-2" />
                <input v-model="createForm.prefix" placeholder="Prefix" class="border rounded px-3 py-2" />
                <input v-model="createForm.separator" placeholder="-" class="border rounded px-3 py-2" />
                <input v-model="createForm.date_format" placeholder="Ymd" class="border rounded px-3 py-2" />
                <input v-model.number="createForm.sequence_length" type="number" min="1" max="20" class="border rounded px-3 py-2" />
                <select v-model="createForm.reset_period" class="border rounded px-3 py-2"><option>DAILY</option><option>MONTHLY</option><option>YEARLY</option><option>NEVER</option></select>
                <button :disabled="createForm.processing" class="md:col-span-6 px-4 py-2 rounded bg-black text-white">Save Configuration</button>
            </form>

            <div class="rounded border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40"><tr><th class="p-3 text-left">Entity</th><th class="p-3 text-left">Prefix</th><th class="p-3 text-left">Separator</th><th class="p-3 text-left">Date</th><th class="p-3 text-left">Length</th><th class="p-3 text-left">Reset</th><th class="p-3 text-right">Action</th></tr></thead>
                    <tbody>
                        <tr v-for="row in props.codeSequences" :key="row.id" class="border-t">
                            <td class="p-3">{{ row.entity_type }}</td>
                            <td class="p-3">{{ row.prefix }}</td>
                            <td class="p-3">{{ row.separator }}</td>
                            <td class="p-3">{{ row.date_format }}</td>
                            <td class="p-3">{{ row.sequence_length }}</td>
                            <td class="p-3">{{ row.reset_period }}</td>
                            <td class="p-3 text-right"><button class="text-red-600" @click="remove(row.id)">Delete</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
