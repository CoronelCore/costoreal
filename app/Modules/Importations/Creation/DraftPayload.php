<?php

namespace App\Modules\Importations\Creation;

use App\Models\ExpenseCategory;
use App\Models\ImportDraft;
use App\Models\User;
use App\Modules\Access\AccessContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Partial editor text is evidence of a draft, never a validated financial input. */
final class DraftPayload
{
    public function validate(array $input, User $user, ?ImportDraft $draft = null): array
    {
        $rules = ['payload' => 'required|array:context,product,expense', 'payload.context' => 'required|array:nombre,descripcion,company_id,fecha_inicio,fecha_fin,moneda_trabajo,tipo_cambio',
            'payload.context.nombre' => 'nullable|string|max:255', 'payload.context.descripcion' => 'nullable|string|max:5000',
            'payload.context.company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('user_id', $user->id)],
            'payload.context.fecha_inicio' => 'nullable|string|max:10', 'payload.context.fecha_fin' => 'nullable|string|max:10',
            'payload.context.moneda_trabajo' => 'nullable|in:USD,EUR,CNY,BRL,PEN', 'payload.context.tipo_cambio' => 'nullable|string|max:40',
            'payload.product' => 'nullable|array:mode,id,fields,base', 'payload.product.mode' => 'required_with:payload.product|in:add,edit',
            'payload.product.id' => ['nullable', 'required_if:payload.product.mode,edit', 'integer', Rule::exists('products', 'id')->where('trip_id', $draft?->trip_id ?? 0)],
            'payload.product.fields' => 'required_with:payload.product|array:nombre,moneda,cantidad,costo_unitario,weight,volume,rate_mode,applied_rate,rate_source,rate_date,expected_revision',
            'payload.product.base' => 'nullable|required_if:payload.product.mode,edit|array:nombre,moneda,cantidad,costo_unitario,weight,volume,rate_mode,applied_rate,rate_source,rate_date',
            'payload.expense' => 'nullable|array:id,fields,receipt_name',
            'payload.expense.id' => ['nullable', 'integer', Rule::exists('expenses', 'id')->where('trip_id', $draft?->trip_id ?? 0)],
            'payload.expense.receipt_name' => 'nullable|string|max:255',
            'payload.expense.fields' => 'required_with:payload.expense|array:expense_category_id,concepto,moneda,monto,rate_mode,applied_rate,rate_source,rate_date,allocation_method,target_product_id,eliminar_comprobante,expected_revision'];
        foreach (['payload.product.fields', 'payload.product.base'] as $prefix) {
            foreach (['nombre', 'moneda', 'cantidad', 'costo_unitario', 'weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date'] as $field) {
                $rules[$prefix.'.'.$field] = 'nullable|string|max:'.(in_array($field, ['nombre', 'rate_source']) ? 255 : 40);
            }
        }
        $rules['payload.product.fields.expected_revision'] = 'required_with:payload.product|integer|min:1';
        foreach (['concepto', 'moneda', 'monto', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method'] as $field) {
            $rules['payload.expense.fields.'.$field] = 'nullable|string|max:'.(in_array($field, ['concepto', 'rate_source']) ? 255 : 40);
        }
        $rules['payload.expense.fields.expected_revision'] = 'required_with:payload.expense|integer|min:1';
        $rules['payload.expense.fields.expense_category_id'] = ['nullable', Rule::exists('expense_categories', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))];
        $rules['payload.expense.fields.target_product_id'] = ['nullable', Rule::exists('products', 'id')->where('trip_id', $draft?->trip_id ?? 0)];
        $rules['payload.expense.fields.eliminar_comprobante'] = 'nullable|boolean';
        foreach (['payload.product.fields', 'payload.product.base', 'payload.expense.fields'] as $prefix) {
            $rules[$prefix.'.moneda'] = 'nullable|in:CLP,USD,EUR,CNY,BRL,PEN';
            $rules[$prefix.'.rate_mode'] = 'nullable|in:estimated,paid';
        }
        $rules['payload.expense.fields.allocation_method'] = 'nullable|in:value,units,weight,volume,direct';
        $data = Validator::make($input, $rules, ['array' => 'El borrador contiene campos no admitidos.', 'exists' => 'El registro del borrador no pertenece a tu espacio de trabajo.'])->validate();

        if (! $draft?->trip_id && (! empty($data['payload']['product']) || ! empty($data['payload']['expense']))) {
            throw ValidationException::withMessages(['payload' => 'Guarda el contexto antes de abrir los editores de productos y gastos.']);
        }

        return $data['payload'];
    }

    private function viewPayload(array $payload): array
    {
        $source = $payload['context'] ?? [];
        $context = [];
        foreach (['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'tipo_cambio'] as $field) {
            $context[$field] = (string) ($source[$field] ?? '');
        }
        $context['company_id'] = isset($source['company_id']) ? (int) $source['company_id'] : '';
        $context['moneda_trabajo'] = $source['moneda_trabajo'] ?? 'USD';
        $product = $payload['product'] ?? null;
        if ($product) {
            $normalize = function (array $fields) use ($context) {
                $data = [];
                foreach (['nombre', 'cantidad', 'costo_unitario', 'weight', 'volume', 'applied_rate', 'rate_source', 'rate_date'] as $field) {
                    $data[$field] = (string) ($fields[$field] ?? '');
                }
                $data['moneda'] = $fields['moneda'] ?? $context['moneda_trabajo'];
                $data['rate_mode'] = $fields['rate_mode'] ?? 'estimated';

                return $data;
            };
            $product = ['mode' => $product['mode'], 'id' => isset($product['id']) ? (int) $product['id'] : null,
                'fields' => [...$normalize($product['fields']), 'expected_revision' => (int) $product['fields']['expected_revision']],
                'base' => isset($product['base']) ? $normalize($product['base']) : null];
        }
        $expense = $payload['expense'] ?? null;
        if ($expense) {
            $source = $expense['fields'];
            $fields = [];
            foreach (['concepto', 'monto', 'applied_rate', 'rate_source', 'rate_date'] as $field) {
                $fields[$field] = (string) ($source[$field] ?? '');
            }
            foreach (['expense_category_id', 'target_product_id'] as $field) {
                $fields[$field] = isset($source[$field]) ? (int) $source[$field] : '';
            }
            $fields = [...$fields, 'moneda' => $source['moneda'] ?? $context['moneda_trabajo'], 'rate_mode' => $source['rate_mode'] ?? 'estimated',
                'allocation_method' => $source['allocation_method'] ?? 'value', 'eliminar_comprobante' => (bool) ($source['eliminar_comprobante'] ?? false), 'expected_revision' => (int) $source['expected_revision']];
            $expense = ['id' => isset($expense['id']) ? (int) $expense['id'] : null, 'fields' => $fields, 'receipt_name' => (string) ($expense['receipt_name'] ?? '')];
        }

        return ['context' => $context, 'product' => $product, 'expense' => $expense];
    }

    public function data(ImportDraft $draft, ?AccessContext $context = null): array
    {
        $context ??= AccessContext::current();
        $actor = $context->actorId();
        abort_unless((int) $draft->user_id === $actor, 403);
        $payload = $this->viewPayload($draft->payload);
        $companyId = $payload['context']['company_id'];
        if ($companyId && ! $context->user->companies()->whereKey($companyId)->exists()) {
            $payload['context']['company_id'] = '';
        }
        $categoryId = $payload['expense']['fields']['expense_category_id'] ?? null;
        if ($categoryId && ! ExpenseCategory::visibleTo($actor)->whereKey($categoryId)->exists()) {
            $payload['expense']['fields']['expense_category_id'] = '';
        }

        return ['id' => $draft->id, 'revision' => $draft->revision, 'step' => $draft->step, 'trip_id' => $draft->trip_id === null ? null : (int) $draft->trip_id,
            'payload' => $payload, 'updated_at' => $draft->updated_at->toISOString(), 'completed_at' => $draft->completed_at?->toISOString()];
    }
}
