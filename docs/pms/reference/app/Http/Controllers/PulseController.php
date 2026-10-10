<?php

namespace App\Http\Controllers;

use App\Models\Pulse;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PulseController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('pulse-view');
        $employees = User::with('roles')->select('id', 'name', 'email')
        ->whereHas('roles', function ($query) {
            $query->where('role_name', '!=', 'admin');
        })
        ->with([
            'onlineHistory' => function ($query) {
                $query->latest();
            }
        ])
        ->get();
        if ($request->ajax()) {
            return response()->json(['data' => $employees->toArray()]);
        }

        return view('dashboard.pulse.index');
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Pulse::create([
            'user_id' => $request->user_id,
            'login_time' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Pulse History created successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
