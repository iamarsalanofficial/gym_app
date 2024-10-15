<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // New User Register API
    public function register(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], status: 400);
        }

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Return success response
        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user
        ], 201);
    }

    // Get Single User By ID
    public function getUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Return the user data if found
        return response()->json([
            'message' => 'User found',
            'user' => $user
        ], 200);
    }

    // Update user details
    public function updateUser(Request $request, $id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Update the user details if provided
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        // Save the updated user details
        $user->save();

        // Return success response
        return response()->json([
            'message' => 'User updated successfully!',
            'user' => $user
        ], 200);
    }

    // Delete a user by ID
    public function deleteUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Delete the user
        $user->delete();

        // Return success response
        return response()->json([
            'message' => 'User deleted successfully!'
        ], 200);
    }
}
