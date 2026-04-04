<?php

namespace App\Http\Controllers\API\V1\Content;

use App\Http\Controllers\API\BaseController;
use App\Models\Content\Testimonial;

class TestimonialController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('order')
            ->orderByDesc('created_at')
            ->get();

        return $this->sendResponse($testimonials, 'Testimonials retrieved successfully.');
    }
}
