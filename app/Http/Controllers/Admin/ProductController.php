<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $user = $this->user();
        $isAdmin = $user['is_admin'] ?? false;

        $fieldIds = $this->manageableProductFieldIds();

        $products = Product::with(['category', 'field.role'])
            ->when(! $isAdmin, fn ($q) => $q->whereIn('custom_field_id', $fieldIds))
            ->orderBy('name')
            ->get();

        return view('admin.products', [
            'products'   => $products,
            'categories' => ProductCategory::orderBy('sort_order')->orderBy('name')->get(),
            'productFields' => CustomField::with('role')
                ->where('type', CustomField::TYPE_PRODUCT)
                ->whereIn('role_id', $this->manageableRoleIds())
                ->get(),
            'isAdmin' => $isAdmin,
            'purgeable' => $isAdmin ? Product::where('active', false)
                ->whereNotNull('deactivated_at')
                ->where('deactivated_at', '<=', now()->subMonths(6))
                ->count() : 0,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_path'] = $this->storeImage($request);

        if (! $data['active']) {
            $data['deactivated_at'] = now();
        }

        $product = Product::create($data);

        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'Produkt "' . $product->name . '" angelegt', $this->user()['username'] ?? null);

        return back()->with('ok', __('Produkt angelegt.'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($product);

        $data = $this->validated($request);

        // Deaktivierungs-Zeitpunkt für die spätere Bereinigung festhalten
        // Record the deactivation time for the later clean-up
        if ($product->active && ! $data['active']) {
            $data['deactivated_at'] = now();
        } elseif (! $product->active && $data['active']) {
            $data['deactivated_at'] = null;
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($product);
            $data['image_path'] = null;
        } elseif ($path = $this->storeImage($request)) {
            $this->deleteImage($product);
            $data['image_path'] = $path;
        }

        $product->update($data);

        return back()->with('ok', __('Produkt gespeichert.'));
    }

    public function destroy(Product $product)
    {
        $this->authorizeProduct($product);

        $this->deleteImage($product);
        $name = $product->name;
        $product->delete();

        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'Produkt "' . $name . '" gelöscht', $this->user()['username'] ?? null);

        return back()->with('ok', __('Produkt gelöscht.'));
    }

    /**
     * Admin: Produkte löschen, die seit mind. 6 Monaten deaktiviert sind
     *
     * Admin: delete products that have been deactivated for at least 6 months
     */
    public function purge()
    {
        abort_unless($this->user()['is_admin'] ?? false, 403, 'Nur für Administratoren.');

        $candidates = Product::where('active', false)
            ->whereNotNull('deactivated_at')
            ->where('deactivated_at', '<=', now()->subMonths(6))
            ->get();

        foreach ($candidates as $product) {
            $this->deleteImage($product);
            $product->delete();
        }

        ActivityLog::write(ActivityLog::TYPE_DELETION, $candidates->count() . ' seit mind. 6 Monaten deaktivierte(s) Produkt(e) bereinigt', $this->user()['username'] ?? null, null, [
            'Produkte' => $candidates->pluck('name')->implode(', ') ?: '–',
        ]);

        return back()->with('ok', $candidates->count() . ' Produkt(e) bereinigt.');
    }

    // ---- Kategorien / Categories ----

    public function storeCategory(Request $request)
    {
        abort_unless($this->user()['is_admin'] ?? false, 403, 'Kategorien pflegen nur Administratoren.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        ProductCategory::create($data);

        return back()->with('ok', __('Kategorie angelegt.'));
    }

    public function updateCategory(Request $request, ProductCategory $category)
    {
        abort_unless($this->user()['is_admin'] ?? false, 403, 'Kategorien pflegen nur Administratoren.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $category->update($data);

        return back()->with('ok', __('Kategorie gespeichert.'));
    }

    public function destroyCategory(ProductCategory $category)
    {
        abort_unless($this->user()['is_admin'] ?? false, 403, 'Kategorien pflegen nur Administratoren.');

        $category->delete(); // Produkte behalten (category_id wird null) / keep products (category_id becomes null)

        return back()->with('ok', __('Kategorie gelöscht (Produkte bleiben erhalten).'));
    }

    // ---- Hilfsfunktionen / Helpers ----

    private function manageableProductFieldIds(): array
    {
        return CustomField::where('type', CustomField::TYPE_PRODUCT)
            ->whereIn('role_id', $this->manageableRoleIds())
            ->pluck('id')
            ->all();
    }

    private function authorizeProduct(Product $product): void
    {
        if ($this->user()['is_admin'] ?? false) {
            return;
        }

        abort_unless(
            $product->custom_field_id
                && in_array($product->custom_field_id, $this->manageableProductFieldIds(), true),
            403,
            'Dieses Produkt gehört zum Feld einer anderen Rolle.'
        );
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'article_no' => ['nullable', 'string', 'max:100'],
            'manufacturer_no' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'custom_field_id' => ['nullable', 'integer', 'exists:custom_fields,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
        ]);

        // Feld-Zuweisung prüfen: nur eigene Produktauswahl-Felder;
        // "kein Feld" (= erscheint überall) dürfen nur Admins setzen
        // Check the field assignment: only own product selection fields;
        // "no field" (= appears everywhere) may only be set by admins
        $allowedFields = $this->manageableProductFieldIds();
        if (! empty($data['custom_field_id'])) {
            abort_unless(in_array((int) $data['custom_field_id'], $allowedFields, true), 403, 'Feld gehört einer anderen Rolle.');
        } elseif (! ($this->user()['is_admin'] ?? false)) {
            abort_unless($allowedFields !== [], 403, 'Bitte zuerst ein Produktauswahl-Feld für Ihre Rolle anlegen.');
            $data['custom_field_id'] = $allowedFields[0]; // Standard: erstes eigenes Feld / default: first own field
        }

        unset($data['image']);
        $data['active'] = $request->boolean('active', true);

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $name = 'produkt-' . time() . '-' . random_int(100, 999) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path('uploads/products'), $name);

        return 'uploads/products/' . $name;
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image_path && is_file(public_path($product->image_path))) {
            @unlink(public_path($product->image_path));
        }
    }
}
