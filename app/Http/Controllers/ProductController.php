<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page');
        $search = $request->query('search');

        $products = Product::when($search !== null, function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('brand', 'like', '%'.$search.'%')
                ->orWhere('type', 'like', '%'.$search.'%');
        })->with('variants')->latest()->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            $products,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:products,name'],
            'brand' => ['required', 'string'],
            'type' => ['required', 'string', 'in:Mug,Jug,Cup,Glass,Plate'],
            'origin' => ['required', 'string'],
            'variants' => ['required', 'array'],
            'variants.*.color' => ['required', 'string'],
            'variants.*.specification' => ['required', 'string'],
            'variants.*.size' => ['required', 'string', 'in:small,medium,large'],
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create($validated);

            foreach ($validated['variants'] as $variant) {
                $product->variants()->create($variant);
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully.',
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(
                [
                    'message' => $th->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $product = Product::with('variants')->findOrFail($id);

            return response()->json([
                'data' => $product,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'unique:products,name,'.$id],
            'brand' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:Mug,Jug,Cup,Glass,Plate'],
            'origin' => ['sometimes', 'string'],
            'variants' => ['required', 'array'],
            'variants.*.id' => ['sometimes', 'exists:variants,id'],
            'variants.*.color' => ['required', 'string'],
            'variants.*.specification' => ['required', 'string'],
            'variants.*.size' => ['required', 'string', 'in:small,medium,large'],
        ]);

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($id);
            $product->update($validated);

            $existingVariantIds = $product->variants()->pluck('id')->toArray();
            $currentVariantIds = array_filter(array_column($validated['variants'], 'id'));
            $removedVariantIds = array_diff($existingVariantIds, $currentVariantIds);
            $product->variants()->whereIn('id', $removedVariantIds)->delete();

            foreach ($validated['variants'] as $variant) {
                if (isset($variant['id'])) {
                    $existingVariant = $product->variants()->findOrFail($variant['id']);
                    $existingVariant->update($variant);
                } else {
                    $product->variants()->create($variant);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully.',
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException) {
            DB::rollBack();

            return response()->json([
                'message' => 'Product or Variant not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(
                [
                    'message' => $th->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->noContent();
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'message' => $th->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
