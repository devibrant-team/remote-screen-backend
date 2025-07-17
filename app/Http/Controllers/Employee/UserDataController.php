<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserDataController extends Controller
{
public function search(Request $request)
{
    $request->validate([
        'query' => 'required|string'
    ]);

    $user=auth()->user();

       if (!$user || !$request->user()->tokenCan('Admin')) {
    return response()->json(['error' => 'Unauthorized1234'], 401);
}

    $query = $request->input('query');

    $users = User::where(function ($q) use ($query) {
        $q->where('email', 'like', "%$query%")
          ->orWhere('name', 'like', "%$query%");

        if (is_numeric($query)) {
            $q->orWhere('id', $query);
        }
    })->get();

    return response()->json([
        'success' => true,
        'data' => $users
    ]);
}


}
