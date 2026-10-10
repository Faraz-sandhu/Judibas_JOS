<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use Illuminate\Http\Request;
use App\Modules\Pms\Services\PmsAuth as Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user->id,403);
        return view('dashboard.profile.index', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->id,403);
        // **Validation Rules**
        $request->validate([
            'name' => 'required|string|max:255',
            'profile_img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'current-password' => 'nullable|required_with:new-password|string|min:6',
            'new-password' => 'nullable|string|min:6|different:current-password',
            'repeat-password' => 'nullable|required_with:new-password|same:new-password',
        ]);

        // **Update Name**
        $user->name = $request->name;

        // **Handle Profile Image Upload**
        if ($request->hasFile('profile_img')) {
            // Delete the old profile image if it exists
            if ($user->profile_img && Storage::disk('public')->exists(str_replace('storage/', '', $user->profile_img))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $user->profile_img));
            }

            // Store new image
            $imagePath = $request->file('profile_img')->store('profile_images', 'public');
            $user->profile_img = asset('storage/' . $imagePath);
        }

        // **Update Password if Provided**
        if ($request->filled('current-password') && $request->filled('new-password')) {
            if (!Hash::check($request->input('current-password'), $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current-password' => 'The current password is incorrect']);
            }
            $user->password = Hash::make($request->input('new-password'));
        }

        // **Save the Changes**
        $user->save();

        return response()->json(['message'=>'Profile updated successfully.']);
    }
}
