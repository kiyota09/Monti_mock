<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$order = App\Models\Ord\SalesOrder::with([
    'recipe.fabricSampleRequest.formulations',
    'recipe.sampleRequests.formulations',
])->where('jo_number', 'JO-20261106-E6KG')->first();

$requests = collect([$order?->recipe?->fabricSampleRequest])
    ->merge($order?->recipe?->sampleRequests ?? collect())
    ->filter()
    ->unique('id')
    ->map(fn ($request) => [
        'id' => $request->id,
        'code' => $request->code,
        'status' => $request->status,
        'formula' => $request->formula,
        'pantone_formula' => $request->pantone_formula,
        'formulations' => $request->formulations->map(fn ($formulation) => [
            'id' => $formulation->id,
            'version' => $formulation->version,
            'status' => $formulation->status,
            'formula' => $formulation->formula,
        ])->values(),
    ])->values();

echo json_encode([
    'jo' => $order?->jo_number,
    'recipe_id' => $order?->recipe_id,
    'recipe_materials' => $order?->recipe?->materials,
    'recipe_dyeing_formula' => $order?->recipe?->dyeing_formula,
    'fsrs' => $requests,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
