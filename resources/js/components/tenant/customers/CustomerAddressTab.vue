<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tenantRoutes } from '@/lib/tenantRoutes';

type AddressRow = {
	id: number;
	name?: string | null;
	address?: string | null;
	receiver_name?: string | null;
	receiver_phone?: string | null;
	default_address: 'YES' | 'NO';
};

const props = defineProps<{
	customerId: string | number;
	addresses: AddressRow[];
}>();

const editingId = ref<number | null>(null);

const form = useForm({
	name: '',
	address: '',
	receiver_name: '',
	receiver_phone: '',
	default_address: 'NO' as 'YES' | 'NO',
});

const isEditing = computed(() => editingId.value !== null);

const resetForm = () => {
	editingId.value = null;
	form.reset();
	form.clearErrors();
	form.default_address = 'NO';
};

const startCreate = () => {
	resetForm();
};

const startEdit = (row: AddressRow) => {
	editingId.value = row.id;
	form.name = row.name ?? '';
	form.address = row.address ?? '';
	form.receiver_name = row.receiver_name ?? '';
	form.receiver_phone = row.receiver_phone ?? '';
	form.default_address = row.default_address === 'YES' ? 'YES' : 'NO';
};

const submit = () => {
	if (isEditing.value && editingId.value !== null) {
		form.put(tenantRoutes.customers.addresses.update(props.customerId, editingId.value), {
			preserveScroll: true,
			onSuccess: () => resetForm(),
		});
		return;
	}

	form.post(tenantRoutes.customers.addresses.store(props.customerId), {
		preserveScroll: true,
		onSuccess: () => resetForm(),
	});
};

const removeAddress = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa địa chỉ này?')) return;

	router.delete(tenantRoutes.customers.addresses.destroy(props.customerId, id), {
		preserveScroll: true,
		onSuccess: () => {
			if (editingId.value === id) {
				resetForm();
			}
		},
	});
};
</script>

<template>
	<div class="space-y-4">
		<section class="rounded-2xl border border-border/70 bg-card p-5">
			<div class="mb-4 flex items-center justify-between">
				<h3 class="text-base font-semibold">Địa chỉ khách hàng</h3>
				<Button type="button" variant="outline" size="sm" @click="startCreate">
					<Plus class="size-4" />
					Thêm địa chỉ
				</Button>
			</div>

			<div class="grid grid-cols-1 gap-3 md:grid-cols-2">
				<div>
					<Label class="mb-1">Tên địa chỉ</Label>
					<Input v-model="form.name" placeholder="VD: Văn phòng chính" />
				</div>
				<div>
					<Label class="mb-1">Người nhận</Label>
					<Input v-model="form.receiver_name" placeholder="Tên người nhận" />
				</div>
				<div>
					<Label class="mb-1">SĐT người nhận</Label>
					<Input v-model="form.receiver_phone" placeholder="SĐT người nhận" />
				</div>
				<div>
					<Label class="mb-1">Mặc định</Label>
					<select v-model="form.default_address" class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-sm">
						<option value="NO">Không</option>
						<option value="YES">Có</option>
					</select>
				</div>
				<div class="md:col-span-2">
					<Label class="mb-1">Địa chỉ</Label>
					<textarea v-model="form.address" rows="2" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
					<p v-if="form.errors.address" class="mt-1 text-xs text-destructive">{{ form.errors.address }}</p>
				</div>
			</div>

			<div class="mt-3 flex gap-2">
				<Button type="button" :disabled="form.processing" @click="submit">{{ isEditing ? 'Lưu địa chỉ' : 'Tạo địa chỉ' }}</Button>
				<Button v-if="isEditing" type="button" variant="outline" @click="resetForm">Hủy</Button>
			</div>
		</section>

		<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
			<table class="w-full text-sm">
				<thead class="bg-muted/40 text-left">
					<tr>
						<th class="p-3 font-medium">Tên</th>
						<th class="p-3 font-medium">Địa chỉ</th>
						<th class="p-3 font-medium">Người nhận</th>
						<th class="p-3 text-center font-medium">Mặc định</th>
						<th class="p-3 text-center font-medium">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="addresses.length === 0">
						<td colspan="5" class="p-6 text-center text-muted-foreground">Chưa có địa chỉ nào.</td>
					</tr>
					<tr v-for="row in addresses" :key="row.id" class="border-t">
						<td class="p-3">{{ row.name || '-' }}</td>
						<td class="p-3">{{ row.address || '-' }}</td>
						<td class="p-3">{{ row.receiver_name || '-' }}{{ row.receiver_phone ? ` (${row.receiver_phone})` : '' }}</td>
						<td class="p-3 text-center">{{ row.default_address === 'YES' ? 'Có' : 'Không' }}</td>
						<td class="p-3">
							<div class="flex items-center justify-center gap-1">
								<Button type="button" variant="outline" size="icon-sm" @click="startEdit(row)">
									<Pencil class="size-3.5" />
								</Button>
								<Button type="button" variant="outline" size="icon-sm" class="text-destructive" @click="removeAddress(row.id)">
									<Trash2 class="size-3.5" />
								</Button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>
