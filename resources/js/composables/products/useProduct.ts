import { computed, nextTick, ref } from 'vue';
import * as XLSX from 'xlsx';
import type { VariantOption } from '@/composables/pos/models';
import { tenantRoutes } from '@/lib/tenantRoutes';

type RowErrors = Partial<Record<'name' | 'sku_barcode' | 'base_unit' | 'retail_price', string>>;

export type ImportProductRow = {
	_key: string;
	_sourceVariantId: number | null;
	name: string;
	sku: string;
	barcode: string;
	base_unit: string;
	cost_price: string;
	retail_price: string;
	wholesale_price: string;
	warehouse_id: number | null;
	initial_inventory: string;
	_errors: RowErrors & Record<string, string>;
	_submitting: boolean;
	_done: boolean;
	_failed: boolean;
	_variantId: number | null;
};

type Warehouse = { id: number; name: string };

type UseProductOptions = {
	warehouses: Warehouse[];
};

type ProductUploadSummary = {
	added: number;
	duplicates: number;
	skipped: number;
	failed?: number;
};

const STORE_BATCH_LIMIT = 500;

let counter = 0;

export function useProduct(options: UseProductOptions) {
	const activeTab = ref<'manual' | 'upload'>('manual');
	const rows = ref<ImportProductRow[]>([]);
	const searchQuery = ref('');

	const submitting = ref(false);
	const submitted = ref(false);
	const globalError = ref('');
	const duplicateNotice = ref('');

	const uploadError = ref('');
	const uploadSummary = ref<ProductUploadSummary | null>(null);
	const uploadedFileName = ref('');

	let duplicateNoticeTimer: ReturnType<typeof setTimeout> | null = null;

	const defaultWarehouseId = computed(() => options.warehouses[0]?.id ?? null);
	const pendingRows = computed(() => rows.value.filter((r) => !r._done));
	const failedRows = computed(() => rows.value.filter((r) => r._failed));
	const doneCount = computed(() => rows.value.filter((r) => r._done).length);

	const canSubmit = computed(() =>
		!submitting.value && pendingRows.value.some((r) => r.name.trim()),
	);

	const makeRow = (partial?: Partial<ImportProductRow>): ImportProductRow => ({
		_key: `r${++counter}`,
		_sourceVariantId: partial?._sourceVariantId ?? null,
		name: partial?.name ?? '',
		sku: partial?.sku ?? '',
		barcode: partial?.barcode ?? '',
		base_unit: partial?.base_unit ?? '',
		cost_price: partial?.cost_price ?? '',
		retail_price: partial?.retail_price ?? '',
		wholesale_price: partial?.wholesale_price ?? '',
		warehouse_id: partial?.warehouse_id ?? defaultWarehouseId.value,
		initial_inventory: partial?.initial_inventory ?? '',
		_errors: {},
		_submitting: false,
		_done: false,
		_failed: false,
		_variantId: null,
	});

	const reset = () => {
		counter = 0;
		rows.value = [];
		searchQuery.value = '';
		submitted.value = false;
		globalError.value = '';
		duplicateNotice.value = '';
		uploadError.value = '';
		uploadSummary.value = null;
		uploadedFileName.value = '';
		activeTab.value = 'manual';
		if (duplicateNoticeTimer) {
			clearTimeout(duplicateNoticeTimer);
			duplicateNoticeTimer = null;
		}
	};

	const showDuplicateNotice = (message: string) => {
		duplicateNotice.value = message;
		if (duplicateNoticeTimer) clearTimeout(duplicateNoticeTimer);
		duplicateNoticeTimer = setTimeout(() => {
			duplicateNotice.value = '';
			duplicateNoticeTimer = null;
		}, 5000);
	};

	const isDuplicate = (candidate: Pick<ImportProductRow, '_sourceVariantId' | 'sku' | 'barcode'>): boolean => {
		return rows.value.some((row) => {
			if (candidate._sourceVariantId && row._sourceVariantId) {
				return row._sourceVariantId === candidate._sourceVariantId;
			}

			if (candidate.sku.trim() && row.sku.trim() && candidate.sku.trim().toLowerCase() === row.sku.trim().toLowerCase()) {
				return true;
			}

			if (candidate.barcode.trim() && row.barcode.trim() && candidate.barcode.trim() === row.barcode.trim()) {
				return true;
			}

			return false;
		});
	};

	const addEmptyRow = () => {
		rows.value.push(makeRow());
	};

	const removeRow = (key: string) => {
		rows.value = rows.value.filter((r) => r._key !== key);
	};

	const addVariantFromSearch = (variant: VariantOption) => {
		const newRow = makeRow({
			_sourceVariantId: variant.id,
			name: (variant.product_name ?? variant.name ?? '').trim(),
			sku: (variant.sku ?? '').trim(),
			barcode: (variant.barcode ?? '').trim(),
			base_unit: (variant.base_unit ?? '').trim(),
			cost_price: '',
			retail_price: Number.isFinite(variant.price) ? String(variant.price) : '',
			wholesale_price: Number.isFinite(variant.price) ? String(variant.price) : '',
			warehouse_id: defaultWarehouseId.value,
			initial_inventory: '',
		});

		if (isDuplicate(newRow)) {
			const label = newRow.name || newRow.sku || newRow.barcode || 'Sản phẩm';
			showDuplicateNotice(`${label} đã có trong danh sách.`);
			searchQuery.value = '';
			return;
		}

		rows.value.push(newRow);
		searchQuery.value = '';
	};

	const addRowFromQuery = (query: string) => {
		const code = query.trim();
		if (!code) return;

		const existed = rows.value.some((row) => row.barcode.trim() === code || row.sku.trim().toLowerCase() === code.toLowerCase());
		if (existed) {
			showDuplicateNotice(`${code} đã có trong danh sách.`);
			searchQuery.value = '';
			return;
		}

		rows.value.push(makeRow({
			barcode: code,
			warehouse_id: defaultWarehouseId.value,
		}));
		searchQuery.value = '';
	};

	const toNumberString = (value: string | number | null | undefined): string => {
		if (value === null || value === undefined) return '';
		const num = typeof value === 'number' ? value : parseFloat(String(value).replace(/,/g, '').trim());
		if (!Number.isFinite(num)) return '';
		return String(num);
	};

	const normalizeHeader = (header: string): string =>
		header
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.replace(/đ/g, 'd')
			.replace(/\*+/g, ' ')
			.replace(/[^a-z0-9\s]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();

	const parseCSVLine = (line: string): string[] => {
		const result: string[] = [];
		let current = '';
		let inQuotes = false;

		for (let i = 0; i < line.length; i += 1) {
			const char = line[i];
			if (char === '"') {
				if (inQuotes && line[i + 1] === '"') {
					current += '"';
					i += 1;
				} else {
					inQuotes = !inQuotes;
				}
			} else if (char === ',' && !inQuotes) {
				result.push(current.trim());
				current = '';
			} else {
				current += char;
			}
		}
		result.push(current.trim());

		return result;
	};

	const mapHeaderToField: Record<string, keyof ImportProductRow | 'index' | 'ignore'> = {
		stt: 'index',
		'ma hang hoa': 'sku',
		'ma sku': 'sku',
		sku: 'sku',
		'ma vach': 'barcode',
		barcode: 'barcode',
		'ten san pham': 'name',
		'ten hang hoa': 'name',
		'don vi tinh co so (uom)': 'base_unit',
		'don vi tinh': 'base_unit',
		uom: 'base_unit',
		'gia nhap': 'cost_price',
		'don gia ton kho': 'cost_price',
		'gia ban le': 'retail_price',
		'gia ban buon': 'wholesale_price',
		'ton kho ban dau': 'initial_inventory',
		'mo ta san pham': 'ignore',
		'xuat xu': 'ignore',
		'thuong hieu': 'ignore',
		'anh san pham': 'ignore',
	};

	const mapRowToImport = (headers: string[], values: string[]): ImportProductRow | null => {
		const row = makeRow();

		headers.forEach((header, index) => {
			const key = mapHeaderToField[normalizeHeader(header)] ?? 'ignore';
			const value = (values[index] ?? '').toString().trim();
			if (!value || key === 'ignore' || key === 'index') return;

			if (key === 'cost_price') {
				row.cost_price = toNumberString(value);
				return;
			}

			if (key === 'retail_price') {
				row.retail_price = toNumberString(value);
				return;
			}

			if (key === 'wholesale_price') {
				row.wholesale_price = toNumberString(value);
				return;
			}

			if (key === 'initial_inventory') {
				row.initial_inventory = toNumberString(value);
				return;
			}

			if (key === 'name') row.name = value;
			if (key === 'sku') row.sku = value;
			if (key === 'barcode') row.barcode = value;
			if (key === 'base_unit') row.base_unit = value;
		});

		if (!row.name.trim() && !row.sku.trim() && !row.barcode.trim()) return null;
		return row;
	};

	const parseCsvFile = async (file: File): Promise<ImportProductRow[]> => {
		const text = await file.text();
		const lines = text
			.split(/\r?\n/)
			.map((line) => line.trim())
			.filter((line) => line.length > 0);

		const headerIndex = lines.findIndex((line, index) => {
			if (index > 30) return false;
			const normalized = normalizeHeader(line);
			return normalized.includes('ma hang hoa') || normalized.includes('sku') || normalized.includes('ma vach');
		});

		if (headerIndex < 0) throw new Error('Không tìm thấy dòng tiêu đề trong file CSV.');

		const headers = parseCSVLine(lines[headerIndex]);
		const parsedRows: ImportProductRow[] = [];

		for (let i = headerIndex + 1; i < lines.length; i += 1) {
			const mapped = mapRowToImport(headers, parseCSVLine(lines[i]));
			if (mapped) parsedRows.push(mapped);
		}

		return parsedRows;
	};

	const parseExcelFile = async (file: File): Promise<ImportProductRow[]> => {
		const data = await file.arrayBuffer();
		const wb = XLSX.read(data, { type: 'array' });
		const firstSheet = wb.SheetNames[0];
		if (!firstSheet) return [];

		const ws = wb.Sheets[firstSheet];
		const sheetData = XLSX.utils.sheet_to_json<(string | number | null)[]>(ws, { header: 1 });

		const headerIndex = sheetData.findIndex((row, index) => {
			if (index > 30 || !row?.length) return false;
			const line = row.map((cell) => normalizeHeader(String(cell ?? ''))).join(' | ');
			return line.includes('ma hang hoa') || line.includes('sku') || line.includes('ma vach');
		});

		if (headerIndex < 0) throw new Error('Không tìm thấy dòng tiêu đề trong file Excel.');

		const headers = (sheetData[headerIndex] ?? []).map((cell) => String(cell ?? ''));
		const parsedRows: ImportProductRow[] = [];

		for (let i = headerIndex + 1; i < sheetData.length; i += 1) {
			const values = (sheetData[i] ?? []).map((cell) => String(cell ?? ''));
			const mapped = mapRowToImport(headers, values);
			if (mapped) parsedRows.push(mapped);
		}

		return parsedRows;
	};

	const importFile = async (file: File): Promise<ProductUploadSummary> => {
		uploadError.value = '';
		uploadSummary.value = null;
		uploadedFileName.value = file.name;

		try {
			const lowerName = file.name.toLowerCase();
			const parsedRows = lowerName.endsWith('.csv')
				? await parseCsvFile(file)
				: await parseExcelFile(file);

			const { existingSkus, existingBarcodes } = await checkExistingInDb(parsedRows);

			const currentSkus = new Set(
				rows.value
					.map((item) => item.sku.trim().toLowerCase())
					.filter((value) => value !== ''),
			);
			const currentBarcodes = new Set(
				rows.value
					.map((item) => item.barcode.trim())
					.filter((value) => value !== ''),
			);

			const seenSkus = new Set<string>();
			const seenBarcodes = new Set<string>();
			const acceptedRows: ImportProductRow[] = [];
			let duplicates = 0;

			for (const row of parsedRows) {
				const skuKey = row.sku.trim().toLowerCase();
				const barcodeKey = row.barcode.trim();

				const duplicatedBySku = skuKey !== '' && (
					currentSkus.has(skuKey)
					|| seenSkus.has(skuKey)
					|| existingSkus.has(skuKey)
				);

				const duplicatedByBarcode = barcodeKey !== '' && (
					currentBarcodes.has(barcodeKey)
					|| seenBarcodes.has(barcodeKey)
					|| existingBarcodes.has(barcodeKey)
				);

				if (duplicatedBySku || duplicatedByBarcode) {
					duplicates += 1;
					continue;
				}

				if (skuKey !== '') {
					seenSkus.add(skuKey);
					currentSkus.add(skuKey);
				}

				if (barcodeKey !== '') {
					seenBarcodes.add(barcodeKey);
					currentBarcodes.add(barcodeKey);
				}

				acceptedRows.push(row);
			}

			if (acceptedRows.length > 0) {
				activeTab.value = 'manual';
				await nextTick();

				const chunkSize = 100;
				for (let index = 0; index < acceptedRows.length; index += chunkSize) {
					rows.value.push(...acceptedRows.slice(index, index + chunkSize));
					if (index + chunkSize < acceptedRows.length) {
						await new Promise<void>((resolve) => {
							requestAnimationFrame(() => resolve());
						});
					}
				}
			}

			const summary = {
				added: acceptedRows.length,
				duplicates,
				skipped: Math.max(parsedRows.length - acceptedRows.length - duplicates, 0),
				failed: 0,
			};
			uploadSummary.value = summary;
			if (summary.added === 0) {
				globalError.value = 'Không có dòng hợp lệ để thêm vào danh sách.';
			} else {
				globalError.value = '';
			}

			return summary;
		} catch (error) {
			uploadError.value = error instanceof Error ? error.message : 'Không thể đọc file nhập.';
			throw error;
		}
	};

	const downloadTemplate = () => {
		const link = document.createElement('a');
		link.href = '/assets/templates/Danh%20Sach%20Nhap%20Hang%20Hoa.xlsx';
		link.download = 'Danh Sach Nhap Hang Hoa.xlsx';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	};

	const validateRow = (row: ImportProductRow): boolean => {
		row._errors = {};
		if (!row.name.trim()) row._errors.name = 'Bắt buộc';
		if (!row.base_unit.trim()) row._errors.base_unit = 'Bắt buộc';
		if (!row.sku.trim() && !row.barcode.trim()) row._errors.sku_barcode = 'Nhập SKU hoặc Barcode';

		const price = Number(row.retail_price);
		if (!row.retail_price || Number.isNaN(price) || price < 0) row._errors.retail_price = 'Bắt buộc';

		return Object.keys(row._errors).length === 0;
	};

	const getCsrfToken = (): string => {
		const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
		return match ? decodeURIComponent(match[1]) : '';
	};

	const jsonPost = async (url: string, payload: unknown) => {
		const res = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-XSRF-TOKEN': getCsrfToken(),
				Accept: 'application/json',
			},
			body: JSON.stringify(payload),
		});
		const data = await res.json().catch(() => ({}));
		return { ok: res.ok, status: res.status, data };
	};

	const checkExistingInDb = async (items: ImportProductRow[]) => {
		const payload = {
			items: items.map((item) => ({
				sku: item.sku.trim() || null,
				barcode: item.barcode.trim() || null,
			})),
		};

		const { ok, data } = await jsonPost(tenantRoutes.products.checkExisting(), payload);
		if (!ok) {
			throw new Error(data?.message || 'Không thể đối chiếu sản phẩm tồn tại trong hệ thống.');
		}

		const existingSkus = new Set<string>(
			(data?.existing?.skus ?? [])
				.map((value: unknown) => String(value ?? '').trim().toLowerCase())
				.filter((value: string) => value !== ''),
		);

		const existingBarcodes = new Set<string>(
			(data?.existing?.barcodes ?? [])
				.map((value: unknown) => String(value ?? '').trim())
				.filter((value: string) => value !== ''),
		);

		return { existingSkus, existingBarcodes };
	};

	const submitAll = async (onSuccess?: () => void) => {
		const targetRows = pendingRows.value.filter((r) => r.name.trim());
		if (targetRows.length === 0) return;

		// ── Client-side field validation ──────────────────────────────────────
		let hasError = false;
		const invalidRows: Array<{ rowKey: string; rowNumber: number; errors: RowErrors & Record<string, string> }> = [];
		for (const row of targetRows) {
			if (!validateRow(row)) {
				hasError = true;
				invalidRows.push({
					rowKey: row._key,
					rowNumber: rows.value.findIndex((item) => item._key === row._key) + 1,
					errors: { ...row._errors },
				});
			}
		}
		if (hasError) {
			const firstInvalid = invalidRows[0];
			const firstMessage = firstInvalid
				? Object.values(firstInvalid.errors).find((value) => String(value).trim() !== '') ?? 'Thiếu dữ liệu bắt buộc.'
				: 'Thiếu dữ liệu bắt buộc.';

			globalError.value = `Có ${invalidRows.length} dòng chưa hợp lệ. Dòng ${firstInvalid?.rowNumber ?? '-'}: ${firstMessage}`;

			if (firstInvalid) {
				void nextTick(() => {
					const fieldPriority: Array<keyof RowErrors> = ['name', 'sku_barcode', 'base_unit', 'retail_price'];
					const firstField = fieldPriority.find((field) => Boolean(firstInvalid.errors[field])) ?? 'name';

					const row = rows.value.find((item) => item._key === firstInvalid.rowKey);
					if (!row) return;

					const targetField = firstField === 'sku_barcode'
						? (row.sku.trim() === '' ? 'sku' : 'barcode')
						: firstField;

					const selector = `[data-import-row-key="${row._key}"][data-import-field="${targetField}"]`;
					const input = document.querySelector<HTMLElement>(selector);
					if (!input) return;

					input.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
					if (typeof (input as HTMLInputElement).focus === 'function') {
						(input as HTMLInputElement).focus();
					}
				});
			}
			return;
		}

		submitting.value = true;
		submitted.value = true;
		globalError.value = '';

		// Mark all as submitting
		for (const row of targetRows) {
			row._submitting = true;
			row._failed = false;
			row._errors = {};
		}

		try {
			const toBatchPayloadRow = (row: ImportProductRow) => ({
				_key: row._key,
				name: row.name.trim(),
				sku: row.sku.trim() || null,
				barcode: row.barcode.trim() || null,
				base_unit: row.base_unit.trim(),
				retail_price: Number(row.retail_price),
				cost_price: row.cost_price.trim() !== '' ? Number(row.cost_price) : null,
				wholesale_price: row.wholesale_price.trim() !== '' ? Number(row.wholesale_price) : null,
				warehouse_id: row.warehouse_id ?? null,
				initial_inventory: row.initial_inventory.trim() !== '' ? Number(row.initial_inventory) : null,
			});

			const allResults: Array<{ _key: string; ok: boolean; variant_id?: number; errors?: Record<string, string[]> }> = [];
			let batchRequestError = '';

			for (let start = 0; start < targetRows.length; start += STORE_BATCH_LIMIT) {
				const batchRows = targetRows.slice(start, start + STORE_BATCH_LIMIT);
				const payload = { rows: batchRows.map(toBatchPayloadRow) };

				const { ok, status, data } = await jsonPost(tenantRoutes.products.storeBatch(), payload);

				if (!ok) {
					if (import.meta.env.DEV) {
						console.groupCollapsed('[store-batch] Request failed');
						console.log('status', status);
						console.log('batchStart', start);
						console.log('batchSize', batchRows.length);
						console.log('response', data);
						console.log('payloadPreview', payload.rows.slice(0, 3));
						console.groupEnd();
					}

					batchRequestError = data?.message ?? 'Không thể lưu danh sách hàng hóa.';
					const fieldErrors: Record<string, string[]> = data?.errors ?? {};
					let anyRowMapped = false;

					const applyErrorToBatch = (field: 'name' | 'base_unit' | 'sku_barcode' | 'retail_price', message: string) => {
						for (const row of batchRows) {
							row._submitting = false;
							row._failed = true;
							if (field === 'name') row._errors.name = message;
							if (field === 'base_unit') row._errors.base_unit = message;
							if (field === 'sku_barcode') row._errors.sku_barcode = message;
							if (field === 'retail_price') row._errors.retail_price = message;
						}
					};

					for (const [key, messages] of Object.entries(fieldErrors)) {
						const match = key.match(/^rows\.(\d+)\.(.+)$/);
						if (!match) {
							const message = (messages as string[])[0] ?? batchRequestError;
							if (key === 'base_unit') {
								anyRowMapped = true;
								applyErrorToBatch('base_unit', message);
								continue;
							}
							if (key === 'name') {
								anyRowMapped = true;
								applyErrorToBatch('name', message);
								continue;
							}
							if (key === 'sku' || key === 'barcode') {
								anyRowMapped = true;
								applyErrorToBatch('sku_barcode', message);
								continue;
							}
							if (key === 'retail_price' || key === 'price') {
								anyRowMapped = true;
								applyErrorToBatch('retail_price', message);
								continue;
							}

							continue;
						}
						const idx = parseInt(match[1], 10);
						const field = match[2];
						const row = batchRows[idx];
						if (!row) continue;

						anyRowMapped = true;
						row._submitting = false;
						row._failed = true;
						if (field === 'name') {
							row._errors.name = (messages as string[])[0] ?? '';
						} else if (field === 'base_unit') {
							row._errors.base_unit = (messages as string[])[0] ?? '';
						} else if (field === 'sku' || field === 'barcode') {
							row._errors.sku_barcode = (messages as string[])[0] ?? '';
						} else if (field === 'retail_price') {
							row._errors.retail_price = (messages as string[])[0] ?? '';
						} else if (!row._errors.name) {
							row._errors.name = (messages as string[])[0] ?? '';
						}
					}

					if (!anyRowMapped) {
						for (const row of batchRows) {
							row._submitting = false;
							row._failed = true;
							row._errors.name = batchRequestError;
						}
					}

					continue;
				}

				allResults.push(...((data.results as Array<{ _key: string; ok: boolean; variant_id?: number; errors?: Record<string, string[]> }>) ?? []));
			}

			// ── Process per-row results ────────────────────────────────────────
			const resultMap = new Map<string, { ok: boolean; variant_id?: number; errors?: Record<string, string[]> }>(
				allResults.map((r) => [r._key, r]),
			);

			for (const row of targetRows) {
				if (!row._submitting) {
					continue;
				}

				row._submitting = false;
				const result = resultMap.get(row._key);
				if (!result || result.ok) {
					row._done = true;
				} else {
					row._failed = true;
					const errs = result.errors ?? {};
					row._errors.name = errs.name?.[0] ?? row._errors.name;
					row._errors.sku_barcode = errs.sku?.[0] ?? errs.barcode?.[0] ?? row._errors.sku_barcode;
					row._errors.base_unit = errs.base_unit?.[0] ?? row._errors.base_unit;
					row._errors.retail_price = errs.retail_price?.[0] ?? errs.price?.[0] ?? row._errors.retail_price;
				}
			}

			if (batchRequestError && failedRows.value.length > 0) {
				globalError.value = batchRequestError;
			}
		} catch {
			for (const row of targetRows) {
				row._submitting = false;
				row._failed = true;
				row._errors.name = 'Lỗi kết nối';
			}
			globalError.value = 'Lỗi kết nối máy chủ.';
		} finally {
			submitting.value = false;
		}

		if (failedRows.value.length > 0) {
			globalError.value = `Có ${failedRows.value.length} dòng lưu thất bại.`;
		}

		if (failedRows.value.length === 0) {
			onSuccess?.();
		}
	};

	reset();

	return {
		activeTab,
		rows,
		searchQuery,
		submitting,
		submitted,
		globalError,
		duplicateNotice,
		uploadedFileName,
		uploadError,
		uploadSummary,
		canSubmit,
		failedRows,
		doneCount,
		addEmptyRow,
		removeRow,
		addVariantFromSearch,
		addRowFromQuery,
		importFile,
		downloadTemplate,
		reset,
		submitAll,
	};
}

