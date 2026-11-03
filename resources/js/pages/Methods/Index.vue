<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, DataTable, Dialog, FieldLabel, PageEmpty, Pagination, Select, SettingsShell, StatusBadge, TextInput } from '@lunarphp/panel';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type Method = { id: number; name: string; code: string; driver: string; enabled: boolean; urls: { edit: string } };

const props = defineProps<{
    methods: { data: Method[] } & Record<string, unknown>;
    columns: { key: string; label: string; width?: string; align?: 'left' | 'right' | 'center' }[];
    tableActions: Record<string, unknown>[];
    drivers: string[];
    urls: { index: string; store: string };
}>();

const { t } = useI18n();

const creating = ref(false);
const form = useForm({ name: '', code: '', driver: props.drivers[0] ?? 'ship-by' });

const submit = (): void => {
    form.post(props.urls.store, { onSuccess: () => (creating.value = false) });
};
</script>

<template>
    <SettingsShell :title="t('lunar-shipping::panel.methods.title')" :description="t('lunar-shipping::panel.methods.description')" wide>
        <template #actions>
            <Button variant="primary" icon="plus" size="sm" @click="form.reset(); form.clearErrors(); creating = true">
                {{ t('lunar-shipping::panel.methods.create') }}
            </Button>
        </template>

        <DataTable :columns="columns" :rows="methods.data" :row-to="(row) => (row as unknown as Method).urls.edit" :row-actions="tableActions">
            <template #cell-name="{ row }">
                <span class="text-[12.5px] text-ink-900 font-medium">{{ (row as unknown as Method).name }}</span>
            </template>
            <template #cell-code="{ row }">
                <span class="text-xs font-mono text-ink-700">{{ (row as unknown as Method).code }}</span>
            </template>
            <template #cell-driver="{ row }">
                <span class="text-xs text-ink-700">{{ t(`lunar-shipping::panel.methods.driver_${(row as unknown as Method).driver}`) }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="(row as unknown as Method).enabled ? 'sage' : 'archived'" size="sm" dot>
                    {{ (row as unknown as Method).enabled ? t('common.active') : t('common.inactive') }}
                </StatusBadge>
            </template>
            <template #empty>
                <PageEmpty :title="t('lunar-shipping::panel.methods.empty_title')" />
            </template>
        </DataTable>

        <div class="mt-4"><Pagination :meta="methods" /></div>
    </SettingsShell>

    <Dialog v-model:open="creating" :title="t('lunar-shipping::panel.methods.create')" :description="t('lunar-shipping::panel.methods.create_description')">
        <div class="flex flex-col gap-3">
            <div>
                <FieldLabel required>{{ t('lunar-shipping::panel.methods.field_name') }}</FieldLabel>
                <TextInput v-model="form.name" :invalid="!!form.errors.name" />
                <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
            </div>
            <div>
                <FieldLabel required>{{ t('lunar-shipping::panel.methods.field_code') }}</FieldLabel>
                <TextInput v-model="form.code" mono :invalid="!!form.errors.code" />
                <div v-if="form.errors.code" class="mt-1 text-[11px] text-danger">{{ form.errors.code }}</div>
            </div>
            <div>
                <FieldLabel>{{ t('lunar-shipping::panel.methods.field_driver') }}</FieldLabel>
                <Select v-model="form.driver">
                    <option v-for="driver in drivers" :key="driver" :value="driver">{{ t(`lunar-shipping::panel.methods.driver_${driver}`) }}</option>
                </Select>
            </div>
        </div>
        <template #footer>
            <Button variant="ghost" @click="creating = false">{{ t('common.cancel') }}</Button>
            <Button variant="primary" :disabled="form.processing" @click="submit">{{ t('common.create') }}</Button>
        </template>
    </Dialog>
</template>
