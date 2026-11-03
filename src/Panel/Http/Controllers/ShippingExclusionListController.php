<?php

namespace Lunar\Shipping\Panel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Core\Models\Product;
use Lunar\Panel\Http\Controllers\Concerns\ResolvesTableExtensions;
use Lunar\Shipping\Models\ShippingExclusion;
use Lunar\Shipping\Models\ShippingExclusionList;

/**
 * The shipping exclusion list screens: a listing, a create dialog, and an edit page for the products on the
 * list. A zone that has the list attached offers no shipping for a cart holding any of those products.
 */
class ShippingExclusionListController
{
    use ResolvesTableExtensions;

    public function index(Request $request): Response
    {
        $resolver = $this->resolveTable('shipping-exclusion-lists.index');
        $columns = [
            ['key' => 'name', 'label' => __('lunar-shipping::panel.exclusion_lists.column_name'), 'width' => 'minmax(0, 1.4fr)'],
            ['key' => 'exclusions_count', 'label' => __('lunar-shipping::panel.exclusion_lists.column_products'), 'width' => '110px', 'align' => 'right'],
        ];

        $lists = ShippingExclusionList::query()
            ->withCount('exclusions')
            ->tap(fn ($query) => $resolver->applyColumnQueries($query))
            ->tap(fn ($query) => $resolver->applyFilters($query, $request))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (ShippingExclusionList $list) use ($resolver): array {
                $row = [
                    'id' => $list->id,
                    'name' => $list->name,
                    'exclusions_count' => (int) $list->getAttribute('exclusions_count'),
                    'urls' => ['edit' => route('panel.settings.shipping-exclusion-lists.edit', $list)],
                    '_actions' => $resolver->resolveRowActionUrls($list),
                ];

                foreach ($resolver->getColumnKeys() as $key) {
                    $row[$key] = $list->getAttribute($key);
                }

                return $row;
            });

        return Inertia::render('lunar-shipping::ExclusionLists/Index', [
            'lists' => $lists,
            ...$this->tableProps($resolver, $columns, $request),
            'urls' => [
                'index' => route('panel.settings.shipping-exclusion-lists.index'),
                'store' => route('panel.settings.shipping-exclusion-lists.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $list = ShippingExclusionList::query()->create(['name' => $validated['name']]);

        return redirect()
            ->route('panel.settings.shipping-exclusion-lists.edit', $list)
            ->with('success', __('lunar-shipping::panel.exclusion_lists.flash_created'));
    }

    public function edit(ShippingExclusionList $shippingExclusionList): Response
    {
        $products = Product::query()
            ->whereIn('id', $shippingExclusionList->exclusions()->where('purchasable_type', (new Product)->getMorphClass())->pluck('purchasable_id'))
            ->get();

        return Inertia::render('lunar-shipping::ExclusionLists/Edit', [
            'list' => [
                'id' => $shippingExclusionList->id,
                'name' => $shippingExclusionList->name,
                'products' => $products->map(fn (Product $product) => $this->productOption($product))->values(),
            ],
            'urls' => [
                'index' => route('panel.settings.shipping-exclusion-lists.index'),
                'update' => route('panel.settings.shipping-exclusion-lists.update', $shippingExclusionList),
                'destroy' => route('panel.settings.shipping-exclusion-lists.destroy', $shippingExclusionList),
                'products' => route('panel.settings.shipping-exclusion-lists.products'),
            ],
        ]);
    }

    public function update(Request $request, ShippingExclusionList $shippingExclusionList): RedirectResponse
    {
        $validated = $request->validate($this->rules($shippingExclusionList));
        $productType = (new Product)->getMorphClass();

        DB::transaction(function () use ($shippingExclusionList, $validated, $productType): void {
            $shippingExclusionList->update(['name' => $validated['name']]);

            $wanted = collect($validated['products'] ?? [])->map(fn ($id) => (int) $id)->unique();
            $current = $shippingExclusionList->exclusions()->where('purchasable_type', $productType)->get();

            $current->reject(fn (ShippingExclusion $exclusion) => $wanted->contains($exclusion->purchasable_id))
                ->each->delete();

            $wanted->diff($current->pluck('purchasable_id'))
                ->each(fn (int $productId) => $shippingExclusionList->exclusions()->create([
                    'purchasable_type' => $productType,
                    'purchasable_id' => $productId,
                ]));
        });

        return back()->with('success', __('lunar-shipping::panel.exclusion_lists.flash_updated'));
    }

    public function destroy(ShippingExclusionList $shippingExclusionList): RedirectResponse
    {
        $shippingExclusionList->delete();

        return redirect()
            ->route('panel.settings.shipping-exclusion-lists.index')
            ->with('success', __('lunar-shipping::panel.exclusion_lists.flash_deleted'));
    }

    /**
     * Products matching a name or SKU, for adding to a list.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->value());

        $products = Product::query()
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', "%{$term}%"))))
            ->limit(20)
            ->get();

        return response()->json(['data' => $products->map(fn (Product $product) => $this->productOption($product))->values()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?ShippingExclusionList $list = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(ShippingExclusionList::class, 'name')->ignore($list?->id)],
            'products' => ['sometimes', 'array'],
            'products.*' => [Rule::exists(Product::class, 'id')],
        ];
    }

    /**
     * @return array{id: int, name: ?string}
     */
    private function productOption(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->translate('name')];
    }
}
