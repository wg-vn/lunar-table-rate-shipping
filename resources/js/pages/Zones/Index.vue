<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, DataTable, Dialog, FieldLabel, PageEmpty, Pagination, Select, SettingsShell, TextInput } from '@lunarphp/panel';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type Zone = { id: number; name: string; type: string; rates_count: number; urls: { edit: string } };

const props = defineProps<{
    zones: { data: Zone[] } & Record<string, unknown>;
    columns: { key: string; label: string; width?: string; align?: 'left' | 'right' | 'center' }[];
    tableActions: Record<string, unknown>[];
    urls: { index: string; store: string };
}>();

const { t } = useI18n();
const types = ['unrestricted', 'countries', 'states', 'postcodes'];

const creating = ref(false);
const form = useForm({ name: '', type: 'countries' });

const submit = (): void => {
    form.post(props.urls.store, { onSuccess: () => (creating.value = false) });
};
</script>

<template>
    <SettingsShell :title="t('lunar-shipping::panel.zones.title')" :description="t('lunar-shipping::panel.zones.description')" wide>
        <template #actions>
            <Button variant="primary" icon="plus" size="sm" @click="form.reset(); form.clearErrors(); creating = true">
                {{ t('lunar-shipping::panel.zones.create') }}
            </Button>
        </template>

        <DataTable :columns="columns" :rows="zones.data" :row-to="(row) => (row as unknown as Zone).urls.edit" :row-actions="tableActions">
            <template #cell-name="{ row }">
                <span class="text-[12.5px] text-ink-900 font-medium">{{ (row as unknown as Zone).name }}</span>
            </template>
            <template #cell-type="{ row }">
                <span class="text-xs text-ink-700">{{ t(`lunar-shipping::panel.zones.type_${(row as unknown as Zone).type}`) }}</span>
            </template>
            <template #empty>
                <PageEmpty :title="t('lunar-shipping::panel.zones.empty_title')" />
            </template>
        </DataTable>

        <div class="mt-4"><Pagination :meta="zones" /></div>
    </SettingsShell>

    <Dialog v-model:open="creating" :title="t('lunar-shipping::panel.zones.create')" :description="t('lunar-shipping::panel.zones.create_description')">
        <div class="flex flex-col gap-3">
            <div>
                <FieldLabel required>{{ t('lunar-shipping::panel.zones.field_name') }}</FieldLabel>
                <TextInput v-model="form.name" :invalid="!!form.errors.name" />
                <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
            </div>
            <div>
                <FieldLabel>{{ t('lunar-shipping::panel.zones.field_type') }}</FieldLabel>
                <Select v-model="form.type">
                    <option v-for="type in types" :key="type" :value="type">{{ t(`lunar-shipping::panel.zones.type_${type}`) }}</option>
                </Select>
            </div>
        </div>
        <template #footer>
            <Button variant="ghost" @click="creating = false">{{ t('common.cancel') }}</Button>
            <Button variant="primary" :disabled="form.processing" @click="submit">{{ t('common.create') }}</Button>
        </template>
    </Dialog>
</template>
