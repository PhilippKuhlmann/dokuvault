<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRecorderRequest;
use App\Models\Customer;
use App\Models\LoginRecorder;
use App\Models\Recorder;
use Illuminate\Support\Facades\Auth;

class LoginRecorderController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginRecorder::class);

        if (Auth::user()->can('see_hidden')) {
            $loginrecorders = LoginRecorder::where('customer_id', $customer->id)
                ->with('recorder')
                ->latest()
                ->paginate(25);
        } else {
            $loginrecorders = LoginRecorder::where('customer_id', $customer->id)
                ->where('hidden', false)
                ->with('recorder')
                ->latest()
                ->paginate(25);
        }

        return view('loginrecorder.index', compact('customer', 'loginrecorders'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LoginRecorder::class);

        $recorders = Recorder::where('customer_id', $customer->id)->get();

        return view('loginrecorder.create', compact('customer', 'recorders'));
    }

    public function store(Customer $customer, LoginRecorderRequest $request)
    {
        $this->authorize('create', LoginRecorder::class);

        $customer->loginrecorders()->create($request->validated());

        return redirect(route('loginrecorder.index', $customer));
    }

    public function edit(Customer $customer, LoginRecorder $loginrecorder)
    {
        $this->authorize('update', LoginRecorder::class);

        $recorder = Recorder::where('customer_id', $customer->id)->get();

        return view('loginrecorder.edit', compact('customer', 'loginrecorder', 'recorder'));
    }

    public function update(Customer $customer, LoginRecorder $loginrecorder, LoginRecorderRequest $request)
    {
        $this->authorize('update', LoginRecorder::class);

        $loginrecorder->update($request->validated());

        return redirect(route('loginrecorder.index', $customer));
    }

    public function destroy(Customer $customer, LoginRecorder $loginrecorder)
    {
        $this->authorize('delete', LoginRecorder::class);

        $loginrecorder->delete();

        return redirect(route('loginrecorder.index', $customer));
    }
}
