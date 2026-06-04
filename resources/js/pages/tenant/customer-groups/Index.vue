<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type GroupRow = {
	id: number;
	code: string;
	name: string;
	description?: string | null;
	customers_count: number;
	default_price_list_name?: string | null;
	is_active: boolean;
};

const props = defineProps<{
	customerGroups: GroupRow[];
}>();

const activeCount = computed(() => props.customerGroups.filter((group) => group.is_active).length);
const totalMembers = computed(() => props.customerGroups.reduce((sum, group) => sum + group.customers_count, 0));

const deleteGroup = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa nhóm khách hàng này?')) return;
	router.delete(tenantRoutes.customerGroups.destroy(id));
};
</script>

<template>
	<Head title="Nhóm khách hàng" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Nhóm khách hàng</h1>
						<p class="mt-1 text-sm text-muted-foreground">Phân nhóm khách hàng và cấu hình bảng giá mặc định theo nhóm.</p>
					</div>
					<Button as-child>
						<Link :href="tenantRoutes.customerGroups.create()">
							<Plus class="size-4" />
							Thêm nhóm
						</Link>
					</Button>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng nhóm</p>
						<p class="mt-1 text-2xl font-semibold">{{ customerGroups.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng khách trong nhóm</p>
						<p class="mt-1 text-2xl font-semibold">{{ totalMembers }}</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã</th>
								<th class="p-3 font-medium">Tên nhóm</th>
								<th class="p-3 font-medium">Bảng giá mặc định</th>
								<th class="p-3 text-center font-medium">Số khách hàng</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="customerGroups.length === 0">
								<td colspan="6" class="p-8 text-center text-muted-foreground">Chưa có nhóm khách hàng nào.</td>
							</tr>
							<tr v-for="group in customerGroups" :key="group.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ group.code }}</td>
								<td class="p-3">
									<p class="font-semibold">{{ group.name }}</p>
									<p class="text-xs text-muted-foreground">{{ group.description || '-' }}</p>
								</td>
								<td class="p-3">{{ group.default_price_list_name || '-' }}</td>
								<td class="p-3 text-center">{{ group.customers_count }}</td>
								<td class="p-3 text-center">
									<Badge :variant="group.is_active ? 'default' : 'secondary'">{{ group.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
								</td>
								<td class="p-3">
									<div class="flex items-center justify-center">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button variant="outline" size="icon-sm"><MoreHorizontal class="size-3.5" /></Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="end" class="w-44">
												<DropdownMenuItem as-child><Link :href="tenantRoutes.customerGroups.show(group.id)" class="inline-flex w-full items-center gap-2"><Eye class="size-4" />Xem</Link></DropdownMenuItem>
												<DropdownMenuItem as-child><Link :href="tenantRoutes.customerGroups.edit(group.id)" class="inline-flex w-full items-center gap-2"><Pencil class="size-4" />Sửa</Link></DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteGroup(group.id)"><Trash2 class="size-4" />Xóa</DropdownMenuItem>
											</DropdownMenuContent>
										</DropdownMenu>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
