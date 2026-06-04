<script setup lang="ts">
import { FileText } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type InvoiceItem = {
	id: number;
	product_name?: string | null;
	unit_name?: string | null;
	quantity?: number | string | null;
	price?: number | string | null;
	discount?: number | string | null;
	tax_rate?: number | string | null;
	total?: number | string | null;
};

type InvoiceRow = {
	id: number;
	code: string;
	items?: InvoiceItem[];
};

const props = defineProps<{
	invoice: InvoiceRow;
}>();

const toNumber = (value: number | string | null | undefined): number => {
	if (value === null || value === undefined) return 0;
	const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
	return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
	return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const csvEscape = (value: string | number): string => {
	const normalized = String(value ?? '').replace(/"/g, '""');
	return `"${normalized}"`;
};

const exportExcel = () => {
	const rows = props.invoice.items ?? [];
	const headers = ['Sản phẩm', 'ĐVT', 'Số lượng', 'Đơn giá', 'Giảm giá', 'Thuế %', 'Thành tiền'];
	const dataRows = rows.map((item) => [
		item.product_name || '-',
		item.unit_name || 'Cái',
		toNumber(item.quantity),
		toNumber(item.price),
		toNumber(item.discount),
		toNumber(item.tax_rate),
		toNumber(item.total),
	]);

	const csvContent = [headers, ...dataRows]
		.map((row) => row.map((cell) => csvEscape(cell)).join(','))
		.join('\n');

	const bom = '\uFEFF';
	const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
	const url = URL.createObjectURL(blob);
	const anchor = document.createElement('a');
	anchor.href = url;
	anchor.download = `${props.invoice.code || 'invoice'}-items.csv`;
	document.body.appendChild(anchor);
	anchor.click();
	document.body.removeChild(anchor);
	URL.revokeObjectURL(url);
};

const exportPdf = () => {
	const rows = props.invoice.items ?? [];
	const rowHtml = rows.length > 0
		? rows.map((item) => `
			<tr>
				<td>${item.product_name || '-'}</td>
				<td>${item.unit_name || 'Cái'}</td>
				<td style="text-align:right;">${formatCurrency(item.quantity)}</td>
				<td style="text-align:right;">${formatCurrency(item.price)}</td>
				<td style="text-align:right;">${formatCurrency(item.discount)}</td>
				<td style="text-align:right;">${formatCurrency(item.tax_rate)}</td>
				<td style="text-align:right;">${formatCurrency(item.total)}</td>
			</tr>
		`).join('')
		: '<tr><td colspan="7" style="text-align:center;">Hóa đơn chưa có dòng sản phẩm.</td></tr>';

	const printableHtml = `
		<!doctype html>
		<html>
			<head>
				<meta charset="utf-8" />
				<title>Invoice ${props.invoice.code}</title>
				<style>
					body { font-family: Arial, sans-serif; padding: 20px; }
					h2 { margin: 0 0 12px; }
					table { width: 100%; border-collapse: collapse; font-size: 13px; }
					th, td { border: 1px solid #d4d4d8; padding: 8px; }
					th { background: #f4f4f5; text-align: left; }
				</style>
			</head>
			<body>
				<h2>Chi tiết hóa đơn ${props.invoice.code}</h2>
				<table>
					<thead>
						<tr>
							<th>Sản phẩm</th>
							<th>ĐVT</th>
							<th style="text-align:right;">Số lượng</th>
							<th style="text-align:right;">Đơn giá</th>
							<th style="text-align:right;">Giảm giá</th>
							<th style="text-align:right;">Thuế %</th>
							<th style="text-align:right;">Thành tiền</th>
						</tr>
					</thead>
					<tbody>${rowHtml}</tbody>
				</table>
			</body>
		</html>
	`;

	const popup = window.open('', '_blank', 'noopener,noreferrer');
	if (!popup) return;

	popup.document.open();
	popup.document.write(printableHtml);
	popup.document.close();
	popup.onload = () => {
		popup.focus();
		popup.print();
	};
};
</script>

<template>
	<div class="space-y-3">
		<div class="flex flex-wrap items-center justify-end gap-2">
			<Button type="button" variant="outline" size="sm" @click="exportExcel">Export Excel</Button>
			<Button type="button" variant="outline" size="sm" @click="exportPdf">Export PDF</Button>
		</div>

		<Alert>
			<FileText class="size-4" />
			<AlertTitle>Dòng hàng đã xuất</AlertTitle>
			<AlertDescription>
				Hiển thị theo từng dòng sản phẩm đã ghi nhận trên hóa đơn: sản phẩm, ĐVT, số lượng, đơn giá thực tế và tổng tiền.
			</AlertDescription>
		</Alert>

		<div class="overflow-x-auto rounded-xl border border-border/70">
			<table class="min-w-[760px] w-full text-sm">
				<thead class="bg-muted/40 text-muted-foreground">
					<tr>
						<th class="px-3 py-2 text-left font-medium">Sản phẩm</th>
						<th class="px-3 py-2 text-left font-medium">ĐVT</th>
						<th class="px-3 py-2 text-right font-medium">Số lượng</th>
						<th class="px-3 py-2 text-right font-medium">Đơn giá</th>
						<th class="px-3 py-2 text-right font-medium">Giảm giá</th>
						<th class="px-3 py-2 text-right font-medium">Thuế %</th>
						<th class="px-3 py-2 text-right font-medium">Thành tiền</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="item in (props.invoice.items ?? [])" :key="item.id" class="border-t border-border/70">
						<td class="px-3 py-2">{{ item.product_name || '-' }}</td>
						<td class="px-3 py-2">{{ item.unit_name || 'Cái' }}</td>
						<td class="px-3 py-2 text-right">{{ formatCurrency(item.quantity) }}</td>
						<td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
						<td class="px-3 py-2 text-right">{{ formatCurrency(item.discount) }}</td>
						<td class="px-3 py-2 text-right">{{ formatCurrency(item.tax_rate) }}</td>
						<td class="px-3 py-2 text-right font-medium">{{ formatCurrency(item.total) }}</td>
					</tr>
					<tr v-if="(props.invoice.items ?? []).length === 0" class="border-t border-border/70">
						<td colspan="7" class="px-3 py-4 text-center text-muted-foreground">Hóa đơn chưa có dòng sản phẩm.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
