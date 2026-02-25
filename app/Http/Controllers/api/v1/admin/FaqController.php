<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-faqs')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);
        $faqs = Faq::when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('ar_question', 'like', '%'.$search.'%')
                    ->orWhere('en_question', 'like', '%'.$search.'%')
                    ->orWhere('ar_answer', 'like', '%'.$search.'%')
                    ->orWhere('en_answer', 'like', '%'.$search.'%');
            });
        })->orderBy('order', 'asc')->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all faqs'),
            'faqs' => $faqs,
        ]);
    }

    public function show($faq_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-faqs')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $faq = Faq::findOrFail($faq_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.faq'),
            'faq' => $faq,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-faqs')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'ar_question' => 'required|string',
            'en_question' => 'required|string',
            'ar_answer' => 'required|string',
            'en_answer' => 'required|string',
            'order' => 'required|integer|min:1|max:'.Faq::max('order') + 1,
        ], [
            'order.max' => __('responses.The order must be less than or equal to').' '.Faq::max('order') + 1,
        ]);
        try {
            DB::beginTransaction();

            Faq::where('order', '>=', $request->order)
                ->orderBy('order', 'desc')
                ->get()
                ->each(function ($faq) {
                    $faq->update(['order' => $faq->order + 1]);
                });

            $faq = Faq::create([
                'ar_question' => $request->ar_question,
                'en_question' => $request->en_question,
                'ar_answer' => $request->ar_answer,
                'en_answer' => $request->en_answer,
                'order' => $request->order,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'faq' => $faq,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function update(Request $request, $faq_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-faqs')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $faq = Faq::findOrFail($faq_id);

        $request->validate([
            'ar_question' => 'sometimes|nullable|string',
            'en_question' => 'sometimes|nullable|string',
            'ar_answer' => 'sometimes|nullable|string',
            'en_answer' => 'sometimes|nullable|string',
            'order' => 'sometimes|nullable|integer|min:1|max:'.Faq::max('order'),
        ], [
            'order.max' => __('responses.The order must be less than or equal to').' '.Faq::max('order'),
        ]);
        try {
            DB::beginTransaction();

            // Handle order change if provided
            if ($request->has('order') && $request->order != $faq->order) {
                $oldOrder = $faq->order;
                $newOrder = $request->order;

                // Temporarily set current FAQ to a very high order to avoid conflicts
                $faq->update(['order' => 999999]);

                if ($newOrder > $oldOrder) {
                    // Moving down: decrement orders between old and new position
                    Faq::where('order', '>', $oldOrder)
                        ->where('order', '<=', $newOrder)
                        ->orderBy('order', 'asc')
                        ->get()
                        ->each(function ($f) {
                            $f->update(['order' => $f->order - 1]);
                        });
                } else {
                    // Moving up: increment orders between new and old position
                    Faq::where('order', '>=', $newOrder)
                        ->where('order', '<', $oldOrder)
                        ->orderBy('order', 'desc')
                        ->get()
                        ->each(function ($f) {
                            $f->update(['order' => $f->order + 1]);
                        });
                }
            }

            $faq->update([
                'ar_question' => $request->ar_question ?? $faq->ar_question,
                'en_question' => $request->en_question ?? $faq->en_question,
                'ar_answer' => $request->ar_answer ?? $faq->ar_answer,
                'en_answer' => $request->en_answer ?? $faq->en_answer,
                'order' => $request->order ?? $faq->order,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'faq' => $faq,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($faq_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-faqs')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $faq = Faq::findOrFail($faq_id);
        try {
            DB::beginTransaction();

            $deletedOrder = $faq->order;
            $faq->delete();

            // Decrement the order of all FAQs that come after the deleted one
            Faq::where('order', '>', $deletedOrder)
                ->orderBy('order', 'asc')
                ->get()
                ->each(function ($f) {
                    $f->update(['order' => $f->order - 1]);
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.faq deleted successfully'),
                'faq' => $faq,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete faq'),
            ], 400);
        }
    }
}
