<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActTypeRequest;
use App\Models\Tenant\ActType;

class ActTypeController extends Controller
{
    public function index()
    {
        return view('tenant.parameters.act-types', ['actTypes' => ActType::orderBy('code')->get()]);
    }

    public function store(ActTypeRequest $request)
    {
        ActType::create($request->validated());
        return back()->with('success', __('mxconnect.param.saved'));
    }

    public function update(ActTypeRequest $request, ActType $act_type)
    {
        $act_type->update($request->validated());
        return back()->with('success', __('mxconnect.param.saved'));
    }
}
