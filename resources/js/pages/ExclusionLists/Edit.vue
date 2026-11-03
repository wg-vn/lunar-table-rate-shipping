<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, ConfirmDialog, FieldLabel, SettingsShell, SideCard, TextInput, http } from '@lunarphp/panel';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type ProductOption = { id: number; name: string | null };

const props = defineProps<{
    list: { id: number; name: string; products: ProductOption[] };
    urls: { index: string; update: string; destroy: string; products: string };
}>();

const { t } = useI18n();
const tr = (key: string, params?: Record<string, unknown>): string => t(`lunar-shipping::panel.exclusion_lists.${key}`, params ?? {});

const breadcrumbs = computed(() => [
    { label: t('nav.settings') },
    { label: tr('title'), href: props.urls.index },
    { label: props.list.name, current: true },
]);

const products = ref<ProductOption[]>([...props.list.products]);
const form = useForm({ name: props.list.name });

const submit = (): void => {
    form.transform((data) => ({ ...data, products: products.value.map((product) => product.id) }))
        .put(props.urls.update, { preserveScroll: true });
};

const query = ref('');
const results = ref<ProductOption[] | null>(null);

const search = async (): Promise<void> => {
    const response = await http.get<{ data: ProductOption[] }>(`${props.urls.products}?${new URLSearchParams({ q: query.value })}`);
    results.value = response.data.filter((product) => !products.value.some((picked) => picked.id === product.id));
};

const add = (product: ProductOption): void => {
    products.value.push(product);
    results.value = (results.value ?? []).filter((row) => row.id !== product.id);
};

const deleting = ref(false);
</script>

<template>
    <SettingsShell :title="tr('edit_title', { name: list.name })" :breadcrumbs="breadcrumbs" wide>
        <template #actions>
            <Button variant="ghost" icon="trash" @click="deleting = true">{{ t('common.delete') }}</Button>
            <Button variant="primary" icon="check" size="sm" :disabled="form.processing" @click="submit">{{ t('common.save') }}</Button>
        </template>

        <SideCard :title="tr('field_name')">
            <div class="max-w-[420px]">
                <TextInput v-model="form.name" :invalid="!!form.errors.name" />
                <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
            </div>
        </SideCard>

        <SideCard :title="tr('section_products')">
            <div v-if="products.length" class="flex flex-col gap-1.5 mb-4">
                <div v-for="(product, index) in products" :key="product.id" class="flex items-center gap-3 px-3 py-1.5 rounded-md border border-line bg-surface text-xs">
                    <span class="text-ink-900">{{ product.name ?? product.id }}</span>
                    <button
                        type="button"
                        class="ml-auto w-5 h-5 inline-flex items-center justify-center rounded-full text-ink-500 hover:text-danger"
                        :aria-label="t('lunar-shipping::panel.common.remove')"
                        @click="products.splice(index, 1)"
                    >
                        ×
                    </button>
                </div>
            </div>
            <div v-else class="mb-4 text-xs text-ink-500">{{ tr('no_products') }}</div>

            <FieldLabel>{{ tr('search_products') }}</FieldLabel>
            <div class="flex gap-2 max-w-[520px]">
                <TextInput v-model="query" :placeholder="tr('search_products')" @keyup.enter="search" />
                <Button icon="search" @click="search">{{ t('lunar-shipping::panel.common.search') }}</Button>
            </div>
            <div v-if="results" class="mt-2 max-w-[520px] flex flex-col gap-1">
                <button
                    v-for="product in results"
                    :key="product.id"
                    type="button"
                    class="text-left px-3 py-1.5 rounded-md border border-line hover:bg-surface-2 text-xs text-ink-900"
                    @click="add(product)"
                >
                    + {{ product.name ?? product.id }}
                </button>
                <div v-if="!results.length" class="text-xs text-ink-500">{{ tr('no_results') }}</div>
            </div>
        </SideCard>
    </SettingsShell>

    <ConfirmDialog
        v-model:open="deleting"
        :title="t('common.delete')"
        :description="t('lunar-shipping::panel.common.confirm_delete')"
        :confirm-label="t('common.delete')"
        tone="danger"
        @confirm="router.delete(urls.destroy)"
    />
</template>
