<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page');
            $search = $request->query('search');

            $orders = Order::when($search !== null, function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })->with(['details', 'details.variant'])
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['data' => $orders], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'address' => ['required', 'string'],
            'total_quantity' => ['required', 'integer'],
            'details' => ['required', 'array'],
            'details.*.variant_id' => ['required', 'exists:variants,id'],
            'details.*.quantity' => ['required', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $order = Order::create($validated);

            foreach ($validated['details'] as $detail) {
                $order->details()->create($detail);
            }

            DB::commit();

            return response()->json(['message' => 'Order created successfully.'], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $order = Order::with(['details', 'details.variant'])->findOrFail($id);

            return response()->json(['data' => $order], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'string', 'email'],
            'address' => ['sometimes', 'string'],
            'total_quantity' => ['sometimes', 'integer'],
            'details' => ['required', 'array'],
            'details.*.id' => ['sometimes', 'exists:order_details,id'],
            'details.*.variant_id' => ['required', 'exists:variants,id'],
            'details.*.quantity' => ['required', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            $order->update($validated);

            $existingDetailIds = $order->details()->pluck('id')->toArray();
            $currentDetailIds = array_filter(array_column($validated['details'], 'id'));
            $removedDetailIds = array_diff($existingDetailIds, $currentDetailIds);

            $order->details()->whereIn('id', $removedDetailIds)->delete();

            foreach ($validated['details'] as $detail) {
                if (isset($detail['id'])) {
                    $orderDetail = $order->details()->findOrFail($detail['id']);
                    $orderDetail->update($detail);
                } else {
                    $order->details()->create($detail);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Order updated successfully.'], Response::HTTP_OK);
        } catch (ModelNotFoundException) {
            DB::rollBack();

            return response()->json(['message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();

            return response()->noContent();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
