<?php

namespace App\Http\Controllers\api\v1\guests;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::orderBy('order', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all faqs'),
            'faqs' => $faqs,
        ]);
    }

    public function show($faq_id)
    {
        $faq = Faq::findOrFail($faq_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.faq'),
            'faq' => $faq,
        ]);
    }
}
